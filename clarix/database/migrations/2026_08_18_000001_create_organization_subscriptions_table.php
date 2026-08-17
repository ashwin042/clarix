<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What an organization pays Clarix, as opposed to the payments table, which is
 * what an agency's own clients pay the agency. The two are unrelated money and
 * are deliberately kept in separate tables with different visibility.
 *
 * organization_id is NOT NULL from the outset — there is no historical data to
 * backfill here, so the column starts closed rather than being tightened later
 * the way the phase 1 tables had to be.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations');

            $table->string('plan');                          // base | standard | pro
            $table->decimal('price', 10, 2);
            $table->string('billing_cycle');                 // monthly | yearly
            $table->date('started_at');
            $table->date('next_renewal_at')->nullable();     // null once cancelled
            $table->string('status')->default('active');     // active | past_due | cancelled

            $table->timestamps();

            // The renewal sweep reads "who is due", so it is worth an index.
            $table->index(['status', 'next_renewal_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_subscriptions');
    }
};
