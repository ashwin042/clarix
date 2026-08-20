<?php

namespace Tests\Feature\Authorization;

use App\Livewire\Admin\ManageUsers;
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
 * The Add User modal, for everybody who is not an admin.
 *
 * The screen was written when only two roles could reach it, so it asks
 * isAdmin() and treats every other answer as "a PM". That was true once. It
 * stopped being true the moment a supervisor could hold users.view, and the
 * result is a modal that tells a supervisor "Only PMs can be added by a PM"
 * and locks the Role field to Project Manager.
 *
 * Two separate faults sit behind that one screenshot, so they get separate
 * tests:
 *
 *   - the create affordance is drawn without asking for users.create at all,
 *     so a role holding only users.view is offered a button whose save() will
 *     refuse it;
 *   - the modal's markup branches on role identity rather than capability,
 *     with the PM's own special case as the catch-all.
 *
 * Every claim is paired with the same claim about the admin (full dropdown)
 * and the PM (locked field), because those two are the behaviour that must
 * survive the fix unchanged.
 */
class ManageUsersCreateModalTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $org;

    protected User $admin;

    protected User $supervisor;

    /** The control text the PM branch renders. */
    private const PM_LOCKED = 'Only PMs can be added by a PM';

    private const ROLE_PICKER = 'wire:model.live="role"';

    private const ADD_BUTTON = 'wire:click="openCreate"';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        PermissionService::flushAll();

        $this->org = $this->populate($this->makeOrganization('mu-a', 'Agency A'), 'A');
        $this->subscribeOrganization($this->org['organization']);

        $this->admin = $this->org['admin'];

        TenantContext::actingAsOrganization($this->org['organization']->id, function () {
            $this->supervisor = User::factory()->create([
                'name'  => 'Supervisor One',
                'email' => 'supervisor.one@example.test',
                'role'  => 'supervisor',
            ]);
        });
    }

    // ── The affordance follows users.create ──────────────────────────────────

    /**
     * The reported case. A supervisor granted users.view reaches the screen
     * legitimately; the button is drawn anyway, and save() then refuses. The
     * button is the bug, not the refusal.
     */
    public function test_a_supervisor_holding_only_users_view_is_not_offered_the_add_button(): void
    {
        $this->grant('supervisor', 'users.view');

        Livewire::actingAs($this->supervisor->fresh())
            ->test(ManageUsers::class)
            ->assertDontSee(self::ADD_BUTTON, false);
    }

    public function test_open_create_is_refused_without_users_create(): void
    {
        $this->grant('supervisor', 'users.view');

        Livewire::actingAs($this->supervisor->fresh())
            ->test(ManageUsers::class)
            ->call('openCreate')
            ->assertForbidden();
    }

    public function test_a_supervisor_granted_users_create_is_offered_the_add_button(): void
    {
        $this->grant('supervisor', 'users.view');
        $this->grant('supervisor', 'users.create');

        Livewire::actingAs($this->supervisor->fresh())
            ->test(ManageUsers::class)
            ->assertSee(self::ADD_BUTTON, false);
    }

    /** The controls: the two roles that always had the button keep it. */
    public function test_the_admin_is_still_offered_the_add_button(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ManageUsers::class)
            ->assertSee(self::ADD_BUTTON, false);
    }

    public function test_the_pm_is_still_offered_the_add_button(): void
    {
        Livewire::actingAs($this->org['pm'])
            ->test(ManageUsers::class)
            ->assertSee(self::ADD_BUTTON, false);
    }

    // ── The modal names the actor's own role ─────────────────────────────────

    public function test_the_modal_does_not_tell_a_supervisor_that_only_pms_can_be_added(): void
    {
        $this->grant('supervisor', 'users.view');
        $this->grant('supervisor', 'users.create');

        Livewire::actingAs($this->supervisor->fresh())
            ->test(ManageUsers::class)
            ->call('openCreate')
            ->assertDontSee(self::PM_LOCKED, false);
    }

    public function test_the_modal_gives_a_supervisor_a_real_role_picker(): void
    {
        $this->grant('supervisor', 'users.view');
        $this->grant('supervisor', 'users.create');

        Livewire::actingAs($this->supervisor->fresh())
            ->test(ManageUsers::class)
            ->call('openCreate')
            ->assertSee(self::ROLE_PICKER, false);
    }

    /**
     * The PM lock is a real rule, not an accident — save() forces role and
     * unit for a PM regardless of what was posted. It must survive.
     */
    public function test_the_pm_still_gets_the_locked_role_field(): void
    {
        Livewire::actingAs($this->org['pm'])
            ->test(ManageUsers::class)
            ->call('openCreate')
            ->assertSee(self::PM_LOCKED, false)
            ->assertDontSee(self::ROLE_PICKER, false);
    }

    public function test_the_admin_still_gets_the_full_role_dropdown(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ManageUsers::class)
            ->call('openCreate')
            ->assertSee(self::ROLE_PICKER, false)
            ->assertDontSee(self::PM_LOCKED, false);
    }

    // ── Minting an admin stays the admin's own power ─────────────────────────

    /*
     * Handing the supervisor a working role picker raises a question the PM
     * lock used to answer by accident: which roles may a non-admin creator
     * choose? "Admin" cannot be one of them. The authorization panel is
     * admin-only, so a supervisor cannot grant itself anything — but creating
     * an admin account and signing in as it reaches the same place, with a
     * password the supervisor chose.
     */

    /**
     * Asserted on the data rather than the markup: the role *filter* at the
     * top of the screen lists every role including admin, and legitimately so
     * — filtering a list is reading, not creating. A markup assertion could
     * not tell the two dropdowns apart.
     */
    public function test_a_supervisor_is_not_offered_admin_in_the_role_picker(): void
    {
        $this->grant('supervisor', 'users.view');
        $this->grant('supervisor', 'users.create');

        $roles = Livewire::actingAs($this->supervisor->fresh())
            ->test(ManageUsers::class)
            ->call('openCreate')
            ->viewData('assignableRoles');

        $this->assertArrayNotHasKey('admin', $roles);
        $this->assertArrayHasKey('writer', $roles);
    }

    public function test_an_admin_is_offered_admin_in_the_role_picker(): void
    {
        $roles = Livewire::actingAs($this->admin)
            ->test(ManageUsers::class)
            ->call('openCreate')
            ->viewData('assignableRoles');

        $this->assertArrayHasKey('admin', $roles);
    }

    public function test_a_supervisor_cannot_create_an_admin_by_posting_the_role(): void
    {
        $this->grant('supervisor', 'users.view');
        $this->grant('supervisor', 'users.create');

        Livewire::actingAs($this->supervisor->fresh())
            ->test(ManageUsers::class)
            ->call('openCreate')
            ->set('name', 'Backdoor')
            ->set('email_username', 'backdoor')
            ->set('password', 'password123')
            ->set('role', 'admin')
            ->call('save')
            ->assertHasErrors(['role']);

        $this->assertDatabaseMissing('users', ['email' => 'backdoor@clarix.com']);
    }

    public function test_a_supervisor_can_create_a_writer(): void
    {
        $this->grant('supervisor', 'users.view');
        $this->grant('supervisor', 'users.create');

        Livewire::actingAs($this->supervisor->fresh())
            ->test(ManageUsers::class)
            ->call('openCreate')
            ->set('name', 'New Writer')
            ->set('email_username', 'new.writer')
            ->set('password', 'password123')
            ->set('role', 'writer')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'new.writer@clarix.com',
            'role'  => 'writer',
        ]);
    }

    /** The control: the admin keeps the power to mint an admin. */
    public function test_an_admin_can_still_create_an_admin(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ManageUsers::class)
            ->call('openCreate')
            ->set('name', 'Second Admin')
            ->set('email_username', 'second.admin')
            ->set('password', 'password123')
            ->set('role', 'admin')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'second.admin@clarix.com',
            'role'  => 'admin',
        ]);
    }

    protected function grant(string $role, string $name): void
    {
        TenantContext::actingAsOrganization($this->org['organization']->id, function () use ($role, $name) {
            RolePermission::updateOrCreate(
                ['role' => $role, 'permission_id' => Permission::where('name', $name)->firstOrFail()->id],
                ['allowed' => true]
            );
        });

        PermissionService::flushAll();
    }
}
