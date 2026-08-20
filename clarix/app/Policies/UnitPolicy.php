<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;

/**
 * Units carry no per-row ownership rule — an agency's units are visible to
 * whoever the agency says may see them — so each ability is exactly its
 * permission from the Authorization panel.
 */
class UnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('units.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('units.create');
    }

    public function update(User $user, Unit $unit): bool
    {
        return $user->hasPermission('units.update');
    }

    /**
     * Admin of the owning agency, full stop — see User::administers().
     */
    public function delete(User $user, Unit $unit): bool
    {
        return $user->administers($unit);
    }
}
