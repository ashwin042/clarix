<?php

namespace App\Policies;

use App\Models\PayrollRecord;
use App\Models\User;

/**
 * Payroll is narrower than attendance or leave, on purpose.
 *
 * There is no "view the team's payroll" permission and no unit-level tier. A
 * PM overseeing a unit has a reason to see who turned up and who is away; they
 * have no such reason to see what their colleagues earn. So there are exactly
 * two capabilities — reading your own history, and managing the agency's
 * payroll — and the second is org-wide because whoever holds it is doing the
 * agency's books.
 *
 * payroll.manage is a toggle for consistency with the rest of the panel, but
 * it is off for every non-admin role by default and is the one an agency
 * should think hardest about before granting.
 */
class PayrollRecordPolicy
{
    public function viewOwn(User $user): bool
    {
        return $user->hasPermission('payroll.view_own');
    }

    /**
     * Opening the management screen at all.
     */
    public function manage(User $user): bool
    {
        return $user->hasPermission('payroll.manage') && $user->organization_id !== null;
    }

    public function view(User $user, PayrollRecord $record): bool
    {
        if ($record->user_id === $user->id) {
            return $this->viewOwn($user);
        }

        // Somebody else's pay: only for whoever manages the agency's payroll,
        // and only inside their own agency.
        return $user->hasPermission('payroll.manage')
            && (int) $record->organization_id === (int) $user->organization_id;
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    /**
     * Editing the figures. The record's own state has the final say — a
     * finalised or paid record is closed to everyone.
     */
    public function update(User $user, PayrollRecord $record): bool
    {
        return $this->view($user, $record)
            && $user->hasPermission('payroll.manage')
            && $record->amountsAreEditable();
    }

    /**
     * Moving a record between states. Separate from update() because
     * finalising and marking paid are exactly the things that are still
     * allowed once the figures are locked.
     */
    public function transition(User $user, PayrollRecord $record): bool
    {
        return $user->hasPermission('payroll.manage')
            && (int) $record->organization_id === (int) $user->organization_id;
    }

    /**
     * Deletion follows the session's rule: admin of the owning agency,
     * structurally, with no permission to grant. A paid record is kept
     * regardless — it describes money that actually moved.
     */
    public function delete(User $user, PayrollRecord $record): bool
    {
        return $user->administers($record) && ! $record->isPaid();
    }
}
