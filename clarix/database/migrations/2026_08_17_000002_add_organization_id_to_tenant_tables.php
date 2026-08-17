<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hangs every tenant-scoped table off an organization.
 *
 * The column lands nullable here so that the backfill migration that follows
 * has something to write into; a later migration tightens it to NOT NULL once
 * the data is proven complete.
 *
 * Deliberately excluded, because they are platform-level rather than
 * agency-level:
 *   migrations, cache, cache_locks, jobs, job_batches, failed_jobs,
 *   sessions, password_reset_tokens  — framework infrastructure
 *   permissions                      — the catalogue of what permissions
 *                                      exist is identical for every agency;
 *                                      only the role -> permission mapping in
 *                                      role_permissions is agency-specific.
 */
return new class extends Migration
{
    /**
     * Every table that becomes tenant-scoped.
     *
     * @var list<string>
     */
    protected array $tables = [
        'units',
        'users',
        'tasks',
        'task_files',
        'task_notes',
        'task_assignments',
        'issues',
        'issue_replies',
        'payments',
        'notifications',
        'daily_chat_requests',
        'account_deletion_requests',
        'unit_storage_usage',
        'role_permissions',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                // Restrict on delete: an organization with live data should
                // never be removable by accident.
                $blueprint->foreignId('organization_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('organizations');
            });
        }

        // A role's permission set is per-agency, so the uniqueness that used
        // to be (role, permission) has to widen to include the owner.
        Schema::table('role_permissions', function (Blueprint $table) {
            $table->dropUnique('role_permissions_role_permission_id_unique');
            $table->unique(['organization_id', 'role', 'permission_id'], 'role_permissions_org_role_permission_unique');
        });
    }

    public function down(): void
    {
        Schema::table('role_permissions', function (Blueprint $table) {
            $table->dropUnique('role_permissions_org_role_permission_unique');
            $table->unique(['role', 'permission_id'], 'role_permissions_role_permission_id_unique');
        });

        foreach (array_reverse($this->tables) as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('organization_id');
            });
        }
    }
};
