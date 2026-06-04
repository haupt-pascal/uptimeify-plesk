<?php
/**
 * Post-install hook: register the hourly synchronization task with the Plesk
 * scheduler. Idempotent — removes any previously registered task first.
 */

declare(strict_types=1);

try {
    $scheduler = pm_Scheduler::getInstance();

    // Clean up a previous registration (e.g. on upgrade) before re-adding.
    foreach ($scheduler->listTasks() as $existing) {
        if ($existing->getScript() === 'sync.php') {
            $scheduler->removeTask($existing);
        }
    }

    $task = new pm_Scheduler_Task();
    $task->setScript('sync.php');
    $task->setSchedule(pm_Scheduler::$EVERY_HOUR);
    $scheduler->putTask($task);

    fwrite(STDOUT, "uptimeify: hourly sync task registered.\n");
} catch (Throwable $e) {
    // Don't fail the installation if scheduling is unavailable; manual sync still works.
    fwrite(STDERR, 'uptimeify: could not register scheduled task: ' . $e->getMessage() . "\n");
}
