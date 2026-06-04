<?php

/**
 * Registers / updates the Plesk scheduled task that runs the background sync.
 *
 * The task runs plib/scripts/sync.php at the configured interval. apply() is
 * idempotent: it removes any previous registration first, then (re)adds the task
 * only when scheduled sync is enabled. Call it on install and whenever the sync
 * settings change.
 */

declare(strict_types=1);

class Modules_Uptimeify_Scheduler
{
    private const SCRIPT = 'sync.php';

    public static function apply(): void
    {
        $scheduler = pm_Scheduler::getInstance();

        foreach ($scheduler->listTasks() as $task) {
            if ($task->getCmd() === self::SCRIPT) {
                $scheduler->removeTask($task);
            }
        }

        if (!Modules_Uptimeify_Settings::isAutoSyncEnabled()) {
            return;
        }

        $task = new pm_Scheduler_Task();
        $task->setCmd(self::SCRIPT);
        $task->setSchedule(self::cronFor(Modules_Uptimeify_Settings::getSyncInterval()));
        $scheduler->putTask($task);
    }

    public static function remove(): void
    {
        $scheduler = pm_Scheduler::getInstance();
        foreach ($scheduler->listTasks() as $task) {
            if ($task->getCmd() === self::SCRIPT) {
                $scheduler->removeTask($task);
            }
        }
    }

    /**
     * @return array{minute:string, hour:string, dom:string, month:string, dow:string}
     */
    private static function cronFor(string $interval): array
    {
        return match ($interval) {
            'every_15_min' => ['minute' => '*/15', 'hour' => '*', 'dom' => '*', 'month' => '*', 'dow' => '*'],
            'every_30_min' => ['minute' => '*/30', 'hour' => '*', 'dom' => '*', 'month' => '*', 'dow' => '*'],
            'daily'        => ['minute' => '0', 'hour' => '3', 'dom' => '*', 'month' => '*', 'dow' => '*'],
            default        => ['minute' => '0', 'hour' => '*', 'dom' => '*', 'month' => '*', 'dow' => '*'], // hourly
        };
    }
}
