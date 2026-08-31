<?php

namespace App\Services;

use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Which tasks the task bot may read back, for whom, and how far a query string
 * is allowed to move that line.
 *
 * The whole security argument of the read endpoint lives in this class, so it
 * is worth stating plainly. The pipeline authenticates with a static shared
 * key, which names the *caller* and says nothing about the *person* — every
 * request from n8n carries the same one. If the endpoint took pm_id or unit_id
 * at face value, a mistake anywhere in the workflow would be enough for one
 * PM's conversation to read another's tasks, and the backend would have no way
 * to know it had happened.
 *
 * It does not take them at face value. ResolveN8nActor has already turned the
 * chat_id into a real Clarix user row before anything here runs, so there is an
 * actual person to ask about. That gives the rule its shape:
 *
 *   ceiling   what this person may see at all, decided here from their role
 *   filters   what they asked for, which may only ever *remove* rows
 *
 * A filter can narrow the ceiling and can never raise it. The one filter that
 * could be mistaken for raising it — unit_id — is checked separately by
 * mayQueryUnit() and refused rather than quietly ignored, because silently
 * dropping a filter would answer a different question than the one asked and
 * the workflow would have no way to tell.
 *
 * ── Why the ceiling matches TaskPolicy ──────────────────────────────────────
 *
 * The arms below are TaskPolicy::owns(), deliberately. The bot is a second
 * window onto the same data, and a PM who is told "4 pending" in Telegram and
 * shown six cards on the board has been told one of those by a bug. Reusing the
 * policy's answer is what keeps the two windows agreeing.
 *
 * That is why a PM's ceiling is their *unit* rather than their own pm_id, which
 * is the one place this diverges from how the endpoint was first described. On
 * the board a PM already sees every task in their unit, including a colleague's
 * and including the ones nobody owns yet; confining the bot to pm_id would have
 * hidden their unit's unassigned work from the count while leaving it visible
 * on screen. pm_id survives as a filter, so "just mine" is still one query
 * away — it just is not the ceiling.
 *
 * Not asked of TaskPolicy directly, because a policy answers about one model
 * instance and this has to answer as a WHERE clause over a set. Gate::forUser
 * per row would be the same rule at hundreds of times the cost, and would still
 * need this class to build the candidate set first.
 */
class N8nTaskQuery
{
    /**
     * The most tasks one response will carry.
     *
     * A Telegram reply cannot show more than a handful anyway, and a
     * conversation that asks with no filters at all must not be handed the
     * agency's entire task table — both because of what it costs to serialise
     * and because an execution log then holds a copy of it.
     *
     * The count is deliberately *not* capped with it. "How many are pending"
     * is one of the three questions this endpoint exists to answer, and a
     * count that stopped at 50 would answer it wrongly while looking right.
     */
    public const LIMIT = 50;

    /**
     * The tasks this person may read, narrowed by whatever they asked for.
     *
     * @param  array<string, mixed>  $filters
     * @return array{tasks: Collection<int, Task>, total: int}
     */
    public function run(User $user, array $filters): array
    {
        $query = $this->ceiling($user);

        // No arm of the ceiling matched, so this role reaches nothing. An empty
        // result rather than an exception: the caller has already been let past
        // authorize(), and "you may look, there is nothing here" is a coherent
        // answer that a workflow can render.
        if ($query === null) {
            return ['tasks' => new Collection, 'total' => 0];
        }

        $this->applyFilters($query, $filters);

        // Counted before the limit, off the same builder, so the two figures
        // cannot describe different sets.
        $total = (clone $query)->count();

        $tasks = $query->orderByDesc('id')->limit(self::LIMIT)->get();

        return ['tasks' => $tasks, 'total' => $total];
    }

    /**
     * Whether this person may name this unit in a query.
     *
     * Refused, not ignored. Dropping an out-of-reach unit_id would answer
     * "everything you can see" to a request that asked "this unit", which for a
     * PM's conversation reads as their own unit's tasks under another unit's
     * heading.
     *
     * A PM is pinned to their own unit; everyone else who gets this far reaches
     * the whole agency, so the only question left is whether the unit is one of
     * their agency's. Unit carries OrganizationScope and this runs inside the
     * acting organization, so the existence check answers that on its own —
     * another agency's unit and a unit that never existed are the same miss,
     * which is what keeps this from reporting whether an id is in use anywhere
     * on the platform.
     */
    public function mayQueryUnit(User $user, int $unitId): bool
    {
        if ($user->isPm()) {
            return $user->unit_id !== null && (int) $user->unit_id === $unitId;
        }

        return Unit::query()->whereKey($unitId)->exists();
    }

    /**
     * What this person may see before any filter is applied.
     *
     * Null means "nothing", which is not the same as an empty builder: an empty
     * builder would still accept filters and could be widened by a later edit,
     * while null cannot be turned into rows by mistake.
     *
     * No arm mentions organization_id. Task carries OrganizationScope and every
     * caller runs inside the acting-as context ResolveN8nActor established, so
     * the scope confines all of these to one agency. An explicit where here
     * would be a second, weaker copy of that guarantee — and the supervisor arm
     * shows why it is not needed: TaskPolicy compares organization_id by hand
     * because a policy has no scope to lean on, whereas a builder does.
     */
    protected function ceiling(User $user): ?Builder
    {
        return match ($user->role) {
            'admin', 'supervisor' => Task::query(),

            // A PM with no unit reaches nothing rather than everything. null
            // would compare as `unit_id is null` and quietly match every
            // unowned task in the agency.
            'pm' => $user->unit_id === null
                ? null
                : Task::query()->where('unit_id', $user->unit_id),

            // A writer carries no unit_id at all, so their reach is the set of
            // tasks assigned to them — the same arm TaskPolicy::owns() uses.
            'writer' => Task::query()->whereHas(
                'assignments',
                fn (Builder $q) => $q->where('writer_id', $user->id)
            ),

            default => null,
        };
    }

    /**
     * The narrowing half. Every one of these can only remove rows.
     *
     * Absent and null are both "not asked for" — n8n sends empty strings for
     * fields a conversation did not fill in, and ConvertEmptyStringsToNull
     * turns those into nulls before validation, so a half-filled query behaves
     * as though the blank fields were never sent.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        if (($unitId = $filters['unit_id'] ?? null) !== null) {
            $query->where('unit_id', (int) $unitId);
        }

        // Exact, never a LIKE. The bot asks this to decide whether a code is
        // free before filing, and a prefix match would report a code taken
        // because a longer one resembles it.
        if (($code = $filters['task_code'] ?? null) !== null) {
            $query->where('task_code', (string) $code);
        }

        if (($pmId = $filters['pm_id'] ?? null) !== null) {
            $query->where('pm_id', (int) $pmId);
        }

        if (($status = $filters['status'] ?? null) !== null) {
            $query->where('status', (string) $status);
        }
    }
}
