<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Services\OrganizationPermissionDefaults;
use App\Services\PermissionService;
use App\Services\TenantContext;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Every permission the Authorization panel may offer.
     *
     * Deletion is deliberately absent. Removing a task, a unit or a person is
     * admin-only by structure rather than by configuration — there is no
     * units.delete, users.delete or tasks.delete to seed, so no toggle exists
     * that could hand the ability to anyone else. The check lives in the
     * policies, on User::administers().
     */
    public function run(): void
    {
        // Define ALL permissions
        $definitions = [
            // Units
            ['name' => 'units.view',   'module' => 'units',   'action' => 'view',         'label' => 'View Units'],
            ['name' => 'units.create', 'module' => 'units',   'action' => 'create',       'label' => 'Create Units'],
            ['name' => 'units.update', 'module' => 'units',   'action' => 'update',       'label' => 'Update Units'],

            // Users
            ['name' => 'users.view',   'module' => 'users',   'action' => 'view',         'label' => 'View Users'],
            ['name' => 'users.create', 'module' => 'users',   'action' => 'create',       'label' => 'Create Users'],
            ['name' => 'users.update', 'module' => 'users',   'action' => 'update',       'label' => 'Update Users'],

            // Tasks
            ['name' => 'tasks.view',         'module' => 'tasks', 'action' => 'view',         'label' => 'View Tasks'],
            ['name' => 'tasks.create',       'module' => 'tasks', 'action' => 'create',       'label' => 'Create Tasks'],
            ['name' => 'tasks.update',       'module' => 'tasks', 'action' => 'update',       'label' => 'Update Tasks'],
            ['name' => 'tasks.update_status', 'module' => 'tasks', 'action' => 'update_status', 'label' => 'Change Task Status'],
            ['name' => 'tasks.assign',       'module' => 'tasks', 'action' => 'assign',       'label' => 'Assign Writers'],
            ['name' => 'tasks.upload_files', 'module' => 'tasks', 'action' => 'upload_files', 'label' => 'Upload Files'],

            // Credits
            ['name' => 'credits.view', 'module' => 'credits', 'action' => 'view',         'label' => 'View Credit List'],

            /*
             * Attendance. Clocking yourself in and out is not listed: that is
             * structural, like editing your own profile, and must not stop
             * working because an admin switched a view toggle off.
             */
            ['name' => 'attendance.view_own', 'module' => 'attendance', 'action' => 'view_own', 'label' => 'View Own Attendance'],
            ['name' => 'attendance.view_all', 'module' => 'attendance', 'action' => 'view_all', 'label' => 'View Team Attendance'],
            ['name' => 'attendance.manage',   'module' => 'attendance', 'action' => 'manage',   'label' => 'Manage Attendance'],

            /*
             * Leave. Submitting a request for yourself is not listed, for the
             * same reason clocking in is not: asking for time off must not
             * depend on a toggle somebody switched off.
             */
            ['name' => 'leave.view_own', 'module' => 'leave', 'action' => 'view_own', 'label' => 'View Own Leave'],
            ['name' => 'leave.view_all', 'module' => 'leave', 'action' => 'view_all', 'label' => 'View Team Leave'],
            ['name' => 'leave.manage',   'module' => 'leave', 'action' => 'manage',   'label' => 'Approve Leave'],

            /*
             * Payroll. Only two: reading your own history, and running the
             * agency's payroll. There is deliberately no "view the team's
             * payroll" — overseeing a unit is a reason to see attendance, not
             * a reason to see what colleagues earn.
             */
            ['name' => 'payroll.view_own', 'module' => 'payroll', 'action' => 'view_own', 'label' => 'View Own Payroll'],
            ['name' => 'payroll.manage',   'module' => 'payroll', 'action' => 'manage',   'label' => 'Manage Payroll'],
        ];

        foreach ($definitions as $def) {
            Permission::firstOrCreate(['name' => $def['name']], $def);
        }

        $permissions = Permission::pluck('id', 'name');

        /*
         * The map for the organization this seeder is running as.
         *
         * The defaults themselves live on OrganizationPermissionDefaults, so
         * the figures a fresh install starts with and the figures a new agency
         * inherits cannot drift apart into two definitions of "sensible".
         *
         * updateOrCreate rather than create: re-running a seeder must not
         * duplicate rows, and the unique index is on (organization, role,
         * permission) so a second run resolves to the same rows.
         */
        foreach (OrganizationPermissionDefaults::BASELINE as $role => $defaults) {
            foreach ($defaults as $permName => $allowed) {
                if (! $permissions->has($permName)) {
                    continue;
                }

                RolePermission::updateOrCreate(
                    ['role' => $role, 'permission_id' => $permissions[$permName]],
                    ['allowed' => $allowed]
                );
            }
        }

        /*
         * Then anything else on the platform that has no map of its own.
         *
         * Without this, seeding a database that already holds several agencies
         * would set up whichever one happens to be ambient and leave the rest
         * denying their members everything.
         */
        $defaults = app(OrganizationPermissionDefaults::class);

        TenantContext::runWithoutScope(function () use ($defaults): void {
            foreach (Organization::all() as $organization) {
                $defaults->applyTo($organization);
            }
        });

        PermissionService::flushAll();
    }
}
