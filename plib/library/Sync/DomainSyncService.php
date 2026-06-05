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

    /** @var array<string, bool> Server IPs already ensured per customer this run. */
    private array $ipEnsured = [];

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
        $websites = $this->api->listWebsites();
        $monitors = $this->indexMonitorsByUrl($websites);
        $mapping  = Modules_Uptimeify_Settings::getMapping();
        $rows     = [];

        foreach ($this->domains->all() as $domain) {
            $name     = (string) $domain['name'];
            $monitor  = $monitors[$this->normalizeUrl($domain['displayUrl'])] ?? null;
            $map      = $mapping[$name] ?? null;
            $existing = $monitor === null
                ? $this->matchCustomer((int) $domain['clientId'], (string) $domain['clientName'], (string) $domain['clientEmail'])
                : null;

            $ignored = Modules_Uptimeify_Settings::isIgnored($name);
            $preview = self::isPreviewDomain($name);

            $rows[] = [
                'domain'           => $name,
                'url'              => $domain['displayUrl'],
                'ip'               => $domain['ip'],
                'clientId'         => $domain['clientId'],
                'ownerName'        => $domain['clientName'] !== '' ? $domain['clientName'] : $domain['clientEmail'],
                'ownerEmail'       => $domain['clientEmail'],
                'monitored'        => $monitor !== null,
                'ignored'          => $ignored,
                'preview'          => $preview,
                'skip'             => $ignored || $preview,
                'excludedByFilter' => $this->isExcludedByFilter((int) $domain['clientId']),
                'websitePublicId'  => $monitor['publicId'] ?? ($map['websitePublicId'] ?? null),
                'status'           => $monitor['status'] ?? null,
                'monitoringType'   => $monitor['monitoringType'] ?? null,
                'customerName'     => $monitor['customerName'] ?? ($existing['name'] ?? null),
                'customerPublicId' => $monitor['customerPublicId'] ?? ($existing['publicId'] ?? null),
            ];
        }

        $this->cacheStatus($websites, $rows);

        return $rows;
    }

    /**
     * Plesk preview/temporary domains (e.g. *.plesk.page) are not real public
     * domains and are excluded from automatic sync by default.
     */
    public static function isPreviewDomain(string $name): bool
    {
        return str_ends_with(strtolower($name), '.plesk.page');
    }

    /**
     * Whether a domain's customer is excluded by the black/whitelist filter.
     * Precedence: an explicit per-customer state wins over the mode default.
     */
    private function isExcludedByFilter(int $clientId): bool
    {
        $state = Modules_Uptimeify_Settings::getCustomerState($clientId);
        if ($state === 'skip') {
            return true;
        }
        if ($state === 'sync') {
            return false;
        }
        return Modules_Uptimeify_Settings::getFilterMode() === 'whitelist';
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
     * @return array<string, mixed> The created website record.
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
        return $this->syncRows($this->getDashboardRows());
    }

    /**
     * Sync only the given domains (by name) — used by the "sync selected" action.
     *
     * @param list<string> $domains
     * @return array{customersCreated:int, websitesCreated:int, skipped:int, errors:list<string>}
     */
    public function syncSelected(array $domains): array
    {
        $wanted = [];
        foreach ($domains as $domain) {
            $wanted[$domain] = true;
        }

        $rows = [];
        foreach ($this->getDashboardRows() as $row) {
            if (isset($wanted[(string) $row['domain']])) {
                $rows[] = $row;
            }
        }

        return $this->syncRows($rows);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array{customersCreated:int, websitesCreated:int, skipped:int, errors:list<string>}
     */
    private function syncRows(array $rows): array
    {
        $summary     = ['customersCreated' => 0, 'websitesCreated' => 0, 'skipped' => 0, 'errors' => []];
        $autoCreate  = Modules_Uptimeify_Settings::isAutoCreateCustomersEnabled();
        $packageType = Modules_Uptimeify_Settings::getDefaultPackageType();

        foreach ($rows as $row) {
            if ($row['monitored'] || !empty($row['skip']) || !empty($row['excludedByFilter'])) {
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

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
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

        if (Modules_Uptimeify_Settings::isDnsblEnabled()) {
            $this->ensureServerIp($customerPublicId, (string) $row['ip']);
        }

        return $website;
    }

    /**
     * Register the server IP for the customer's DNSBL monitoring — once per
     * (customer, IP). Every customer hosted on the server gets its own entry so
     * each one is notified about blacklisting; duplicates are deduplicated by the
     * API ("IP already exists", 409) and skipped within a run.
     */
    private function ensureServerIp(string $customerPublicId, string $ip): void
    {
        if ($ip === '') {
            return;
        }

        $key = $customerPublicId . '|' . $ip;
        if (isset($this->ipEnsured[$key])) {
            return;
        }
        $this->ipEnsured[$key] = true;

        try {
            $this->api->createCustomerIp($customerPublicId, $ip, 'Plesk: ' . $this->serverHostname());
        } catch (Modules_Uptimeify_Api_Exception_ApiException) {
            // 409 = already registered for this customer (fine); other errors are
            // best-effort and must never block the website creation.
        }
    }

    private function serverHostname(): string
    {
        $host = gethostname();
        return is_string($host) && $host !== '' ? $host : 'server';
    }

    /**
     * Cache the home-page widget metrics, in two scopes:
     *  - account-wide (the whole uptimeify organization: every monitor/incident),
     *  - this Plesk server only (its hosting domains and their monitors).
     *
     * Incidents are fetched once and reused for both. Everything is best-effort:
     * the widget metrics must never break the dashboard load, so on an incident
     * API error we keep the last cached incident counts.
     *
     * @param list<array<string, mixed>> $websites
     * @param list<array<string, mixed>> $rows
     */
    private function cacheStatus(array $websites, array $rows): void
    {
        $incidents = $this->fetchIncidents();

        // --- Account-wide (whole uptimeify organization) ---
        $down = 0;
        foreach ($websites as $site) {
            if ($this->needsAttention((string) ($site['status'] ?? ''))) {
                $down++;
            }
        }
        $orgIncidents = $incidents === null
            ? Modules_Uptimeify_Settings::getStatusIncidents()
            : $this->countOpenIncidents($incidents, null);
        Modules_Uptimeify_Settings::setStatus($down, count($websites), $orgIncidents);

        // --- This Plesk server only ---
        $serverTotal     = 0;
        $serverMonitored = 0;
        $serverAttention = 0;
        $serverUrls      = [];
        foreach ($rows as $row) {
            if (!empty($row['preview'])) {
                continue; // *.plesk.page previews are not real domains
            }
            $serverTotal++;
            if (!empty($row['monitored'])) {
                $serverMonitored++;
                $serverUrls[$this->normalizeUrl((string) ($row['url'] ?? ''))] = true;
                if ($this->needsAttention((string) ($row['status'] ?? ''))) {
                    $serverAttention++;
                }
            }
        }
        $serverIncidents = $incidents === null
            ? Modules_Uptimeify_Settings::getServerIncidents()
            : $this->countOpenIncidents($incidents, $serverUrls);
        Modules_Uptimeify_Settings::setServerStatus($serverTotal, $serverMonitored, $serverAttention, $serverIncidents);
    }

    private function needsAttention(string $status): bool
    {
        return in_array(strtolower($status), ['down', 'inactive', 'paused', 'listed'], true);
    }

    /**
     * Fetch org-wide incidents, or null when the API call fails (signals the
     * caller to keep the last cached counts).
     *
     * @return list<array<string, mixed>>|null
     */
    private function fetchIncidents(): ?array
    {
        try {
            return $this->api->listIncidents(Modules_Uptimeify_Settings::getOrganizationId());
        } catch (Modules_Uptimeify_Api_Exception_ApiException) {
            return null;
        }
    }

    /**
     * Count open incidents. When $urls is non-null, only incidents whose website
     * URL is in the set are counted (server scope); null counts all (org scope).
     *
     * @param list<array<string, mixed>> $incidents
     * @param array<string, bool>|null   $urls
     */
    private function countOpenIncidents(array $incidents, ?array $urls): int
    {
        $open = 0;
        foreach ($incidents as $incident) {
            if (strtolower((string) ($incident['status'] ?? '')) !== 'open') {
                continue;
            }
            if ($urls !== null) {
                $website = $incident['website'] ?? null;
                $url     = is_array($website) ? $this->normalizeUrl((string) ($website['url'] ?? '')) : '';
                if ($url === '' || !isset($urls[$url])) {
                    continue;
                }
            }
            $open++;
        }
        return $open;
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
