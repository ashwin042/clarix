<?php

namespace Tests\Feature\Authorization;

use App\Livewire\Admin\AuthorizationPanel;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\OrganizationPermissionDefaults;
use App\Services\PermissionService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * Where Supervisor and HR come from, for an agency that already exists and for
 * one created tomorrow.
 *
 * Two halves that have to agree. A role added to the baseline reaches new
 * agencies through the observer, and reaches the ones already on the platform
 * only through a migration — the fourth time that pattern is needed, and the
 * failure it prevents is a role whose toggles all read off because the rows
 * were never written.
 */
class NewRoleProvisioningTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $a;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        PermissionService::flushAll();

        $this->a = $this->populate($this->makeOrganization('prov-a', 'Agency A'), 'A');
    }

    // ── The baseline itself ──────────────────────────────────────────────────

    public function test_the_baseline_carries_both_new_roles(): void
    {
        $this->assertArrayHasKey('supervisor', OrganizationPermissionDefaults::BASELINE);
        $this->assertArrayHasKey('hr', OrganizationPermissionDefaults::BASELINE);
    }

    public function test_the_baseline_gives_supervisor_the_org_wide_work_set(): void
    {
        $supervisor = OrganizationPermissionDefaults::BASELINE['supervisor'];

        foreach (['units.view', 'units.create', 'tasks.view', 'tasks.create', 'tasks.update', 'tasks.assign', 'tasks.upload_files'] as $name) {
            $this->assertTrue($supervisor[$name], "supervisor baseline should allow {$name}");
        }

        foreach (['units.update', 'users.view', 'users.create', 'users.update', 'credits.view', 'attendance.manage', 'leave.manage', 'payroll.manage'] as $name) {
            $this->assertFalse($supervisor[$name], "supervisor baseline must deny {$name}");
        }
    }

    public function test_the_baseline_gives_hr_the_three_modules_and_nothing_else(): void
    {
        $hr = OrganizationPermissionDefaults::BASELINE['hr'];

        foreach (['attendance.view_all', 'attendance.manage', 'leave.view_all', 'leave.manage', 'payroll.manage'] as $name) {
            $this->assertTrue($hr[$name], "hr baseline should allow {$name}");
        }

        foreach (['units.view', 'units.create', 'units.update', 'users.view', 'users.create', 'users.update', 'tasks.view', 'tasks.create', 'tasks.update', 'tasks.assign', 'tasks.upload_files', 'credits.view'] as $name) {
            $this->assertFalse($hr[$name], "hr baseline must deny {$name}");
        }
    }

    /**
     * The rule this codebase holds without exception: no role but admin may
     * destroy anything, and there is no permission that could say otherwise.
     */
    public function test_neither_new_role_carries_a_delete_grant(): void
    {
        foreach (['supervisor', 'hr'] as $role) {
            foreach (array_keys(OrganizationPermissionDefaults::BASELINE[$role]) as $name) {
                $this->assertStringNotContainsString('.delete', $name, "{$role} was given {$name}");
            }
        }
    }

    // ── A new agency ─────────────────────────────────────────────────────────

    public function test_a_new_organization_is_provisioned_with_both_roles(): void
    {
        $fresh = Organization::create([
            'name'              => 'Fresh Agency',
            'contact_number'    => '0',
            'email'             => 'fresh@example.test',
            'address'           => 'Somewhere',
            'subscription_type' => 'base',
            'slug'              => 'fresh-agency',
        ]);

        foreach (['supervisor', 'hr'] as $role) {
            $this->assertTrue(
                RolePermission::withoutGlobalScopes()
                    ->where('organization_id', $fresh->id)
                    ->where('role', $role)
                    ->exists(),
                "a new organization should receive {$role} rows"
            );
        }
    }

    public function test_a_new_organizations_supervisor_can_create_tasks_out_of_the_box(): void
    {
        $fresh = Organization::create([
            'name'              => 'Fresh Two',
            'contact_number'    => '0',
            'email'             => 'fresh2@example.test',
            'address'           => 'Somewhere',
            'subscription_type' => 'base',
            'slug'              => 'fresh-two',
        ]);

        $supervisor = TenantContext::actingAsOrganization(
            $fresh->id,
            fn () => User::factory()->create(['role' => 'supervisor', 'email' => 'sup.fresh@example.test'])
        );

        PermissionService::flushAll();

        $this->assertTrue($supervisor->hasPermission('tasks.create'));
        $this->assertFalse($supervisor->hasPermission('payroll.manage'));
    }

    public function test_a_new_organizations_hr_can_manage_payroll_out_of_the_box(): void
    {
        $fresh = Organization::create([
            'name'              => 'Fresh Three',
            'contact_number'    => '0',
            'email'             => 'fresh3@example.test',
            'address'           => 'Somewhere',
            'subscription_type' => 'base',
            'slug'              => 'fresh-three',
        ]);

        $hr = TenantContext::actingAsOrganization(
            $fresh->id,
            fn () => User::factory()->create(['role' => 'hr', 'email' => 'hr.fresh@example.test'])
        );

        PermissionService::flushAll();

        $this->assertTrue($hr->hasPermission('payroll.manage'));
        $this->assertTrue($hr->hasPermission('leave.manage'));
        $this->assertFalse($hr->hasPermission('tasks.view'));
    }

    // ── An agency that predates the roles ────────────────────────────────────

    /**
     * The backfill, run against an agency stripped back to the state it would
     * have been in before this work: PM and writer rows, and nothing for the
     * two new roles.
     */
    public function test_the_migration_backfills_an_organization_that_has_no_rows_for_the_new_roles(): void
    {
        $this->stripNewRoleRows();

        $this->assertSame(0, $this->newRoleRowCount());

        $this->runBackfill();

        foreach (['supervisor', 'hr'] as $role) {
            $this->assertTrue(
                RolePermission::withoutGlobalScopes()
                    ->where('organization_id', $this->a['organization']->id)
                    ->where('role', $role)
                    ->exists(),
                "{$role} should have been backfilled"
            );
        }
    }

    public function test_the_backfilled_grants_match_the_baseline(): void
    {
        $this->stripNewRoleRows();
        $this->runBackfill();
        PermissionService::flushAll();

        $supervisor = TenantContext::actingAsOrganization(
            $this->a['organization']->id,
            fn () => User::factory()->create(['role' => 'supervisor', 'email' => 'sup.backfill@example.test'])
        );

        $hr = TenantContext::actingAsOrganization(
            $this->a['organization']->id,
            fn () => User::factory()->create(['role' => 'hr', 'email' => 'hr.backfill@example.test'])
        );

        $this->assertTrue($supervisor->hasPermission('tasks.assign'));
        $this->assertFalse($supervisor->hasPermission('leave.manage'));

        $this->assertTrue($hr->hasPermission('leave.manage'));
        $this->assertFalse($hr->hasPermission('units.create'));
    }

    public function test_the_migration_can_be_run_twice_without_duplicating_rows(): void
    {
        $this->stripNewRoleRows();

        $this->runBackfill();
        $first = $this->newRoleRowCount();

        $this->runBackfill();

        $this->assertSame($first, $this->newRoleRowCount());
    }

    /**
     * An agency that has already made up its mind keeps its decision. The
     * backfill fills gaps; it does not restate defaults over the top of
     * choices somebody made deliberately.
     */
    public function test_the_migration_leaves_an_existing_opinion_alone(): void
    {
        $this->stripNewRoleRows();

        $permissionId = Permission::where('name', 'tasks.create')->firstOrFail()->id;

        DB::table('role_permissions')->insert([
            'organization_id' => $this->a['organization']->id,
            'role'            => 'supervisor',
            'permission_id'   => $permissionId,
            'allowed'         => false,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->runBackfill();

        $this->assertSame(
            0,
            (int) DB::table('role_permissions')
                ->where('organization_id', $this->a['organization']->id)
                ->where('role', 'supervisor')
                ->where('permission_id', $permissionId)
                ->value('allowed')
        );
    }

    // ── The Authorization panel ──────────────────────────────────────────────

    public function test_the_panel_lists_both_new_roles(): void
    {
        $component = Livewire::actingAs($this->a['admin'])->test(AuthorizationPanel::class);

        $roles = $component->get('roles');

        $this->assertContains('supervisor', $roles);
        $this->assertContains('hr', $roles);

        $labels = $component->get('roleLabels');

        $this->assertSame('Supervisor', $labels['supervisor']);
        $this->assertSame('HR', $labels['hr']);
    }

    public function test_the_panel_builds_a_matrix_row_for_both_new_roles(): void
    {
        $matrix = Livewire::actingAs($this->a['admin'])
            ->test(AuthorizationPanel::class)
            ->get('matrix');

        foreach (['supervisor', 'hr'] as $role) {
            $this->assertArrayHasKey($role, $matrix);
            $this->assertArrayHasKey('tasks.create', $matrix[$role]);
        }

        $this->assertTrue($matrix['supervisor']['tasks.create']);
        $this->assertTrue($matrix['hr']['payroll.manage']);
        $this->assertFalse($matrix['hr']['tasks.create']);
    }

    public function test_an_admin_can_customise_a_new_roles_permission_from_the_panel(): void
    {
        Livewire::actingAs($this->a['admin'])
            ->test(AuthorizationPanel::class)
            ->call('toggle', 'supervisor', 'credits.view');

        PermissionService::flushAll();

        $supervisor = TenantContext::actingAsOrganization(
            $this->a['organization']->id,
            fn () => User::factory()->create(['role' => 'supervisor', 'email' => 'sup.panel@example.test'])
        );

        $this->assertTrue($supervisor->hasPermission('credits.view'));
    }

    public function test_the_panel_still_refuses_a_delete_toggle_for_the_new_roles(): void
    {
        Permission::firstOrCreate(
            ['name' => 'tasks.delete'],
            ['module' => 'tasks', 'action' => 'delete', 'label' => 'Forced']
        );

        foreach (['supervisor', 'hr'] as $role) {
            Livewire::actingAs($this->a['admin'])
                ->test(AuthorizationPanel::class)
                ->call('toggle', $role, 'tasks.delete')
                ->assertForbidden();
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    protected function newRoleRowCount(): int
    {
        return RolePermission::withoutGlobalScopes()
            ->where('organization_id', $this->a['organization']->id)
            ->whereIn('role', ['supervisor', 'hr'])
            ->count();
    }

    /**
     * Put agency A back into the state it held before this work: the older
     * roles configured, the new ones absent entirely.
     */
    protected function stripNewRoleRows(): void
    {
        DB::table('role_permissions')
            ->where('organization_id', $this->a['organization']->id)
            ->whereIn('role', ['supervisor', 'hr'])
            ->delete();

        PermissionService::flushAll();
    }

    protected function runBackfill(): void
    {
        $migration = require database_path('migrations/2026_08_26_000002_grant_supervisor_and_hr_permissions_to_existing_organizations.php');

        $migration->up();

        PermissionService::flushAll();
    }
}
