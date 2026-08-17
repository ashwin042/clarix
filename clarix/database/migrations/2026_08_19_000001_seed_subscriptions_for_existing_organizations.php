<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Gives every organization that predates subscription enforcement a live plan.
 *
 * Without this, the nightly job would find Code Next Door with no subscription
 * at all. The middleware treats "no subscription" as "not in arrears" and lets
 * them through, so nobody would actually be locked out — but the organization
 * would sit outside billing indefinitely and silently, which is its own kind
 * of wrong. Seeding a real row means enforcement starts from a known state
 * rather than from an absence.
 *
 * The renewal date is set a full cycle ahead of today rather than of the
 * organization's creation date, so nothing lands already lapsed the moment
 * this runs.
 *
 * price is 0.00 on purpose: what these organizations actually pay is not
 * recorded anywhere in the database, and inventing a number would be worse
 * than leaving an obvious placeholder for a superadmin to correct.
 */
return new class extends Migration
{
    public function up(): void
    {
        $organizations = DB::table('organizations')
            ->whereNotIn('id', function ($query) {
                $query->select('organization_id')->from('organization_subscriptions');
            })
            ->get();

        foreach ($organizations as $organization) {
            $plan = in_array($organization->subscription_type, ['base', 'standard', 'pro'], true)
                ? $organization->subscription_type
                : 'base';

            DB::table('organization_subscriptions')->insert([
                'organization_id' => $organization->id,
                'plan'            => $plan,
                'price'           => 0.00,
                'billing_cycle'   => 'monthly',
                'started_at'      => now()->toDateString(),
                'next_renewal_at' => now()->addMonth()->toDateString(),
                'status'          => 'active',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            Log::info('Seeded a starting subscription for an existing organization.', [
                'organization_id'   => $organization->id,
                'organization_name' => $organization->name,
                'plan'              => $plan,
                'next_renewal_at'   => now()->addMonth()->toDateString(),
                'note'              => 'price is a placeholder and needs setting from the superadmin portal',
            ]);
        }
    }

    public function down(): void
    {
        // Only the placeholder rows this migration is responsible for: a
        // subscription that has been given a real price, or paid against, was
        // not created here and is not ours to remove.
        DB::table('organization_subscriptions')
            ->where('price', 0.00)
            ->whereNotIn('id', function ($query) {
                $query->select('subscription_id')->from('organization_subscription_payments');
            })
            ->delete();
    }
};
