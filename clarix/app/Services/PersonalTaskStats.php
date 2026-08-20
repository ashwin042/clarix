<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * How much work is one person's own.
 *
 * Deliberately not the same question the dashboard stats answer. AdminStats
 * counts the whole agency, PmStats counts a unit, and WriterStats counts
 * assignment rows rather than tasks. Those are the right figures for a
 * dashboard and the wrong ones for a profile: showing a PM their unit's
 * throughput on a page headed with their own name would read as a claim about
 * them personally.
 *
 * So "mine" is answered from whichever column names the person, and it differs
 * by role because the schema names them differently:
 *
 *   writer  the tasks they hold an assignment on   (Task::forWriter)
 *   pm      the tasks they run                     (tasks.pm_id)
 *   admin   the tasks handed to them               (tasks.assigned_admin_id)
 *
 * A superadmin belongs to no agency and appears in none of those columns, so
 * they get zeroes rather than an error — the profile page has to open for
 * whoever is signed in.
 *
 * Every query goes through the ordinary Task model, so OrganizationScope keeps
 * the counts inside the caller's own agency without this class mentioning it.
 */
class PersonalTaskStats
{
    /**
     * The statuses a task can carry, in the order they are shown.
     *
     * The board's four columns plus 'cancelled', which the board omits because
     * it is not work in progress. It is counted here so that the breakdown
     * always adds up to the total — a figure that silently excluded cancelled
     * work would leave people trying to account for the difference.
     */
    public const STATUSES = [
        'pending'         => 'Pending',
        'in_progress'     => 'In progress',
        'sent_for_review' => 'Sent for review',
        'completed'       => 'Completed',
        'cancelled'       => 'Cancelled',
    ];

    /**
     * One person's task counts.
     *
     * @return array{total: int, breakdown: array<string, int>}
     */
    public function for(User $user): array
    {
        $query = $this->tasksOf($user);

        // Counted through the Eloquent builder rather than its underlying
        // query builder: pluck() applies the global scopes on the way out, and
        // OrganizationScope is one of them. Reaching for getQuery() here would
        // strip the tenant filter and count every agency's work.
        $counts = $query === null
            ? collect()
            : $query->select('status')->selectRaw('count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status');

        $breakdown = [];

        foreach (array_keys(self::STATUSES) as $status) {
            $breakdown[$status] = (int) $counts->get($status, 0);
        }

        return [
            'total'     => array_sum($breakdown),
            'breakdown' => $breakdown,
        ];
    }

    /**
     * The tasks that belong to this person, or null if none ever could.
     */
    protected function tasksOf(User $user): ?Builder
    {
        return match ($user->role) {
            'writer' => Task::query()->forWriter($user->id),
            'pm'     => Task::query()->where('pm_id', $user->id),
            'admin'  => Task::query()->where('assigned_admin_id', $user->id),
            default  => null,
        };
    }
}
