<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Gives agencies that already exist the leave permissions and the default
 * categories.
 *
 * The observer provisions an organization on creation and the defaults
 * services no-op on one that already has rows — both correct, and together
 * they mean an agency set up before today would never see leave at all. Its
 * people would find the page refusing them and, if they got in, no categories
 * to request against.
 *
 * Only missing rows are inserted, so an agency that has already been given an
 * opinion about any of this keeps it.
 *
 * Query builder rather than models throughout: these tables carry a global
 * scope that would confine the statements to whichever organization happened
 * to be ambient, and a migration has no acting user.
 */
return new class extends Migration
{
    /**
     * @var array<string, array{module: string, action: string, label: string, defaults: array<string, bool>}>
     */
    protected array $permissions = [
        'leave.view_own' => [
            'module' => 'leave', 'action' => 'view_own', 'label' => 'View Own Leave',
            'defaults' => ['pm' => true, 'writer' => true],
        ],
        'leave.view_all' => [
            'module' => 'leave', 'action' => 'view_all', 'label' => 'View Team Leave',
            'defaults' => ['pm' => true, 'writer' => false],
        ],
        'leave.manage' => [
            'module' => 'leave', 'action' => 'manage', 'label' => 'Approve Leave',
            'defaults' => ['pm' => false, 'writer' => false],
        ],
    ];

    /**
     * Mirrors OrganizationLeaveDefaults::DEFAULT_TYPES, inlined so this
     * migration keeps behaving the same way if that class later changes.
     *
     * @var list<string>
     */
    protected array $leaveTypes = ['Annual Leave', 'Sick Leave', 'Casual Leave'];

    public function up(): void
    {
        $organizations = DB::table('organizations')->pluck('id');

        if ($organizations->isEmpty()) {
            return;
        }

        $grants = $this->grantPermissions($organizations);
        $types  = $this->createLeaveTypes($organizations);

        if ($grants > 0 || $types > 0) {
            Log::info('Provisioned leave for existing organizations.', [
                'permission_rows' => $grants,
                'leave_types'     => $types,
                'organizations'   => $organizations->count(),
            ]);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>  $organizations
     */
    protected function grantPermissions($organizations): int
    {
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

        return $inserted;
    }

    /**
     * Only for agencies holding no categories at all. One that has already
     * defined its own is left exactly as it is.
     *
     * @param  \Illuminate\Support\Collection<int, int>  $organizations
     */
    protected function createLeaveTypes($organizations): int
    {
        $inserted = 0;

        foreach ($organizations as $organizationId) {
            $hasAny = DB::table('leave_types')->where('organization_id', $organizationId)->exists();

            if ($hasAny) {
                continue;
            }

            foreach ($this->leaveTypes as $name) {
                DB::table('leave_types')->insert([
                    'organization_id'          => $organizationId,
                    'name'                     => $name,
                    // Null, not a number: an allowance invented here would
                    // look like the agency's policy without being it.
                    'default_annual_allowance' => null,
                    'created_at'               => now(),
                    'updated_at'               => now(),
                ]);

                $inserted++;
            }
        }

        return $inserted;
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('name', array_keys($this->permissions))->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }

        // Leave types are deliberately left alone. By the time this rolls back
        // an agency may have booked real requests against them, and the
        // foreign key would refuse anyway.
    }
};
