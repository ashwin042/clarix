<?php

namespace Tests\Feature\Authorization;

use App\Livewire\Admin\RoleUserManagement;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * The per-role staff screens, once supervisor and HR have their own.
 *
 * RoleUserManagement is one component behind several routes, picked by a
 * ->defaults('role', ...) on each. So "add two pages" is really "widen one
 * whitelist and two match arms" — and the risk is not that the new pages fail
 * loudly but that they quietly inherit something meant for the PM, whose
 * screen is the only one carrying a unit.
 *
 * Every claim about the new roles is therefore paired with the same claim
 * about an existing one: the writer screen (no unit) and the PM screen (unit
 * required) bracket the behaviour the new pages should and should not copy.
 */
class RoleStaffScreensTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $a;

    protected User $admin;

    protected User $supervisor;

    protected User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        PermissionService::flushAll();

        $this->a = $this->populate($this->makeOrganization('staff-a', 'Agency A'), 'A');
        $this->subscribeOrganization($this->a['organization']);

        $this->admin = $this->a['admin'];

        TenantContext::actingAsOrganization($this->a['organization']->id, function () {
            $this->supervisor = User::factory()->create([
                'name'  => 'Supervisor One',
                'email' => 'supervisor.one@example.test',
                'role'  => 'supervisor',
            ]);

            $this->hr = User::factory()->create([
                'name'  => 'HR One',
                'email' => 'hr.one@example.test',
                'role'  => 'hr',
            ]);
        });
    }

    // ── The routes exist and answer ──────────────────────────────────────────

    public function test_the_writers_screen_opens_for_an_admin(): void
    {
        $this->actingAs($this->admin)->get(route('admin.writers.index'))->assertOk();
    }

    public function test_the_supervisors_screen_opens_for_an_admin(): void
    {
        $this->actingAs($this->admin)->get(route('admin.supervisors.index'))->assertOk();
    }

    public function test_the_hr_screen_opens_for_an_admin(): void
    {
        $this->actingAs($this->admin)->get(route('admin.hr.index'))->assertOk();
    }

    /**
     * The whitelist in mount() is what stops a hand-typed role reaching the
     * component. Widening it for two roles must not open it to anything else.
     */
    public function test_an_unknown_managed_role_is_still_refused(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RoleUserManagement::class, ['role' => 'superadmin'])
            ->assertStatus(404);
    }

    // ── Each screen lists its own role and nobody else's ─────────────────────

    public function test_the_supervisors_screen_lists_supervisors_and_no_other_role(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RoleUserManagement::class, ['role' => 'supervisor'])
            ->assertSee('Supervisor One')
            ->assertDontSee('HR One')
            ->assertDontSee('Writer A')
            ->assertDontSee('PM A');
    }

    public function test_the_hr_screen_lists_hr_and_no_other_role(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RoleUserManagement::class, ['role' => 'hr'])
            ->assertSee('HR One')
            ->assertDontSee('Supervisor One')
            ->assertDontSee('Writer A');
    }

    // ── Creating ─────────────────────────────────────────────────────────────

    /**
     * Neither role belongs to a unit — a supervisor's reach is the agency and
     * HR's is its people — so the unit field the PM screen requires must not
     * be asked for here, and must land as null.
     */
    public function test_admin_can_create_a_supervisor_without_naming_a_unit(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RoleUserManagement::class, ['role' => 'supervisor'])
            ->call('openCreate')
            ->set('name', 'Supervisor Two')
            ->set('email_username', 'supervisor.two')
            ->set('password', 'password123')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'name'    => 'Supervisor Two',
            'email'   => 'supervisor.two@clarix.com',
            'role'    => 'supervisor',
            'unit_id' => null,
        ]);
    }

    public function test_admin_can_create_an_hr_user_without_naming_a_unit(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RoleUserManagement::class, ['role' => 'hr'])
            ->call('openCreate')
            ->set('name', 'HR Two')
            ->set('email_username', 'hr.two')
            ->set('password', 'password123')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'name'    => 'HR Two',
            'email'   => 'hr.two@clarix.com',
            'role'    => 'hr',
            'unit_id' => null,
        ]);
    }

    /**
     * The control. The PM screen still demands a unit, so the assertions above
     * are about supervisor and HR rather than about unit validation having
     * been switched off everywhere.
     */
    public function test_the_pm_screen_still_demands_a_unit(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RoleUserManagement::class, ['role' => 'pm'])
            ->call('openCreate')
            ->set('name', 'PM Two')
            ->set('email_username', 'pm.two')
            ->set('password', 'password123')
            ->call('save')
            ->assertHasErrors(['unit_id']);
    }

    public function test_creating_a_supervisor_still_validates_the_basics(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RoleUserManagement::class, ['role' => 'supervisor'])
            ->call('openCreate')
            ->set('name', '')
            ->set('email_username', '')
            ->set('password', '')
            ->call('save')
            ->assertHasErrors(['name', 'email_username', 'password']);
    }

    // ── Editing ──────────────────────────────────────────────────────────────

    public function test_admin_can_edit_a_supervisor(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RoleUserManagement::class, ['role' => 'supervisor'])
            ->call('openEdit', $this->supervisor->id)
            ->set('name', 'Supervisor Renamed')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Supervisor Renamed', $this->supervisor->fresh()->name);
    }

    public function test_admin_can_edit_an_hr_user(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RoleUserManagement::class, ['role' => 'hr'])
            ->call('openEdit', $this->hr->id)
            ->set('name', 'HR Renamed')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('HR Renamed', $this->hr->fresh()->name);
    }

    /**
     * The screens stay sealed off from one another: the supervisor page may
     * not be used to edit an HR account by passing its id.
     */
    public function test_the_supervisor_screen_refuses_to_edit_an_hr_user(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RoleUserManagement::class, ['role' => 'supervisor'])
            ->call('openEdit', $this->hr->id)
            ->assertForbidden();
    }

    // ── Deleting ─────────────────────────────────────────────────────────────

    public function test_admin_can_delete_a_supervisor(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RoleUserManagement::class, ['role' => 'supervisor'])
            ->call('openDeleteModal', $this->supervisor->id, 'Supervisor One')
            ->call('confirmDelete');

        $this->assertDatabaseMissing('users', ['id' => $this->supervisor->id]);
    }

    public function test_admin_can_delete_an_hr_user(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RoleUserManagement::class, ['role' => 'hr'])
            ->call('openDeleteModal', $this->hr->id, 'HR One')
            ->call('confirmDelete');

        $this->assertDatabaseMissing('users', ['id' => $this->hr->id]);
    }

    public function test_the_hr_screen_refuses_to_delete_a_supervisor(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RoleUserManagement::class, ['role' => 'hr'])
            ->call('openDeleteModal', $this->supervisor->id, 'Supervisor One')
            ->call('confirmDelete')
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $this->supervisor->id]);
    }

    /**
     * ucfirst('hr') is "Hr". The delete modal built its title that way, which
     * stayed invisible while the PM was the only special case.
     */
    public function test_the_hr_screen_never_renders_the_role_as_hr_titlecased(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RoleUserManagement::class, ['role' => 'hr'])
            ->assertDontSee('Hr', false);
    }

    // ── Who may reach the screens ────────────────────────────────────────────

    public function test_a_pm_cannot_open_the_supervisors_screen(): void
    {
        $this->actingAs($this->a['pm'])->get(route('admin.supervisors.index'))->assertForbidden();
    }

    public function test_a_pm_cannot_open_the_hr_screen(): void
    {
        $this->actingAs($this->a['pm'])->get(route('admin.hr.index'))->assertForbidden();
    }

    public function test_a_supervisor_cannot_open_the_hr_screen(): void
    {
        $this->actingAs($this->supervisor)->get(route('admin.hr.index'))->assertForbidden();
    }

    // ── The sidebar ──────────────────────────────────────────────────────────

    public function test_the_sidebar_offers_the_admin_all_five_staff_screens(): void
    {
        $sidebar = $this->actingAs($this->admin)
            ->get(route('leave.index'))
            ->assertOk()
            ->getContent();

        foreach (['admins', 'pms', 'writers', 'supervisors', 'hr'] as $screen) {
            $this->assertStringContainsString(
                route("admin.{$screen}.index"),
                $sidebar,
                "the sidebar should offer an admin the {$screen} screen"
            );
        }
    }

    /**
     * The staff screens are role:admin at the route and refuse a non-admin in
     * the component's own mount(), so drawing them for anybody else — however
     * they came by users.view — is drawing a link to a 403.
     */
    public function test_the_sidebar_hides_the_new_screens_from_a_non_admin_holding_users_view(): void
    {
        $this->setPermissionFor('supervisor', 'users.view', true);

        $sidebar = $this->actingAs($this->supervisor->fresh())
            ->get(route('leave.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(route('admin.supervisors.index'), $sidebar);
        $this->assertStringNotContainsString(route('admin.hr.index'), $sidebar);
        $this->assertStringNotContainsString(route('admin.writers.index'), $sidebar);
    }

    protected function setPermissionFor(string $role, string $name, bool $allowed): void
    {
        TenantContext::actingAsOrganization($this->a['organization']->id, function () use ($role, $name, $allowed) {
            RolePermission::updateOrCreate(
                ['role' => $role, 'permission_id' => Permission::where('name', $name)->firstOrFail()->id],
                ['allowed' => $allowed]
            );
        });

        PermissionService::flushAll();
    }
}
