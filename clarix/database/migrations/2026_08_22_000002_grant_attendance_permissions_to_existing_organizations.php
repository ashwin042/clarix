<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Hands the new attendance permissions to agencies that already exist.
 *
 * OrganizationPermissionDefaults only provisions an organization that has no
 * map at all, which is right — it must never overwrite decisions an agency has
 * made for itself — but it means an agency set up before today would never see
 * a permission added afterwards. Its PMs and writers would find the attendance
 * page refusing them, with a panel showing three toggles switched off that
 * nobody ever switched off.
 *
 * Only missing rows are inserted. An agency that has already been given an
 * opinion about one of these keeps it.
 *
 * Written with the query builder rather than the models: RolePermission now
 * carries a global scope that would confine these statements to whichever
 * organization happened to be ambient, and a migration has no acting user.
 */
return new class extends Migration
{
    /**
     * The new permissions, and the default each role gets.
     *
     * Mirrors OrganizationPermissionDefaults::BASELINE at the time of writing,
     * inlined so this migration keeps behaving the same way if that class
     * later changes.
     *
     * @var array<string, array{module: string, action: string, label: string, defaults: array<string, bool>}>
     */
    protected array $permissions = [
        'attendance.view_own' => [
            'module' => 'attendance', 'action' => 'view_own', 'label' => 'View Own Attendance',
            'defaults' => ['pm' => true, 'writer' => true],
        ],
        'attendance.view_all' => [
            'module' => 'attendance', 'action' => 'view_all', 'label' => 'View Team Attendance',
            'defaults' => ['pm' => true, 'writer' => false],
        ],
        'attendance.manage' => [
            'module' => 'attendance', 'action' => 'manage', 'label' => 'Manage Attendance',
            'defaults' => ['pm' => false, 'writer' => false],
        ],
    ];

    public function up(): void
    {
        $organizations = DB::table('organizations')->pluck('id');

        if ($organizations->isEmpty()) {
            return;
        }

        $inserted = 0;

        foreach ($this->permissions as $name => $definition) {
            $permissionId = DB::table('permissions')->where('name', $name)->value('id');

            if ($permissionId === null) {
                $permissionId = DB::table('permissions')->insertGetId([
                    'name'       => $name,
                    'module'     => $definition['module'],
                    'action'     => $definition['action'],
                    'label'      => $definition['label'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($organizations as $organizationId) {
                foreach ($definition['defaults'] as $role => $allowed) {
                    $exists = DB::table('role_permissions')
                        ->where('organization_id', $organizationId)
                        ->where('role', $role)
                        ->where('permission_id', $permissionId)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('role_permissions')->insert([
                        'organization_id' => $organizationId,
                        'role'            => $role,
                        'permission_id'   => $permissionId,
                        'allowed'         => $allowed,
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);

                    $inserted++;
                }
            }
        }

        if ($inserted > 0) {
            Log::info('Granted attendance permissions to existing organizations.', [
                'rows'          => $inserted,
                'organizations' => $organizations->count(),
            ]);
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('name', array_keys($this->permissions))->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
