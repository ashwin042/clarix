<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tenant root.
 *
 * Everything that is agency-specific hangs off an organization. Clarix ran as
 * a single agency until now, so the first row this table gets is the original
 * agency itself and every existing record is backfilled onto it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_number')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();

            // base | standard | pro. Kept as a plain string rather than an
            // enum so adding a tier later is a data change, not a migration.
            $table->string('subscription_type')->default('base');

            // URL-safe handle. Reserved for org-scoped login routes
            // (/o/{slug}/login) in a later phase.
            $table->string('slug')->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
