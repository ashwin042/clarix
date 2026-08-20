<?php

namespace App\Policies;

use App\Models\LeaveType;
use App\Models\User;

/**
 * Defining the leave categories an agency offers.
 *
 * Everyone who may see leave at all may read the list — a request form has to
 * name the categories. Changing them is an admin's job: it shapes the agency's
 * policy rather than administering a single person's time off, so it is not
 * folded into leave.manage.
 */
class LeaveTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('leave.view_own') || $user->hasPermission('leave.view_all');
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, LeaveType $leaveType): bool
    {
        return $user->administers($leaveType);
    }

    public function delete(User $user, LeaveType $leaveType): bool
    {
        return $user->administers($leaveType);
    }
}
