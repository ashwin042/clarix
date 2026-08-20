<?php

namespace App\Observers;

use App\Models\Task;
use App\Services\TaskActivityLogger;

/**
 * Turns changes to a task into activity entries.
 *
 * On the model rather than in ManageTasks::save(), so the create-task form,
 * the board's edit modal and anything added later are all covered without
 * each remembering to log.
 *
 * Status is handled separately in Task::applyStatusChange(), which every path
 * already funnels through; it is skipped here so a status change does not also
 * read as a field edit.
 */
class TaskActivityObserver
{
    public function __construct(protected TaskActivityLogger $log)
    {
    }

    public function created(Task $task): void
    {
        $this->log->record($task, 'created');
    }

    public function updated(Task $task): void
    {
        $this->log->recordChanges($task);
    }
}
