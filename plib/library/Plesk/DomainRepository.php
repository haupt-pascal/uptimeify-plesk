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
     * @return list<array{name:string, asciiName:string, displayUrl:string, ip:string}>
     */
    public function all(): array
    {
        $domains = [];

        foreach (pm_Domain::getAllDomains() as $domain) {
            /** @var pm_Domain $domain */
            if (!$domain->hasHosting()) {
                continue;
            }

            $name = $domain->getName();

            $domains[] = [
                'name'       => $name,
                'asciiName'  => $domain->getAsciiName(),
                'displayUrl' => $this->toUrl($name),
                'ip'         => $this->resolveIp($domain),
            ];
        }

        usort($domains, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $domains;
    }

    private function toUrl(string $name): string
    {
        return 'https://' . $name;
    }

    private function resolveIp(pm_Domain $domain): string
    {
        try {
            $addresses = $domain->getIpAddresses();
            $first     = is_array($addresses) ? reset($addresses) : '';
            return is_string($first) ? $first : '';
        } catch (Throwable) {
            return '';
        }
    }
}
