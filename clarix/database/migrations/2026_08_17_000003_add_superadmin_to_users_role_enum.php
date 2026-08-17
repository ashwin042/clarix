<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the platform-level role that sits above admin.
 *
 * A superadmin belongs to no organization and administers the platform
 * itself; admin remains the top role *within* an agency. Nothing in this
 * phase grants the role any behaviour — that arrives with the superadmin
 * portal.
 */
return new class extends Migration
{
    public function up(): void
    {
        // sqlite (used by the test suite) has no native enum; the column is
        // a plain varchar there and already accepts the new value.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('superadmin', 'admin', 'pm', 'writer') NOT NULL DEFAULT 'writer'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // The value disappears from the enum, so any holder has to go first.
        DB::table('users')->where('role', 'superadmin')->delete();

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'pm', 'writer') NOT NULL DEFAULT 'writer'");
    }
};
