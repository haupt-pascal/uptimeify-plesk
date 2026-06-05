<?php

/**
 * Registers the uptimeify admin home-page widget (Plesk "promo" block).
 */

declare(strict_types=1);

class Modules_Uptimeify_Promos extends pm_Hook_Promos
{
    /**
     * @return list<pm_Promo_AdminHome>
     */
    public function getPromos()
    {
        return [new Modules_Uptimeify_Promo_Status()];
    }
}
