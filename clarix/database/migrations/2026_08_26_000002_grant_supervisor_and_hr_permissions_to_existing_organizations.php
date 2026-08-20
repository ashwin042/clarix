<?php

use App\Services\OrganizationPermissionDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Hands the two new roles' defaults to agencies that already exist.
 *
 * Fourth time this pattern appears, and for a new reason: the previous three
 * added permissions to existing roles, this one adds roles to the existing
 * permissions. The consequence is the same either way — the defaults service
 * only provisions an organization with no map at all, so an agency created
 * last week would have every supervisor and HR toggle reading off, with no
 * hint that the rows were simply never written.
 *
 * The grants are read from OrganizationPermissionDefaults::BASELINE rather
 * than restated here, so a backfilled agency and one created tomorrow cannot
 * end up with two different ideas of what these roles are.
 *
 * No permission is created: both roles are made entirely out of the existing
 * catalogue, which is the point of the design. Deletion is absent from that
 * catalogue and stays absent — neither new role may destroy anything, and
 * there is no row here that could say otherwise.
 *
 * Only missing rows are inserted, so an agency that has already formed an
 * opinion about one of these keeps it.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    protected array $roles = ['supervisor', 'hr'];

    public function up(): void
    {
        $organizations = DB::table('organizations')->pluck('id');

        if ($organizations->isEmpty()) {
            return;
        }

        $permissions = DB::table('permissions')->pluck('id', 'name');

        // A database whose catalogue has not been seeded has nothing to grant
        // from. Nothing to do, and nothing wrong.
        if ($permissions->isEmpty()) {
            return;
        }

        $inserted = 0;

        foreach ($this->roles as $role) {
            $defaults = OrganizationPermissionDefaults::BASELINE[$role] ?? [];

            foreach ($defaults as $name => $allowed) {
                if (! isset($permissions[$name])) {
                    continue;
                }

                foreach ($organizations as $organizationId) {
                    $exists = DB::table('role_permissions')
                        ->where('organization_id', $organizationId)
                        ->where('role', $role)
                        ->where('permission_id', $permissions[$name])
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('role_permissions')->insert([
                        'organization_id' => $organizationId,
                        'role'            => $role,
                        'permission_id'   => $permissions[$name],
                        'allowed'         => $allowed,
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);

                    $inserted++;
                }
            }
        }

        if ($inserted > 0) {
            Log::info('Granted supervisor and HR permissions to existing organizations.', [
                'rows'          => $inserted,
                'organizations' => $organizations->count(),
            ]);
        }
    }

    /**
     * The rows go, the permissions stay.
     *
     * Nothing here created a permission — both roles are assembled from the
     * existing catalogue — so deleting one on the way down would take a
     * capability away from PM and writer as well.
     */
    public function down(): void
    {
        DB::table('role_permissions')->whereIn('role', $this->roles)->delete();
    }
};
