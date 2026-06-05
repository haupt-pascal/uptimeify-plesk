<?php

/**
 * Plesk Home page (SPV dashboard) hook — registers the uptimeify status block.
 * Available since Plesk Obsidian 18.0.60.
 */

declare(strict_types=1);

return new class () extends \Plesk\SDK\Hook\Home {
    /**
     * @return list<\Plesk\SDK\Hook\Home\Block>
     */
    public function getBlocks(): array
    {
        return [new \Modules_Uptimeify_Hook_StatusBlock()];
    }
};
