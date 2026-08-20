<?php

namespace Tests\Feature\Billing;

use App\Livewire\Finance\Subscription as SubscriptionScreen;
use App\Livewire\Superadmin\OrganizationDetail;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\OrganizationSubscriptionPayment;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * Subscription billing is visible in two directions and nowhere else: the
 * platform sees every agency's, and each agency sees its own in full. The
 * second half of that is the one worth pinning down — an agency's billing is
 * as private from its neighbours as its work is.
 */
class SubscriptionVisibilityTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $a;

    /** @var array<string, mixed> */
    protected array $b;

    protected OrganizationSubscription $subA;

    protected OrganizationSubscription $subB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = $this->populate($this->makeOrganization('org-a', 'Agency A'), 'A');
        $this->b = $this->populate($this->makeOrganization('org-b', 'Agency B'), 'B');

        // Agency A: standard, monthly, three payments.
        $this->subA = $this->subscribe($this->a['organization'], 'standard', 99.00, 'monthly', 12, [3, 2, 1]);

        // Agency B: pro, yearly, one payment, and overdue.
        $this->subB = $this->subscribe($this->b['organization'], 'pro', 999.00, 'yearly', -3, [11]);
    }

    /**
     * @param  list<int>  $paidMonthsAgo
     */
    protected function subscribe(
        Organization $organization,
        string $plan,
        float $price,
        string $cycle,
        int $renewalInDays,
        array $paidMonthsAgo
    ): OrganizationSubscription {
        return TenantContext::actingAsOrganization($organization->id, function () use ($plan, $price, $cycle, $renewalInDays, $paidMonthsAgo) {
            $subscription = OrganizationSubscription::create([
                'plan'            => $plan,
                'price'           => $price,
                'billing_cycle'   => $cycle,
                'started_at'      => now()->subYear(),
                'next_renewal_at' => now()->addDays($renewalInDays),
                'status'          => $renewalInDays < 0 ? 'past_due' : 'active',
            ]);

            foreach ($paidMonthsAgo as $monthsAgo) {
                OrganizationSubscriptionPayment::create([
                    'subscription_id' => $subscription->id,
                    'amount'          => $price,
                    'paid_at'         => now()->subMonths($monthsAgo),
                    'method'          => 'bank_transfer',
                ]);
            }

            return $subscription;
        });
    }

    protected function superadmin(): User
    {
        return User::withoutGlobalScopes()->where('role', 'superadmin')->firstOrFail();
    }

    public function test_the_fixtures_wrote_both_agencies_billing(): void
    {
        // Scoped to the two agencies: the founding organization carries a
        // seeded subscription of its own from the enforcement migration.
        $this->assertSame(2, DB::table('organization_subscriptions')
            ->whereIn('organization_id', [$this->a['organization']->id, $this->b['organization']->id])
            ->count());
        $this->assertSame(4, DB::table('organization_subscription_payments')->count());
        $this->assertSame(0, DB::table('organization_subscriptions')->whereNull('organization_id')->count());
        $this->assertSame(0, DB::table('organization_subscription_payments')->whereNull('organization_id')->count());
    }

    public function test_billing_records_are_owned_by_the_right_organization(): void
    {
        $this->assertSame($this->a['organization']->id, $this->subA->organization_id);
        $this->assertSame($this->b['organization']->id, $this->subB->organization_id);

        // Payments inherit the owner from the subscription they are against.
        $this->assertSame(
            $this->b['organization']->id,
            (int) DB::table('organization_subscription_payments')
                ->where('subscription_id', $this->subB->id)
                ->value('organization_id')
        );
    }

    public function test_a_superadmin_sees_every_organizations_billing(): void
    {
        $this->actingAs($this->superadmin());

        // Three: both agencies plus the founding organization.
        $this->assertSame(3, OrganizationSubscription::count());
        $this->assertSame(4, OrganizationSubscriptionPayment::count());
        $this->assertNotNull(OrganizationSubscription::find($this->subA->id));
        $this->assertNotNull(OrganizationSubscription::find($this->subB->id));
    }

    public function test_an_admin_sees_only_their_own_organizations_subscription(): void
    {
        $this->actingAs($this->a['admin']);

        $this->assertSame(1, OrganizationSubscription::count());
        $this->assertNotNull(OrganizationSubscription::find($this->subA->id));
        $this->assertNull(OrganizationSubscription::find($this->subB->id), 'another agency\'s plan must be invisible');
        $this->assertSame(0, OrganizationSubscription::where('id', $this->subB->id)->count());
    }

    public function test_an_admin_sees_their_full_payment_history_and_no_one_elses(): void
    {
        $this->actingAs($this->a['admin']);

        $payments = OrganizationSubscriptionPayment::all();

        $this->assertCount(3, $payments, 'the complete history, not a recent slice');
        $this->assertSame(297.0, (float) $payments->sum('amount'));

        foreach ($payments as $payment) {
            $this->assertSame($this->a['organization']->id, (int) $payment->organization_id);
        }

        $this->assertSame(0, OrganizationSubscriptionPayment::where('subscription_id', $this->subB->id)->count());
    }

    public function test_the_other_admin_sees_their_own_side_of_it(): void
    {
        $this->actingAs($this->b['admin']);

        $this->assertSame(1, OrganizationSubscription::count());
        $this->assertSame('pro', OrganizationSubscription::first()->plan);
        $this->assertCount(1, OrganizationSubscriptionPayment::all());
    }

    public function test_an_admin_cannot_update_or_delete_another_organizations_billing(): void
    {
        $this->actingAs($this->a['admin']);

        OrganizationSubscription::where('id', $this->subB->id)->update(['status' => 'cancelled']);
        OrganizationSubscription::where('id', $this->subB->id)->delete();

        $row = DB::table('organization_subscriptions')->where('id', $this->subB->id)->first();

        $this->assertNotNull($row, 'agency B\'s subscription must still exist');
        $this->assertSame('past_due', $row->status, 'and be unchanged');
    }

    public function test_the_org_admin_billing_screen_shows_their_own_plan_and_history(): void
    {
        $this->actingAs($this->a['admin']);

        Livewire::test(SubscriptionScreen::class)
            ->assertViewHas('subscription', fn ($s) => $s->id === $this->subA->id)
            ->assertViewHas('payments', fn ($p) => $p->count() === 3)
            ->assertViewHas('totalPaid', fn ($t) => (float) $t === 297.0)
            ->assertSee('standard')
            ->assertSee('Renews in 12 days')
            ->assertDontSee('999.00');
    }

    public function test_the_billing_screen_is_admin_only(): void
    {
        foreach ([$this->a['pm'], $this->a['writer']] as $user) {
            $this->actingAs($user);
            $this->get(route('admin.subscription'))->assertForbidden();
        }

        $this->actingAs($this->a['admin']);
        $this->get(route('admin.subscription'))->assertSuccessful();
    }

    public function test_the_superadmin_detail_page_shows_that_organizations_billing_only(): void
    {
        $this->actingAs($this->superadmin());

        Livewire::test(OrganizationDetail::class, ['organization' => $this->a['organization']])
            ->assertViewHas('subscription', fn ($s) => $s->id === $this->subA->id)
            ->assertViewHas('payments', fn ($p) => $p->count() === 3)
            ->assertViewHas('userCount', 3)
            ->assertSee('Standard')
            ->assertDontSee('999.00');

        Livewire::test(OrganizationDetail::class, ['organization' => $this->b['organization']])
            ->assertViewHas('subscription', fn ($s) => $s->id === $this->subB->id)
            ->assertViewHas('payments', fn ($p) => $p->count() === 1);
    }

    public function test_the_renewal_summary_reads_correctly(): void
    {
        $this->assertSame('Renews in 12 days', $this->subA->renewalSummary());
        $this->assertSame('Overdue by 3 days', $this->subB->renewalSummary());

        $this->subA->next_renewal_at = now();
        $this->assertSame('Renews today', $this->subA->renewalSummary());

        $this->subA->status = 'cancelled';
        $this->assertSame('Cancelled', $this->subA->renewalSummary());
    }
}
