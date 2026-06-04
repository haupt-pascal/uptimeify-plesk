<?php

/**
 * Minimal stubs of the Plesk Extension SDK classes used by the library layer.
 *
 * These exist ONLY for static analysis (PHPStan) and unit tests — the real
 * implementations are provided by the Plesk runtime. pm_Settings is given a
 * working in-memory backing store so the pure library can be exercised in tests.
 */

declare(strict_types=1);

if (!class_exists('pm_Settings')) {
    class pm_Settings
    {
        /** @var array<string, string> */
        private static array $store = [];

        public static function get(string $key, mixed $default = null): mixed
        {
            return self::$store[$key] ?? $default;
        }

        public static function set(string $key, string $value): void
        {
            self::$store[$key] = $value;
        }

        public static function reset(): void
        {
            self::$store = [];
        }
    }
}

if (!class_exists('pm_Context')) {
    class pm_Context
    {
        public static function getPlibDir(): string
        {
            return '';
        }
    }
}

if (!class_exists('pm_Domain')) {
    class pm_Domain
    {
        /** @return list<pm_Domain> */
        public static function getAllDomains(): array
        {
            return [];
        }

        public function hasHosting(): bool
        {
            return true;
        }

        public function getName(): string
        {
            return '';
        }

        public function getDisplayName(): string
        {
            return '';
        }

        /** @return array<int|string, string> */
        public function getIpAddresses(bool $public = true): array
        {
            return [];
        }

        public function getClient(): pm_Client
        {
            return new pm_Client();
        }
    }
}

if (!class_exists('pm_Client')) {
    class pm_Client
    {
        public function getId(): int
        {
            return 0;
        }

        public function getLogin(): string
        {
            return '';
        }

        public function getProperty(string $name): string
        {
            return '';
        }
    }
}
