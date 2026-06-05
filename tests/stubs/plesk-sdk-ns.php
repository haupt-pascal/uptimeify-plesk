<?php

declare(strict_types=1);

namespace Plesk\SDK\Hook\Home;

abstract class Block
{
    public const SECTION_PLESK = 'plesk';
    public const SECTION_SERVER = 'server';
    public const SECTION_SECURITY = 'security';

    public function getId(): string
    {
        return '';
    }

    public function getTitle(): string
    {
        return '';
    }

    public function getContent(): string
    {
        return '';
    }

    public function getColumn(): int
    {
        return 2;
    }

    public function isAsyncLoaded(): bool
    {
        return true;
    }
}
