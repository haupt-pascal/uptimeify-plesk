<?php

/**
 * Post-install hook: register the scheduled synchronization task according to
 * the current settings. Idempotent and safe to run on every upgrade.
 */

declare(strict_types=1);

try {
    Modules_Uptimeify_Scheduler::apply();
    fwrite(STDOUT, "uptimeify: scheduled sync task applied.\n");
} catch (Throwable $e) {
    // Don't fail the installation if scheduling is unavailable; manual sync still works.
    fwrite(STDERR, 'uptimeify: could not register scheduled task: ' . $e->getMessage() . "\n");
}
