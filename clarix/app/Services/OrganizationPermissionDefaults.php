<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\RolePermission;
use Illuminate\Support\Facades\Log;

/**
 * Gives a new agency a working permission map on the day it is created.
 *
 * Without this an organization starts with no role_permissions rows at all,
 * which does not read as "no configuration yet" — it reads as every PM and
 * writer being denied everything, on a screen that shows every toggle off and
 * offers no hint that the agency was never set up.
 *
 * The template is Code Next Door's live map, which is what the founding agency
 * has settled on after real use and is a better starting point than anything
 * invented here. That choice has a known weakness: the map is mutable, so an
 * admin who revokes something on Tuesday changes what every agency created on
 * Wednesday begins with. The baseline below is the guard against that going
 * badly wrong — anything the template cannot answer falls back to it, so a new
 * agency can never inherit an empty or half-built map.
 */
class OrganizationPermissionDefaults
{
    /**
     * The slug of the agency whose map is copied.
     */
    public const TEMPLATE_SLUG = 'code-next-door';

    /**
     * A stable, known-good map, used for anything the template cannot supply.
     *
     * Deletion is absent and stays absent: removing a task, a unit or a person
     * is admin-only by structure, there is no permission for it in the
     * catalogue, and nothing here should reintroduce the idea.
     *
     * @var array<string, array<string, bool>>
     */
    public const BASELINE = [
        'pm' => [
            'units.view'         => true,
            'units.create'       => false,
            'units.update'       => false,
            'users.view'         => true,
            'users.create'       => true,
            'users.update'       => false,
            'tasks.view'         => true,
            'tasks.create'       => true,
            'tasks.update'       => true,
            'tasks.assign'       => true,
            'tasks.upload_files' => true,
            'credits.view'       => true,

            // Advancing a task through the board. Deliberately not folded
            // into tasks.update: editing a task and moving it through the
            // workflow are different authorities, and tasks.update is on by
            // default for the PM, so reusing it would have handed the PM the
            // workflow without anybody deciding it should.
            'tasks.update_status' => false,

            // A PM oversees a unit, so they see their unit's attendance —
            // AttendancePolicy is what confines "all" to their own people.
            // Corrections stay with an admin until an agency says otherwise.
            'attendance.view_own' => true,
            'attendance.view_all' => true,
            'attendance.manage'   => false,

            // A PM sees their unit's leave for planning, but approving it
            // stays with an admin until an agency decides otherwise.
            'leave.view_own' => true,
            'leave.view_all' => true,
            'leave.manage'   => false,

            // Everyone sees their own pay. Running payroll stays with an
            // admin — it is the most sensitive thing in the panel.
            'payroll.view_own' => true,
            'payroll.manage'   => false,
        ],
        /*
         * Supervisor: a PM's work, across the whole agency.
         *
         * The grants below look close to a PM's and mean something different,
         * because the difference is not in this table. A PM is confined to
         * their own unit by TaskPolicy, structurally, and no entry here widens
         * that; a supervisor carries no unit at all and TaskPolicy lets them
         * reach every task the agency owns. So the same tasks.update reads as
         * "your unit" for one role and "the agency" for the other.
         *
         * units.view and tasks.view are not decoration: without them the four
         * capabilities the role exists for cannot be exercised, because the
         * only screens offering them refuse the page first.
         *
         * People are not the supervisor's remit. Users, the credit list and
         * all three HR modules are off, and deletion is absent here exactly as
         * it is absent everywhere else.
         */
        'supervisor' => [
            'units.view'         => true,
            'units.create'       => true,
            'units.update'       => false,
            'users.view'         => false,
            'users.create'       => false,
            'users.update'       => false,
            'tasks.view'         => true,
            'tasks.create'       => true,
            'tasks.update'       => true,
            'tasks.assign'       => true,
            'tasks.upload_files' => true,
            'credits.view'       => false,

            // Advancing a task through the board. Deliberately not folded
            // into tasks.update: editing a task and moving it through the
            // workflow are different authorities, and tasks.update is on by
            // default for the PM, so reusing it would have handed the PM the
            // workflow without anybody deciding it should.
            'tasks.update_status' => true,

            // Their own record, like everyone. Overseeing the work is not a
            // reason to see when colleagues clocked in.
            'attendance.view_own' => true,
            'attendance.view_all' => false,
            'attendance.manage'   => false,

            'leave.view_own' => true,
            'leave.view_all' => false,
            'leave.manage'   => false,

            'payroll.view_own' => true,
            'payroll.manage'   => false,
        ],

        /*
         * HR: three modules, at the level an admin holds them, and nothing
         * else at all.
         *
         * The mirror image of the supervisor. Every Management permission is
         * off — including tasks.view, so the work is not merely read-only to
         * HR but absent — and attendance, leave and payroll are on all the way
         * up to manage.
         *
         * Those grants only reach the whole agency because AttendancePolicy
         * and LeaveRequestPolicy name the role in their structural half as
         * well. Handing HR attendance.manage on its own would have left them
         * correcting nobody's record but their own.
         */
        'hr' => [
            'units.view'         => false,
            'units.create'       => false,
            'units.update'       => false,
            'users.view'         => false,
            'users.create'       => false,
            'users.update'       => false,
            'tasks.view'         => false,
            'tasks.create'       => false,
            'tasks.update'       => false,
            'tasks.assign'       => false,
            'tasks.upload_files' => false,
            'credits.view'       => false,

            // Advancing a task through the board. Deliberately not folded
            // into tasks.update: editing a task and moving it through the
            // workflow are different authorities, and tasks.update is on by
            // default for the PM, so reusing it would have handed the PM the
            // workflow without anybody deciding it should.
            'tasks.update_status' => false,

            'attendance.view_own' => true,
            'attendance.view_all' => true,
            'attendance.manage'   => true,

            'leave.view_own' => true,
            'leave.view_all' => true,
            'leave.manage'   => true,

            // The one role other than admin that runs payroll. There is no
            // "view the team's payroll" to grant — managing it is the only way
            // to see it, and that is deliberate.
            'payroll.view_own' => true,
            'payroll.manage'   => true,
        ],
        'writer' => [
            'units.view'         => false,
            'units.create'       => false,
            'units.update'       => false,
            'users.view'         => false,
            'users.create'       => false,
            'users.update'       => false,
            'tasks.view'         => true,
            'tasks.create'       => false,
            'tasks.update'       => false,
            'tasks.assign'       => false,
            'tasks.upload_files' => false,
            'credits.view'       => false,

            // Advancing a task through the board. Deliberately not folded
            // into tasks.update: editing a task and moving it through the
            // workflow are different authorities, and tasks.update is on by
            // default for the PM, so reusing it would have handed the PM the
            // workflow without anybody deciding it should.
            'tasks.update_status' => false,

            // A writer sees their own record and nobody else's.
            'attendance.view_own' => true,
            'attendance.view_all' => false,
            'attendance.manage'   => false,

            // A writer asks for their own leave and sees their own history.
            'leave.view_own' => true,
            'leave.view_all' => false,
            'leave.manage'   => false,

            'payroll.view_own' => true,
            'payroll.manage'   => false,
        ],
    ];

    /**
     * Provision an organization, unless it already has a map of its own.
     *
     * Idempotent by design: it runs from an observer on every create, from the
     * migration that provisions agencies which predate this work, and from the
     * seeder. Re-running must never overwrite decisions an agency has already
     * made for itself.
     */
    public function applyTo(Organization $organization): void
    {
        if ($this->hasMap($organization->id)) {
            return;
        }

        $permissions = Permission::pluck('id', 'name');

        // A fresh database that has not been seeded yet has no catalogue to
        // grant from. Nothing to do, and nothing wrong.
        if ($permissions->isEmpty()) {
            return;
        }

        $template = $this->template();

        /*
         * The write is aimed at the new organization explicitly. The actor is
         * usually a superadmin, who belongs to no agency, so the auto-stamp on
         * BelongsToOrganization would otherwise resolve null and hit the NOT
         * NULL constraint.
         */
        TenantContext::actingAsOrganization($organization->id, function () use ($organization, $permissions, $template): void {
            foreach (self::BASELINE as $role => $fallback) {
                foreach ($fallback as $name => $default) {
                    if (! $permissions->has($name)) {
                        continue;
                    }

                    RolePermission::create([
                        'role'          => $role,
                        'permission_id' => $permissions[$name],
                        'allowed'       => $template[$role][$name] ?? $default,
                    ]);
                }
            }
        });

        PermissionService::flushAll();

        Log::info('Provisioned default role permissions for a new organization.', [
            'organization_id' => $organization->id,
            'source'          => $template === [] ? 'baseline' : 'template',
        ]);
    }

    /**
     * Whether an organization already holds any permission rows.
     */
    public function hasMap(int $organizationId): bool
    {
        return RolePermission::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->exists();
    }

    /**
     * Code Next Door's current map as role => [permission name => allowed].
     *
     * Read without the tenant scope: this runs while acting as the agency
     * being created, and the template belongs to a different one. Returns an
     * empty array when there is no template agency or it holds no rows, in
     * which case every lookup falls through to the baseline.
     *
     * @return array<string, array<string, bool>>
     */
    protected function template(): array
    {
        $templateId = Organization::withoutGlobalScopes()
            ->where('slug', self::TEMPLATE_SLUG)
            ->value('id');

        if ($templateId === null) {
            return [];
        }

        $rows = RolePermission::withoutGlobalScopes()
            ->with('permission')
            ->where('organization_id', $templateId)
            ->get();

        $map = [];

        foreach ($rows as $row) {
            if ($row->permission === null) {
                continue;
            }

            $map[$row->role][$row->permission->name] = (bool) $row->allowed;
        }

        return $map;
    }
}
