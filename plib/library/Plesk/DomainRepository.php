<?php

/**
 * Reads the local Plesk domains the admin can monitor, together with the
 * owning Plesk client (customer) — the basis for the 1:1 mirror into uptimeify.
 *
 * Uses the Plesk PHP API (pm_Domain / pm_Client) which already scopes results
 * to what the current admin/reseller is allowed to see.
 */

declare(strict_types=1);

class Modules_Uptimeify_Plesk_DomainRepository
{
    /**
     * @return list<array{
     *     name:string, displayName:string, displayUrl:string, ip:string,
     *     clientId:int, clientName:string, clientEmail:string
     * }>
     */
    public function all(): array
    {
        $domains = [];

        foreach (pm_Domain::getAllDomains() as $domain) {
            /** @var pm_Domain $domain */
            if (!$domain->hasHosting()) {
                continue;
            }

            $name   = $domain->getName(); // already ASCII / punycode
            $client = $this->clientInfo($domain);

            $domains[] = [
                'name'        => $name,
                'displayName' => $domain->getDisplayName(),
                'displayUrl'  => 'https://' . $name,
                'ip'          => $this->resolveIp($domain),
                'clientId'    => $client['id'],
                'clientName'  => $client['name'],
                'clientEmail' => $client['email'],
            ];
        }

        usort($domains, static function (array $a, array $b): int {
            return [$a['clientName'], $a['name']] <=> [$b['clientName'], $b['name']];
        });

        return $domains;
    }

    /**
     * @return array{id:int, name:string, email:string}
     */
    private function clientInfo(pm_Domain $domain): array
    {
        try {
            $client = $domain->getClient();
        } catch (Throwable) {
            return ['id' => 0, 'name' => '', 'email' => ''];
        }

        $login = '';
        try {
            $login = $client->getLogin();
        } catch (Throwable) {
            // ignore
        }

        $id = 0;
        try {
            $id = $client->getId();
        } catch (Throwable) {
            // ignore
        }

        // Plesk client property keys: cname = company, pname = contact person.
        $name = $this->prop($client, 'cname')
            ?: $this->prop($client, 'pname')
            ?: $login;

        return [
            'id'    => $id,
            'name'  => $name,
            'email' => $this->prop($client, 'email'),
        ];
    }

    /**
     * Read a client property, tolerating unknown keys / SDK exceptions.
     */
    private function prop(pm_Client $client, string $name): string
    {
        try {
            return trim((string) $client->getProperty($name));
        } catch (Throwable) {
            return '';
        }
    }

    private function resolveIp(pm_Domain $domain): string
    {
        try {
            $addresses = $domain->getIpAddresses();
        } catch (Throwable) {
            return '';
        }

        foreach ((array) $addresses as $key => $value) {
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
