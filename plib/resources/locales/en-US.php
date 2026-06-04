<?php

declare(strict_types=1);

return [
    'pageTitle' => 'Uptimeify Monitoring',

    'tabs.dashboard' => 'Dashboard',
    'tabs.settings'  => 'Settings',

    'dashboard.notConnected'  => 'Connect your uptimeify.io API token to get started.',
    'dashboard.syncAll'       => 'Sync now',
    'dashboard.filter'        => 'Filter domains…',
    'dashboard.colDomain'     => 'Domain',
    'dashboard.colType'       => 'Type',
    'dashboard.colStatus'     => 'Status',
    'dashboard.colCustomer'   => 'Customer',
    'dashboard.colPackage'    => 'Package',
    'dashboard.colMonitoring' => 'Monitoring',
    'dashboard.enable'        => 'Enable',
    'dashboard.chooseCustomer' => 'Customer…',
    'dashboard.choosePackage'  => 'Package…',
    'dashboard.enabled'       => 'Monitoring enabled for %%domain%%.',
    'dashboard.disabled'      => 'Monitoring disabled for %%domain%%.',
    'dashboard.noDomains'     => 'No hosting domains found on this server.',

    'settings.intro'            => 'This extension is an open-source API client for the',
    'settings.apiToken'         => 'Organization API token',
    'settings.apiTokenHint'     => 'Your uptimeify.io token (starts with wsm_). Create one under Settings → API.',
    'settings.defaultCustomer'  => 'Default customer (auto-create)',
    'settings.defaultCustomerHint' => 'Used by the scheduled sync when auto-creating monitors for new domains.',
    'settings.defaultPackage'   => 'Default package (auto-create)',
    'settings.monitoringType'   => 'Default monitoring type',
    'settings.checkInterval'    => 'Default check interval (minutes)',
    'settings.checkIntervalHint' => 'Between 1 and 60. The minimum depends on the package.',
    'settings.autoSync'         => 'Enable scheduled sync (hourly)',
    'settings.autoCreate'       => 'Auto-create monitors for new domains',
    'settings.autoCreateHint'   => 'When the scheduled sync runs, new unmonitored domains are added under the default customer + package.',
    'settings.dnsbl'            => 'Also register server IP for blacklist (DNSBL) monitoring',
    'settings.dnsblHint'        => 'Requires the DNSBL add-on on your uptimeify plan.',
    'settings.choose'           => '— choose —',
    'settings.save'             => 'Save',
    'settings.saved'            => 'Settings saved.',
    'settings.connected'        => 'Connected to organization “%%org%%”.',
    'settings.connectFailed'    => 'Could not validate the token. Please check it and try again.',
    'settings.invalidPrefix'    => 'The token looks invalid — uptimeify tokens start with wsm_.',

    'error.unauthorized' => 'Your API token was rejected. Open Settings to re-connect.',
    'error.quota'        => 'Limit reached! Your uptimeify plan does not allow more monitors. Upgrade your plan.',
    'error.missingParams' => 'Missing required parameters.',

    'widget.title'     => 'Uptimeify',
    'widget.allNominal' => 'All systems nominal',
    'widget.systemsDown' => '%%count%% systems down',
    'widget.notConnected' => 'Not connected',
];
