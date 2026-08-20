<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.create');
    }

    /**
     * A granted permission, except when the target is an admin.
     *
     * The second half is structural, like delete()'s. Without it a role
     * holding users.update could open an admin's row, pick any role its own
     * ceiling allowed and set a password — demoting the agency's admin and
     * taking the account over in one submit. delete() already refuses a
     * non-admin by way of administers(); update() ignored its target
     * altogether, which made the ceiling on *creating* an admin pointless.
     */
    public function update(User $user, User $model): bool
    {
        if ($model->isAdmin() && ! $user->isAdmin()) {
            return false;
        }

        return $user->hasPermission('users.update');
    }

    /**
     * Admin of the owning agency, and never themselves. Both halves are
     * structural: there is no users.delete permission to grant, and no grant
     * would let someone remove their own account from under themselves.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->administers($model) && $user->id !== $model->id;
    }
}
