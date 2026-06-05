<?php

/**
 * Per-server uptimeify status block for the Plesk Home page (SPV dashboard).
 *
 * Companion to the account-wide block: this one is scoped to THIS Plesk server
 * only — the hosting domains on it and their monitors — which is what the server
 * operator actually cares about. Reads only the cached server status (no API
 * call on home-page load); each method initialises the module context first.
 */

declare(strict_types=1);

class Modules_Uptimeify_Hook_ServerStatusBlock extends \Plesk\SDK\Hook\Home\Block
{
    private const MODULE = 'uptimeify';

    public function getId(): string
    {
        return 'uptimeify-server-status';
    }

    public function getTitle(): string
    {
        pm_Context::init(self::MODULE);
        return pm_Locale::lmsg('widget.serverTitle', ['brand' => Modules_Uptimeify_Settings::getBrandName()]);
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
        $total  = Modules_Uptimeify_Settings::getServerTotal();

        // No cached server status yet — it fills on the first dashboard open
        // (or scheduled sync), since the widget never calls the API itself.
        if ($total < 0) {
            return '<div>' . htmlspecialchars(pm_Locale::lmsg('widget.serverOpen', ['brand' => $brand])) . '</div>'
                . Modules_Uptimeify_Hook_Widget::button($url, $button);
        }

        $monitored = Modules_Uptimeify_Settings::getServerMonitored();
        $attention = Modules_Uptimeify_Settings::getServerAttention();
        $incidents = Modules_Uptimeify_Settings::getServerIncidents();
        $online    = max(0, $monitored - $attention);

        return Modules_Uptimeify_Hook_Widget::statRow([
            Modules_Uptimeify_Hook_Widget::stat($monitored, pm_Locale::lmsg('widget.statMonitored'), Modules_Uptimeify_Hook_Widget::COLOR_NEUTRAL),
            Modules_Uptimeify_Hook_Widget::stat($online, pm_Locale::lmsg('widget.statOnline'), Modules_Uptimeify_Hook_Widget::COLOR_OK),
            Modules_Uptimeify_Hook_Widget::stat($attention, pm_Locale::lmsg('widget.statAttention'), Modules_Uptimeify_Hook_Widget::alertColor($attention)),
            Modules_Uptimeify_Hook_Widget::stat($incidents, pm_Locale::lmsg('widget.statIncidents'), Modules_Uptimeify_Hook_Widget::alertColor($incidents)),
        ])
            . Modules_Uptimeify_Hook_Widget::caption(pm_Locale::lmsg('widget.serverCoverage', ['monitored' => (string) $monitored, 'total' => (string) $total]))
            . Modules_Uptimeify_Hook_Widget::button($url, $button);
    }
}
