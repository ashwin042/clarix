<?php

namespace App\Observers;

use App\Models\TaskNote;
use App\Services\TaskActivityLogger;

/**
 * Logs that a note was added, not what it said.
 *
 * The note's text lives in the Notes section and is already on the page; the
 * log records that the conversation moved, which is what a history is for.
 */
class TaskNoteActivityObserver
{
    public function __construct(protected TaskActivityLogger $log)
    {
    }

    public function created(TaskNote $note): void
    {
        if ($task = $note->task) {
            $this->log->record($task, 'note_added');
        }
    }
}
