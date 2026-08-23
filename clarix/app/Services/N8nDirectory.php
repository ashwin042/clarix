<?php

namespace App\Services;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * The two lists an admin's Telegram conversation picks from, and the one
 * definition of who may appear in the second of them.
 *
 * A service rather than two queries in a controller, because the same answer is
 * needed in two places that must not be allowed to disagree: the endpoint that
 * *offers* a unit's people, and the validator that decides whether the id the
 * workflow sent back is one of them. Written twice, the pair drifts the moment
 * a role is added — the bot would list somebody it then refuses to accept, and
 * the failure would surface as a validation error in a Telegram reply with
 * nothing to explain it.
 *
 * Neither method filters by organization, and that is not an omission. Unit and
 * User both carry OrganizationScope, and every caller here runs inside the
 * acting-as context ResolveN8nActor established, so the scope resolves these to
 * the acting admin's own agency and cannot reach another's. Adding an explicit
 * where would be a second, weaker copy of the same guarantee.
 */
class N8nDirectory
{
    /**
     * The roles that may own a task filed into a unit.
     *
     * pm and writer, matching what the endpoint's consumers call "PMs" — the
     * pm_id column names whoever the work belongs to inside the unit. Today
     * only PMs are given a unit_id (see Admin\ManageUsers), so a writer never
     * actually appears; the role is listed anyway so that giving writers units
     * changes one fact rather than four queries.
     *
     * admin, supervisor and hr are absent on purpose. They belong to no unit,
     * so they could not match the unit filter even if they were listed, and
     * naming them here would suggest they are a valid answer to "whose is it".
     *
     * @var list<string>
     */
    public const ASSIGNABLE_ROLES = ['pm', 'writer'];

    /**
     * Every unit of the acting agency, for the admin to choose between.
     *
     * @return Collection<int, Unit>
     */
    public function units(): Collection
    {
        return Unit::query()->orderBy('name')->get();
    }

    /**
     * The people in one unit who may be handed its work.
     *
     * Takes a Unit rather than an id so the caller has to have resolved it
     * under the tenant scope first — which is what makes another agency's unit
     * a 404 at the edge instead of an empty list here.
     *
     * @return Collection<int, User>
     */
    public function peopleIn(Unit $unit): Collection
    {
        return User::query()
            ->where('unit_id', $unit->getKey())
            ->whereIn('role', self::ASSIGNABLE_ROLES)
            ->orderBy('name')
            ->get();
    }

    /**
     * Whether $userId names somebody who may be given work in $unitId.
     *
     * The validator's question, answered by the same query that builds the
     * list. Both ids are checked together rather than in sequence: a PM who
     * exists and a unit that exists are not enough, they have to be each
     * other's.
     */
    public function isAssignableTo(int $userId, int $unitId): bool
    {
        return User::query()
            ->whereKey($userId)
            ->where('unit_id', $unitId)
            ->whereIn('role', self::ASSIGNABLE_ROLES)
            ->exists();
    }
}
