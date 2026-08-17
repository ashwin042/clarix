<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every payment an organization has made toward its Clarix subscription.
 *
 * Kept as an append-only history rather than a "last paid" column on the
 * subscription, because the organization's own admin is entitled to see the
 * full record of what they have paid, not just the most recent line.
 *
 * The subscription foreign key restricts on delete for the same reason the
 * organization one does: a plan that has been paid against is not something to
 * remove quietly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('subscription_id')->constrained('organization_subscriptions');

            $table->decimal('amount', 10, 2);
            $table->timestamp('paid_at');
            $table->string('method')->nullable();            // bank_transfer, card, ...

            $table->timestamps();

            // The billing screens both read one organization's history newest
            // first.
            $table->index(['organization_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_subscription_payments');
    }
};
