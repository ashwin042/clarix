<?php

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;

/**
 * The same split as attendance, for the same reason.
 *
 *   May this role look past its own requests?  -> leave.view_all
 *   May it decide them?                        -> leave.manage
 *   Whose, then?                               -> structural: an admin the
 *                                                 agency, a PM their unit.
 *
 * Submitting a request for yourself appears nowhere here. That is structural,
 * like clocking yourself in: an admin revoking a view toggle must not leave
 * staff unable to ask for time off.
 *
 * Note that deciding your own request is barred in LeaveApproval rather than
 * here, so it holds for an admin too — a policy that returned true for admins
 * would let the most senior person sign off their own leave.
 */
class LeaveRequestPolicy
{
    /**
     * The people whose requests a viewer may reach, ignoring permissions.
     */
    protected function reaches(User $user, User $subject): bool
    {
        if ($user->id === $subject->id) {
            return true;
        }

        return match ($user->role) {
            'admin' => true,

            // The whole agency, as in AttendancePolicy and for the same
            // reason: approving time off is HR's job across every unit, and
            // the unit tier exists for the PM who oversees one.
            'hr' => (int) $subject->organization_id === (int) $user->organization_id,

            'pm'    => $subject->unit_id !== null && $subject->unit_id === $user->unit_id,
            default => false,
        };
    }

    public function viewOwn(User $user): bool
    {
        return $user->hasPermission('leave.view_own');
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('leave.view_all');
    }

    public function view(User $user, LeaveRequest $request): bool
    {
        if ($request->user_id === $user->id) {
            return $this->viewOwn($user);
        }

        return $user->hasPermission('leave.view_all')
            && $this->reaches($user, $request->user);
    }

    /**
     * Approving or rejecting.
     */
    public function decide(User $user, LeaveRequest $request): bool
    {
        return $user->hasPermission('leave.manage')
            && $this->reaches($user, $request->user);
    }

    /**
     * Withdrawing. Yours alone, and only while it is undecided — the rest of
     * that rule lives in LeaveApproval::cancel().
     */
    public function cancel(User $user, LeaveRequest $request): bool
    {
        return $request->user_id === $user->id;
    }

    /**
     * Deletion follows the session's rule: admin of the owning agency,
     * structurally, with no permission to grant.
     */
    public function delete(User $user, LeaveRequest $request): bool
    {
        return $user->administers($request);
    }
}
