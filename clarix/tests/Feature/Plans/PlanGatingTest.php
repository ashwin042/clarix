<?php

namespace Tests\Feature\Plans;

use App\Models\OrganizationSubscription;
use App\Services\PermissionService;
use App\Services\PlanFeatures;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

class PlanGatingTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        PermissionService::flushAll();
    }

    /**
     * Put an organization on a plan, starting today so that it is newer than
     * the fixture's own subscription and therefore the one that counts.
     */
    protected function subscribe(int $organizationId, string $plan): void
    {
        TenantContext::actingAsOrganization($organizationId, function () use ($plan) {
            $subscription = new OrganizationSubscription([
                'plan'          => $plan,
                'price'         => 1000,
                'billing_cycle' => 'monthly',
                'started_at'    => now()->toDateString(),
                'status'        => 'active',
            ]);
            $subscription->next_renewal_at = $subscription->renewalDateFrom(now());
            $subscription->save();
        });

        PlanFeatures::flush();
    }

    public function test_the_middleware_refuses_a_plan_that_lacks_the_feature(): void
    {
        Route::middleware(['web', 'auth', 'plan:erp'])->get('/_test/erp', fn () => 'reached');

        $org = $this->populate($this->makeOrganization('gate-base', 'Base Co'), 'B');
        $this->subscribe($org['organization']->id, 'base');

        $this->actingAs($org['admin'])->get('/_test/erp')
            ->assertStatus(402)
            ->assertSee('Upgrade to Standard', escape: false);
    }

    public function test_the_middleware_lets_a_sufficient_plan_through(): void
    {
        Route::middleware(['web', 'auth', 'plan:erp'])->get('/_test/erp', fn () => 'reached');

        $org = $this->populate($this->makeOrganization('gate-std', 'Standard Co'), 'S');
        $this->subscribe($org['organization']->id, 'standard');

        $this->actingAs($org['admin'])->get('/_test/erp')->assertOk()->assertSee('reached');
    }

    public function test_the_refusal_names_the_feature_and_the_current_plan(): void
    {
        Route::middleware(['web', 'auth', 'plan:automation'])->get('/_test/auto', fn () => 'reached');

        $org = $this->populate($this->makeOrganization('gate-std2', 'Standard Co'), 'S');
        $this->subscribe($org['organization']->id, 'standard');

        $this->actingAs($org['admin'])->get('/_test/auto')
            ->assertStatus(402)
            ->assertSee('MCP, plugins and automation')
            ->assertSee('Standard plan')
            ->assertSee('Upgrade to Pro', escape: false);
    }

    public function test_the_refusal_page_offers_a_way_back(): void
    {
        Route::middleware(['web', 'auth', 'plan:erp'])->get('/_test/erp', fn () => 'reached');

        $org = $this->populate($this->makeOrganization('gate-back', 'Base Co'), 'B');
        $this->subscribe($org['organization']->id, 'base');

        // A dead end would be wrong here: only this feature is unavailable.
        $this->actingAs($org['admin'])->get('/_test/erp')
            ->assertStatus(402)
            ->assertSee(route('dashboard'), escape: false);
    }

    public function test_a_superadmin_passes_the_middleware_on_any_feature(): void
    {
        Route::middleware(['web', 'auth', 'plan:automation'])->get('/_test/auto', fn () => 'reached');

        $superadmin = \App\Models\User::factory()->create([
            'role'            => 'superadmin',
            'organization_id' => null,
        ]);

        $this->actingAs($superadmin)->get('/_test/auto')->assertOk();
    }

    // ── The real route table ─────────────────────────────────────────────────

    /**
     * @return array<string, array{0: string, 1: array<string, int>}>
     */
    public static function planRouteMatrix(): array
    {
        $erp = ['/attendance', '/leave', '/leave/types', '/payroll', '/payroll/manage'];
        $ai  = ['/ai/chatbot', '/ai/calendar', '/ai/mcp', '/ai/scheduled-tasks'];

        return [
            'base' => ['base', array_merge(
                array_fill_keys($erp, 402),
                array_fill_keys($ai, 402),
                ['/ai/overview' => 200, '/tasks' => 200],
            )],
            'standard' => ['standard', array_merge(
                array_fill_keys($erp, 200),
                ['/ai/chatbot' => 200, '/ai/calendar' => 200],
                ['/ai/mcp' => 402, '/ai/scheduled-tasks' => 402],
                ['/ai/overview' => 200, '/tasks' => 200],
            )],
            'pro' => ['pro', array_merge(
                array_fill_keys($erp, 200),
                array_fill_keys($ai, 200),
                ['/ai/overview' => 200, '/tasks' => 200],
            )],
        ];
    }

    /**
     * @dataProvider planRouteMatrix
     *
     * @param  array<string, int>  $expected
     */
    public function test_a_plan_reaches_exactly_its_own_routes(string $plan, array $expected): void
    {
        $org = $this->populate($this->makeOrganization('matrix-'.$plan, ucfirst($plan)), 'M');
        $this->subscribe($org['organization']->id, $plan);

        // An admin holds every permission unconditionally, so anything refused
        // below can only be the plan layer talking — never the role layer.
        $admin = $org['admin'];

        foreach ($expected as $url => $status) {
            $this->actingAs($admin)->get($url)->assertStatus($status);
        }
    }
}
