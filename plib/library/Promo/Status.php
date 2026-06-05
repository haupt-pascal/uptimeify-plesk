<?php

/**
 * Admin home-page widget: shows the cached monitoring status and links into the
 * extension. Reads only the cached status (no blocking API call on home load).
 */

declare(strict_types=1);

class Modules_Uptimeify_Promo_Status extends pm_Promo_AdminHome
{
    public function isActive(): bool
    {
        return Modules_Uptimeify_Settings::hasApiToken() && Modules_Uptimeify_Settings::isValidated();
    }

    public function getTitle(): string
    {
        return $this->lmsg('widget.title');
    }

    public function getText(): string
    {
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
        return pm_Context::getBaseUrl() . 'css/logo.svg';
    }

    public function getButtonText(): string
    {
        return $this->lmsg('widget.openButton');
    }

    public function getButtonUrl(): string
    {
        return pm_Context::getBaseUrl();
    }
}
