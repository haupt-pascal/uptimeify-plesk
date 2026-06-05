<?php

/**
 * Account-wide uptimeify status block for the Plesk Home page (SPV dashboard).
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
                . Modules_Uptimeify_Hook_Widget::button($url, $button);
        }

        $down      = Modules_Uptimeify_Settings::getStatusDown();
        $incidents = Modules_Uptimeify_Settings::getStatusIncidents();

        return Modules_Uptimeify_Hook_Widget::statRow([
            Modules_Uptimeify_Hook_Widget::stat($total, pm_Locale::lmsg('widget.statMonitors'), Modules_Uptimeify_Hook_Widget::COLOR_NEUTRAL),
            Modules_Uptimeify_Hook_Widget::stat($down, pm_Locale::lmsg('widget.statAttention'), Modules_Uptimeify_Hook_Widget::alertColor($down)),
            Modules_Uptimeify_Hook_Widget::stat($incidents, pm_Locale::lmsg('widget.statIncidents'), Modules_Uptimeify_Hook_Widget::alertColor($incidents)),
        ])
            . Modules_Uptimeify_Hook_Widget::caption(pm_Locale::lmsg('widget.scope', ['brand' => $brand]))
            . Modules_Uptimeify_Hook_Widget::button($url, $button);
    }
}
