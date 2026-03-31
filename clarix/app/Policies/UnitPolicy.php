<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;

class UnitPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'pm']);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Unit $unit): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Unit $unit): bool
    {
        return $user->isAdmin();
    }
}
