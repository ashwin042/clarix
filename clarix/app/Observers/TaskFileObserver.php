<?php

namespace App\Observers;

use App\Models\TaskFile;
use App\Services\StorageUsageService;
use Illuminate\Support\Facades\Log;

/**
 * Keeps unit_storage_usage in step with task_files.
 *
 * Hooking the model rather than each call site means every upload and delete
 * route — the file controller, the create-task form and the task detail
 * component — is covered by one piece of bookkeeping.
 *
 * The one path this cannot see is a mass delete such as
 * $task->files()->delete(), which bypasses model events. Task::deleteWithFiles
 * accounts for its own files directly for that reason.
 */
class TaskFileObserver
{
    public function __construct(protected StorageUsageService $usage)
    {
    }

    public function created(TaskFile $file): void
    {
        if ($unitId = $this->unitIdFor($file)) {
            $this->usage->increment($unitId, (int) $file->file_size);
        }
    }

    public function deleted(TaskFile $file): void
    {
        if ($unitId = $this->unitIdFor($file)) {
            $this->usage->decrement($unitId, (int) $file->file_size);
        }
    }

    /**
     * The unit that owns this file, by way of its task. A missing task means
     * the file cannot be attributed, so it is logged for the nightly
     * reconcile to sort out rather than charged to the wrong unit.
     */
    protected function unitIdFor(TaskFile $file): ?int
    {
        $unitId = $file->task?->unit_id;

        if (! $unitId) {
            Log::warning('Task file has no resolvable unit; storage usage not adjusted.', [
                'task_file_id' => $file->id,
                'task_id'      => $file->task_id,
                'file_path'    => $file->file_path,
            ]);

            return null;
        }

        return (int) $unitId;
    }
}
