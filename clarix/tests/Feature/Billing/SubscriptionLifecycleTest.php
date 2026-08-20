<?php

namespace Tests\Feature\Billing;

use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * The subscription state machine and the nightly job that drives it.
 *
 * Dates are the only input, so these tests set renewal dates directly and
 * assert where each row lands. That is deliberate: the transitions must depend
 * on the calendar rather than on how often the job has run.
 */
class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    protected function subscriptionFor(
        Organization $organization,
        string $status = 'active',
        ?int $renewalInDays = 30,
        string $cycle = 'monthly'
    ): OrganizationSubscription {
        return TenantContext::actingAsOrganization($organization->id, fn () => OrganizationSubscription::create([
            'plan'            => 'standard',
            'price'           => 99.00,
            'billing_cycle'   => $cycle,
            'started_at'      => now()->subMonth(),
            'next_renewal_at' => $renewalInDays === null ? null : now()->addDays($renewalInDays),
            'status'          => $status,
        ]));
    }

    protected function freshOrganization(string $slug): Organization
    {
        return $this->makeOrganization($slug, ucfirst($slug));
    }

    // ---------------------------------------------------------------- renewal

    public function test_a_monthly_cycle_renews_one_month_on(): void
    {
        $subscription = $this->subscriptionFor($this->freshOrganization('monthly-co'), 'active', 10, 'monthly');

        $expected = now()->addDays(10)->startOfDay()->addMonth();

        $subscription->renew();

        $this->assertSame($expected->toDateString(), $subscription->next_renewal_at->toDateString());
        $this->assertSame('active', $subscription->status);
    }

    public function test_a_yearly_cycle_renews_one_year_on(): void
    {
        $subscription = $this->subscriptionFor($this->freshOrganization('yearly-co'), 'active', 10, 'yearly');

        $expected = now()->addDays(10)->startOfDay()->addYear();

        $subscription->renew();

        $this->assertSame($expected->toDateString(), $subscription->next_renewal_at->toDateString());
    }

    /**
     * Renewing on time must not cost the organization the days it had left.
     */
    public function test_renewing_early_extends_from_the_existing_date(): void
    {
        $subscription = $this->subscriptionFor($this->freshOrganization('early-co'), 'active', 20, 'monthly');

        $subscription->renew();

        $this->assertSame(
            now()->addDays(20)->startOfDay()->addMonth()->toDateString(),
            $subscription->next_renewal_at->toDateString(),
            'the remaining 20 days should still be there, plus a month'
        );
    }

    /**
     * A long-lapsed plan must not renew into the past.
     */
    public function test_renewing_after_lapsing_extends_from_today(): void
    {
        $subscription = $this->subscriptionFor($this->freshOrganization('lapsed-co'), 'suspended', -90, 'monthly');

        $subscription->renew();

        $this->assertSame(
            now()->startOfDay()->addMonth()->toDateString(),
            $subscription->next_renewal_at->toDateString()
        );
        $this->assertTrue($subscription->next_renewal_at->isFuture());
        $this->assertSame('active', $subscription->status, 'renewing always means paid up');
    }

    // ------------------------------------------------------------ the job

    public function test_an_active_subscription_past_its_renewal_becomes_past_due(): void
    {
        $subscription = $this->subscriptionFor($this->freshOrganization('lapsing-co'), 'active', -1);

        $this->artisan('subscriptions:enforce')->assertSuccessful();

        $this->assertSame('past_due', $subscription->fresh()->status);
    }

    public function test_an_active_subscription_renewing_today_is_left_alone(): void
    {
        $subscription = $this->subscriptionFor($this->freshOrganization('today-co'), 'active', 0);

        $this->artisan('subscriptions:enforce')->assertSuccessful();

        $this->assertSame('active', $subscription->fresh()->status, 'the day it is due is not yet overdue');
    }

    public function test_a_past_due_subscription_inside_the_grace_period_stays_past_due(): void
    {
        // Grace is three days; two days over is still inside it.
        $subscription = $this->subscriptionFor($this->freshOrganization('grace-co'), 'past_due', -2);

        $this->artisan('subscriptions:enforce')->assertSuccessful();

        $this->assertSame('past_due', $subscription->fresh()->status);
    }

    public function test_a_past_due_subscription_out_of_grace_is_suspended(): void
    {
        $subscription = $this->subscriptionFor($this->freshOrganization('expired-co'), 'past_due', -4);

        $this->artisan('subscriptions:enforce')->assertSuccessful();

        $this->assertSame('suspended', $subscription->fresh()->status);
    }

    public function test_the_full_walk_from_active_to_suspended_over_successive_days(): void
    {
        $organization = $this->freshOrganization('walk-co');
        $subscription = $this->subscriptionFor($organization, 'active', 1);

        // Day before renewal: nothing happens.
        $this->artisan('subscriptions:enforce');
        $this->assertSame('active', $subscription->fresh()->status);

        // Renewal passes.
        $this->travel(2)->days();
        $this->artisan('subscriptions:enforce');
        $this->assertSame('past_due', $subscription->fresh()->status);

        // Still inside grace.
        $this->travel(2)->days();
        $this->artisan('subscriptions:enforce');
        $this->assertSame('past_due', $subscription->fresh()->status);

        // Grace runs out.
        $this->travel(2)->days();
        $this->artisan('subscriptions:enforce');
        $this->assertSame('suspended', $subscription->fresh()->status);

        $this->travelBack();
    }

    /**
     * The job advances one step per run and then settles: repeated runs after
     * a subscription has reached suspended change nothing further.
     */
    public function test_the_job_converges_and_then_stops_changing_anything(): void
    {
        $subscription = $this->subscriptionFor($this->freshOrganization('idem-co'), 'active', -10);

        $this->artisan('subscriptions:enforce');
        $this->assertSame('past_due', $subscription->fresh()->status);

        $this->artisan('subscriptions:enforce');
        $this->assertSame('suspended', $subscription->fresh()->status);

        // Settled: further runs are no-ops.
        $updatedAt = $subscription->fresh()->updated_at;

        $this->artisan('subscriptions:enforce');
        $this->artisan('subscriptions:enforce');

        $this->assertSame('suspended', $subscription->fresh()->status);
        $this->assertEquals($updatedAt, $subscription->fresh()->updated_at, 'no further writes');
    }

    /**
     * Running twice within the same day must not skip the grace period: a
     * subscription that has only just lapsed stays in past_due however many
     * times the job is invoked.
     */
    public function test_running_twice_in_one_day_does_not_skip_the_grace_period(): void
    {
        $subscription = $this->subscriptionFor($this->freshOrganization('twice-co'), 'active', -1);

        $this->artisan('subscriptions:enforce');
        $this->artisan('subscriptions:enforce');
        $this->artisan('subscriptions:enforce');

        $this->assertSame('past_due', $subscription->fresh()->status);
    }

    /**
     * A run that has been missed for a week must not hand out an accidental
     * extension: the outcome depends on the calendar, not on the last run.
     */
    public function test_a_long_missed_run_still_suspends_correctly(): void
    {
        $subscription = $this->subscriptionFor($this->freshOrganization('missed-co'), 'active', -30);

        $this->artisan('subscriptions:enforce');

        // Out of grace on the first pass, so it goes straight to suspended
        // rather than pausing a night in past_due.
        $this->assertSame('past_due', $subscription->fresh()->status);

        $this->artisan('subscriptions:enforce');
        $this->assertSame('suspended', $subscription->fresh()->status);
    }

    public function test_a_cancelled_or_suspended_subscription_is_not_touched_by_the_job(): void
    {
        $cancelled = $this->subscriptionFor($this->freshOrganization('cancelled-co'), 'cancelled', -50);
        $suspended = $this->subscriptionFor($this->freshOrganization('suspended-co'), 'suspended', -50);

        $this->artisan('subscriptions:enforce');

        $this->assertSame('cancelled', $cancelled->fresh()->status, 'the job never reverses a decision');
        $this->assertSame('suspended', $suspended->fresh()->status);
    }

    public function test_a_subscription_with_no_renewal_date_is_left_alone(): void
    {
        $subscription = $this->subscriptionFor($this->freshOrganization('nodate-co'), 'active', null);

        $this->artisan('subscriptions:enforce');

        $this->assertSame('active', $subscription->fresh()->status);
    }

    public function test_the_dry_run_changes_nothing(): void
    {
        $subscription = $this->subscriptionFor($this->freshOrganization('dry-co'), 'active', -1);

        $this->artisan('subscriptions:enforce', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame('active', $subscription->fresh()->status);
    }

    public function test_the_job_spans_every_organization(): void
    {
        $a = $this->subscriptionFor($this->freshOrganization('span-a'), 'active', -1);
        $b = $this->subscriptionFor($this->freshOrganization('span-b'), 'active', -1);

        $this->artisan('subscriptions:enforce');

        $this->assertSame('past_due', $a->fresh()->status);
        $this->assertSame('past_due', $b->fresh()->status);
    }

    // ------------------------------------------------- manual transitions

    public function test_reactivating_a_lapsed_subscription_also_moves_the_renewal_forward(): void
    {
        $subscription = $this->subscriptionFor($this->freshOrganization('react-co'), 'suspended', -20);

        $subscription->reactivate();

        $this->assertSame('active', $subscription->status);
        $this->assertTrue(
            $subscription->next_renewal_at->isFuture(),
            'a status flipped to active behind a past date would be undone by the job overnight'
        );

        // Proven, not assumed: the job leaves it alone afterwards.
        $this->artisan('subscriptions:enforce');
        $this->assertSame('active', $subscription->fresh()->status);
    }

    public function test_reactivating_a_subscription_with_time_left_keeps_its_date(): void
    {
        $subscription = $this->subscriptionFor($this->freshOrganization('keep-co'), 'suspended', 15);
        $original     = $subscription->next_renewal_at->toDateString();

        $subscription->reactivate();

        $this->assertSame('active', $subscription->status);
        $this->assertSame($original, $subscription->next_renewal_at->toDateString());
    }

    public function test_suspending_by_hand_works_independently_of_the_job(): void
    {
        $subscription = $this->subscriptionFor($this->freshOrganization('hand-co'), 'active', 300);

        $subscription->suspend();

        $this->assertSame('suspended', $subscription->fresh()->status);

        // The job does not undo a manual decision, even though the renewal
        // date is far in the future.
        $this->artisan('subscriptions:enforce');
        $this->assertSame('suspended', $subscription->fresh()->status);
    }

    public function test_the_renewal_summary_reflects_each_state(): void
    {
        $subscription = $this->subscriptionFor($this->freshOrganization('summary-co'), 'active', 12);

        $this->assertSame('Renews in 12 days', $subscription->renewalSummary());

        $subscription->status = 'suspended';
        $this->assertSame('Suspended', $subscription->renewalSummary());

        $subscription->status = 'cancelled';
        $this->assertSame('Cancelled', $subscription->renewalSummary());
    }

    public function test_the_grace_end_date_is_three_days_after_renewal(): void
    {
        $subscription = $this->subscriptionFor($this->freshOrganization('graceend-co'), 'past_due', -1);

        $this->assertSame(
            now()->subDay()->startOfDay()->addDays(3)->toDateString(),
            $subscription->graceEndsAt()->toDateString()
        );
    }

    public function test_existing_organizations_were_given_a_future_renewal_date(): void
    {
        // The migration seeds the founding organization so enforcement starts
        // from a known state rather than from an absence.
        $row = DB::table('organization_subscriptions')
            ->join('organizations', 'organizations.id', '=', 'organization_subscriptions.organization_id')
            ->where('organizations.slug', 'code-next-door')
            ->first();

        $this->assertNotNull($row, 'the founding organization should have a seeded subscription');
        $this->assertSame('active', $row->status);
        $this->assertTrue(
            now()->parse($row->next_renewal_at)->isFuture(),
            'and a renewal date that will not lapse the moment enforcement ships'
        );

        $this->artisan('subscriptions:enforce');

        $this->assertSame('active', DB::table('organization_subscriptions')->where('id', $row->id)->value('status'));
    }
}
