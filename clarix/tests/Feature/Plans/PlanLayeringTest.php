<?php

namespace Tests\Feature\Plans;

use App\Models\LeaveType;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Services\PermissionService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * The two layers are independent and both must pass.
 *
 * These are the cases that would catch one layer having quietly absorbed the
 * other: a plan that grants a permission, or a permission that buys a plan.
 */
class PlanLayeringTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        PermissionService::flushAll();

        $this->org = $this->populate($this->makeOrganization('layer-a', 'Agency A'), 'A');
    }

    protected function setPlan(string $plan): void
    {
        $this->subscribeOrganization($this->org['organization'], $plan);
    }

    protected function setPermission(string $role, string $name, bool $allowed): void
    {
        TenantContext::actingAsOrganization($this->org['organization']->id, function () use ($role, $name, $allowed) {
            RolePermission::updateOrCreate(
                ['role' => $role, 'permission_id' => Permission::where('name', $name)->firstOrFail()->id],
                ['allowed' => $allowed]
            );
        });

        PermissionService::flushAll();
    }

    protected function makeLeaveType(): void
    {
        TenantContext::actingAsOrganization(
            $this->org['organization']->id,
            fn () => LeaveType::create(['name' => 'Sabbatical', 'default_annual_allowance' => 12])
        );
    }

    public function test_a_pro_plan_does_not_grant_a_writer_a_permission_they_lack(): void
    {
        $this->setPlan('pro');
        $this->setPermission('writer', 'leave.view_own', false);
        $this->makeLeaveType();

        /*
         * The plan opens the page; the permission still withholds the balances.
         * If the plan layer had absorbed the permission layer they would show.
         *
         * Asserted on the balances heading rather than on the leave type's
         * name: the type also appears in the request form's dropdown, which is
         * open to everyone by design, so the name is present either way.
         */
        $this->actingAs($this->org['writer'])->get('/leave')
            ->assertOk()
            ->assertDontSee('This year');
    }

    public function test_a_pro_writer_holding_the_permission_sees_their_leave(): void
    {
        $this->setPlan('pro');
        $this->setPermission('writer', 'leave.view_own', true);
        $this->makeLeaveType();

        $this->actingAs($this->org['writer'])->get('/leave')
            ->assertOk()
            ->assertSee('This year')
            ->assertSee('Sabbatical');
    }

    public function test_every_permission_in_the_panel_does_not_buy_a_base_org_erp(): void
    {
        $this->setPlan('base');

        // An admin already holds every permission unconditionally, so these
        // refusals can only be the plan layer.
        $this->actingAs($this->org['admin'])->get('/payroll')->assertStatus(402);
        $this->actingAs($this->org['admin'])->get('/attendance')->assertStatus(402);
        $this->actingAs($this->org['admin'])->get('/leave')->assertStatus(402);
    }

    public function test_granting_a_permission_cannot_open_a_feature_the_plan_excludes(): void
    {
        $this->setPlan('base');

        // Hand a writer everything the panel can hand out. The plan still says no.
        foreach (['leave.view_own', 'leave.view_all', 'leave.manage',
            'attendance.view_own', 'attendance.view_all', 'attendance.manage',
            'payroll.view_own', 'payroll.manage'] as $name) {
            $this->setPermission('writer', $name, true);
        }

        $this->actingAs($this->org['writer'])->get('/leave')->assertStatus(402);
        $this->actingAs($this->org['writer'])->get('/attendance')->assertStatus(402);
        $this->actingAs($this->org['writer'])->get('/payroll')->assertStatus(402);
    }

    public function test_the_permission_layer_still_refuses_inside_an_included_plan(): void
    {
        $this->setPlan('pro');
        $this->setPermission('writer', 'payroll.view_own', false);

        // Plan says yes, policy says no: a 403 from the policy, not a 402.
        $this->actingAs($this->org['writer'])->get('/payroll')->assertForbidden();
    }
}
