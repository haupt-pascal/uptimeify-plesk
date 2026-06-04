<?php

declare(strict_types=1);

return [
    'pageTitle' => 'Uptimeify Monitoring',

    'tabs.dashboard' => 'Dashboard',
    'tabs.settings'  => 'Einstellungen',

    'dashboard.notConnected'  => 'Hinterlege deinen uptimeify.io API-Token, um zu starten.',
    'dashboard.syncAll'       => 'Jetzt synchronisieren',
    'dashboard.filter'        => 'Domains filtern…',
    'dashboard.colDomain'     => 'Domain',
    'dashboard.colType'       => 'Typ',
    'dashboard.colStatus'     => 'Status',
    'dashboard.colCustomer'   => 'Kunde',
    'dashboard.colPackage'    => 'Paket',
    'dashboard.colMonitoring' => 'Monitoring',
    'dashboard.enable'        => 'Aktivieren',
    'dashboard.chooseCustomer' => 'Kunde…',
    'dashboard.choosePackage'  => 'Paket…',
    'dashboard.enabled'       => 'Monitoring für %%domain%% aktiviert.',
    'dashboard.disabled'      => 'Monitoring für %%domain%% deaktiviert.',
    'dashboard.noDomains'     => 'Keine Hosting-Domains auf diesem Server gefunden.',

    'settings.intro'            => 'Diese Erweiterung ist ein quelloffener API-Client für',
    'settings.apiToken'         => 'Organisations-API-Token',
    'settings.apiTokenHint'     => 'Dein uptimeify.io-Token (beginnt mit wsm_). Erstelle ihn unter Einstellungen → API.',
    'settings.defaultCustomer'  => 'Standard-Kunde (Auto-Anlage)',
    'settings.defaultCustomerHint' => 'Wird vom geplanten Sync genutzt, wenn Monitore für neue Domains automatisch angelegt werden.',
    'settings.defaultPackage'   => 'Standard-Paket (Auto-Anlage)',
    'settings.monitoringType'   => 'Standard-Monitoring-Typ',
    'settings.checkInterval'    => 'Standard-Prüfintervall (Minuten)',
    'settings.checkIntervalHint' => 'Zwischen 1 und 60. Das Minimum hängt vom Paket ab.',
    'settings.autoSync'         => 'Geplanten Sync aktivieren (stündlich)',
    'settings.autoCreate'       => 'Monitore für neue Domains automatisch anlegen',
    'settings.autoCreateHint'   => 'Beim geplanten Sync werden neue, nicht überwachte Domains unter Standard-Kunde + Paket angelegt.',
    'settings.dnsbl'            => 'Server-IP zusätzlich für Blacklist-Monitoring (DNSBL) registrieren',
    'settings.dnsblHint'        => 'Erfordert das DNSBL-Add-on in deinem uptimeify-Tarif.',
    'settings.choose'           => '— auswählen —',
    'settings.save'             => 'Speichern',
    'settings.saved'            => 'Einstellungen gespeichert.',
    'settings.connected'        => 'Verbunden mit Organisation „%%org%%".',
    'settings.connectFailed'    => 'Token konnte nicht validiert werden. Bitte prüfen und erneut versuchen.',
    'settings.invalidPrefix'    => 'Der Token sieht ungültig aus — uptimeify-Tokens beginnen mit wsm_.',

    'error.unauthorized' => 'Dein API-Token wurde abgelehnt. Öffne die Einstellungen, um dich neu zu verbinden.',
    'error.quota'        => 'Limit erreicht! Dein uptimeify-Tarif erlaubt keine weiteren Monitore. Jetzt Tarif upgraden.',
    'error.missingParams' => 'Erforderliche Parameter fehlen.',

    'widget.title'     => 'Uptimeify',
    'widget.allNominal' => 'Alle Systeme nominal',
    'widget.systemsDown' => '%%count%% Systeme down',
    'widget.notConnected' => 'Nicht verbunden',
];
