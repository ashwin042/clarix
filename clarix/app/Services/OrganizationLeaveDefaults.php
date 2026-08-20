<?php

namespace App\Services;

use App\Models\LeaveType;
use App\Models\Organization;

/**
 * The leave categories a new agency starts with.
 *
 * Three names that almost every agency wants, and no allowances. The names are
 * safe to assume; the numbers are not — how many sick days a year an agency
 * grants is its own policy, and a figure invented here would look official on
 * a screen while being something nobody chose. Each type ships with a null
 * allowance, meaning "not tracked", and an admin sets real numbers if they
 * want balances measured.
 *
 * Kept apart from OrganizationPermissionDefaults because the two answer
 * different questions and change for different reasons; the observer calls
 * both.
 */
class OrganizationLeaveDefaults
{
    /**
     * @var list<string>
     */
    public const DEFAULT_TYPES = ['Annual Leave', 'Sick Leave', 'Casual Leave'];

    /**
     * Provision an organization, unless it already has categories of its own.
     *
     * Idempotent: it runs from the observer on create, from the migration that
     * provisions agencies predating this work, and from the seeder. Re-running
     * must never overwrite what an agency has since decided.
     */
    public function applyTo(Organization $organization): void
    {
        if ($this->hasTypes($organization->id)) {
            return;
        }

        // Aimed at the new organization explicitly: the actor is usually a
        // superadmin, who belongs to no agency, so the auto-stamp would
        // otherwise resolve null and hit the NOT NULL column.
        TenantContext::actingAsOrganization($organization->id, function (): void {
            foreach (self::DEFAULT_TYPES as $name) {
                LeaveType::create([
                    'name'                     => $name,
                    'default_annual_allowance' => null,
                ]);
            }
        });
    }

    public function hasTypes(int $organizationId): bool
    {
        return LeaveType::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->exists();
    }
}
