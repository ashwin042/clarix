<?php

namespace App\Observers;

use App\Models\Organization;
use App\Services\OrganizationLeaveDefaults;
use App\Services\OrganizationPermissionDefaults;

/**
 * Everything an organization needs before anyone can work in it.
 *
 * Hung off the model rather than called from the superadmin portal so that it
 * holds for every path that creates an agency — the portal today, the seeder,
 * the test fixtures, and whatever signup flow arrives later. Setup that has to
 * be remembered at the call site is setup some future path will forget, and
 * the symptom would be an agency whose people are silently denied everything.
 *
 * Each provisioner is idempotent and independent, so a new one can be added
 * here without disturbing the others.
 */
class OrganizationObserver
{
    public function __construct(
        protected OrganizationPermissionDefaults $permissions,
        protected OrganizationLeaveDefaults $leaveTypes,
    ) {
    }

    public function created(Organization $organization): void
    {
        $this->permissions->applyTo($organization);
        $this->leaveTypes->applyTo($organization);
    }
}
