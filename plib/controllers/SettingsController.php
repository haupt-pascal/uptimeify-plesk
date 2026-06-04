<?php

/**
 * Settings tab as a two-step setup wizard:
 *   Step 1 — connect the organization API token (validated against the API).
 *   Step 2 — once connected, configure sync defaults (customer/package, etc.).
 *
 * Uses native Plesk styling only (no custom stylesheet).
 */

declare(strict_types=1);

class SettingsController extends pm_Controller_Action
{
    public function init(): void
    {
        parent::init();

        $this->view->pageTitle = $this->lmsg('pageTitle');

        $this->view->tabs = [
            ['title' => $this->lmsg('tabs.dashboard'), 'action' => 'index', 'controller' => 'index'],
            ['title' => $this->lmsg('tabs.settings'), 'action' => 'index', 'controller' => 'settings'],
        ];
    }

    public function indexAction(): void
    {
        // Auto-validate an already stored token (e.g. after an upgrade) so the
        // admin doesn't have to re-enter it to reach step 2.
        if (!$this->getRequest()->isPost()
            && Modules_Uptimeify_Settings::hasApiToken()
            && !Modules_Uptimeify_Settings::isValidated()) {
            $this->handshake();
        }

        $connected = Modules_Uptimeify_Settings::hasApiToken()
            && Modules_Uptimeify_Settings::isValidated();

        $form = $this->buildForm((bool) $connected);

        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {
            $this->handleSubmit($form, (bool) $connected);
            return;
        }

        $this->view->connected = (bool) $connected;
        $this->view->orgName   = Modules_Uptimeify_Settings::getOrganizationName();
        $this->view->form      = $form;
    }

    private function handleSubmit(pm_Form_Simple $form, bool $connected): void
    {
        $token = trim((string) $form->getValue(Modules_Uptimeify_Settings::KEY_API_TOKEN));

        // When already connected, an empty token field means "keep current".
        if ($token !== '' || !$connected) {
            if ($token === '') {
                $this->_status->addMessage('error', $this->lmsg('error.missingParams'));
                $this->_helper->json(['redirect' => pm_Context::getActionUrl('settings', 'index')]);
                return;
            }
            if (!str_starts_with($token, 'wsm_')) {
                $this->_status->addMessage('error', $this->lmsg('settings.invalidPrefix'));
                $this->_helper->json(['redirect' => pm_Context::getActionUrl('settings', 'index')]);
                return;
            }

            Modules_Uptimeify_Settings::setApiToken($token);
            if (!$this->handshake()) {
                $this->_helper->json(['redirect' => pm_Context::getActionUrl('settings', 'index')]);
                return;
            }
        }

        // Persist sync defaults only in the connected (step 2) form.
        if ($connected) {
            $this->saveDefaults($form);
            $this->_status->addMessage('info', $this->lmsg('settings.saved'));
            $this->_helper->json(['redirect' => pm_Context::getActionUrl('index', 'index')]);
            return;
        }

        // Just connected for the first time -> reload settings into step 2.
        $this->_helper->json(['redirect' => pm_Context::getActionUrl('settings', 'index')]);
    }

    private function saveDefaults(pm_Form_Simple $form): void
    {
        Modules_Uptimeify_Settings::setAutoSyncEnabled((bool) $form->getValue(Modules_Uptimeify_Settings::KEY_AUTO_SYNC));
        Modules_Uptimeify_Settings::setSyncInterval((string) $form->getValue(Modules_Uptimeify_Settings::KEY_SYNC_INTERVAL));
        Modules_Uptimeify_Settings::setAutoCreateCustomersEnabled((bool) $form->getValue(Modules_Uptimeify_Settings::KEY_AUTO_CREATE_CUSTOMERS));
        Modules_Uptimeify_Settings::setDnsblEnabled((bool) $form->getValue(Modules_Uptimeify_Settings::KEY_DNSBL_ENABLED));
        Modules_Uptimeify_Settings::setDefaultPackageType((string) $form->getValue(Modules_Uptimeify_Settings::KEY_DEFAULT_PACKAGE));
        Modules_Uptimeify_Settings::setDefaultCheckInterval((int) $form->getValue(Modules_Uptimeify_Settings::KEY_CHECK_INTERVAL));
        Modules_Uptimeify_Settings::setDefaultMonitoringType((string) $form->getValue(Modules_Uptimeify_Settings::KEY_MONITORING_TYPE));

        // Re-register the Plesk scheduled task to match the new sync settings.
        try {
            Modules_Uptimeify_Scheduler::apply();
        } catch (Throwable $e) {
            $this->_status->addMessage('warning', $this->lmsg('settings.scheduleWarning', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Resolve the organization for the stored token and persist id + name.
     */
    private function handshake(): bool
    {
        try {
            $org = Modules_Uptimeify_Api_Client::fromSettings()->getOrganization();
            Modules_Uptimeify_Settings::setOrganization(
                (int) ($org['id'] ?? 0),
                (string) ($org['name'] ?? ''),
            );
            Modules_Uptimeify_Settings::setValidated(true);
            $this->_status->addMessage('info', $this->lmsg('settings.connected', [
                'org' => (string) ($org['name'] ?? ''),
            ]));
            return true;
        } catch (Modules_Uptimeify_Api_Exception_UnauthorizedException) {
            Modules_Uptimeify_Settings::setValidated(false);
            $this->_status->addMessage('error', $this->lmsg('settings.connectFailed'));
            return false;
        } catch (Modules_Uptimeify_Api_Exception_ApiException $e) {
            Modules_Uptimeify_Settings::setValidated(false);
            $this->_status->addMessage('error', $e->getMessage());
            return false;
        }
    }

    private function buildForm(bool $connected): pm_Form_Simple
    {
        $form = new pm_Form_Simple();

        $form->addElement('password', Modules_Uptimeify_Settings::KEY_API_TOKEN, [
            'label'          => $connected ? $this->lmsg('settings.changeToken') : $this->lmsg('settings.apiToken'),
            'description'    => $this->lmsg('settings.apiTokenHint'),
            'renderPassword' => true,
            'required'       => !$connected,
        ]);

        if ($connected) {
            // --- Automatic synchronization ---
            $form->addElement('checkbox', Modules_Uptimeify_Settings::KEY_AUTO_SYNC, [
                'label'       => $this->lmsg('settings.autoSync'),
                'checked'     => Modules_Uptimeify_Settings::isAutoSyncEnabled(),
                'description' => $this->lmsg('settings.autoSyncHint'),
            ]);

            $form->addElement('select', Modules_Uptimeify_Settings::KEY_SYNC_INTERVAL, [
                'label'        => $this->lmsg('settings.syncInterval'),
                'multiOptions' => [
                    'every_15_min' => $this->lmsg('settings.interval15'),
                    'every_30_min' => $this->lmsg('settings.interval30'),
                    'hourly'       => $this->lmsg('settings.intervalHourly'),
                    'daily'        => $this->lmsg('settings.intervalDaily'),
                ],
                'value'        => Modules_Uptimeify_Settings::getSyncInterval(),
                'description'  => $this->lmsg('settings.syncIntervalHint'),
            ]);

            $form->addElement('checkbox', Modules_Uptimeify_Settings::KEY_AUTO_CREATE_CUSTOMERS, [
                'label'       => $this->lmsg('settings.autoCreateCustomers'),
                'checked'     => Modules_Uptimeify_Settings::isAutoCreateCustomersEnabled(),
                'description' => $this->lmsg('settings.autoCreateCustomersHint'),
            ]);

            $form->addElement('select', Modules_Uptimeify_Settings::KEY_DEFAULT_PACKAGE, [
                'label'        => $this->lmsg('settings.defaultPackage'),
                'multiOptions' => $this->packageOptions(),
                'value'        => Modules_Uptimeify_Settings::getDefaultPackageType(),
                'description'  => $this->lmsg('settings.defaultPackageHint'),
            ]);

            // --- Monitor defaults ---
            $form->addElement('select', Modules_Uptimeify_Settings::KEY_MONITORING_TYPE, [
                'label'        => $this->lmsg('settings.monitoringType'),
                'multiOptions' => [
                    'combined'    => 'Combined',
                    'http status' => 'HTTP status',
                    'ssl check'   => 'SSL check',
                ],
                'value'        => Modules_Uptimeify_Settings::getDefaultMonitoringType(),
            ]);

            $form->addElement('text', Modules_Uptimeify_Settings::KEY_CHECK_INTERVAL, [
                'label'       => $this->lmsg('settings.checkInterval'),
                'value'       => Modules_Uptimeify_Settings::getDefaultCheckInterval(),
                'description' => $this->lmsg('settings.checkIntervalHint'),
            ]);

            $form->addElement('checkbox', Modules_Uptimeify_Settings::KEY_DNSBL_ENABLED, [
                'label'       => $this->lmsg('settings.dnsbl'),
                'checked'     => Modules_Uptimeify_Settings::isDnsblEnabled(),
                'description' => $this->lmsg('settings.dnsblHint'),
            ]);
        }

        $form->addControlButtons([
            'cancelHidden' => true,
            'sendTitle'    => $connected ? $this->lmsg('settings.save') : $this->lmsg('settings.connectButton'),
        ]);

        return $form;
    }

    /**
     * @return array<string, string>
     */
    private function packageOptions(): array
    {
        $options = ['' => $this->lmsg('settings.choose')];
        try {
            foreach (Modules_Uptimeify_Api_Client::fromSettings()->listPackageConfigs() as $p) {
                $key = (string) ($p['packageType'] ?? '');
                if ($key !== '') {
                    $options[$key] = (string) ($p['displayName'] ?? $p['packageType']);
                }
            }
        } catch (Modules_Uptimeify_Api_Exception_ApiException) {
            // ignore — banner reports it
        }
        return $options;
    }
}
