<?php

/**
 * uptimeify status block for the Plesk Home page (SPV dashboard).
 *
 * Extends the namespaced SDK base class but keeps the Plesk underscore class
 * name so it is autoloaded normally. Reads only the cached status — no blocking
 * API call on home-page load. Each method initialises the module context first,
 * since the block runs outside the extension context.
 */

declare(strict_types=1);

class Modules_Uptimeify_Hook_StatusBlock extends \Plesk\SDK\Hook\Home\Block
{
    private const MODULE = 'uptimeify';

    private const COLOR_NEUTRAL = '#2b2b2b';
    private const COLOR_ALERT   = '#d4351c';
    private const COLOR_OK      = '#3c8c40';

    public function getId(): string
    {
        return 'uptimeify-status';
    }

    public function getTitle(): string
    {
        pm_Context::init(self::MODULE);
        return pm_Locale::lmsg('widget.title', ['brand' => Modules_Uptimeify_Settings::getBrandName()]);
    }

    public function isAsyncLoaded(): bool
    {
        return false;
    }

    public function getContent(): string
    {
        pm_Context::init(self::MODULE);

        $brand  = Modules_Uptimeify_Settings::getBrandName();
        $url    = pm_Context::getBaseUrl();
        $button = pm_Locale::lmsg('widget.openButton', ['brand' => $brand]);
        $total  = Modules_Uptimeify_Settings::getStatusTotal();

        // No cached status yet (dashboard never opened) — prompt to sync.
        if ($total < 0) {
            return '<div>' . htmlspecialchars(pm_Locale::lmsg('widget.open', ['brand' => $brand])) . '</div>'
                . $this->buttonHtml($url, $button);
        }

        $down      = Modules_Uptimeify_Settings::getStatusDown();
        $incidents = Modules_Uptimeify_Settings::getStatusIncidents();

        $stats = $this->statHtml($total, pm_Locale::lmsg('widget.statMonitors'), self::COLOR_NEUTRAL)
            . $this->statHtml($down, pm_Locale::lmsg('widget.statAttention'), $down > 0 ? self::COLOR_ALERT : self::COLOR_OK)
            . $this->statHtml($incidents, pm_Locale::lmsg('widget.statIncidents'), $incidents > 0 ? self::COLOR_ALERT : self::COLOR_OK);

        return '<div style="display:flex;gap:24px;flex-wrap:wrap">' . $stats . '</div>'
            . '<div style="margin-top:8px;color:#6b6f75;font-size:12px">'
            . htmlspecialchars(pm_Locale::lmsg('widget.scope', ['brand' => $brand])) . '</div>'
            . $this->buttonHtml($url, $button);
    }

    /**
     * One stat cell: a big coloured number above a muted label.
     */
    private function statHtml(int $value, string $label, string $color): string
    {
        return '<div style="min-width:80px">'
            . '<div style="font-size:28px;font-weight:600;line-height:1.1;color:' . $color . '">' . $value . '</div>'
            . '<div style="margin-top:2px;color:#6b6f75;font-size:12px">' . htmlspecialchars($label) . '</div>'
            . '</div>';
    }

    private function buttonHtml(string $url, string $label): string
    {
        return '<div style="margin-top:14px">'
            . '<a class="btn" href="' . htmlspecialchars($url) . '">' . htmlspecialchars($label) . '</a>'
            . '</div>';
    }
}
