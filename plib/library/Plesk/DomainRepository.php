<?php

/**
 * Reads the local Plesk domains the admin can monitor.
 *
 * Uses the Plesk PHP API (pm_Domain) which already scopes results to what the
 * current admin/reseller is allowed to see.
 */

declare(strict_types=1);

class Modules_Uptimeify_Plesk_DomainRepository
{
    /**
     * @return list<array{name:string, displayName:string, displayUrl:string, ip:string}>
     */
    public function all(): array
    {
        $domains = [];

        foreach (pm_Domain::getAllDomains() as $domain) {
            /** @var pm_Domain $domain */
            if (!$domain->hasHosting()) {
                continue;
            }

            $name = $domain->getName(); // already ASCII / punycode

            $domains[] = [
                'name'        => $name,
                'displayName' => $domain->getDisplayName(),
                'displayUrl'  => 'https://' . $name,
                'ip'          => $this->resolveIp($domain),
            ];
        }

        usort($domains, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $domains;
    }

    private function resolveIp(pm_Domain $domain): string
    {
        try {
            $addresses = $domain->getIpAddresses();
        } catch (Throwable) {
            return '';
        }

        foreach ((array) $addresses as $key => $value) {
            // getIpAddresses() may return [ip => type] or [index => ip].
            if (is_string($value) && filter_var($value, FILTER_VALIDATE_IP) !== false) {
                return $value;
            }
            if (is_string($key) && filter_var($key, FILTER_VALIDATE_IP) !== false) {
                return $key;
            }
        }

        return '';
    }
}
