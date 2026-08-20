<?php

use App\Services\OrganizationPermissionDefaults;
use App\Services\PermissionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Hands tasks.update_status to agencies that already exist.
 *
 * Fifth time this pattern appears, for the reason it always appears: the
 * defaults service only provisions an organization with no map at all, so a
 * permission added after an agency was created never reaches it, and the
 * toggle reads off with no hint that the row was simply never written.
 *
 * What is new here is the direction of the risk. The previous four handed out
 * capabilities nobody had before, so a missed agency merely kept the status
 * quo. This one *replaces* a hardcoded isAdmin() in TaskPolicy::updateStatus.
 * An agency that does not get these rows finds its supervisors unable to move
 * a card — the same place they were before — while an agency that does gets
 * the behaviour the panel now advertises. Admins are unaffected either way:
 * hasPermission() is unconditionally true for them and they need no row.
 *
 * The values come from OrganizationPermissionDefaults::BASELINE rather than
 * being restated, so a backfilled agency and one created tomorrow cannot end
 * up with two different ideas of who runs the board.
 *
 * Only missing rows are inserted, so an agency that has already formed an
 * opinion about this keeps it.
 */
return new class extends Migration
{
    protected string $permission = 'tasks.update_status';

    public function up(): void
    {
        $permissionId = DB::table('permissions')->where('name', $this->permission)->value('id');

        if ($permissionId === null) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name'       => $this->permission,
                'module'     => 'tasks',
                'action'     => 'update_status',
                'label'      => 'Change Task Status',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $organizations = DB::table('organizations')->pluck('id');

        if ($organizations->isEmpty()) {
            return;
        }

        $inserted = 0;

        foreach (OrganizationPermissionDefaults::BASELINE as $role => $defaults) {
            if (! array_key_exists($this->permission, $defaults)) {
                continue;
            }

            foreach ($organizations as $organizationId) {
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
                    'allowed'         => $defaults[$this->permission],
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);

                $inserted++;
            }
        }

        // Otherwise the rows are live but every cached answer predates them,
        // and the new toggle appears to do nothing until the TTL lapses.
        PermissionService::flushAll();

        if ($inserted > 0) {
            Log::info('Granted tasks.update_status to existing organizations.', [
                'rows'          => $inserted,
                'organizations' => $organizations->count(),
            ]);
        }
    }

    /**
     * Both the rows and the permission go.
     *
     * Unlike the supervisor/HR backfill, this migration created the permission
     * itself and nothing else refers to it, so removing it on the way down
     * takes nothing away from any other role. TaskPolicy::updateStatus would
     * then refuse everyone but an admin — which is exactly where it stood
     * before this migration ran.
     */
    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', $this->permission)->value('id');

        if ($permissionId === null) {
            return;
        }

        DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
