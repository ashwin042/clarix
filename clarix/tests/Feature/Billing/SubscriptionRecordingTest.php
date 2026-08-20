<?php

namespace Tests\Feature\Billing;

use App\Livewire\Finance\Subscription as SubscriptionScreen;
use App\Livewire\Superadmin\OrganizationDetail;
use App\Models\OrganizationSubscription;
use App\Models\OrganizationSubscriptionPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * Recording billing from the superadmin portal.
 *
 * The thing worth pinning down is the same one that mattered when the portal
 * learned to create an organization's first admin: a superadmin belongs to no
 * organization, so the target has to be named rather than inferred, or the
 * record lands unowned — or, here, fails outright against a NOT NULL column.
 */
class SubscriptionRecordingTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $a;

    /** @var array<string, mixed> */
    protected array $b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = $this->populate($this->makeOrganization('org-a', 'Agency A'), 'A');
        $this->b = $this->populate($this->makeOrganization('org-b', 'Agency B'), 'B');
    }

    protected function superadmin(): User
    {
        return User::withoutGlobalScopes()->where('role', 'superadmin')->firstOrFail();
    }

    protected function recordSubscriptionFor(array $set, string $plan = 'standard', float $price = 99.00): void
    {
        Livewire::test(OrganizationDetail::class, ['organization' => $set['organization']])
            ->call('openSubscription')
            ->set('plan', $plan)
            ->set('price', (string) $price)
            ->set('billing_cycle', 'monthly')
            // No renewal date: it is derived from started_at and the cycle.
            ->set('started_at', now()->subDays(18)->format('Y-m-d'))
            ->set('status', 'active')
            ->call('saveSubscription')
            ->assertHasNoErrors();
    }

    public function test_a_superadmin_creates_a_subscription_against_the_intended_organization(): void
    {
        $this->actingAs($this->superadmin());

        $this->recordSubscriptionFor($this->a);

        $row = DB::table('organization_subscriptions')
            ->where('organization_id', $this->a['organization']->id)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(
            $this->a['organization']->id,
            (int) $row->organization_id,
            'the subscription must belong to the organization being viewed'
        );
        $this->assertNotNull($row->organization_id, 'and never be left unowned');
        $this->assertSame('standard', $row->plan);
        $this->assertSame('monthly', $row->billing_cycle);
        $this->assertSame('active', $row->status);

        // The renewal date is never typed in: one cycle on from started_at.
        $this->assertSame(
            now()->subDays(18)->startOfDay()->addMonth()->toDateString(),
            now()->parse($row->next_renewal_at)->toDateString()
        );
    }

    public function test_a_yearly_cycle_derives_a_renewal_a_year_out(): void
    {
        $this->actingAs($this->superadmin());

        Livewire::test(OrganizationDetail::class, ['organization' => $this->a['organization']])
            ->call('openSubscription')
            ->set('plan', 'pro')
            ->set('price', '999')
            ->set('billing_cycle', 'yearly')
            ->set('started_at', now()->format('Y-m-d'))
            ->set('status', 'active')
            ->call('saveSubscription')
            ->assertHasNoErrors();

        $row = DB::table('organization_subscriptions')
            ->where('organization_id', $this->a['organization']->id)
            ->first();

        $this->assertSame(
            now()->startOfDay()->addYear()->toDateString(),
            now()->parse($row->next_renewal_at)->toDateString()
        );
    }

    public function test_two_organizations_get_their_own_subscriptions(): void
    {
        $this->actingAs($this->superadmin());

        $this->recordSubscriptionFor($this->a, 'standard', 99.00);
        $this->recordSubscriptionFor($this->b, 'pro', 999.00);

        $this->assertSame(2, DB::table('organization_subscriptions')
            ->whereIn('organization_id', [$this->a['organization']->id, $this->b['organization']->id])
            ->count());

        $forA = DB::table('organization_subscriptions')->where('organization_id', $this->a['organization']->id)->first();
        $forB = DB::table('organization_subscriptions')->where('organization_id', $this->b['organization']->id)->first();

        $this->assertSame('standard', $forA->plan);
        $this->assertSame('pro', $forB->plan);
    }

    public function test_saving_again_updates_the_existing_subscription_rather_than_adding_one(): void
    {
        $this->actingAs($this->superadmin());

        $this->recordSubscriptionFor($this->a, 'standard', 99.00);

        Livewire::test(OrganizationDetail::class, ['organization' => $this->a['organization']])
            ->call('openSubscription')
            ->assertSet('plan', 'standard')
            ->set('plan', 'pro')
            ->set('price', '499.00')
            ->set('status', 'past_due')
            ->call('saveSubscription')
            ->assertHasNoErrors();

        $this->assertSame(
            1,
            DB::table('organization_subscriptions')
                ->where('organization_id', $this->a['organization']->id)
                ->count(),
            'updated in place rather than stacking a second row'
        );

        $row = DB::table('organization_subscriptions')
            ->where('organization_id', $this->a['organization']->id)
            ->first();
        $this->assertSame('pro', $row->plan);
        $this->assertSame('past_due', $row->status);
        $this->assertSame($this->a['organization']->id, (int) $row->organization_id);
    }

    public function test_a_payment_is_recorded_against_the_intended_organization(): void
    {
        $this->actingAs($this->superadmin());

        $this->recordSubscriptionFor($this->a);

        Livewire::test(OrganizationDetail::class, ['organization' => $this->a['organization']])
            ->call('openPayment')
            ->set('amount', '99.00')
            ->set('paid_at', now()->format('Y-m-d\TH:i'))
            ->set('method', 'bank_transfer')
            ->call('savePayment')
            ->assertHasNoErrors();

        $row = DB::table('organization_subscription_payments')->first();

        $this->assertNotNull($row);
        $this->assertSame($this->a['organization']->id, (int) $row->organization_id);
        // Compared numerically: sqlite and MySQL render decimals differently
        // ('99' against '99.00'), and the value is what matters here.
        $this->assertSame(99.0, (float) $row->amount);
        $this->assertSame('bank_transfer', $row->method);

        $subscriptionId = DB::table('organization_subscriptions')
            ->where('organization_id', $this->a['organization']->id)
            ->value('id');
        $this->assertSame((int) $subscriptionId, (int) $row->subscription_id);
    }

    public function test_a_payment_cannot_be_logged_without_a_subscription(): void
    {
        $this->actingAs($this->superadmin());

        Livewire::test(OrganizationDetail::class, ['organization' => $this->a['organization']])
            ->call('openPayment')
            ->set('amount', '50.00')
            ->set('paid_at', now()->format('Y-m-d\TH:i'))
            ->call('savePayment')
            ->assertDispatched('notify', type: 'error');

        $this->assertSame(0, DB::table('organization_subscription_payments')->count());
    }

    public function test_recording_billing_does_not_confine_the_superadmin_afterwards(): void
    {
        $this->actingAs($this->superadmin());

        $this->recordSubscriptionFor($this->a);
        $this->recordSubscriptionFor($this->b, 'pro', 999.00);

        // Still unconfined: both agencies' billing is visible again.
        $this->assertNull(\App\Services\TenantContext::organizationId());
        $this->assertSame(3, OrganizationSubscription::count(), 'both agencies plus the founding organization');
    }

    /**
     * The end of the loop the user actually cares about: the superadmin logs a
     * payment, and the agency's own admin sees it without anything else
     * happening in between.
     */
    public function test_the_org_admin_sees_a_payment_immediately_after_the_superadmin_records_it(): void
    {
        $this->actingAs($this->superadmin());
        $this->recordSubscriptionFor($this->a, 'standard', 99.00);

        // Nothing yet.
        $this->actingAs($this->a['admin']);
        Livewire::test(SubscriptionScreen::class)
            ->assertViewHas('payments', fn ($p) => $p->count() === 0)
            ->assertViewHas('totalPaid', fn ($t) => (float) $t === 0.0);

        // Superadmin records two payments.
        $this->actingAs($this->superadmin());
        foreach ([['99.00', '2 months'], ['99.00', '1 month']] as [$amount, $ago]) {
            Livewire::test(OrganizationDetail::class, ['organization' => $this->a['organization']])
                ->call('openPayment')
                ->set('amount', $amount)
                ->set('paid_at', now()->sub($ago)->format('Y-m-d\TH:i'))
                ->set('method', 'bank_transfer')
                ->call('savePayment')
                ->assertHasNoErrors();
        }

        // The agency's admin sees them straight away, in full.
        $this->actingAs($this->a['admin']);
        Livewire::test(SubscriptionScreen::class)
            ->assertViewHas('payments', fn ($p) => $p->count() === 2)
            ->assertViewHas('totalPaid', fn ($t) => (float) $t === 198.0)
            ->assertViewHas('subscription', fn ($s) => $s->plan === 'standard')
            // The renewal date is derived from started_at plus one cycle, so
            // it is computed here rather than hard-coded to a day count that
            // would drift with the length of the month.
            ->assertViewHas('subscription', fn ($s) => $s->next_renewal_at->toDateString()
                === now()->subDays(18)->startOfDay()->addMonth()->toDateString())
            ->assertSee('198.00');
    }

    public function test_the_other_agency_sees_none_of_it(): void
    {
        $this->actingAs($this->superadmin());
        $this->recordSubscriptionFor($this->a, 'standard', 99.00);

        Livewire::test(OrganizationDetail::class, ['organization' => $this->a['organization']])
            ->call('openPayment')
            ->set('amount', '99.00')
            ->set('paid_at', now()->format('Y-m-d\TH:i'))
            ->call('savePayment');

        $this->actingAs($this->b['admin']);

        $this->assertSame(0, OrganizationSubscription::count());
        $this->assertSame(0, OrganizationSubscriptionPayment::count());

        Livewire::test(SubscriptionScreen::class)
            ->assertViewHas('subscription', null)
            ->assertViewHas('payments', fn ($p) => $p->count() === 0)
            ->assertSee('No subscription on record');
    }

    public function test_the_recording_actions_are_closed_to_an_organization_admin(): void
    {
        $this->actingAs($this->a['admin']);

        $component = new OrganizationDetail();
        $component->organization = $this->a['organization'];

        foreach (['openSubscription', 'saveSubscription', 'openPayment', 'savePayment'] as $action) {
            try {
                $component->{$action}();
                $this->fail("{$action} should be refused for an organization admin");
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame(403, $e->getStatusCode(), $action);
            }
        }

        $this->assertSame(0, DB::table('organization_subscriptions')
            ->where('organization_id', $this->a['organization']->id)
            ->count());
    }

    public function test_the_subscription_form_validates(): void
    {
        $this->actingAs($this->superadmin());

        Livewire::test(OrganizationDetail::class, ['organization' => $this->a['organization']])
            ->call('openSubscription')
            ->set('plan', 'enterprise')
            ->set('price', '-5')
            ->set('billing_cycle', 'weekly')
            ->set('status', 'paused')
            ->call('saveSubscription')
            ->assertHasErrors(['plan', 'price', 'billing_cycle', 'status']);

        $this->assertSame(0, DB::table('organization_subscriptions')
            ->where('organization_id', $this->a['organization']->id)
            ->count());
    }
}
