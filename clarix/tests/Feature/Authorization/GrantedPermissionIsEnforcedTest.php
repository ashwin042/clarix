<?php

namespace Tests\Feature\Authorization;

use App\Livewire\Admin\ManageUnits;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\Unit;
use App\Models\User;
use App\Services\PermissionService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Authorization panel is a promise: a toggle that reads "granted" has to
 * mean the action actually goes through.
 *
 * Every test here grants a permission to a role that does not have it by
 * default and then performs the real action over HTTP. Asserting that the
 * role_permissions row saved would prove nothing — the bug being pinned is
 * precisely that the row saves and the request is still refused, because the
 * route middleware, the form requests and the policies were all deciding on
 * $user->role and never consulting the permission at all.
 */
class GrantedPermissionIsEnforcedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        // PermissionService memoises per role for the lifetime of the process,
        // and the process outlives a single test. Without this, a role read in
        // one test would serve a stale answer to the next.
        PermissionService::flushAll();
    }

    /**
     * Turn a permission on for a role, exactly as the Authorization panel does.
     */
    protected function grant(string $role, string $permission): void
    {
        RolePermission::updateOrCreate(
            ['role' => $role, 'permission_id' => Permission::where('name', $permission)->firstOrFail()->id],
            ['allowed' => true]
        );

        PermissionService::flushAll();
    }

    protected function writer(): User
    {
        return User::factory()->create(['role' => 'writer']);
    }

    // ── Units ────────────────────────────────────────────────────────────────

    public function test_a_writer_granted_units_view_can_open_the_units_page(): void
    {
        $writer = $this->writer();

        $this->actingAs($writer)->get(route('admin.units.index'))->assertForbidden();

        $this->grant('writer', 'units.view');

        $this->actingAs($writer)->get(route('admin.units.index'))->assertOk();
    }

    public function test_a_writer_without_units_view_is_still_refused(): void
    {
        $this->actingAs($this->writer())
            ->get(route('admin.units.index'))
            ->assertForbidden();
    }

    public function test_a_writer_granted_units_create_can_create_a_unit(): void
    {
        $writer = $this->writer();

        $this->grant('writer', 'units.view');
        $this->grant('writer', 'units.create');

        $this->actingAs($writer)
            ->post(route('admin.units.store'), ['name' => 'Granted Unit'])
            ->assertRedirect();

        $this->assertDatabaseHas('units', ['name' => 'Granted Unit']);
    }

    public function test_units_create_is_refused_without_the_grant(): void
    {
        $this->grant('writer', 'units.view');

        $this->actingAs($this->writer())
            ->post(route('admin.units.store'), ['name' => 'Ungranted Unit'])
            ->assertForbidden();

        $this->assertDatabaseMissing('units', ['name' => 'Ungranted Unit']);
    }

    // ── Users ────────────────────────────────────────────────────────────────

    public function test_a_writer_granted_users_view_can_open_the_users_page(): void
    {
        $writer = $this->writer();

        $this->actingAs($writer)->get(route('admin.users.index'))->assertForbidden();

        $this->grant('writer', 'users.view');

        $this->actingAs($writer)->get(route('admin.users.index'))->assertOk();
    }

    // ── Tasks ────────────────────────────────────────────────────────────────

    public function test_a_writer_granted_tasks_create_can_open_the_create_page(): void
    {
        $unit   = Unit::create(['name' => 'Writers Unit']);
        $writer = User::factory()->create(['role' => 'writer', 'unit_id' => $unit->id]);

        $this->actingAs($writer)->get(route('tasks.create'))->assertForbidden();

        $this->grant('writer', 'tasks.create');

        $this->actingAs($writer)->get(route('tasks.create'))->assertOk();
    }

    public function test_a_writer_granted_tasks_assign_can_assign_a_writer(): void
    {
        $unit   = Unit::create(['name' => 'Assign Unit']);
        $pm     = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
        $writer = User::factory()->create(['role' => 'writer']);
        $target = User::factory()->create(['role' => 'writer']);

        $task = Task::create([
            'title'         => 'Assignable',
            'task_code'     => 'AS_001',
            'unit_id'       => $unit->id,
            'created_by'    => $pm->id,
            'pm_id'         => $pm->id,
            'priority'      => 'medium',
            'status'        => 'pending',
            'deadline'      => now()->addDays(7),
            'credit_amount' => 1.00,
        ]);

        $payload = ['task_id' => $task->id, 'writer_ids' => [$target->id]];

        $this->actingAs($writer)
            ->post(route('tasks.assignments.store', $task), $payload)
            ->assertForbidden();

        $this->grant('writer', 'tasks.assign');

        $this->actingAs($writer)
            ->post(route('tasks.assignments.store', $task), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('task_assignments', [
            'task_id'   => $task->id,
            'writer_id' => $target->id,
        ]);
    }

    // ── The panel cannot grant its way into itself ───────────────────────────

    public function test_no_grant_opens_the_authorization_panel_to_a_non_admin(): void
    {
        $writer = $this->writer();

        // Everything the panel can hand out, handed out.
        foreach (Permission::pluck('name') as $permission) {
            $this->grant('writer', $permission);
        }

        // Editing the matrix is not itself a permission in the matrix, so
        // there is no sequence of grants that reaches it.
        $this->actingAs($writer)->get(route('admin.authorization'))->assertForbidden();
    }

    // ── Livewire actions are gated before they write ─────────────────────────

    /**
     * ManageUnits::render() checked the permission, but a Livewire action
     * posts to Livewire's own endpoint and render() runs after the action —
     * so a write landed and only then produced a 403.
     */
    public function test_a_livewire_action_is_refused_before_it_writes(): void
    {
        $writer = $this->writer();
        $this->grant('writer', 'units.view');

        Livewire::actingAs($writer)
            ->test(ManageUnits::class)
            ->set('name', 'Snuck In')
            ->call('save')
            ->assertForbidden();

        $this->assertDatabaseMissing('units', ['name' => 'Snuck In']);
    }

    // ── A permission widens capability, never row access ─────────────────────

    /**
     * The two halves of the decision stay separate: tasks.upload_files says a
     * writer may upload at all, their assignment says to which task. Granting
     * the permission must not hand them somebody else's work.
     */
    public function test_a_grant_does_not_widen_which_rows_a_role_reaches(): void
    {
        $unit   = Unit::create(['name' => 'Scoped Unit']);
        $pm     = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
        $writer = User::factory()->create(['role' => 'writer']);

        $task = Task::create([
            'title'         => 'Not theirs',
            'task_code'     => 'NT_001',
            'unit_id'       => $unit->id,
            'created_by'    => $pm->id,
            'pm_id'         => $pm->id,
            'priority'      => 'medium',
            'status'        => 'pending',
            'deadline'      => now()->addDays(7),
            'credit_amount' => 1.00,
        ]);

        $this->grant('writer', 'tasks.upload_files');

        $this->assertFalse(
            $writer->can('uploadFiles', $task),
            'the permission is granted, but the task is not assigned to this writer'
        );

        TaskAssignment::create([
            'task_id'     => $task->id,
            'writer_id'   => $writer->id,
            'assigned_by' => $pm->id,
            'status'      => 'pending',
        ]);

        $this->assertTrue(
            $writer->fresh()->can('uploadFiles', $task->fresh()),
            'once assigned, the granted permission applies'
        );
    }

    // ── The pre-deploy audit ─────────────────────────────────────────────────

    /**
     * The audit exists to be run against a copy of production before this
     * change ships, so it has to be right about which capabilities a role
     * loses. Seeded defaults grant a PM tasks.create; switching it off is
     * exactly the case the command must report.
     */
    public function test_the_audit_reports_a_capability_a_role_would_lose(): void
    {
        RolePermission::updateOrCreate(
            ['role' => 'pm', 'permission_id' => Permission::where('name', 'tasks.create')->firstOrFail()->id],
            ['allowed' => false]
        );

        $this->artisan('permissions:audit')
            ->expectsOutputToContain('LOSES  pm could tasks.create')
            ->assertSuccessful();
    }

    public function test_the_audit_is_quiet_when_the_defaults_are_intact(): void
    {
        $this->artisan('permissions:audit')
            ->doesntExpectOutputToContain('LOSES')
            ->assertSuccessful();
    }

    // ── Admin is unaffected ──────────────────────────────────────────────────

    public function test_an_admin_keeps_every_capability_without_any_rows(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // hasPermission() short-circuits for admins, so none of the above
        // grants exist for this role and every screen must still open.
        $this->actingAs($admin)->get(route('admin.units.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.authorization'))->assertOk();
    }

    /**
     * Revoking has to bite as hard as granting. A PM has tasks.create by
     * default; turning it off must actually close the page.
     */
    public function test_revoking_a_default_permission_closes_the_action(): void
    {
        $unit = Unit::create(['name' => 'PM Unit']);
        $pm   = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);

        $this->actingAs($pm)->get(route('tasks.create'))->assertOk();

        RolePermission::updateOrCreate(
            ['role' => 'pm', 'permission_id' => Permission::where('name', 'tasks.create')->firstOrFail()->id],
            ['allowed' => false]
        );
        PermissionService::flushAll();

        $this->actingAs($pm)->get(route('tasks.create'))->assertForbidden();
    }
}
