<?php

namespace Tests\Feature\Plans;

use App\Services\PermissionService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * What a plan does to the navigation.
 *
 * The other axis — which roles see which links — is SidebarSectionsTest's
 * business, and that suite puts its agency on the top plan so the two stay
 * independent.
 */
class PlanSidebarTest extends TestCase
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
     * @return array<string, mixed>
     */
    protected function orgOnPlan(string $slug, string $plan): array
    {
        $org = $this->populate($this->makeOrganization($slug, ucfirst($plan).' Co'), 'X');

        $this->subscribeOrganization($org['organization'], $plan);

        return $org;
    }

    public function test_a_base_org_is_offered_no_erp_or_paid_ai_links(): void
    {
        $org = $this->orgOnPlan('side-base', 'base');

        $response = $this->actingAs($org['admin'])->get('/dashboard')->assertOk();

        foreach ([route('attendance.index'), route('leave.index'), route('payroll.index'),
            route('ai.chatbot'), route('ai.calendar'), route('ai.mcp'),
            route('ai.scheduled-tasks')] as $url) {
            $response->assertDontSee($url, escape: false);
        }

        // The ungated overview stays, so there is somewhere to read what
        // upgrading buys.
        $response->assertSee(route('ai.overview'), escape: false);
    }

    public function test_a_base_org_is_not_shown_an_empty_hr_heading(): void
    {
        $org = $this->orgOnPlan('side-hdr', 'base');

        // The header must go with its entries rather than hang over nothing.
        $this->actingAs($org['admin'])->get('/dashboard')
            ->assertOk()
            ->assertDontSee('>HR<', escape: false);
    }

    public function test_a_standard_org_is_offered_erp_and_chat_but_not_automation(): void
    {
        $org = $this->orgOnPlan('side-std', 'standard');

        $response = $this->actingAs($org['admin'])->get('/dashboard')->assertOk();

        $response->assertSee(route('attendance.index'), escape: false);
        $response->assertSee(route('leave.index'), escape: false);
        $response->assertSee(route('ai.chatbot'), escape: false);
        $response->assertSee(route('ai.calendar'), escape: false);
        $response->assertDontSee(route('ai.mcp'), escape: false);
        $response->assertDontSee(route('ai.scheduled-tasks'), escape: false);
    }

    public function test_a_pro_org_is_offered_everything(): void
    {
        $org = $this->orgOnPlan('side-pro', 'pro');

        $response = $this->actingAs($org['admin'])->get('/dashboard')->assertOk();

        foreach ([route('attendance.index'), route('leave.index'), route('payroll.index'),
            route('ai.chatbot'), route('ai.calendar'), route('ai.mcp'),
            route('ai.scheduled-tasks'), route('ai.overview')] as $url) {
            $response->assertSee($url, escape: false);
        }
    }

    public function test_a_base_orgs_dashboard_does_not_render_the_clock_widget(): void
    {
        $org = $this->orgOnPlan('side-clock', 'base');

        // The widget refuses with a 402 of its own, so leaving it embedded
        // would take the whole dashboard down rather than quietly omit it.
        $this->actingAs($org['admin'])->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Clock in');
    }

    public function test_a_pro_orgs_dashboard_still_renders_the_clock_widget(): void
    {
        $org = $this->orgOnPlan('side-clock-pro', 'pro');

        $this->actingAs($org['admin'])->get('/dashboard')
            ->assertOk()
            ->assertSee('Clock in');
    }

    public function test_a_base_orgs_profile_withholds_erp_for_the_plan_not_the_role(): void
    {
        $org = $this->orgOnPlan('side-profile', 'base');

        // Both refusals are "not available", but they send the reader to
        // different people, so they must not read alike.
        $this->actingAs($org['admin'])->get('/profile')
            ->assertOk()
            ->assertSee("isn't included in your plan", escape: false)
            ->assertDontSee('your role does not have this turned on');
    }

    public function test_a_pro_orgs_profile_shows_its_erp_sections(): void
    {
        $org = $this->orgOnPlan('side-profile-pro', 'pro');

        $this->actingAs($org['admin'])->get('/profile')
            ->assertOk()
            ->assertDontSee("isn't included in your plan", escape: false);
    }
}
