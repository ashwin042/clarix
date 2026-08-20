<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;

/**
 * Two questions, kept apart, as everywhere else in Clarix.
 *
 *   May this role look beyond its own record?  -> the Authorization panel,
 *                                                 via attendance.view_all.
 *   Whose records, then?                       -> structural, from the
 *                                                 viewer's place in the
 *                                                 agency. An admin sees the
 *                                                 organization, a PM their
 *                                                 own unit.
 *
 * Granting attendance.view_all widens what a role may do; it never widens
 * which people they may do it to. A PM handed the permission still sees only
 * their unit, and cannot be granted their way into another one.
 *
 * Clocking yourself in and out is not represented here at all. Recording your
 * own attendance is structural, like editing your own profile — an admin
 * revoking a *view* toggle must not silently stop staff logging their day.
 */
class AttendancePolicy
{
    /**
     * The people whose records a viewer may reach, ignoring permissions.
     */
    protected function reaches(User $user, User $subject): bool
    {
        if ($user->id === $subject->id) {
            return true;
        }

        return match ($user->role) {
            'admin' => true,

            /*
             * HR reaches the whole agency, like an admin and for the same
             * reason: attendance has a unit tier because a PM oversees a unit,
             * and HR oversees none — they keep the agency's records.
             *
             * This is reach, not permission. An agency that switches
             * attendance.manage off for HR takes the capability away; this arm
             * only ever said whose records it would have applied to.
             */
            'hr' => (int) $subject->organization_id === (int) $user->organization_id,

            'pm'     => $subject->unit_id !== null && $subject->unit_id === $user->unit_id,
            default  => false,
        };
    }

    /**
     * Seeing your own history.
     */
    public function viewOwn(User $user): bool
    {
        return $user->hasPermission('attendance.view_own');
    }

    /**
     * Opening a list of other people's attendance at all.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('attendance.view_all');
    }

    public function view(User $user, Attendance $attendance): bool
    {
        if ($attendance->user_id === $user->id) {
            return $this->viewOwn($user);
        }

        return $user->hasPermission('attendance.view_all')
            && $this->reaches($user, $attendance->user);
    }

    /**
     * Correcting somebody's record, or marking a day for them.
     *
     * Editing your own row is deliberately included here rather than being
     * free: a person may clock themselves in, but rewriting the times
     * afterwards is a correction, and corrections are what attendance.manage
     * governs.
     */
    public function manage(User $user, User $subject): bool
    {
        return $user->hasPermission('attendance.manage')
            && $this->reaches($user, $subject);
    }

    public function update(User $user, Attendance $attendance): bool
    {
        return $this->manage($user, $attendance->user);
    }

    /**
     * Removing an attendance record is a correction of the strongest kind and
     * follows the session's rule for deletion: admin of the owning agency,
     * structurally, with no permission to grant.
     */
    public function delete(User $user, Attendance $attendance): bool
    {
        return $user->administers($attendance);
    }
}
