<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the one platform-level account.
 *
 * The superadmin administers Clarix itself rather than any single agency, so
 * it carries no organization_id and no unit_id. It is created here rather than
 * in a seeder so that every environment the migrations touch ends up with
 * exactly one, without anyone having to remember to run a seeder.
 */
return new class extends Migration
{
    protected string $email = 'ashwinkhadka@superadmin.com';

    public function up(): void
    {
        // Idempotent: never clobber the password of an account that already
        // exists, since it is expected to be rotated straight after setup.
        if (DB::table('users')->where('email', $this->email)->exists()) {
            return;
        }

        DB::table('users')->insert([
            'name'            => 'Ashwin Khadka',
            'email'           => $this->email,
            // Placeholder credential, to be changed on first sign-in.
            'password'        => Hash::make('password'),
            'role'            => 'superadmin',
            'unit_id'         => null,
            'organization_id' => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('users')->where('email', $this->email)->delete();
    }
};
