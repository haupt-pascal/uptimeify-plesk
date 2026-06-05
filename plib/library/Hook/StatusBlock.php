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

    public function getId(): string
    {
        return 'uptimeify-status';
    }

    public function getTitle(): string
    {
        pm_Context::init(self::MODULE);
        return pm_Locale::lmsg('widget.title');
    }

    public function isAsyncLoaded(): bool
    {
        return false;
    }

    public function getContent(): string
    {
        pm_Context::init(self::MODULE);

        $total = Modules_Uptimeify_Settings::getStatusTotal();
        if ($total < 0) {
            $text = pm_Locale::lmsg('widget.open');
        } else {
            $down = Modules_Uptimeify_Settings::getStatusDown();
            $text = $down > 0
                ? pm_Locale::lmsg('widget.attention', ['count' => (string) $down, 'total' => (string) $total])
                : pm_Locale::lmsg('widget.allNominal', ['total' => (string) $total]);
        }

        $url    = pm_Context::getBaseUrl();
        $button = pm_Locale::lmsg('widget.openButton');

        return '<div>' . htmlspecialchars($text) . '</div>'
            . '<div style="margin-top:10px">'
            . '<a class="btn" href="' . htmlspecialchars($url) . '">' . htmlspecialchars($button) . '</a>'
            . '</div>';
    }
}
