<?php

namespace Tests\Feature\Authorization;

use App\Livewire\Admin\AuthorizationPanel;
use App\Livewire\Admin\ManageUnits;
use App\Livewire\Admin\ManageUsers;
use App\Livewire\Tasks\ManageTasks;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * Deleting a task, a unit or a person is admin-only by structure.
 *
 * Not "the permission happens to be off" — there is no permission. These tests
 * are written so that they would fail if deletion ever became configurable
 * again: several of them force a grant directly into the database, which the
 * Authorization panel will not even offer, and still expect a refusal.
 */
class DeletionIsAdminOnlyTest extends TestCase
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

        $this->seed(PermissionSeeder::class);
        PermissionService::flushAll();

        $this->a = $this->populate($this->makeOrganization('del-a', 'Agency A'), 'A');
        $this->b = $this->populate($this->makeOrganization('del-b', 'Agency B'), 'B');
    }

    /**
     * Manufacture a delete permission and hand it to a role, bypassing the
     * panel entirely. Nothing in the application creates these any more.
     *
     * Granted inside agency A specifically. role_permissions is tenant-scoped,
     * so a grant written with no organization in context would land on the
     * founding agency and leave agency A's people unaffected — the test would
     * then pass without ever putting the guard under load.
     */
    protected function forceGrant(string $role, string $name, string $module = null): void
    {
        $permission = Permission::firstOrCreate(
            ['name' => $name],
            ['module' => $module ?? explode('.', $name)[0], 'action' => explode('.', $name)[1], 'label' => 'Forced'],
        );

        TenantContext::actingAsOrganization($this->a['organization']->id, function () use ($role, $permission) {
            RolePermission::updateOrCreate(
                ['role' => $role, 'permission_id' => $permission->id],
                ['allowed' => true]
            );
        });

        PermissionService::flushAll();
    }

    // ── The catalogue no longer contains deletion ────────────────────────────

    public function test_the_seeder_creates_no_delete_permissions(): void
    {
        $this->assertSame(0, Permission::where('action', 'delete')->count());

        foreach (['units.delete', 'users.delete', 'tasks.delete'] as $name) {
            $this->assertNull(Permission::where('name', $name)->first(), $name.' must not exist');
        }
    }

    public function test_the_panel_offers_no_delete_toggle_for_any_role(): void
    {
        // A leftover row from an older database, to prove the panel filters
        // rather than merely relying on the seeder.
        $this->forceGrant('pm', 'tasks.delete');

        $component = Livewire::actingAs($this->a['admin'])->test(AuthorizationPanel::class);

        foreach ($component->get('matrix') as $role => $permissions) {
            foreach (array_keys($permissions) as $name) {
                $this->assertStringNotContainsString('.delete', $name, "{$role} was offered {$name}");
            }
        }

        $this->assertArrayNotHasKey('delete', $component->get('actionLabels'));
    }

    public function test_the_panel_refuses_to_toggle_a_delete_permission(): void
    {
        $this->forceGrant('pm', 'tasks.delete');

        Livewire::actingAs($this->a['admin'])
            ->test(AuthorizationPanel::class)
            ->call('toggle', 'pm', 'tasks.delete')
            ->assertForbidden();
    }

    public function test_grant_all_does_not_reach_a_delete_permission(): void
    {
        // A leftover delete row for agency A, switched off. "Grant all" must
        // walk straight past it rather than switching it on with the rest.
        $permission = Permission::create([
            'name' => 'tasks.delete', 'module' => 'tasks', 'action' => 'delete', 'label' => 'Leftover',
        ]);

        TenantContext::actingAsOrganization($this->a['organization']->id, function () use ($permission) {
            RolePermission::create([
                'role'          => 'pm',
                'permission_id' => $permission->id,
                'allowed'       => false,
            ]);
        });

        PermissionService::flushAll();

        Livewire::actingAs($this->a['admin'])
            ->test(AuthorizationPanel::class)
            ->call('grantAll', 'pm');

        PermissionService::flushAll();

        $this->assertFalse(
            $this->a['pm']->fresh()->hasPermission('tasks.delete'),
            '"grant all" must not hand out a capability that is not grantable'
        );

        // The grant really did run, so the assertion above is not vacuous.
        $this->assertTrue($this->a['pm']->fresh()->hasPermission('tasks.assign'));
    }

    // ── A PM cannot delete, whatever is forced into the table ────────────────

    public function test_a_pm_cannot_delete_a_task_even_with_a_forced_grant(): void
    {
        $this->forceGrant('pm', 'tasks.delete');

        Livewire::actingAs($this->a['pm'])
            ->test(ManageTasks::class)
            ->call('openDeleteModal', $this->a['task']->id)
            ->call('confirmDelete')
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $this->a['task']->id]);
    }

    public function test_a_pm_cannot_delete_a_unit_even_with_a_forced_grant(): void
    {
        $this->forceGrant('pm', 'units.delete');
        $this->forceGrant('pm', 'units.view');

        Livewire::actingAs($this->a['pm'])
            ->test(ManageUnits::class)
            ->call('openDeleteModal', $this->a['unit']->id)
            ->call('confirmDelete')
            ->assertForbidden();

        $this->assertDatabaseHas('units', ['id' => $this->a['unit']->id]);
    }

    public function test_a_pm_cannot_delete_a_user_even_with_a_forced_grant(): void
    {
        $this->forceGrant('pm', 'users.delete');

        // The users screen now refuses at openDeleteModal rather than waiting
        // for confirmDelete, so the modal never opens for somebody the policy
        // would turn away. confirmDelete still asks the same policy — this
        // asserts on the first refusal because a call that aborts leaves no
        // snapshot for a second call to chain onto.
        Livewire::actingAs($this->a['pm'])
            ->test(ManageUsers::class)
            ->call('openDeleteModal', $this->a['writer']->id)
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $this->a['writer']->id]);
    }

    // ── A writer cannot delete either ────────────────────────────────────────

    public function test_a_writer_cannot_delete_a_task_a_unit_or_a_user(): void
    {
        foreach (['tasks.delete', 'units.delete', 'users.delete'] as $name) {
            $this->forceGrant('writer', $name);
        }

        // The view permissions are granted too, so each screen actually
        // renders. Without them the component would refuse at render() and the
        // test would pass without the delete guard ever being exercised.
        foreach (['units.view', 'users.view', 'tasks.view'] as $name) {
            $this->forceGrant('writer', $name);
        }

        Livewire::actingAs($this->a['writer'])
            ->test(ManageTasks::class)
            ->call('openDeleteModal', $this->a['task']->id)
            ->call('confirmDelete')
            ->assertForbidden();

        Livewire::actingAs($this->a['writer'])
            ->test(ManageUnits::class)
            ->call('openDeleteModal', $this->a['unit']->id)
            ->call('confirmDelete')
            ->assertForbidden();

        // Refused a step earlier than the other two screens — see the note in
        // the PM test above.
        Livewire::actingAs($this->a['writer'])
            ->test(ManageUsers::class)
            ->call('openDeleteModal', $this->a['pm']->id)
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $this->a['task']->id]);
        $this->assertDatabaseHas('units', ['id' => $this->a['unit']->id]);
        $this->assertDatabaseHas('users', ['id' => $this->a['pm']->id]);
    }

    // ── An admin can delete all three, inside their own agency ───────────────

    public function test_an_admin_deletes_a_task_a_unit_and_a_user_in_their_own_organization(): void
    {
        $admin = $this->a['admin'];

        Livewire::actingAs($admin)
            ->test(ManageUsers::class)
            ->call('openDeleteModal', $this->a['writer']->id)
            ->call('confirmDelete');
        $this->assertDatabaseMissing('users', ['id' => $this->a['writer']->id]);

        Livewire::actingAs($admin)
            ->test(ManageTasks::class)
            ->call('openDeleteModal', $this->a['task']->id)
            ->call('confirmDelete');
        $this->assertDatabaseMissing('tasks', ['id' => $this->a['task']->id]);
    }

    public function test_an_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->a['admin'];

        Livewire::actingAs($admin)
            ->test(ManageUsers::class)
            ->call('openDeleteModal', $admin->id)
            ->call('confirmDelete');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    // ── Tenant isolation still holds ─────────────────────────────────────────

    /**
     * Being an admin is not being an admin everywhere. Agency A's admin is
     * refused agency B's records — by the scope, which stops the row loading
     * at all, and by administers(), which compares the owner.
     */
    public function test_an_admin_cannot_delete_another_organizations_records(): void
    {
        $admin = $this->a['admin'];

        $this->assertFalse($admin->administers($this->b['task']), 'another agency\'s task');
        $this->assertFalse($admin->administers($this->b['unit']), 'another agency\'s unit');
        $this->assertFalse($admin->administers($this->b['writer']), 'another agency\'s member');

        /*
         * Through the real screens the row cannot even be found:
         * OrganizationScope confines findOrFail() before the policy is
         * consulted, so the refusal surfaces as "no such record" rather than
         * as a forbidden one. That is the stronger of the two outcomes — the
         * admin learns nothing about agency B at all.
         */
        $components = [
            ManageTasks::class => $this->b['task']->id,
            ManageUnits::class => $this->b['unit']->id,
            ManageUsers::class => $this->b['writer']->id,
        ];

        foreach ($components as $component => $foreignId) {
            $this->assertThrows(
                fn () => Livewire::actingAs($admin)
                    ->test($component)
                    ->call('openDeleteModal', $foreignId)
                    ->call('confirmDelete'),
                ModelNotFoundException::class,
            );
        }

        $this->assertDatabaseHas('tasks', ['id' => $this->b['task']->id]);
        $this->assertDatabaseHas('units', ['id' => $this->b['unit']->id]);
        $this->assertDatabaseHas('users', ['id' => $this->b['writer']->id]);
    }

    /**
     * The policy on its own, with the scope lifted, so the second lock is
     * tested rather than hidden behind the first.
     */
    public function test_the_policy_refuses_a_cross_organization_record_with_the_scope_lifted(): void
    {
        $admin = $this->a['admin'];

        TenantContext::runWithoutScope(function () use ($admin) {
            $foreignTask = Task::withoutGlobalScopes()->find($this->b['task']->id);
            $foreignUnit = Unit::withoutGlobalScopes()->find($this->b['unit']->id);
            $foreignUser = User::withoutGlobalScopes()->find($this->b['writer']->id);

            $this->assertNotNull($foreignTask, 'the row really is reachable here');

            $this->assertFalse($admin->can('delete', $foreignTask));
            $this->assertFalse($admin->can('delete', $foreignUnit));
            $this->assertFalse($admin->can('delete', $foreignUser));
        });
    }

    /**
     * A platform superadmin administers the platform, not the agencies' work.
     */
    public function test_a_superadmin_cannot_delete_operational_records(): void
    {
        $superadmin = User::withoutGlobalScopes()->where('role', 'superadmin')->firstOrFail();

        TenantContext::runWithoutScope(function () use ($superadmin) {
            $task = Task::withoutGlobalScopes()->find($this->a['task']->id);

            $this->assertFalse($superadmin->administers($task));
            $this->assertFalse($superadmin->can('delete', $task));
        });
    }
}
