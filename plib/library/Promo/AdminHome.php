<?php

/**
 * Admin home-page widget (Plesk promo block).
 *
 * Promos run in the Plesk home-page context, not the extension context, so each
 * method must call pm_Context::init() first before using lmsg()/pm_Settings/etc.
 * Reads only the cached status — no blocking API call on home-page load.
 */

declare(strict_types=1);

class Modules_Uptimeify_Promo_AdminHome extends pm_Promo_AdminHome
{
    private const MODULE = 'uptimeify';

    public function getTitle(): string
    {
        pm_Context::init(self::MODULE);
        return $this->lmsg('widget.title');
    }

    public function getText(): string
    {
        pm_Context::init(self::MODULE);

        $total = Modules_Uptimeify_Settings::getStatusTotal();
        if ($total < 0) {
            return $this->lmsg('widget.open');
        }

        $down = Modules_Uptimeify_Settings::getStatusDown();
        if ($down > 0) {
            return $this->lmsg('widget.attention', ['count' => (string) $down, 'total' => (string) $total]);
        }

        return $this->lmsg('widget.allNominal', ['total' => (string) $total]);
    }

    public function getIconUrl(): string
    {
        pm_Context::init(self::MODULE);
        return pm_Context::getBaseUrl() . 'css/logo.svg';
    }

    public function getButtonText(): string
    {
        pm_Context::init(self::MODULE);
        return $this->lmsg('widget.openButton');
    }

    public function getButtonUrl(): string
    {
        pm_Context::init(self::MODULE);
        return pm_Context::getBaseUrl();
    }
}
