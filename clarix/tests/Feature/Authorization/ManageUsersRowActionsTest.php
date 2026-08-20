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
 * The Edit and Delete buttons on each row of the users list.
 *
 * Same shape of fault the Add User button had: the affordance was drawn for
 * anybody who could see the list, and only save() and confirmDelete() asked
 * whether the actor was allowed. A PM is the live case — the defaults give a
 * PM users.view and users.create but *not* users.update, so every PM today
 * sees an Edit button on their own unit's people that refuses them on submit.
 *
 * The two buttons do not share a rule, and that is the point of separating
 * them here:
 *
 *   - Edit follows users.update, a granted permission;
 *   - Delete follows UserPolicy::delete, which is structural — administers()
 *     is false for everyone but an admin of the owning agency, so no grant
 *     reaches it.
 *
 * Each claim about a role that should be refused is paired with the same claim
 * about the admin, so hiding a button cannot quietly hide it from everyone.
 */
class ManageUsersRowActionsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $org;

    protected User $admin;

    protected User $supervisor;

    /**
     * A writer inside the PM's own unit.
     *
     * The shared fixture's writer carries no unit_id, and render() narrows a
     * PM to its own unit — so a PM's list is empty without this, and every
     * "the PM is not offered the button" assertion would pass by finding no
     * rows at all. Each PM test asserts this row is on screen first.
     */
    protected User $unitWriter;

    private const EDIT_BUTTON = 'wire:click="openEdit';

    private const DELETE_BUTTON = 'wire:click="openDeleteModal';

    private const PM_LOCKED = 'Only PMs can be added by a PM';

    private const ROLE_PICKER = 'wire:model.live="role"';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        PermissionService::flushAll();

        $this->org = $this->populate($this->makeOrganization('mura', 'Agency A'), 'A');
        $this->subscribeOrganization($this->org['organization']);

        $this->admin = $this->org['admin'];

        TenantContext::actingAsOrganization($this->org['organization']->id, function () {
            $this->supervisor = User::factory()->create([
                'name'  => 'Supervisor One',
                'email' => 'supervisor.one@example.test',
                'role'  => 'supervisor',
            ]);

            $this->unitWriter = User::factory()->create([
                'name'    => 'Unit Writer',
                'email'   => 'unit.writer@example.test',
                'role'    => 'writer',
                'unit_id' => $this->org['unit']->id,
            ]);
        });
    }

    // ── Edit follows users.update ────────────────────────────────────────────

    /**
     * The live default. No grant is needed to reproduce this: a PM ships with
     * users.view and users.create but not users.update.
     */
    public function test_a_pm_without_users_update_is_not_offered_the_edit_button(): void
    {
        $this->assertFalse($this->org['pm']->hasPermission('users.update'));

        Livewire::actingAs($this->org['pm'])
            ->test(ManageUsers::class)
            ->assertSee('Unit Writer')
            ->assertDontSee(self::EDIT_BUTTON, false);
    }

    public function test_a_supervisor_holding_only_users_view_is_not_offered_the_edit_button(): void
    {
        $this->grant('supervisor', 'users.view');

        Livewire::actingAs($this->supervisor->fresh())
            ->test(ManageUsers::class)
            ->assertDontSee(self::EDIT_BUTTON, false);
    }

    public function test_a_supervisor_granted_users_update_is_offered_the_edit_button(): void
    {
        $this->grant('supervisor', 'users.view');
        $this->grant('supervisor', 'users.update');

        Livewire::actingAs($this->supervisor->fresh())
            ->test(ManageUsers::class)
            ->assertSee(self::EDIT_BUTTON, false);
    }

    public function test_the_admin_is_still_offered_the_edit_button(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ManageUsers::class)
            ->assertSee(self::EDIT_BUTTON, false);
    }

    public function test_open_edit_is_refused_without_users_update(): void
    {
        $this->grant('supervisor', 'users.view');

        Livewire::actingAs($this->supervisor->fresh())
            ->test(ManageUsers::class)
            ->call('openEdit', $this->org['writer']->id)
            ->assertForbidden();
    }

    public function test_the_admin_can_open_the_edit_modal(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ManageUsers::class)
            ->call('openEdit', $this->org['writer']->id)
            ->assertOk()
            ->assertSet('showModal', true);
    }

    // ── Delete is structural, not granted ────────────────────────────────────

    /**
     * users.update does not carry deletion with it. There is no users.delete
     * to grant, so the button must stay hidden however generous the panel is.
     */
    public function test_a_supervisor_with_users_update_is_still_not_offered_the_delete_button(): void
    {
        $this->grant('supervisor', 'users.view');
        $this->grant('supervisor', 'users.update');

        Livewire::actingAs($this->supervisor->fresh())
            ->test(ManageUsers::class)
            ->assertSee(self::EDIT_BUTTON, false)
            ->assertDontSee(self::DELETE_BUTTON, false);
    }

    public function test_a_pm_is_not_offered_the_delete_button(): void
    {
        Livewire::actingAs($this->org['pm'])
            ->test(ManageUsers::class)
            ->assertSee('Unit Writer')
            ->assertDontSee(self::DELETE_BUTTON, false);
    }

    public function test_the_admin_is_still_offered_the_delete_button(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ManageUsers::class)
            ->assertSee(self::DELETE_BUTTON, false);
    }

    public function test_open_delete_modal_is_refused_for_a_non_admin(): void
    {
        $this->grant('supervisor', 'users.view');
        $this->grant('supervisor', 'users.update');

        Livewire::actingAs($this->supervisor->fresh())
            ->test(ManageUsers::class)
            ->call('openDeleteModal', $this->org['writer']->id, 'Writer A')
            ->assertForbidden();
    }

    /** Already correct before this change — pinned so it stays that way. */
    public function test_confirm_delete_is_still_refused_for_a_non_admin(): void
    {
        $this->grant('supervisor', 'users.view');
        $this->grant('supervisor', 'users.update');

        Livewire::actingAs($this->supervisor->fresh())
            ->test(ManageUsers::class)
            ->set('deletingId', $this->org['writer']->id)
            ->call('confirmDelete')
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $this->org['writer']->id]);
    }

    public function test_the_admin_can_still_delete_a_user(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ManageUsers::class)
            ->call('openDeleteModal', $this->org['writer']->id, 'Writer A')
            ->call('confirmDelete');

        $this->assertDatabaseMissing('users', ['id' => $this->org['writer']->id]);
    }

    // ── An admin account is managed by admins only ───────────────────────────

    /*
     * Found while gating the button. users.update alone let a supervisor open
     * an admin's row, choose any role its own ceiling allowed, and set a
     * password — demoting the agency's admin and taking the account over in
     * one submit. UserPolicy::delete already refuses a non-admin structurally;
     * update ignored its target entirely.
     */

    public function test_a_supervisor_cannot_open_an_admin_for_editing(): void
    {
        $this->grant('supervisor', 'users.view');
        $this->grant('supervisor', 'users.update');

        Livewire::actingAs($this->supervisor->fresh())
            ->test(ManageUsers::class)
            ->call('openEdit', $this->admin->id)
            ->assertForbidden();
    }

    public function test_a_supervisor_cannot_demote_an_admin(): void
    {
        $this->grant('supervisor', 'users.view');
        $this->grant('supervisor', 'users.update');

        Livewire::actingAs($this->supervisor->fresh())
            ->test(ManageUsers::class)
            ->set('editingId', $this->admin->id)
            ->set('name', 'Demoted')
            ->set('email_username', 'admin.a')
            ->set('role', 'writer')
            ->set('password', 'newpassword123')
            ->call('save')
            ->assertForbidden();

        $this->assertSame('admin', $this->admin->fresh()->role);
    }

    /** The control: an admin keeps the power to edit another admin. */
    public function test_an_admin_can_edit_another_admin(): void
    {
        $second = TenantContext::actingAsOrganization(
            $this->org['organization']->id,
            fn () => User::factory()->create([
                'name'  => 'Second Admin',
                'email' => 'second.admin@example.test',
                'role'  => 'admin',
            ])
        );

        Livewire::actingAs($this->admin)
            ->test(ManageUsers::class)
            ->call('openEdit', $second->id)
            ->set('name', 'Second Admin Renamed')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Second Admin Renamed', $second->fresh()->name);
    }

    // ── The edit modal is the create modal ───────────────────────────────────

    /*
     * One modal serves both, so the isAdmin()/else-PM branch fixed for Add
     * User covers Edit too. Pinned rather than assumed: the two paths set
     * different properties on the way in, and only the view is shared.
     */

    public function test_the_edit_modal_does_not_tell_a_supervisor_only_pms_can_be_added(): void
    {
        $this->grant('supervisor', 'users.view');
        $this->grant('supervisor', 'users.update');

        Livewire::actingAs($this->supervisor->fresh())
            ->test(ManageUsers::class)
            ->call('openEdit', $this->org['writer']->id)
            ->assertDontSee(self::PM_LOCKED, false)
            ->assertSee(self::ROLE_PICKER, false);
    }

    public function test_the_pm_edit_modal_still_shows_the_locked_field(): void
    {
        $this->grant('pm', 'users.update');

        Livewire::actingAs($this->org['pm']->fresh())
            ->test(ManageUsers::class)
            ->call('openEdit', $this->org['writer']->id)
            ->assertSee(self::PM_LOCKED, false)
            ->assertDontSee(self::ROLE_PICKER, false);
    }

    public function test_the_admin_edit_modal_still_shows_the_full_dropdown(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ManageUsers::class)
            ->call('openEdit', $this->org['writer']->id)
            ->assertSee(self::ROLE_PICKER, false)
            ->assertDontSee(self::PM_LOCKED, false);
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
