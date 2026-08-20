<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Two more roles inside an agency, either side of the PM.
 *
 * Supervisor runs the work across every unit without being handed the agency
 * itself; HR runs attendance, leave and pay and touches none of the work.
 * Both sit below admin, which stays the top role within an agency, and above
 * nothing — neither may destroy anything, which is why no delete permission
 * appears anywhere in this change.
 *
 * The ordering in the enum follows seniority rather than the order the roles
 * were added, so the column reads as the hierarchy it describes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // sqlite (used by the test suite) has no native enum; the column is
        // a plain varchar there and already accepts the new values.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('superadmin', 'admin', 'supervisor', 'pm', 'hr', 'writer') NOT NULL DEFAULT 'writer'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // The values disappear from the enum, so any holder has to go first.
        DB::table('users')->whereIn('role', ['supervisor', 'hr'])->delete();

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('superadmin', 'admin', 'pm', 'writer') NOT NULL DEFAULT 'writer'");
    }
};
