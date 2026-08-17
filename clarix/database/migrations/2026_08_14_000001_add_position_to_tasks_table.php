<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ordering of kanban cards within a status column. Position is only
     * meaningful relative to the other tasks sharing a status, so the backfill
     * numbers each status group independently, oldest task first.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('tasks', 'position')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->integer('position')->nullable()->default(0)->after('status');
            });
        }

        $statuses = DB::table('tasks')->distinct()->pluck('status');

        foreach ($statuses as $status) {
            $ids = DB::table('tasks')
                ->where('status', $status)
                ->orderBy('created_at')
                ->orderBy('id')
                ->pluck('id');

            foreach ($ids as $index => $id) {
                DB::table('tasks')->where('id', $id)->update(['position' => $index]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tasks', 'position')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropColumn('position');
            });
        }
    }
};
