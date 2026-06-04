<?php

/**
 * Core synchronization logic between local Plesk domains and uptimeify monitors.
 *
 * Responsibilities:
 *  - Build the merged view (local domain x remote monitor) for the dashboard.
 *  - Enable monitoring for a single domain (per-domain customer + package choice).
 *  - Disable monitoring (delete the remote monitor).
 *  - Run the scheduled reconcile that auto-creates monitors for new domains
 *    using the configured default customer + package.
 */

declare(strict_types=1);

class Modules_Uptimeify_Sync_DomainSyncService
{
    public function __construct(
        private readonly Modules_Uptimeify_Api_Client $api,
        private readonly Modules_Uptimeify_Plesk_DomainRepository $domains,
    ) {
    }

    public static function create(): self
    {
        return new self(
            Modules_Uptimeify_Api_Client::fromSettings(),
            new Modules_Uptimeify_Plesk_DomainRepository(),
        );
    }

    /**
     * Merge local Plesk domains with their uptimeify monitor (if any).
     *
     * @return list<array{
     *     domain:string,
     *     url:string,
     *     ip:string,
     *     monitored:bool,
     *     websitePublicId:?string,
     *     customerPublicId:?string,
     *     customerName:?string,
     *     status:?string,
     *     monitoringType:?string,
     *     packageType:?string
     * }>
     */
    public function getDashboardRows(): array
    {
        $monitors = $this->indexMonitorsByUrl($this->api->listWebsites());
        $mapping  = Modules_Uptimeify_Settings::getMapping();
        $rows     = [];

        foreach ($this->domains->all() as $domain) {
            $url     = $domain['displayUrl'];
            $monitor = $monitors[$this->normalizeUrl($url)] ?? null;
            $map     = $mapping[$domain['name']] ?? null;

            $rows[] = [
                'domain'           => $domain['name'],
                'url'              => $url,
                'ip'               => $domain['ip'],
                'monitored'        => $monitor !== null,
                'websitePublicId'  => $monitor['publicId'] ?? ($map['websitePublicId'] ?? null),
                'customerPublicId' => $monitor['customerPublicId'] ?? ($map['customerPublicId'] ?? null),
                'customerName'     => $monitor['customerName'] ?? null,
                'status'           => $monitor['status'] ?? null,
                'monitoringType'   => $monitor['monitoringType'] ?? null,
                'packageType'      => $map['packageType'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * Enable monitoring for a single domain.
     *
     * @throws Modules_Uptimeify_Api_Exception_ApiException
     */
    public function enable(
        string $domain,
        string $customerPublicId,
        string $packageType,
        ?string $monitoringType = null,
        ?int $checkInterval = null,
    ): array {
        $monitoringType ??= Modules_Uptimeify_Settings::getDefaultMonitoringType();
        $checkInterval  ??= Modules_Uptimeify_Settings::getDefaultCheckInterval();

        $website = $this->api->createWebsite(
            $customerPublicId,
            'Plesk: ' . $domain,
            'https://' . $domain,
            $monitoringType,
            $checkInterval,
        );

        $websitePublicId = (string) ($website['publicId'] ?? $website['id'] ?? '');
        Modules_Uptimeify_Settings::setMappingFor($domain, $websitePublicId, $customerPublicId, $packageType);

        if (Modules_Uptimeify_Settings::isDnsblEnabled()) {
            $this->registerServerIp($domain, $customerPublicId);
        }

        return $website;
    }

    /**
     * Disable monitoring for a single domain (irreversible remote delete).
     *
     * @throws Modules_Uptimeify_Api_Exception_ApiException
     */
    public function disable(string $domain, string $websitePublicId): bool
    {
        $ok = $this->api->deleteWebsite($websitePublicId);
        if ($ok) {
            Modules_Uptimeify_Settings::removeMappingFor($domain);
        }
        return $ok;
    }

    /**
     * Scheduled reconcile: create monitors for new, unmonitored domains using
     * the configured default customer + package. Returns a short summary.
     *
     * @return array{created:int, skipped:int, errors:list<string>}
     */
    public function reconcile(): array
    {
        $summary = ['created' => 0, 'skipped' => 0, 'errors' => []];

        if (!Modules_Uptimeify_Settings::isAutoCreateEnabled()) {
            return $summary;
        }

        $customerPublicId = Modules_Uptimeify_Settings::getDefaultCustomerPublicId();
        $packageType      = Modules_Uptimeify_Settings::getDefaultPackageType();
        if ($customerPublicId === '') {
            $summary['errors'][] = 'No default customer configured for auto-create.';
            return $summary;
        }

        foreach ($this->getDashboardRows() as $row) {
            if ($row['monitored']) {
                $summary['skipped']++;
                continue;
            }

            try {
                $this->enable($row['domain'], $customerPublicId, $packageType);
                $summary['created']++;
            } catch (Modules_Uptimeify_Api_Exception_QuotaExceededException $e) {
                $summary['errors'][] = $row['domain'] . ': quota reached';
                break; // no point continuing once the package limit is hit
            } catch (Modules_Uptimeify_Api_Exception_ApiException $e) {
                $summary['errors'][] = $row['domain'] . ': ' . $e->getMessage();
            }
        }

        return $summary;
    }

    private function registerServerIp(string $domain, string $customerPublicId): void
    {
        foreach ($this->domains->all() as $d) {
            if ($d['name'] === $domain && $d['ip'] !== '') {
                try {
                    $this->api->createCustomerIp($customerPublicId, $d['ip'], 'Plesk Server IP');
                } catch (Modules_Uptimeify_Api_Exception_ApiException) {
                    // DNSBL is a best-effort add-on; never block the website creation.
                }
                return;
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $monitors
     * @return array<string, array<string, mixed>>
     */
    private function indexMonitorsByUrl(array $monitors): array
    {
        $indexed = [];
        foreach ($monitors as $monitor) {
            $url = (string) ($monitor['url'] ?? '');
            if ($url !== '') {
                $indexed[$this->normalizeUrl($url)] = $monitor;
            }
        }
        return $indexed;
    }

    private function normalizeUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: $url;
        return strtolower(ltrim((string) preg_replace('#^https?://#i', '', $host), '/'));
    }
}
