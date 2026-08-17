<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tightens organization_id to NOT NULL now that every write path supplies it.
 *
 * This was written during phase 1 and deliberately kept out of the migration
 * folder until phase 2, because nothing populated organization_id on insert
 * back then and the first task created would have hit the constraint. The
 * BelongsToOrganization trait now stamps every create, and StorageUsageService
 * resolves the owner for the rollups it writes from the console, so the column
 * can be closed.
 *
 * Two tables are deliberately left nullable:
 *
 *   users             a platform superadmin belongs to no organization, so
 *                     NULL is the correct and permanent value there.
 *   role_permissions  AuthorizationPanel and PermissionSeeder still write
 *                     org-less rows; tightening this before they are made
 *                     org-aware would break the authorization screen.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    protected array $tables = [
        'units',
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
    ];

    public function up(): void
    {
        $this->assertFullyBackfilled();

        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('organization_id')->nullable(false)->change();
            });
        }
    }

    /**
     * Refuse to tighten a column that still holds NULLs.
     *
     * Without this the migration would fail halfway through on MySQL and leave
     * some tables tightened and others not; failing up front keeps the schema
     * consistent and names the tables that still need attention.
     */
    protected function assertFullyBackfilled(): void
    {
        $unresolved = [];

        foreach ($this->tables as $table) {
            $count = DB::table($table)->whereNull('organization_id')->count();

            if ($count > 0) {
                $unresolved[] = "{$table} ({$count} rows)";
            }
        }

        if ($unresolved !== []) {
            throw new RuntimeException(
                'organization_id is still NULL in: '.implode(', ', $unresolved).
                '. Re-run the backfill migration before tightening the column.'
            );
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('organization_id')->nullable()->change();
            });
        }
    }
};
