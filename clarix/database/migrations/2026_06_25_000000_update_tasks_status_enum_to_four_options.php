<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Status options change from
     *   pending, in_progress, submitted, verified, completed
     * to
     *   pending, in_progress, completed, cancelled
     *
     * Retired statuses (submitted, verified) are remapped to completed.
     */
    public function up(): void
    {
        // Remap retired statuses to completed before tightening the enum so no
        // existing row violates the new constraint. Preserve/derive completed_at.
        DB::table('tasks')
            ->whereIn('status', ['submitted', 'verified'])
            ->update([
                'status'       => 'completed',
                'completed_at' => DB::raw('COALESCE(completed_at, updated_at)'),
            ]);

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE tasks MODIFY COLUMN status ENUM('pending', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // 'cancelled' does not exist in the old enum; fold it back to pending.
        DB::table('tasks')->where('status', 'cancelled')->update(['status' => 'pending']);

        DB::statement("ALTER TABLE tasks MODIFY COLUMN status ENUM('pending', 'in_progress', 'submitted', 'verified', 'completed') NOT NULL DEFAULT 'pending'");
    }
};
