<?php

namespace Tests\Feature\Billing;

use App\Livewire\Superadmin\OrganizationDetail;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * What a suspension actually does to the people using Clarix.
 *
 * The state machine is covered by SubscriptionLifecycleTest; this is about the
 * middleware in front of the ordinary application, and about the superadmin
 * keeping the access needed to lift a suspension.
 */
class SubscriptionEnforcementTest extends TestCase
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

    protected function subscribe(Organization $organization, string $status, int $renewalInDays = 30): OrganizationSubscription
    {
        return TenantContext::actingAsOrganization($organization->id, fn () => OrganizationSubscription::create([
            'plan'            => 'standard',
            'price'           => 99.00,
            'billing_cycle'   => 'monthly',
            'started_at'      => now()->subMonth(),
            'next_renewal_at' => now()->addDays($renewalInDays),
            'status'          => $status,
        ]));
    }

    /**
     * @return list<string>
     */
    protected function orgRoutes(): array
    {
        return [
            route('dashboard'),
            route('tasks.index'),
            route('credits.index'),
            route('issues.index'),
            route('admin.subscription'),
        ];
    }

    // -------------------------------------------------------------- blocking

    public function test_a_suspended_organization_is_blocked_everywhere(): void
    {
        $this->subscribe($this->a['organization'], 'suspended', -10);

        foreach ([$this->a['admin'], $this->a['pm'], $this->a['writer']] as $user) {
            $this->actingAs($user);

            foreach ($this->orgRoutes() as $url) {
                $this->get($url)
                    ->assertStatus(402)
                    ->assertSee('Subscription suspended')
                    ->assertSee('contact support to reactivate', false);
            }
        }
    }

    public function test_a_blocked_user_stays_signed_in(): void
    {
        $this->subscribe($this->a['organization'], 'suspended', -10);

        $this->actingAs($this->a['admin']);
        $this->get(route('dashboard'))->assertStatus(402);

        // Blocked from the work, not thrown out — they can still see who they
        // are and still sign out.
        $this->assertAuthenticated();
        $this->assertSame($this->a['admin']->id, auth()->id());
    }

    public function test_an_active_organization_is_unaffected(): void
    {
        $this->subscribe($this->a['organization'], 'active', 30);

        $this->actingAs($this->a['admin']);

        $this->get(route('dashboard'))->assertSuccessful();
        $this->get(route('tasks.index'))->assertSuccessful();
    }

    /**
     * The grace period has to be genuinely graceful, or it is just a slower
     * suspension.
     */
    public function test_a_past_due_organization_still_works_fully(): void
    {
        $this->subscribe($this->a['organization'], 'past_due', -2);

        $this->actingAs($this->a['admin']);

        foreach ($this->orgRoutes() as $url) {
            $this->get($url)->assertSuccessful();
        }
    }

    public function test_a_past_due_organization_sees_a_warning_banner(): void
    {
        $this->subscribe($this->a['organization'], 'past_due', -2);

        $this->actingAs($this->a['admin']);

        $this->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee('subscription renewal is overdue', false)
            ->assertSee('Access will be suspended after', false);
    }

    public function test_an_active_organization_sees_no_banner(): void
    {
        $this->subscribe($this->a['organization'], 'active', 30);

        $this->actingAs($this->a['admin']);

        $this->get(route('dashboard'))
            ->assertSuccessful()
            ->assertDontSee('subscription renewal is overdue', false);
    }

    /**
     * Every organization predates billing at the moment this ships. Treating a
     * missing subscription as arrears would take all of them offline at once.
     */
    public function test_an_organization_with_no_subscription_is_not_blocked(): void
    {
        $this->assertSame(
            0,
            OrganizationSubscription::withoutGlobalScopes()
                ->where('organization_id', $this->a['organization']->id)
                ->count(),
            'this agency has no billing set up'
        );

        $this->actingAs($this->a['admin']);

        $this->get(route('dashboard'))->assertSuccessful();
    }

    public function test_one_organizations_suspension_does_not_affect_another(): void
    {
        $this->subscribe($this->a['organization'], 'suspended', -10);
        $this->subscribe($this->b['organization'], 'active', 30);

        $this->actingAs($this->a['admin']);
        $this->get(route('dashboard'))->assertStatus(402);

        $this->actingAs($this->b['admin']);
        $this->get(route('dashboard'))->assertSuccessful();
    }

    // ------------------------------------------------------ superadmin access

    public function test_a_superadmin_reaches_the_portal_while_an_organization_is_suspended(): void
    {
        $this->subscribe($this->a['organization'], 'suspended', -10);

        $this->actingAs($this->superadmin());

        $this->get(route('superadmin.organizations.index'))->assertSuccessful();
        $this->get(route('superadmin.organizations.show', $this->a['organization']))->assertSuccessful();
        $this->get(route('superadmin.organizations.admin', $this->a['organization']))->assertSuccessful();
    }

    public function test_a_superadmin_is_never_subject_to_enforcement(): void
    {
        // Suspend every organization there is.
        $this->subscribe($this->a['organization'], 'suspended', -10);
        $this->subscribe($this->b['organization'], 'suspended', -10);

        $this->actingAs($this->superadmin());

        // The superadmin has no organization, so there is nothing to enforce
        // against them — the dashboard still redirects them to the portal.
        $this->get(route('dashboard'))->assertRedirect(route('superadmin.organizations.index'));
    }

    public function test_a_superadmin_can_still_see_the_suspended_organizations_billing(): void
    {
        $subscription = $this->subscribe($this->a['organization'], 'suspended', -10);

        $this->actingAs($this->superadmin());

        Livewire::test(OrganizationDetail::class, ['organization' => $this->a['organization']])
            ->assertViewHas('subscription', fn ($s) => $s->id === $subscription->id)
            ->assertSee('Suspended');
    }

    // ------------------------------------------------ reactivation restores access

    public function test_reactivating_from_the_portal_restores_access_immediately(): void
    {
        $this->subscribe($this->a['organization'], 'suspended', -10);

        // Blocked.
        $this->actingAs($this->a['admin']);
        $this->get(route('dashboard'))->assertStatus(402);

        // Superadmin lifts it.
        $this->actingAs($this->superadmin());
        Livewire::test(OrganizationDetail::class, ['organization' => $this->a['organization']])
            ->call('reactivateSubscription')
            ->assertDispatched('notify', type: 'success');

        // Back in, with no other step in between.
        $this->actingAs($this->a['admin']);
        $this->get(route('dashboard'))->assertSuccessful();
        $this->get(route('tasks.index'))->assertSuccessful();
    }

    public function test_renewing_from_the_portal_also_restores_access(): void
    {
        $this->subscribe($this->a['organization'], 'suspended', -10);

        $this->actingAs($this->a['admin']);
        $this->get(route('dashboard'))->assertStatus(402);

        $this->actingAs($this->superadmin());
        Livewire::test(OrganizationDetail::class, ['organization' => $this->a['organization']])
            ->call('renewSubscription')
            ->assertDispatched('notify', type: 'success');

        $this->actingAs($this->a['admin']);
        $this->get(route('dashboard'))->assertSuccessful();

        $subscription = OrganizationSubscription::withoutGlobalScopes()
            ->where('organization_id', $this->a['organization']->id)
            ->first();

        $this->assertSame('active', $subscription->status);
        $this->assertTrue($subscription->next_renewal_at->isFuture());
    }

    public function test_suspending_from_the_portal_blocks_access_immediately(): void
    {
        $this->subscribe($this->a['organization'], 'active', 300);

        $this->actingAs($this->a['admin']);
        $this->get(route('dashboard'))->assertSuccessful();

        $this->actingAs($this->superadmin());
        Livewire::test(OrganizationDetail::class, ['organization' => $this->a['organization']])
            ->call('suspendSubscription')
            ->assertDispatched('notify', type: 'success');

        $this->actingAs($this->a['admin']);
        $this->get(route('dashboard'))->assertStatus(402);
    }

    public function test_the_lifecycle_actions_are_closed_to_an_organization_admin(): void
    {
        $this->subscribe($this->a['organization'], 'suspended', -10);

        $this->actingAs($this->a['admin']);

        $component = new OrganizationDetail();
        $component->organization = $this->a['organization'];

        foreach (['renewSubscription', 'reactivateSubscription', 'suspendSubscription'] as $action) {
            try {
                $component->{$action}();
                $this->fail("{$action} should be refused for an organization admin");
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame(403, $e->getStatusCode(), $action);
            }
        }

        $this->assertSame(
            'suspended',
            OrganizationSubscription::withoutGlobalScopes()
                ->where('organization_id', $this->a['organization']->id)
                ->value('status')
        );
    }
}
