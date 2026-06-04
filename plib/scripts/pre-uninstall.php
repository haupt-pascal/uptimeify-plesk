<?php
/**
 * Pre-uninstall hook: remove the scheduled sync task. Local Plesk settings are
 * dropped by Plesk automatically; remote uptimeify monitors are left untouched.
 */

declare(strict_types=1);

try {
    $scheduler = pm_Scheduler::getInstance();
    foreach ($scheduler->listTasks() as $task) {
        if ($task->getScript() === 'sync.php') {
            $scheduler->removeTask($task);
        }
    }
    fwrite(STDOUT, "uptimeify: scheduled task removed.\n");
} catch (Throwable $e) {
    fwrite(STDERR, 'uptimeify: cleanup warning: ' . $e->getMessage() . "\n");
}
