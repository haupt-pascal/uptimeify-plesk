<?php

/**
 * Shared HTML rendering for the uptimeify home-page status blocks (account-wide
 * and per-server). Inline styles only — the home dashboard ships no extension
 * stylesheet. Values are ints, labels are escaped; colours are fixed constants.
 */

declare(strict_types=1);

class Modules_Uptimeify_Hook_Widget
{
    public const COLOR_NEUTRAL = '#2b2b2b';
    public const COLOR_ALERT   = '#d4351c';
    public const COLOR_OK      = '#3c8c40';

    /** Red when something needs looking at, green when all clear. */
    public static function alertColor(int $value): string
    {
        return $value > 0 ? self::COLOR_ALERT : self::COLOR_OK;
    }

    /** One stat cell: a big coloured number above a muted label. */
    public static function stat(int $value, string $label, string $color): string
    {
        return '<div style="min-width:80px">'
            . '<div style="font-size:28px;font-weight:600;line-height:1.1;color:' . $color . '">' . $value . '</div>'
            . '<div style="margin-top:2px;color:#6b6f75;font-size:12px">' . htmlspecialchars($label) . '</div>'
            . '</div>';
    }

    /** @param list<string> $cells */
    public static function statRow(array $cells): string
    {
        return '<div style="display:flex;gap:24px;flex-wrap:wrap">' . implode('', $cells) . '</div>';
    }

    public static function caption(string $text): string
    {
        return '<div style="margin-top:8px;color:#6b6f75;font-size:12px">' . htmlspecialchars($text) . '</div>';
    }

    public static function button(string $url, string $label): string
    {
        return '<div style="margin-top:14px">'
            . '<a class="btn" href="' . htmlspecialchars($url) . '">' . htmlspecialchars($label) . '</a>'
            . '</div>';
    }
}
