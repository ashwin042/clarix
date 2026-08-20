<?php

namespace App\Observers;

use App\Models\TaskFile;
use App\Services\TaskActivityLogger;

/**
 * Logs files arriving and leaving.
 *
 * A second observer beside TaskFileObserver rather than more methods inside
 * it: that one keeps unit_storage_usage correct and has nothing to do with
 * who did what. Laravel takes a list on #[ObservedBy], so both run.
 *
 * A file whose task has gone is not logged — the task's own rows are on their
 * way out with it.
 */
class TaskFileActivityObserver
{
    public function __construct(protected TaskActivityLogger $log)
    {
    }

    public function created(TaskFile $file): void
    {
        $this->record($file, 'file_uploaded');
    }

    public function deleted(TaskFile $file): void
    {
        $this->record($file, 'file_deleted');
    }

    protected function record(TaskFile $file, string $event): void
    {
        if (! $task = $file->task) {
            return;
        }

        $this->log->record($task, $event, [
            'filename'  => $file->original_name,
            'completed' => (bool) $file->is_completed_file,
        ]);
    }
}
