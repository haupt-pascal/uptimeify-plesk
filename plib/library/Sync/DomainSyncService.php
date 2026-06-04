<?php

/**
 * Core synchronization logic — mirrors the Plesk customer/domain structure into
 * uptimeify.
 *
 * Model: each Plesk client (customer) maps 1:1 to an uptimeify customer. A
 * client is matched to an existing uptimeify customer by email, then by name;
 * if none exists it is auto-created (when enabled) with the default package.
 * Each Plesk domain becomes a website monitor under its client's customer.
 */

declare(strict_types=1);

class Modules_Uptimeify_Sync_DomainSyncService
{
    /** @var list<array<string, mixed>>|null */
    private ?array $customersCache = null;

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
     * Merge local Plesk domains (with their owning client) and the uptimeify
     * monitor + customer they map to.
     *
     * @return list<array<string, mixed>>
     */
    public function getDashboardRows(): array
    {
        $monitors = $this->indexMonitorsByUrl($this->api->listWebsites());
        $mapping  = Modules_Uptimeify_Settings::getMapping();
        $rows     = [];

        foreach ($this->domains->all() as $domain) {
            $monitor  = $monitors[$this->normalizeUrl($domain['displayUrl'])] ?? null;
            $map      = $mapping[$domain['name']] ?? null;
            $existing = $monitor === null
                ? $this->matchCustomer((int) $domain['clientId'], (string) $domain['clientName'], (string) $domain['clientEmail'])
                : null;

            $rows[] = [
                'domain'           => $domain['name'],
                'url'              => $domain['displayUrl'],
                'ip'               => $domain['ip'],
                'clientId'         => $domain['clientId'],
                'ownerName'        => $domain['clientName'] !== '' ? $domain['clientName'] : $domain['clientEmail'],
                'ownerEmail'       => $domain['clientEmail'],
                'monitored'        => $monitor !== null,
                'websitePublicId'  => $monitor['publicId'] ?? ($map['websitePublicId'] ?? null),
                'status'           => $monitor['status'] ?? null,
                'monitoringType'   => $monitor['monitoringType'] ?? null,
                'customerName'     => $monitor['customerName'] ?? ($existing['name'] ?? null),
                'customerPublicId' => $monitor['customerPublicId'] ?? ($existing['publicId'] ?? null),
            ];
        }

        return $rows;
    }

    /**
     * Existing uptimeify customers, for the dashboard override dropdown.
     *
     * @return list<array{publicId:string, name:string}>
     */
    public function listCustomerChoices(): array
    {
        $choices = [];
        foreach ($this->getCustomers() as $c) {
            $publicId = (string) ($c['publicId'] ?? $c['id'] ?? '');
            if ($publicId !== '') {
                $choices[] = ['publicId' => $publicId, 'name' => (string) ($c['name'] ?? $publicId)];
            }
        }
        return $choices;
    }

    /**
     * Enable monitoring for one domain.
     *
     * @param string $customerChoice 'auto' (mirror the Plesk client) or an explicit customer public id.
     *
     * @throws Modules_Uptimeify_Api_Exception_ApiException
     */
    public function enable(string $domain, string $customerChoice = 'auto', ?string $packageType = null): array
    {
        $row = $this->findDomain($domain);
        if ($row === null) {
            throw new Modules_Uptimeify_Api_Exception_ApiException('Unknown domain: ' . $domain);
        }

        $packageType ??= Modules_Uptimeify_Settings::getDefaultPackageType();

        if ($customerChoice !== '' && $customerChoice !== 'auto') {
            $customerPublicId = $customerChoice;
            // Remember the admin's choice for this client so future domains follow it.
            Modules_Uptimeify_Settings::setCustomerForClient((int) $row['clientId'], $customerPublicId);
        } else {
            $created          = false;
            $customerPublicId = $this->resolveCustomer($row, $packageType, Modules_Uptimeify_Settings::isAutoCreateCustomersEnabled(), $created);
            if ($customerPublicId === null) {
                throw new Modules_Uptimeify_Api_Exception_ApiException(
                    'No uptimeify customer for "' . $row['ownerName'] . '" and auto-creation is disabled.',
                );
            }
        }

        return $this->createMonitor($row, $customerPublicId, $packageType);
    }

    /**
     * Disable monitoring for one domain (irreversible remote delete).
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
     * Mirror the whole Plesk customer base and create monitors for every
     * unmonitored domain in one pass.
     *
     * @return array{customersCreated:int, websitesCreated:int, skipped:int, errors:list<string>}
     */
    public function mirrorAndSyncAll(): array
    {
        $summary    = ['customersCreated' => 0, 'websitesCreated' => 0, 'skipped' => 0, 'errors' => []];
        $autoCreate = Modules_Uptimeify_Settings::isAutoCreateCustomersEnabled();
        $packageType = Modules_Uptimeify_Settings::getDefaultPackageType();

        foreach ($this->getDashboardRows() as $row) {
            if ($row['monitored']) {
                $summary['skipped']++;
                continue;
            }

            try {
                $created          = false;
                $customerPublicId = $this->resolveCustomer($row, $packageType, $autoCreate, $created);
                if ($customerPublicId === null) {
                    $summary['errors'][] = $row['domain'] . ': no customer for "' . $row['ownerName'] . '"';
                    continue;
                }
                if ($created) {
                    $summary['customersCreated']++;
                }
                $this->createMonitor($row, $customerPublicId, $packageType);
                $summary['websitesCreated']++;
            } catch (Modules_Uptimeify_Api_Exception_QuotaExceededException) {
                $summary['errors'][] = $row['domain'] . ': quota reached';
                break;
            } catch (Modules_Uptimeify_Api_Exception_ApiException $e) {
                $summary['errors'][] = $row['domain'] . ': ' . $e->getMessage();
            }
        }

        return $summary;
    }

    /**
     * Scheduled reconcile (cron): mirror + sync when auto-sync is enabled.
     *
     * @return array{customersCreated:int, websitesCreated:int, skipped:int, errors:list<string>}
     */
    public function reconcile(): array
    {
        if (!Modules_Uptimeify_Settings::isAutoSyncEnabled()) {
            return ['customersCreated' => 0, 'websitesCreated' => 0, 'skipped' => 0, 'errors' => []];
        }
        return $this->mirrorAndSyncAll();
    }

    private function createMonitor(array $row, string $customerPublicId, string $packageType): array
    {
        $website = $this->api->createWebsite(
            $customerPublicId,
            'Plesk: ' . $row['domain'],
            'https://' . $row['domain'],
            Modules_Uptimeify_Settings::getDefaultMonitoringType(),
            Modules_Uptimeify_Settings::getDefaultCheckInterval(),
        );

        $websitePublicId = (string) ($website['publicId'] ?? $website['id'] ?? '');
        Modules_Uptimeify_Settings::setMappingFor((string) $row['domain'], $websitePublicId, $customerPublicId, $packageType);

        if (Modules_Uptimeify_Settings::isDnsblEnabled() && (string) $row['ip'] !== '') {
            try {
                $this->api->createCustomerIp($customerPublicId, (string) $row['ip'], 'Plesk Server IP');
            } catch (Modules_Uptimeify_Api_Exception_ApiException) {
                // Best-effort: never block the website on a DNSBL/quota hiccup.
            }
        }

        return $website;
    }

    /**
     * Resolve (and optionally create) the uptimeify customer for a domain's
     * Plesk client.
     *
     * @param array<string, mixed> $row
     */
    private function resolveCustomer(array $row, string $packageType, bool $autoCreate, bool &$created): ?string
    {
        $created = false;
        $clientId = (int) $row['clientId'];

        $match = $this->matchCustomer($clientId, (string) $row['ownerName'], (string) $row['ownerEmail']);
        if ($match !== null) {
            return $match['publicId'];
        }

        if (!$autoCreate) {
            return null;
        }

        $name  = (string) $row['ownerName'] !== '' ? (string) $row['ownerName'] : (string) $row['domain'];
        $email = (string) $row['ownerEmail'] !== '' ? (string) $row['ownerEmail'] : 'webmaster@' . $row['domain'];

        $customer = $this->api->createCustomer($name, $email, $packageType);
        $publicId = (string) ($customer['publicId'] ?? '');
        if ($publicId === '') {
            return null;
        }

        Modules_Uptimeify_Settings::setCustomerForClient($clientId, $publicId);
        if ($this->customersCache !== null) {
            $this->customersCache[] = $customer; // keep the in-request cache consistent
        }
        $created = true;

        return $publicId;
    }

    /**
     * Match an existing uptimeify customer for a Plesk client (stored map first,
     * then email, then name). Persists the match. Never creates.
     *
     * @return array{publicId:string, name:string}|null
     */
    private function matchCustomer(int $clientId, string $name, string $email): ?array
    {
        $customers = $this->getCustomers();

        $mapped = Modules_Uptimeify_Settings::getCustomerForClient($clientId);
        if ($mapped !== null) {
            foreach ($customers as $c) {
                if ((string) ($c['publicId'] ?? '') === $mapped) {
                    return ['publicId' => $mapped, 'name' => (string) ($c['name'] ?? $mapped)];
                }
            }
        }

        $emailLc = strtolower(trim($email));
        $nameLc  = strtolower(trim($name));

        foreach ($customers as $c) {
            $publicId = (string) ($c['publicId'] ?? $c['id'] ?? '');
            if ($publicId === '') {
                continue;
            }
            $cEmail = strtolower(trim((string) ($c['email'] ?? '')));
            $cName  = strtolower(trim((string) ($c['name'] ?? '')));

            if (($emailLc !== '' && $cEmail === $emailLc) || ($nameLc !== '' && $cName === $nameLc)) {
                if ($clientId > 0) {
                    Modules_Uptimeify_Settings::setCustomerForClient($clientId, $publicId);
                }
                return ['publicId' => $publicId, 'name' => (string) ($c['name'] ?? $publicId)];
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getCustomers(): array
    {
        return $this->customersCache ??= $this->api->listCustomers(Modules_Uptimeify_Settings::getOrganizationId());
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findDomain(string $domain): ?array
    {
        foreach ($this->getDashboardRows() as $row) {
            if ($row['domain'] === $domain) {
                return $row;
            }
        }
        return null;
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
