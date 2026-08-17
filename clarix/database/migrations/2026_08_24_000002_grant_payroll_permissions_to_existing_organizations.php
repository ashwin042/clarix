<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Hands the payroll permissions to agencies that already exist.
 *
 * Third time this pattern appears, for the same reason each time: the defaults
 * service only provisions an organization that has no map at all, so a
 * permission added after an agency was created would never reach it and its
 * people would find the screen refusing them.
 *
 * Only missing rows are inserted, so an agency that has already been given an
 * opinion about either of these keeps it.
 *
 * Unlike attendance and leave there are no per-organization defaults to seed
 * here — payroll has no categories or types, only records an admin writes — so
 * nothing is added to OrganizationTeardown's provisioned list. payroll_records
 * is operational data and joins the tables that block an organization from
 * being deleted.
 */
return new class extends Migration
{
    /**
     * @var array<string, array{module: string, action: string, label: string, defaults: array<string, bool>}>
     */
    protected array $permissions = [
        'payroll.view_own' => [
            'module' => 'payroll', 'action' => 'view_own', 'label' => 'View Own Payroll',
            'defaults' => ['pm' => true, 'writer' => true],
        ],
        'payroll.manage' => [
            'module' => 'payroll', 'action' => 'manage', 'label' => 'Manage Payroll',
            // Off for everyone but an admin, who passes without a row at all.
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
            Log::info('Granted payroll permissions to existing organizations.', [
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
