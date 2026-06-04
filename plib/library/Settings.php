<?php

/**
 * Persistent extension settings (admin-level, stored via pm_Settings).
 *
 * Holds the uptimeify organization API token, the resolved organization id,
 * sync defaults and the local domain -> remote monitor mapping.
 */

declare(strict_types=1);

class Modules_Uptimeify_Settings
{
    public const KEY_API_TOKEN       = 'apiToken';
    public const KEY_ORG_ID          = 'organizationId';
    public const KEY_ORG_NAME        = 'organizationName';
    public const KEY_VALIDATED       = 'connectionValidated';
    public const KEY_AUTO_SYNC       = 'autoSyncEnabled';
    public const KEY_AUTO_CREATE     = 'autoCreateNewDomains';
    public const KEY_DEFAULT_CUSTOMER = 'defaultCustomerPublicId';
    public const KEY_DEFAULT_PACKAGE = 'defaultPackageType';
    public const KEY_CHECK_INTERVAL  = 'defaultCheckInterval';
    public const KEY_MONITORING_TYPE = 'defaultMonitoringType';
    public const KEY_DNSBL_ENABLED   = 'dnsblEnabled';
    public const KEY_MAPPING         = 'domainMapping';

    public static function getApiToken(): string
    {
        return (string) pm_Settings::get(self::KEY_API_TOKEN, '');
    }

    public static function hasApiToken(): bool
    {
        return self::getApiToken() !== '';
    }

    public static function setApiToken(string $token): void
    {
        pm_Settings::set(self::KEY_API_TOKEN, trim($token));
        // A new/changed token must be re-validated before it counts as connected.
        self::setValidated(false);
    }

    /**
     * Whether the stored token has been successfully validated against the API.
     * This is the source of truth for "connected" — the organization id is not
     * required (it is derived from the token by the API).
     */
    public static function isValidated(): bool
    {
        return (bool) pm_Settings::get(self::KEY_VALIDATED, false);
    }

    public static function setValidated(bool $validated): void
    {
        pm_Settings::set(self::KEY_VALIDATED, $validated ? '1' : '');
    }

    public static function getOrganizationId(): ?int
    {
        $value = pm_Settings::get(self::KEY_ORG_ID, '');
        return $value === '' ? null : (int) $value;
    }

    public static function setOrganization(int $id, string $name): void
    {
        pm_Settings::set(self::KEY_ORG_ID, (string) $id);
        pm_Settings::set(self::KEY_ORG_NAME, $name);
    }

    public static function getOrganizationName(): string
    {
        return (string) pm_Settings::get(self::KEY_ORG_NAME, '');
    }

    public static function isAutoSyncEnabled(): bool
    {
        return (bool) pm_Settings::get(self::KEY_AUTO_SYNC, false);
    }

    public static function setAutoSyncEnabled(bool $enabled): void
    {
        pm_Settings::set(self::KEY_AUTO_SYNC, $enabled ? '1' : '');
    }

    public static function isAutoCreateEnabled(): bool
    {
        return (bool) pm_Settings::get(self::KEY_AUTO_CREATE, false);
    }

    public static function setAutoCreateEnabled(bool $enabled): void
    {
        pm_Settings::set(self::KEY_AUTO_CREATE, $enabled ? '1' : '');
    }

    public static function getDefaultCustomerPublicId(): string
    {
        return (string) pm_Settings::get(self::KEY_DEFAULT_CUSTOMER, '');
    }

    public static function setDefaultCustomerPublicId(string $publicId): void
    {
        pm_Settings::set(self::KEY_DEFAULT_CUSTOMER, $publicId);
    }

    public static function getDefaultPackageType(): string
    {
        return (string) pm_Settings::get(self::KEY_DEFAULT_PACKAGE, '');
    }

    public static function setDefaultPackageType(string $packageType): void
    {
        pm_Settings::set(self::KEY_DEFAULT_PACKAGE, $packageType);
    }

    public static function getDefaultCheckInterval(): int
    {
        return (int) pm_Settings::get(self::KEY_CHECK_INTERVAL, 5);
    }

    public static function setDefaultCheckInterval(int $minutes): void
    {
        pm_Settings::set(self::KEY_CHECK_INTERVAL, (string) max(1, min(60, $minutes)));
    }

    public static function getDefaultMonitoringType(): string
    {
        return (string) pm_Settings::get(self::KEY_MONITORING_TYPE, 'combined');
    }

    public static function setDefaultMonitoringType(string $type): void
    {
        pm_Settings::set(self::KEY_MONITORING_TYPE, $type);
    }

    public static function isDnsblEnabled(): bool
    {
        return (bool) pm_Settings::get(self::KEY_DNSBL_ENABLED, false);
    }

    public static function setDnsblEnabled(bool $enabled): void
    {
        pm_Settings::set(self::KEY_DNSBL_ENABLED, $enabled ? '1' : '');
    }

    /**
     * Domain mapping: domainName => ['websitePublicId' => ..., 'customerPublicId' => ..., 'packageType' => ...].
     *
     * @return array<string, array{websitePublicId:string, customerPublicId:string, packageType:string}>
     */
    public static function getMapping(): array
    {
        $raw = (string) pm_Settings::get(self::KEY_MAPPING, '');
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array{websitePublicId:string, customerPublicId:string, packageType:string}|null
     */
    public static function getMappingFor(string $domain): ?array
    {
        return self::getMapping()[$domain] ?? null;
    }

    public static function setMappingFor(string $domain, string $websitePublicId, string $customerPublicId, string $packageType): void
    {
        $mapping = self::getMapping();
        $mapping[$domain] = [
            'websitePublicId'  => $websitePublicId,
            'customerPublicId' => $customerPublicId,
            'packageType'      => $packageType,
        ];
        self::saveMapping($mapping);
    }

    public static function removeMappingFor(string $domain): void
    {
        $mapping = self::getMapping();
        unset($mapping[$domain]);
        self::saveMapping($mapping);
    }

    /**
     * @param array<string, array<string, string>> $mapping
     */
    private static function saveMapping(array $mapping): void
    {
        pm_Settings::set(self::KEY_MAPPING, json_encode($mapping, JSON_THROW_ON_ERROR));
    }
}
