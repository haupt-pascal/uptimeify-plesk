<?php
/**
 * Plesk admin Home page widget.
 *
 * Aggregates the state of all monitored websites and shows a compact
 * "All systems nominal" / "X systems down" line with a link into the extension.
 *
 * NOTE: the exact Home page hook surface differs slightly between Plesk Obsidian
 * releases. This targets the pm_Hook_HomePage controller-block API; verify the
 * rendered panel after install and adjust getButtons()/getControllerButtons()
 * for your target Plesk version if needed.
 */

declare(strict_types=1);

class Modules_Uptimeify_HomePage extends pm_Hook_HomePage
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getButtons(): array
    {
        $summary = $this->summarize();

        return [
            [
                'title'       => $this->lmsg('widget.title'),
                'description' => $summary['text'],
                'icon'        => pm_Context::getBaseUrl() . 'css/logo.svg',
                'link'        => pm_Context::getActionUrl('index', 'index'),
            ],
        ];
    }

    /**
     * @return array{down:int, total:int, text:string}
     */
    private function summarize(): array
    {
        if (!Modules_Uptimeify_Settings::hasApiToken()) {
            return ['down' => 0, 'total' => 0, 'text' => $this->lmsg('widget.notConnected')];
        }

        try {
            $websites = Modules_Uptimeify_Api_Client::fromSettings()->listWebsites();
        } catch (Modules_Uptimeify_Api_Exception_ApiException) {
            return ['down' => 0, 'total' => 0, 'text' => $this->lmsg('widget.notConnected')];
        }

        $down = 0;
        foreach ($websites as $site) {
            $status = strtolower((string) ($site['status'] ?? ''));
            if (in_array($status, ['down', 'inactive', 'listed'], true)) {
                $down++;
            }
        }

        $text = $down === 0
            ? $this->lmsg('widget.allNominal')
            : $this->lmsg('widget.systemsDown', ['count' => $down]);

        return ['down' => $down, 'total' => count($websites), 'text' => $text];
    }

    private function lmsg(string $key, array $params = []): string
    {
        return pm_Locale::lmsg($key, $params);
    }
}
