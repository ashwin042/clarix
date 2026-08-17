<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Retires deletion as a grantable capability.
 *
 * Removing a task, a unit or a person is now admin-only by structure: the
 * policies ask User::administers() and never consult the permission table, so
 * these rows decide nothing. They are dropped rather than left switched off,
 * because a row that still exists is a row an admin can find a way to flip,
 * and a permission nobody reads is worse than no permission at all — it reads
 * like a control that works.
 *
 * The role_permissions rows go first even though the foreign key cascades, so
 * the count that gets logged is the real one and the migration does not depend
 * on the cascade being configured the way it is today.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    protected array $names = ['units.delete', 'users.delete', 'tasks.delete'];

    public function up(): void
    {
        $ids = DB::table('permissions')->whereIn('name', $this->names)->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        $grants = DB::table('role_permissions')->whereIn('permission_id', $ids)->count();
        $live   = DB::table('role_permissions')
            ->whereIn('permission_id', $ids)
            ->where('allowed', true)
            ->count();

        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        // A non-zero $live means some role really could delete through the
        // panel before this ran. Worth a line in the log rather than a silent
        // change of behaviour.
        Log::info('Deletion retired as a grantable permission.', [
            'permissions_removed'   => $ids->count(),
            'role_grants_removed'   => $grants,
            'were_actually_allowed' => $live,
        ]);
    }

    /**
     * Puts the catalogue rows back, but not the grants.
     *
     * Rolling back restores the permissions as options; it deliberately does
     * not restore who had them, because the policies would still ignore the
     * answer until the code is rolled back too.
     */
    public function down(): void
    {
        $definitions = [
            ['name' => 'units.delete', 'module' => 'units', 'action' => 'delete', 'label' => 'Delete Units'],
            ['name' => 'users.delete', 'module' => 'users', 'action' => 'delete', 'label' => 'Delete Users'],
            ['name' => 'tasks.delete', 'module' => 'tasks', 'action' => 'delete', 'label' => 'Delete Tasks'],
        ];

        foreach ($definitions as $definition) {
            if (DB::table('permissions')->where('name', $definition['name'])->exists()) {
                continue;
            }

            DB::table('permissions')->insert($definition + [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
