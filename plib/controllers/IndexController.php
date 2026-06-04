<?php

/**
 * Main admin controller: connection banner, domain dashboard and sync actions.
 */

declare(strict_types=1);

class IndexController extends pm_Controller_Action
{
    public function init(): void
    {
        parent::init();

        $this->view->pageTitle = $this->lmsg('pageTitle');
        $this->view->headScript()->appendFile(pm_Context::getBaseUrl() . 'js/uptimeify.js');

        $this->view->tabs = [
            ['title' => $this->lmsg('tabs.dashboard'), 'action' => 'index', 'controller' => 'index'],
            ['title' => $this->lmsg('tabs.settings'), 'action' => 'index', 'controller' => 'settings'],
        ];
    }

    public function indexAction(): void
    {
        if (!Modules_Uptimeify_Settings::hasApiToken() || !Modules_Uptimeify_Settings::isValidated()) {
            $this->_status->addMessage('info', $this->lmsg('dashboard.notConnected'));
            $this->_forward('index', 'settings');
            return;
        }

        $this->view->organizationName = Modules_Uptimeify_Settings::getOrganizationName();
        $this->view->autoCreate       = Modules_Uptimeify_Settings::isAutoCreateCustomersEnabled();
        $this->view->defaultPackage   = Modules_Uptimeify_Settings::getDefaultPackageType();

        try {
            $service = Modules_Uptimeify_Sync_DomainSyncService::create();
            $this->view->rows      = $service->getDashboardRows();
            $this->view->customers = $service->listCustomerChoices();
            $this->view->packages  = Modules_Uptimeify_Api_Client::fromSettings()->listPackageConfigs();
            $this->view->loadError = null;
        } catch (Modules_Uptimeify_Api_Exception_UnauthorizedException) {
            $this->view->rows      = [];
            $this->view->customers = [];
            $this->view->packages  = [];
            $this->view->loadError = $this->lmsg('error.unauthorized');
        } catch (Modules_Uptimeify_Api_Exception_ApiException $e) {
            $this->view->rows      = [];
            $this->view->customers = [];
            $this->view->packages  = [];
            $this->view->loadError = $e->getMessage();
        }
    }

    /**
     * AJAX: enable monitoring for one domain.
     * Params: domain, customerChoice ('auto' or a customer public id), packageType.
     */
    public function enableAction(): void
    {
        $this->_forbidGet();

        $domain   = (string) $this->_getParam('domain');
        $choice   = (string) $this->_getParam('customerChoice') ?: 'auto';
        $package  = (string) $this->_getParam('packageType');

        if ($domain === '') {
            $this->_helper->json(['success' => false, 'message' => $this->lmsg('error.missingParams')]);
            return;
        }

        try {
            Modules_Uptimeify_Sync_DomainSyncService::create()->enable($domain, $choice, $package !== '' ? $package : null);
            $this->_helper->json([
                'success' => true,
                'message' => $this->lmsg('dashboard.enabled', ['domain' => $domain]),
            ]);
        } catch (Modules_Uptimeify_Api_Exception_QuotaExceededException) {
            $this->_helper->json([
                'success'    => false,
                'quota'      => true,
                'upgradeUrl' => 'https://uptimeify.io',
                'message'    => $this->lmsg('error.quota'),
            ]);
        } catch (Modules_Uptimeify_Api_Exception_ApiException $e) {
            $this->_helper->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: disable monitoring for one domain. Params: domain, websitePublicId.
     */
    public function disableAction(): void
    {
        $this->_forbidGet();

        $domain  = (string) $this->_getParam('domain');
        $website = (string) $this->_getParam('websitePublicId');

        if ($domain === '' || $website === '') {
            $this->_helper->json(['success' => false, 'message' => $this->lmsg('error.missingParams')]);
            return;
        }

        try {
            Modules_Uptimeify_Sync_DomainSyncService::create()->disable($domain, $website);
            $this->_helper->json([
                'success' => true,
                'message' => $this->lmsg('dashboard.disabled', ['domain' => $domain]),
            ]);
        } catch (Modules_Uptimeify_Api_Exception_ApiException $e) {
            $this->_helper->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: mirror the Plesk customer base and sync all unmonitored domains.
     */
    public function syncAction(): void
    {
        $this->_forbidGet();

        try {
            $summary = Modules_Uptimeify_Sync_DomainSyncService::create()->mirrorAndSyncAll();
            $this->_helper->json(['success' => true, 'summary' => $summary]);
        } catch (Modules_Uptimeify_Api_Exception_ApiException $e) {
            $this->_helper->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: sync only the selected domains. Param: domains[] (list of names).
     */
    public function syncSelectedAction(): void
    {
        $this->_forbidGet();

        $domains = $this->_getParam('domains');
        $domains = is_array($domains) ? array_values(array_filter(array_map('strval', $domains), 'strlen')) : [];

        if (!$domains) {
            $this->_helper->json(['success' => false, 'message' => $this->lmsg('error.missingParams')]);
            return;
        }

        try {
            $summary = Modules_Uptimeify_Sync_DomainSyncService::create()->syncSelected($domains);
            $this->_helper->json(['success' => true, 'summary' => $summary]);
        } catch (Modules_Uptimeify_Api_Exception_ApiException $e) {
            $this->_helper->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function _forbidGet(): void
    {
        if (!$this->getRequest()->isPost()) {
            $this->_helper->json(['success' => false, 'message' => 'POST required']);
        }
    }
}
