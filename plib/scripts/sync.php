<?php
/**
 * CLI entry point for the scheduled (cron) synchronization.
 *
 * Registered by post-install.php via pm_Scheduler to run hourly. It reconciles
 * local Plesk domains with uptimeify monitors and (optionally) auto-creates
 * monitors for new domains under the configured default customer + package.
 *
 * Run manually for debugging:
 *   plesk php /usr/local/psa/admin/htdocs/modules/uptimeify/scripts/sync.php
 */

declare(strict_types=1);

if (!Modules_Uptimeify_Settings::isAutoSyncEnabled()) {
    fwrite(STDOUT, "uptimeify: scheduled sync disabled, nothing to do.\n");
    exit(0);
}

if (!Modules_Uptimeify_Settings::hasApiToken()) {
    fwrite(STDERR, "uptimeify: no API token configured.\n");
    exit(1);
}

try {
    $summary = Modules_Uptimeify_Sync_DomainSyncService::create()->reconcile();
    fwrite(STDOUT, sprintf(
        "uptimeify sync: created=%d skipped=%d errors=%d\n",
        $summary['created'],
        $summary['skipped'],
        count($summary['errors']),
    ));
    foreach ($summary['errors'] as $error) {
        fwrite(STDERR, '  - ' . $error . "\n");
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'uptimeify sync failed: ' . $e->getMessage() . "\n");
    exit(1);
}
