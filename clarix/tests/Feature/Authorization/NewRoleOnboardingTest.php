<?php

namespace Tests\Feature\Authorization;

use App\Livewire\Admin\ManageUsers;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * The two ends of a new role's life in the app: an admin creating somebody
 * into it, and that person signing in and landing somewhere.
 *
 * The landing half is not cosmetic. DashboardController resolves the role
 * through a match with no default arm, so a role the controller has not been
 * told about does not degrade to a plain page — it throws UnhandledMatchError
 * on the first screen after login, which is every screen a new hire sees.
 */
class NewRoleOnboardingTest extends TestCase
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

        $this->a = $this->populate($this->makeOrganization('onboard-a', 'Agency A'), 'A');
        $this->subscribeOrganization($this->a['organization']);
    }

    protected function userWithRole(string $role): User
    {
        return TenantContext::actingAsOrganization(
            $this->a['organization']->id,
            fn () => User::factory()->create(['role' => $role, 'email' => "{$role}.onboard@example.test"])
        );
    }

    // ── Signing in ───────────────────────────────────────────────────────────

    public function test_a_supervisor_lands_on_a_dashboard(): void
    {
        $this->actingAs($this->userWithRole('supervisor'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Track and direct work across every unit.');
    }

    public function test_an_hr_lands_on_a_dashboard(): void
    {
        $this->actingAs($this->userWithRole('hr'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Attendance, leave and payroll for the agency.');
    }

    /**
     * The supervisor's figures span the agency. A PM's dashboard counts one
     * unit, and a supervisor belongs to none, so the same component filtering
     * on unit_id would have shown them a page of zeroes with a real backlog
     * behind it.
     */
    public function test_the_supervisor_dashboard_counts_tasks_across_every_unit(): void
    {
        $supervisor = $this->userWithRole('supervisor');

        $stats = Livewire::actingAs($supervisor)
            ->test(\App\Livewire\Dashboard\PmStats::class)
            ->viewData('stats');

        $this->assertSame(1, $stats['total']);
    }

    public function test_the_hr_dashboard_shows_nothing_from_management(): void
    {
        $page = $this->actingAs($this->userWithRole('hr'))
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(route('tasks.index'), $page);
        $this->assertStringNotContainsString(route('credits.index'), $page);
        $this->assertStringNotContainsString(route('admin.units.index'), $page);
    }

    // ── Being created ────────────────────────────────────────────────────────

    public function test_an_admin_can_create_a_supervisor(): void
    {
        Livewire::actingAs($this->a['admin'])
            ->test(ManageUsers::class)
            ->call('openCreate')
            ->set('name', 'New Supervisor')
            ->set('email_username', 'new.supervisor')
            ->set('password', 'secret-password')
            ->set('role', 'supervisor')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'name'            => 'New Supervisor',
            'role'            => 'supervisor',
            'organization_id' => $this->a['organization']->id,
        ]);
    }

    public function test_an_admin_can_create_an_hr(): void
    {
        Livewire::actingAs($this->a['admin'])
            ->test(ManageUsers::class)
            ->call('openCreate')
            ->set('name', 'New HR')
            ->set('email_username', 'new.hr')
            ->set('password', 'secret-password')
            ->set('role', 'hr')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'name'            => 'New HR',
            'role'            => 'hr',
            'organization_id' => $this->a['organization']->id,
        ]);
    }

    /**
     * Neither new role belongs to a unit — the supervisor because their reach
     * is the agency, HR because they have no work to be filed against.
     */
    public function test_neither_new_role_is_given_a_unit(): void
    {
        foreach (['supervisor' => 'unit.sup', 'hr' => 'unit.hr'] as $role => $username) {
            Livewire::actingAs($this->a['admin'])
                ->test(ManageUsers::class)
                ->call('openCreate')
                ->set('name', "Unitless {$role}")
                ->set('email_username', $username)
                ->set('password', 'secret-password')
                ->set('role', $role)
                ->set('unit_id', (string) $this->a['unit']->id)
                ->call('save')
                ->assertHasNoErrors();

            $this->assertNull(
                User::where('name', "Unitless {$role}")->firstOrFail()->unit_id,
                "{$role} should not be filed under a unit"
            );
        }
    }

    public function test_an_unknown_role_is_still_rejected(): void
    {
        Livewire::actingAs($this->a['admin'])
            ->test(ManageUsers::class)
            ->call('openCreate')
            ->set('name', 'Impostor')
            ->set('email_username', 'impostor')
            ->set('password', 'secret-password')
            ->set('role', 'overlord')
            ->call('save')
            ->assertHasErrors('role');

        $this->assertDatabaseMissing('users', ['name' => 'Impostor']);
    }
}
