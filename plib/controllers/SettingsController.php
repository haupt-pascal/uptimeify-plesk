<?php
/**
 * Settings tab: API token, connection handshake and sync defaults.
 */

declare(strict_types=1);

class SettingsController extends pm_Controller_Action
{
    public function init(): void
    {
        parent::init();

        $this->view->pageTitle = $this->lmsg('pageTitle');
        $this->view->headLink()->appendStylesheet(pm_Context::getBaseUrl() . 'css/uptimeify.css');

        $this->_tabs = [
            ['title' => $this->lmsg('tabs.dashboard'), 'action' => 'index', 'controller' => 'index'],
            ['title' => $this->lmsg('tabs.settings'), 'action' => 'index'],
        ];
    }

    public function indexAction(): void
    {
        $form = $this->buildForm();

        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {
            $token = trim((string) $form->getValue(Modules_Uptimeify_Settings::KEY_API_TOKEN));

            if ($token !== '' && !str_starts_with($token, 'wsm_')) {
                $this->_status->addMessage('error', $this->lmsg('settings.invalidPrefix'));
                $this->_helper->json(['redirect' => pm_Context::getBaseUrl()]);
                return;
            }

            Modules_Uptimeify_Settings::setApiToken($token);

            if ($token !== '' && !$this->handshake()) {
                $this->_helper->json(['redirect' => pm_Context::getBaseUrl()]);
                return;
            }

            Modules_Uptimeify_Settings::setAutoSyncEnabled((bool) $form->getValue(Modules_Uptimeify_Settings::KEY_AUTO_SYNC));
            Modules_Uptimeify_Settings::setAutoCreateEnabled((bool) $form->getValue(Modules_Uptimeify_Settings::KEY_AUTO_CREATE));
            Modules_Uptimeify_Settings::setDnsblEnabled((bool) $form->getValue(Modules_Uptimeify_Settings::KEY_DNSBL_ENABLED));
            Modules_Uptimeify_Settings::setDefaultCustomerPublicId((string) $form->getValue(Modules_Uptimeify_Settings::KEY_DEFAULT_CUSTOMER));
            Modules_Uptimeify_Settings::setDefaultPackageType((string) $form->getValue(Modules_Uptimeify_Settings::KEY_DEFAULT_PACKAGE));
            Modules_Uptimeify_Settings::setDefaultCheckInterval((int) $form->getValue(Modules_Uptimeify_Settings::KEY_CHECK_INTERVAL));
            Modules_Uptimeify_Settings::setDefaultMonitoringType((string) $form->getValue(Modules_Uptimeify_Settings::KEY_MONITORING_TYPE));

            $this->_status->addMessage('info', $this->lmsg('settings.saved'));
            $this->_helper->json(['redirect' => pm_Context::getBaseUrl() . 'index/index']);
            return;
        }

        $this->view->form = $form;
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
            $this->_status->addMessage('info', $this->lmsg('settings.connected', [
                'org' => (string) ($org['name'] ?? ''),
            ]));
            return true;
        } catch (Modules_Uptimeify_Api_Exception_UnauthorizedException) {
            $this->_status->addMessage('error', $this->lmsg('settings.connectFailed'));
            return false;
        } catch (Modules_Uptimeify_Api_Exception_ApiException $e) {
            $this->_status->addMessage('error', $e->getMessage());
            return false;
        }
    }

    private function buildForm(): pm_Form_Simple
    {
        $form = new pm_Form_Simple();

        $form->addElement('password', Modules_Uptimeify_Settings::KEY_API_TOKEN, [
            'label'       => $this->lmsg('settings.apiToken'),
            'value'       => Modules_Uptimeify_Settings::getApiToken(),
            'description' => $this->lmsg('settings.apiTokenHint'),
            'renderPassword' => true,
        ]);

        $form->addElement('select', Modules_Uptimeify_Settings::KEY_DEFAULT_CUSTOMER, [
            'label'        => $this->lmsg('settings.defaultCustomer'),
            'multiOptions' => $this->customerOptions(),
            'value'        => Modules_Uptimeify_Settings::getDefaultCustomerPublicId(),
            'description'  => $this->lmsg('settings.defaultCustomerHint'),
        ]);

        $form->addElement('select', Modules_Uptimeify_Settings::KEY_DEFAULT_PACKAGE, [
            'label'        => $this->lmsg('settings.defaultPackage'),
            'multiOptions' => $this->packageOptions(),
            'value'        => Modules_Uptimeify_Settings::getDefaultPackageType(),
        ]);

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

        $form->addElement('checkbox', Modules_Uptimeify_Settings::KEY_AUTO_SYNC, [
            'label'   => $this->lmsg('settings.autoSync'),
            'checked' => Modules_Uptimeify_Settings::isAutoSyncEnabled(),
        ]);

        $form->addElement('checkbox', Modules_Uptimeify_Settings::KEY_AUTO_CREATE, [
            'label'       => $this->lmsg('settings.autoCreate'),
            'checked'     => Modules_Uptimeify_Settings::isAutoCreateEnabled(),
            'description' => $this->lmsg('settings.autoCreateHint'),
        ]);

        $form->addElement('checkbox', Modules_Uptimeify_Settings::KEY_DNSBL_ENABLED, [
            'label'       => $this->lmsg('settings.dnsbl'),
            'checked'     => Modules_Uptimeify_Settings::isDnsblEnabled(),
            'description' => $this->lmsg('settings.dnsblHint'),
        ]);

        $form->addControlButtons([
            'cancelHidden' => true,
            'sendTitle'    => $this->lmsg('settings.save'),
        ]);

        return $form;
    }

    /**
     * @return array<string, string>
     */
    private function customerOptions(): array
    {
        $options = ['' => $this->lmsg('settings.choose')];
        if (!Modules_Uptimeify_Settings::hasApiToken() || !Modules_Uptimeify_Settings::getOrganizationId()) {
            return $options;
        }
        try {
            foreach (Modules_Uptimeify_Api_Client::fromSettings()->listCustomers((int) Modules_Uptimeify_Settings::getOrganizationId()) as $c) {
                $publicId = (string) ($c['publicId'] ?? $c['id'] ?? '');
                if ($publicId !== '') {
                    $options[$publicId] = (string) ($c['name'] ?? $publicId);
                }
            }
        } catch (Modules_Uptimeify_Api_Exception_ApiException) {
            // Leave the placeholder only; the connection banner already reports the error.
        }
        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function packageOptions(): array
    {
        $options = ['' => $this->lmsg('settings.choose')];
        if (!Modules_Uptimeify_Settings::hasApiToken()) {
            return $options;
        }
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
