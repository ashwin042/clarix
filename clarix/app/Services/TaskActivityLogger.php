<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;

/**
 * The one place an activity row is written.
 *
 * The observers decide *that* something happened; this decides what gets
 * recorded about it. Keeping the write in one method means the actor is
 * captured the same way every time — including the part that is easy to get
 * wrong, which is snapshotting the actor's role rather than resolving it later.
 */
class TaskActivityLogger
{
    /**
     * Task fields worth a line in the log when they change.
     *
     * status is absent on purpose: it has its own event, raised from
     * applyStatusChange() so that a card dragged across the board and a status
     * picked from a dropdown produce the same entry. position is absent
     * because reordering a column is not something anybody needs to read about
     * afterwards, and it changes several times per drag.
     *
     * @var list<string>
     */
    public const TRACKED_FIELDS = [
        'title',
        'deadline',
        'credit_amount',
        'pm_id',
        'assigned_admin_id',
        'task_type',
        'priority',
    ];

    /** Fields holding a user id, which read as a name rather than a number. */
    private const USER_FIELDS = ['pm_id', 'assigned_admin_id'];

    public function record(Task $task, string $event, array $details = []): ?TaskActivity
    {
        $actor = auth()->user();

        return TaskActivity::create([
            'task_id'    => $task->getKey(),
            'user_id'    => $actor?->getKey(),
            // Snapshot, not a reference. Masking reads this, and a writer
            // promoted to PM tomorrow must not retroactively become named.
            'actor_role' => $actor?->role,
            'event'      => $event,
            'details'    => $details ?: null,
        ]);
    }

    /**
     * Log whichever tracked fields actually changed, one entry each.
     *
     * One per field rather than one per save, so each line reads on its own
     * and a filter by field is possible later. Values are resolved to
     * something readable on the way in — see valueFor().
     */
    public function recordChanges(Task $task): void
    {
        foreach (self::TRACKED_FIELDS as $field) {
            if (! $task->wasChanged($field)) {
                continue;
            }

            $this->record($task, 'updated', [
                'field' => $field,
                'from'  => $this->valueFor($field, $task->getOriginal($field)),
                'to'    => $this->valueFor($field, $task->getAttribute($field)),
            ]);
        }
    }

    /**
     * A field's value as it should be stored for reading.
     *
     * User ids become names, so an entry does not need a join to make sense
     * of, and dates become plain dates rather than a full timestamp.
     */
    protected function valueFor(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (in_array($field, self::USER_FIELDS, true)) {
            return User::withoutGlobalScopes()->find($value)?->name ?? 'a removed account';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('M d, Y');
        }

        if ($field === 'deadline') {
            return (string) $value;
        }

        return (string) $value;
    }
}
