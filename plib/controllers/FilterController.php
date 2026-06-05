<?php

/**
 * Filter tab: black/whitelist which Plesk customers are synced.
 *
 * Mode "blacklist" syncs everyone except customers set to "never"; mode
 * "whitelist" syncs only customers set to "always". A per-customer "default"
 * follows the mode. (Service-plan level is a planned follow-up.)
 */

declare(strict_types=1);

class FilterController extends pm_Controller_Action
{
    public function init(): void
    {
        parent::init();

        $this->view->pageTitle = $this->lmsg('pageTitle');

        $this->view->tabs = [
            ['title' => $this->lmsg('tabs.dashboard'), 'action' => 'index', 'controller' => 'index'],
            ['title' => $this->lmsg('tabs.filter'), 'action' => 'index', 'controller' => 'filter'],
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

        $customers = $this->customers();
        $form      = $this->buildForm($customers);

        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {
            Modules_Uptimeify_Settings::setFilterMode((string) $form->getValue(Modules_Uptimeify_Settings::KEY_FILTER_MODE));
            foreach ($customers as $customer) {
                Modules_Uptimeify_Settings::setCustomerState(
                    $customer['id'],
                    (string) $form->getValue('customer_' . $customer['id']),
                );
            }
            $this->_status->addMessage('info', $this->lmsg('filter.saved'));
            $this->_helper->json(['redirect' => pm_Context::getActionUrl('filter', 'index')]);
            return;
        }

        $this->view->form         = $form;
        $this->view->customerless = $customers === [];
    }

    /**
     * @param list<array{id:int, name:string, count:int}> $customers
     */
    private function buildForm(array $customers): pm_Form_Simple
    {
        $form = new pm_Form_Simple();

        $form->addElement('select', Modules_Uptimeify_Settings::KEY_FILTER_MODE, [
            'label'        => $this->lmsg('filter.mode'),
            'multiOptions' => [
                'blacklist' => $this->lmsg('filter.modeBlacklist'),
                'whitelist' => $this->lmsg('filter.modeWhitelist'),
            ],
            'value'        => Modules_Uptimeify_Settings::getFilterMode(),
            'description'  => $this->lmsg('filter.modeHint'),
        ]);

        $stateOptions = [
            'default' => $this->lmsg('filter.stateDefault'),
            'sync'    => $this->lmsg('filter.stateSync'),
            'skip'    => $this->lmsg('filter.stateSkip'),
        ];

        foreach ($customers as $customer) {
            $form->addElement('select', 'customer_' . $customer['id'], [
                'label'        => $customer['name'] . ' (' . $customer['count'] . ')',
                'multiOptions' => $stateOptions,
                'value'        => Modules_Uptimeify_Settings::getCustomerState($customer['id']),
            ]);
        }

        $form->addControlButtons([
            'cancelHidden' => true,
            'sendTitle'    => $this->lmsg('settings.save'),
        ]);

        return $form;
    }

    /**
     * Plesk clients that own at least one domain, with their domain count.
     *
     * @return list<array{id:int, name:string, count:int}>
     */
    private function customers(): array
    {
        $byClient = [];
        foreach ((new Modules_Uptimeify_Plesk_DomainRepository())->all() as $domain) {
            $id = (int) $domain['clientId'];
            if (!isset($byClient[$id])) {
                $name = $domain['clientName'] !== '' ? $domain['clientName'] : $domain['clientEmail'];
                $byClient[$id] = ['id' => $id, 'name' => $name !== '' ? $name : ('#' . $id), 'count' => 0];
            }
            $byClient[$id]['count']++;
        }

        usort($byClient, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return array_values($byClient);
    }
}
