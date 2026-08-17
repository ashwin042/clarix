<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The kanban board needs a review state between "in progress" and
     * "completed". Reviews were previously only tracked per writer on
     * task_assignments (ready_for_review), which the board cannot use because
     * its columns are the task's own status. So the task status enum gains
     * a matching option:
     *   pending, in_progress, sent_for_review, completed, cancelled
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE tasks MODIFY COLUMN status ENUM('pending', 'in_progress', 'sent_for_review', 'completed', 'cancelled') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // 'sent_for_review' does not exist in the old enum; fold it back to the
        // state it is reached from.
        DB::table('tasks')->where('status', 'sent_for_review')->update(['status' => 'in_progress']);

        DB::statement("ALTER TABLE tasks MODIFY COLUMN status ENUM('pending', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending'");
    }
};
