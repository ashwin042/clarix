<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Closes the last nullable tenant key.
 *
 * Phase 1 added organization_id to role_permissions and widened the unique
 * index to (organization_id, role, permission_id), but left the column
 * nullable and said why: the Authorization panel and the seeder were still
 * writing org-less rows, and tightening it then would have broken the
 * authorization screen. Both now write through BelongsToOrganization, so the
 * column can be closed.
 *
 * Three steps, in the order every prior migration this session has used:
 * backfill what is missing, provision what was never there, then verify
 * nothing is left before altering the column. The verification is not
 * ceremony — on MySQL a NOT NULL change against a table still holding NULLs
 * fails partway, and failing up front with the count named is a better
 * outcome than a half-applied schema.
 *
 * Written with the query builder rather than the models on purpose. A
 * migration has to keep working years after the model it was written against
 * has moved on, and RolePermission now carries a global scope that would
 * confine these statements to whichever organization happened to be ambient.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->backfillOrphanedRows();
        $this->provisionOrganizationsWithNoMap();
        $this->assertFullyBackfilled();

        Schema::table('role_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable(false)->change();
        });
    }

    /**
     * Rows written before the column existed belong to the founding agency.
     *
     * Clarix served one agency for its whole life until phase 1, so a
     * permission row with no owner can only be theirs. Phase 1's backfill
     * already did this; it is repeated because the seeder and the panel have
     * been writing org-less rows ever since, and because a migration that
     * assumes an earlier one left nothing behind is a migration that fails on
     * the one database where it did.
     */
    protected function backfillOrphanedRows(): void
    {
        $organizationId = DB::table('organizations')->where('slug', 'code-next-door')->value('id')
            ?? DB::table('organizations')->orderBy('id')->value('id');

        if ($organizationId === null) {
            return;
        }

        $orphaned = DB::table('role_permissions')->whereNull('organization_id')->count();

        if ($orphaned === 0) {
            return;
        }

        DB::table('role_permissions')
            ->whereNull('organization_id')
            ->update(['organization_id' => $organizationId]);

        Log::info('Claimed org-less role_permissions rows for the founding organization.', [
            'rows'            => $orphaned,
            'organization_id' => $organizationId,
        ]);
    }

    /**
     * Give every organization that has no map at all a working one.
     *
     * An agency created through the superadmin portal between phase 1 and now
     * has no role_permissions rows, which is not a neutral state: it denies
     * every PM and writer everything. Tightening the column without fixing
     * this would make that permanent and invisible.
     *
     * The template is the founding agency's live map, falling back per
     * permission to a copy of the seeder's baseline for anything it cannot
     * answer. The baseline is inlined rather than read from
     * OrganizationPermissionDefaults so this migration keeps behaving the same
     * way if that class later changes.
     */
    protected function provisionOrganizationsWithNoMap(): void
    {
        $permissions = DB::table('permissions')->pluck('id', 'name');

        // A database whose catalogue has not been seeded yet has nothing to
        // grant. The seeder will provision these organizations when it runs.
        if ($permissions->isEmpty()) {
            return;
        }

        $baseline = [
            'pm' => [
                'units.view' => true, 'units.create' => false, 'units.update' => false,
                'users.view' => true, 'users.create' => true, 'users.update' => false,
                'tasks.view' => true, 'tasks.create' => true, 'tasks.update' => true,
                'tasks.assign' => true, 'tasks.upload_files' => true,
                'credits.view' => true,
            ],
            'writer' => [
                'units.view' => false, 'units.create' => false, 'units.update' => false,
                'users.view' => false, 'users.create' => false, 'users.update' => false,
                'tasks.view' => true, 'tasks.create' => false, 'tasks.update' => false,
                'tasks.assign' => false, 'tasks.upload_files' => false,
                'credits.view' => false,
            ],
        ];

        $template = $this->templateMap($permissions);

        $owned = DB::table('role_permissions')
            ->whereNotNull('organization_id')
            ->distinct()
            ->pluck('organization_id')
            ->all();

        $provisioned = [];

        foreach (DB::table('organizations')->orderBy('id')->get(['id', 'name']) as $organization) {
            if (in_array($organization->id, $owned)) {
                continue;
            }

            $rows = [];

            foreach ($baseline as $role => $defaults) {
                foreach ($defaults as $name => $allowed) {
                    if (! $permissions->has($name)) {
                        continue;
                    }

                    $rows[] = [
                        'organization_id' => $organization->id,
                        'role'            => $role,
                        'permission_id'   => $permissions[$name],
                        'allowed'         => $template[$role][$name] ?? $allowed,
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ];
                }
            }

            if ($rows === []) {
                continue;
            }

            DB::table('role_permissions')->insert($rows);
            $provisioned[$organization->name] = count($rows);
        }

        if ($provisioned !== []) {
            Log::info('Provisioned default role permissions for organizations that had none.', $provisioned);
        }
    }

    /**
     * The founding agency's map, as role => [permission name => allowed].
     *
     * @param  \Illuminate\Support\Collection<string, int>  $permissions
     * @return array<string, array<string, bool>>
     */
    protected function templateMap($permissions): array
    {
        $templateId = DB::table('organizations')->where('slug', 'code-next-door')->value('id');

        if ($templateId === null) {
            return [];
        }

        $names = $permissions->flip();
        $map   = [];

        foreach (DB::table('role_permissions')->where('organization_id', $templateId)->get() as $row) {
            if (! $names->has($row->permission_id)) {
                continue;
            }

            $map[$row->role][$names[$row->permission_id]] = (bool) $row->allowed;
        }

        return $map;
    }

    protected function assertFullyBackfilled(): void
    {
        $count = DB::table('role_permissions')->whereNull('organization_id')->count();

        if ($count > 0) {
            throw new RuntimeException(
                "organization_id is still NULL in role_permissions ({$count} rows). ".
                'Re-run the backfill before tightening the column.'
            );
        }
    }

    public function down(): void
    {
        Schema::table('role_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->change();
        });
    }
};
