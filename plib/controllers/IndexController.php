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
        $this->view->headLink()->appendStylesheet(pm_Context::getBaseUrl() . 'css/uptimeify.css');
        $this->view->headScript()->appendFile(pm_Context::getBaseUrl() . 'js/uptimeify.js');

        $this->_tabs = [
            ['title' => $this->lmsg('tabs.dashboard'), 'action' => 'index'],
            ['title' => $this->lmsg('tabs.settings'), 'action' => 'index', 'controller' => 'settings'],
        ];
    }

    public function indexAction(): void
    {
        if (!Modules_Uptimeify_Settings::hasApiToken()) {
            $this->_status->addMessage('info', $this->lmsg('dashboard.notConnected'));
            $this->_forward('index', 'settings');
            return;
        }

        $this->view->organizationName = Modules_Uptimeify_Settings::getOrganizationName();
        $this->view->autoSync         = Modules_Uptimeify_Settings::isAutoSyncEnabled();
        $this->view->signupUrl        = 'https://uptimeify.io';

        try {
            $service = Modules_Uptimeify_Sync_DomainSyncService::create();
            $this->view->rows      = $service->getDashboardRows();
            $this->view->customers = Modules_Uptimeify_Api_Client::fromSettings()
                ->listCustomers((int) Modules_Uptimeify_Settings::getOrganizationId());
            $this->view->packages  = Modules_Uptimeify_Api_Client::fromSettings()->listPackageConfigs();
            $this->view->loadError = null;
        } catch (Modules_Uptimeify_Api_Exception_UnauthorizedException $e) {
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
     * AJAX: enable monitoring for one domain. Expects domain, customerPublicId, packageType.
     */
    public function enableAction(): void
    {
        $this->_forbidGet();

        $domain   = (string) $this->_getParam('domain');
        $customer = (string) $this->_getParam('customerPublicId');
        $package  = (string) $this->_getParam('packageType');

        if ($domain === '' || $customer === '') {
            $this->_helper->json(['success' => false, 'message' => $this->lmsg('error.missingParams')]);
            return;
        }

        try {
            Modules_Uptimeify_Sync_DomainSyncService::create()->enable($domain, $customer, $package);
            $this->_helper->json([
                'success' => true,
                'message' => $this->lmsg('dashboard.enabled', ['domain' => $domain]),
            ]);
        } catch (Modules_Uptimeify_Api_Exception_QuotaExceededException $e) {
            $this->_helper->json([
                'success'   => false,
                'quota'     => true,
                'upgradeUrl' => 'https://uptimeify.io',
                'message'   => $this->lmsg('error.quota'),
            ]);
        } catch (Modules_Uptimeify_Api_Exception_ApiException $e) {
            $this->_helper->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: disable monitoring for one domain. Expects domain, websitePublicId.
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
     * Manual full sync (runs the same reconcile as the scheduled task).
     */
    public function syncAction(): void
    {
        $this->_forbidGet();

        try {
            $summary = Modules_Uptimeify_Sync_DomainSyncService::create()->reconcile();
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
