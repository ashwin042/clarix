<?php

namespace App\Observers;

use App\Models\TaskAssignment;
use App\Services\TaskActivityLogger;

/**
 * Logs writers joining and leaving a task.
 *
 * The writer's own id is deliberately not recorded in details. Nothing renders
 * it — describeFor() says "assigned a writer" without naming them — so storing
 * it would only be a copy of the identity the rest of the log is careful to
 * withhold, sitting in a column somebody could later render by accident. The
 * assignment row itself is where that association properly lives.
 */
class TaskAssignmentActivityObserver
{
    public function __construct(protected TaskActivityLogger $log)
    {
    }

    public function created(TaskAssignment $assignment): void
    {
        $this->record($assignment, 'writer_assigned');
    }

    public function deleted(TaskAssignment $assignment): void
    {
        $this->record($assignment, 'writer_removed');
    }

    protected function record(TaskAssignment $assignment, string $event): void
    {
        if ($task = $assignment->task) {
            $this->log->record($task, $event);
        }
    }
}
