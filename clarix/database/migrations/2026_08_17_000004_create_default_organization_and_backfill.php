<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Creates the original agency and moves every existing row onto it.
 *
 * Clarix has only ever served one agency, so all pre-existing data belongs to
 * "Code Next Door" by definition. Rather than blanket-stamping every table,
 * each row is resolved through its natural parent (a task file belongs to the
 * organization of its task, a task to that of its unit, and so on). That costs
 * nothing while there is a single organization, but it exercises the real
 * ownership path and leaves any row whose parent has gone missing visibly
 * unresolved instead of silently correct.
 *
 * Rows that no derivation could reach are then swept onto the default
 * organization, since orphaned agency data still belongs to the only agency
 * that has ever existed. The sweep counts are logged so the drift is a matter
 * of record rather than a guess.
 */
return new class extends Migration
{
    /**
     * Tables whose owner is derived from a parent row, in dependency order:
     * table => [parent table, local key on the child, key on the parent].
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    protected array $derived = [
        'tasks'                     => ['units', 'unit_id', 'id'],
        'payments'                  => ['units', 'unit_id', 'id'],
        'unit_storage_usage'        => ['units', 'unit_id', 'id'],
        'task_files'                => ['tasks', 'task_id', 'id'],
        'task_notes'                => ['tasks', 'task_id', 'id'],
        'task_assignments'          => ['tasks', 'task_id', 'id'],
        'issues'                    => ['users', 'created_by', 'id'],
        'issue_replies'             => ['issues', 'issue_id', 'id'],
        'daily_chat_requests'       => ['users', 'user_id', 'id'],
        'account_deletion_requests' => ['users', 'user_id', 'id'],
    ];

    /**
     * Every table carrying organization_id, used for the final sweep.
     *
     * @var list<string>
     */
    protected array $all = [
        'units', 'users', 'tasks', 'task_files', 'task_notes', 'task_assignments',
        'issues', 'issue_replies', 'payments', 'notifications',
        'daily_chat_requests', 'account_deletion_requests', 'unit_storage_usage',
        'role_permissions',
    ];

    public function up(): void
    {
        $organizationId = $this->defaultOrganizationId();

        // Roots: units own themselves, and every existing human belongs to the
        // original agency. A superadmin has no organization, so it is excluded
        // here as well as by the sweep below.
        DB::table('units')->whereNull('organization_id')->update(['organization_id' => $organizationId]);

        DB::table('users')
            ->whereNull('organization_id')
            ->where('role', '!=', 'superadmin')
            ->update(['organization_id' => $organizationId]);

        // A role's permission map is agency configuration; it has no parent
        // row to derive from.
        DB::table('role_permissions')->whereNull('organization_id')->update(['organization_id' => $organizationId]);

        foreach ($this->derived as $table => [$parent, $foreignKey, $ownerKey]) {
            $this->deriveFromParent($table, $parent, $foreignKey, $ownerKey);
        }

        $this->backfillNotifications();

        $this->sweepRemaining($organizationId);
    }

    /**
     * Find or create the organization that owns all pre-existing data.
     *
     * Written idempotently so a re-run after a partial failure resolves to the
     * same row rather than creating a second agency.
     */
    protected function defaultOrganizationId(): int
    {
        $existing = DB::table('organizations')->where('slug', 'code-next-door')->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) DB::table('organizations')->insertGetId([
            'name'              => 'Code Next Door',
            // Placeholders — the real details get filled in from the
            // superadmin portal once it exists.
            'contact_number'    => '0000000000',
            'email'             => 'placeholder@codenextdoor.example',
            'address'           => 'Address to be updated',
            // The founding agency, so the top tier.
            'subscription_type' => 'pro',
            'slug'              => 'code-next-door',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    /**
     * Copy the owner down from a parent row.
     *
     * Uses a correlated subquery rather than an UPDATE ... JOIN so the same
     * statement runs on MySQL and on the sqlite database the tests build.
     * Reading one table while updating a different one is legal on both.
     */
    protected function deriveFromParent(string $table, string $parent, string $foreignKey, string $ownerKey): void
    {
        DB::statement(
            "UPDATE `{$table}`
                SET `organization_id` = (
                    SELECT `p`.`organization_id` FROM `{$parent}` AS `p`
                     WHERE `p`.`{$ownerKey}` = `{$table}`.`{$foreignKey}`
                )
              WHERE `organization_id` IS NULL"
        );
    }

    /**
     * Notifications hang off their notifiable, which is always a user today.
     *
     * The polymorphic column is matched explicitly so that adding a second
     * notifiable type later cannot quietly mis-assign rows.
     */
    protected function backfillNotifications(): void
    {
        DB::statement(
            "UPDATE `notifications`
                SET `organization_id` = (
                    SELECT `u`.`organization_id` FROM `users` AS `u`
                     WHERE `u`.`id` = `notifications`.`notifiable_id`
                )
              WHERE `organization_id` IS NULL
                AND `notifiable_type` = ?",
            [\App\Models\User::class]
        );
    }

    /**
     * Catch anything the derivations could not reach.
     *
     * A row lands here when its parent no longer exists — a notification for a
     * deleted user, a task whose unit was removed. It is still this agency's
     * data, so it is claimed rather than left unowned, and the count is logged
     * because a non-zero sweep is worth knowing about.
     */
    protected function sweepRemaining(int $organizationId): void
    {
        foreach ($this->all as $table) {
            $query = DB::table($table)->whereNull('organization_id');

            // The platform superadmin is intentionally organization-less.
            if ($table === 'users') {
                $query->where('role', '!=', 'superadmin');
            }

            $count = (clone $query)->count();

            if ($count === 0) {
                continue;
            }

            $query->update(['organization_id' => $organizationId]);

            Log::info('Multi-tenancy backfill swept rows with no derivable owner.', [
                'table'           => $table,
                'rows'            => $count,
                'organization_id' => $organizationId,
            ]);
        }
    }

    public function down(): void
    {
        foreach ($this->all as $table) {
            DB::table($table)->update(['organization_id' => null]);
        }

        DB::table('organizations')->where('slug', 'code-next-door')->delete();
    }
};
