<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        DB::statement("ALTER TABLE task_assignments MODIFY COLUMN status ENUM('pending', 'in_progress', 'ready_for_review', 'completed') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        DB::statement("ALTER TABLE task_assignments MODIFY COLUMN status ENUM('pending', 'in_progress', 'ready_for_review') NOT NULL DEFAULT 'pending'");
    }
};
