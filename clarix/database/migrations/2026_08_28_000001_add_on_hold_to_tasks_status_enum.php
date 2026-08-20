<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds 'on_hold' to the tasks.status enum.
 *
 * The one part of this feature the test suite cannot speak to. The suite runs
 * on sqlite, which has no enum type and stores whatever string it is handed,
 * so every test asserting a task can be put on hold passes with or without
 * this migration. On MySQL the same write is truncated or rejected outright.
 * A green run is not evidence here; a clone is.
 *
 * Written as a raw ALTER rather than through the schema builder because
 * doctrine/dbal does not model enums, and Laravel's change() would rewrite the
 * column as a plain string — silently dropping the constraint on every other
 * value at the same time.
 *
 * The new value is placed after 'pending' to match Task::STATUSES and the
 * board's column order. Enum ordinals shift as a result, which is only visible
 * to an ORDER BY on the column itself; nothing in the codebase sorts by
 * status, and the ordering that does matter — the board's — is the key order
 * of BOARD_COLUMNS.
 */
return new class extends Migration
{
    private const WITH_ON_HOLD = "'pending','on_hold','in_progress','sent_for_review','completed','cancelled'";

    private const WITHOUT_ON_HOLD = "'pending','in_progress','sent_for_review','completed','cancelled'";

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            'ALTER TABLE tasks MODIFY COLUMN status ENUM('
            . self::WITH_ON_HOLD
            . ") NOT NULL DEFAULT 'pending'"
        );
    }

    /**
     * Held tasks are returned to pending before the value disappears.
     *
     * Without this the ALTER would truncate them to an empty string — MySQL
     * does not refuse rows holding a value being removed, it silently empties
     * them, which is worse than losing the column would be. Pending is the
     * honest destination: a task that was waiting is waiting still.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('tasks')->where('status', 'on_hold')->update(['status' => 'pending']);

        DB::statement(
            'ALTER TABLE tasks MODIFY COLUMN status ENUM('
            . self::WITHOUT_ON_HOLD
            . ") NOT NULL DEFAULT 'pending'"
        );
    }
};
