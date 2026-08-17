<?php

namespace Tests\Feature\Plans;

use App\Models\OrganizationSubscription;
use App\Services\PlanFeatures;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

class PlanFeaturesTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    protected function planFeatures(): PlanFeatures
    {
        PlanFeatures::flush();

        return app(PlanFeatures::class);
    }

    protected function subscribe(int $organizationId, string $plan, ?string $startedAt = null): void
    {
        // Defaults to today so that this subscription is newer than the one
        // the fixture creates, and therefore the one that counts.
        $startedAt ??= now()->toDateString();

        TenantContext::actingAsOrganization($organizationId, function () use ($plan, $startedAt) {
            $subscription = new OrganizationSubscription([
                'plan'          => $plan,
                'price'         => 1000,
                'billing_cycle' => 'monthly',
                'started_at'    => $startedAt,
                'status'        => 'active',
            ]);
            $subscription->next_renewal_at = $subscription->renewalDateFrom($startedAt);
            $subscription->save();
        });

        PlanFeatures::flush();
    }

    public function test_an_organization_with_no_subscription_is_treated_as_base(): void
    {
        $org = $this->makeOrganization('pf-none', 'No Plan');

        OrganizationSubscription::withoutGlobalScopes()->where('organization_id', $org->id)->delete();
        PlanFeatures::flush();

        $this->assertSame('base', $this->planFeatures()->planFor($org->id));
    }

    public function test_the_plan_comes_from_the_subscription(): void
    {
        $org = $this->makeOrganization('pf-std', 'Standard');
        $this->subscribe($org->id, 'standard');

        $this->assertSame('standard', $this->planFeatures()->planFor($org->id));
    }

    public function test_the_newest_subscription_wins(): void
    {
        $org = $this->makeOrganization('pf-up', 'Upgrader');
        $this->subscribe($org->id, 'base', '2026-01-01');
        $this->subscribe($org->id, 'pro', '2026-06-01');

        $this->assertSame('pro', $this->planFeatures()->planFor($org->id));
    }

    public function test_an_unrecognised_plan_name_falls_back_to_base(): void
    {
        $org = $this->makeOrganization('pf-junk', 'Junk');
        $this->subscribe($org->id, 'enterprise-deluxe');

        $this->assertSame('base', $this->planFeatures()->planFor($org->id));
    }

    public function test_each_plan_includes_the_plans_below_it(): void
    {
        $expected = [
            'base'     => ['tasks' => true, 'files' => true, 'erp' => false, 'ai_chat' => false, 'calendar' => false, 'automation' => false],
            'standard' => ['tasks' => true, 'files' => true, 'erp' => true,  'ai_chat' => true,  'calendar' => true,  'automation' => false],
            'pro'      => ['tasks' => true, 'files' => true, 'erp' => true,  'ai_chat' => true,  'calendar' => true,  'automation' => true],
        ];

        foreach ($expected as $plan => $matrix) {
            $org = $this->makeOrganization('pf-'.$plan.'-m', ucfirst($plan).' Matrix');
            $this->subscribe($org->id, $plan);

            $features = $this->planFeatures();

            foreach ($matrix as $feature => $allowed) {
                $this->assertSame(
                    $allowed,
                    $features->allows($feature, $org->id),
                    "{$plan} should ".($allowed ? 'include' : 'exclude')." {$feature}"
                );
            }
        }
    }

    public function test_an_unknown_feature_is_denied_even_on_pro(): void
    {
        $org = $this->makeOrganization('pf-unknown', 'Pro');
        $this->subscribe($org->id, 'pro');

        $this->assertFalse($this->planFeatures()->allows('teleportation', $org->id));
    }

    public function test_a_null_organization_is_treated_as_base(): void
    {
        $this->assertSame('base', $this->planFeatures()->planFor(null));
        $this->assertFalse($this->planFeatures()->allows('erp', null));
    }

    public function test_the_minimum_plan_drives_the_upgrade_message(): void
    {
        $features = $this->planFeatures();

        $this->assertSame('standard', $features->minimumPlanFor('erp'));
        $this->assertSame('pro', $features->minimumPlanFor('automation'));
        $this->assertNull($features->minimumPlanFor('teleportation'));
    }

    public function test_a_member_of_a_base_organization_is_refused_erp(): void
    {
        $org = $this->populate($this->makeOrganization('pf-user-base', 'Base Co'), 'B');
        $this->subscribe($org['organization']->id, 'base');

        $this->assertTrue($org['admin']->planAllows('tasks'));
        $this->assertFalse($org['admin']->planAllows('erp'));
    }

    public function test_a_superadmin_is_not_plan_gated_at_all(): void
    {
        $superadmin = \App\Models\User::factory()->create([
            'name'            => 'Platform Super',
            'email'           => 'super@example.test',
            'role'            => 'superadmin',
            'organization_id' => null,
        ]);

        // No organization, therefore no plan to consult. Every feature.
        foreach (['tasks', 'files', 'erp', 'ai_chat', 'calendar', 'automation'] as $feature) {
            $this->assertTrue($superadmin->planAllows($feature), "superadmin should reach {$feature}");
        }
    }
}
