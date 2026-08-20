<?php

namespace Tests\Feature\Authorization;

use App\Livewire\Tasks\ManageTasks;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use App\Services\OrganizationPermissionDefaults;
use App\Services\PermissionService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * Moving a task through the workflow, once it is a permission.
 *
 * updateStatus() was `isAdmin()` and nothing else, which made it the one
 * ability in TaskPolicy that the Authorization panel could not describe. A
 * supervisor showing tasks.update in the panel still could not drag a card
 * between columns, and nothing on the screen explained why.
 *
 * tasks.update lets a role edit a task's fields; it says nothing about who may
 * advance it. Those are not the same authority, and tasks.update cannot stand
 * in for the second because it is on by default for the PM as well as the
 * supervisor — reusing it would have handed every PM the workflow silently.
 *
 * So status gets its own permission. The tests that matter most here are the
 * two that prove it is genuinely a permission rather than a role check
 * wearing one: revoking it from the supervisor closes the board, and granting
 * it to the PM opens it.
 */
class TaskStatusPermissionTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $a;

    /** @var array<string, mixed> */
    protected array $b;

    protected User $supervisor;

    protected User $hr;

    /** A second unit, so the supervisor's reach is tested outside its own. */
    protected Unit $otherUnit;

    protected Task $otherTask;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        PermissionService::flushAll();

        $this->a = $this->populate($this->makeOrganization('tsp-a', 'Agency A'), 'A');
        $this->b = $this->populate($this->makeOrganization('tsp-b', 'Agency B'), 'B');
        $this->subscribeOrganization($this->a['organization']);

        TenantContext::actingAsOrganization($this->a['organization']->id, function () {
            $this->supervisor = User::factory()->create([
                'name'  => 'Supervisor A',
                'email' => 'supervisor.a@example.test',
                'role'  => 'supervisor',
            ]);

            $this->hr = User::factory()->create([
                'name'  => 'HR A',
                'email' => 'hr.a@example.test',
                'role'  => 'hr',
            ]);

            $this->otherUnit = Unit::create(['name' => 'Second Unit A']);

            $this->otherTask = Task::create([
                'title'         => 'Second Unit Task',
                'task_code'     => 'TSP_OTHER',
                'unit_id'       => $this->otherUnit->id,
                'created_by'    => $this->a['pm']->id,
                'pm_id'         => $this->a['pm']->id,
                'priority'      => 'medium',
                'status'        => 'pending',
                'deadline'      => now()->addDays(7),
                'credit_amount' => 10.00,
            ]);
        });
    }

    // ── The permission exists and is describable ─────────────────────────────

    public function test_the_seeder_creates_the_status_permission(): void
    {
        $this->assertDatabaseHas('permissions', [
            'name'   => 'tasks.update_status',
            'module' => 'tasks',
        ]);
    }

    public function test_the_baseline_allows_it_for_supervisor_and_denies_it_to_the_others(): void
    {
        $baseline = OrganizationPermissionDefaults::BASELINE;

        $this->assertTrue($baseline['supervisor']['tasks.update_status']);

        foreach (['pm', 'hr', 'writer'] as $role) {
            $this->assertFalse(
                $baseline[$role]['tasks.update_status'],
                "{$role} must not hold tasks.update_status by default"
            );
        }
    }

    public function test_the_default_grants_reach_the_roles_themselves(): void
    {
        $this->assertTrue($this->supervisor->hasPermission('tasks.update_status'));

        foreach ([$this->a['pm'], $this->a['writer'], $this->hr] as $user) {
            $this->assertFalse(
                $user->hasPermission('tasks.update_status'),
                "{$user->role} must not hold tasks.update_status by default"
            );
        }
    }

    // ── The panel can describe it ────────────────────────────────────────────

    /*
     * The complaint that started this: the panel showed a supervisor holding
     * tasks.update, and the board still would not move. An ability the panel
     * cannot name is an ability an agency cannot reason about, so these assert
     * the toggle is really on the screen and really drives the policy.
     */

    public function test_the_panel_offers_the_status_toggle(): void
    {
        Livewire::actingAs($this->a['admin'])
            ->test(\App\Livewire\Admin\AuthorizationPanel::class)
            ->assertSee('tasks.update_status')
            ->assertSee('Change Task Status');
    }

    public function test_turning_the_toggle_off_in_the_panel_stops_the_supervisor_moving_a_card(): void
    {
        Livewire::actingAs($this->a['admin'])
            ->test(\App\Livewire\Admin\AuthorizationPanel::class)
            ->call('toggle', 'supervisor', 'tasks.update_status');

        PermissionService::flushAll();

        Livewire::actingAs($this->supervisor->fresh())
            ->test(ManageTasks::class)
            ->call('moveTask', $this->otherTask->id, 'in_progress', [
                'in_progress' => [$this->otherTask->id],
            ])
            ->assertForbidden();

        $this->assertSame('pending', $this->otherTask->fresh()->status);
    }

    // ── The policy ───────────────────────────────────────────────────────────

    public function test_the_supervisor_may_change_the_status_of_a_task_in_any_unit(): void
    {
        $this->assertTrue(
            Gate::forUser($this->supervisor)->allows('updateStatus', $this->otherTask)
        );
    }

    public function test_the_admin_may_still_change_the_status(): void
    {
        $this->assertTrue(
            Gate::forUser($this->a['admin'])->allows('updateStatus', $this->a['task'])
        );
    }

    /**
     * The control that gives this suite its point. The PM holds tasks.update
     * by default and reaches this task structurally, so if status had been
     * hung off tasks.update this would pass — and the PM would have gained the
     * workflow without anybody deciding they should.
     */
    public function test_the_pm_may_not_change_the_status_of_its_own_units_task(): void
    {
        $this->assertTrue($this->a['pm']->hasPermission('tasks.update'));
        $this->assertTrue(Gate::forUser($this->a['pm'])->allows('update', $this->a['task']));

        $this->assertFalse(
            Gate::forUser($this->a['pm'])->allows('updateStatus', $this->a['task'])
        );
    }

    public function test_the_writer_may_not_change_the_status_of_an_assigned_task(): void
    {
        $this->assertFalse(
            Gate::forUser($this->a['writer'])->allows('updateStatus', $this->a['task'])
        );
    }

    public function test_hr_may_not_change_the_status(): void
    {
        $this->assertFalse(
            Gate::forUser($this->hr)->allows('updateStatus', $this->a['task'])
        );
    }

    // ── It is a permission, not a role check in disguise ─────────────────────

    public function test_revoking_it_closes_the_status_change_for_the_supervisor(): void
    {
        $this->setPermission($this->a['organization']->id, 'supervisor', 'tasks.update_status', false);

        $this->assertFalse(
            Gate::forUser($this->supervisor->fresh())->allows('updateStatus', $this->otherTask)
        );
    }

    /**
     * The other half. An agency that wants its PMs running the board can say
     * so, and the policy obeys — which is what makes this a permission rather
     * than a role name spelled differently.
     */
    public function test_granting_it_opens_the_status_change_for_a_pm(): void
    {
        $this->setPermission($this->a['organization']->id, 'pm', 'tasks.update_status', true);

        $this->assertTrue(
            Gate::forUser($this->a['pm']->fresh())->allows('updateStatus', $this->a['task'])
        );
    }

    /**
     * Granting it does not widen *which* rows. A PM given the workflow still
     * only runs it inside its own unit.
     */
    public function test_granting_it_to_a_pm_does_not_widen_which_tasks_it_reaches(): void
    {
        $this->setPermission($this->a['organization']->id, 'pm', 'tasks.update_status', true);

        $this->assertFalse(
            Gate::forUser($this->a['pm']->fresh())->allows('updateStatus', $this->otherTask)
        );
    }

    public function test_a_grant_in_one_agency_does_not_follow_the_role_into_another(): void
    {
        $supervisorB = TenantContext::actingAsOrganization(
            $this->b['organization']->id,
            fn () => User::factory()->create([
                'name'  => 'Supervisor B',
                'email' => 'supervisor.b@example.test',
                'role'  => 'supervisor',
            ])
        );

        $this->setPermission($this->b['organization']->id, 'supervisor', 'tasks.update_status', false);

        $this->assertFalse($supervisorB->fresh()->hasPermission('tasks.update_status'));
        $this->assertTrue($this->supervisor->fresh()->hasPermission('tasks.update_status'));
    }

    // ── The board ────────────────────────────────────────────────────────────

    public function test_the_supervisor_can_drag_a_card_between_columns(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(ManageTasks::class)
            ->call('moveTask', $this->otherTask->id, 'in_progress', [
                'in_progress' => [$this->otherTask->id],
            ]);

        $this->assertSame('in_progress', $this->otherTask->fresh()->status);
    }

    public function test_the_admin_can_still_drag_a_card_between_columns(): void
    {
        Livewire::actingAs($this->a['admin'])
            ->test(ManageTasks::class)
            ->call('moveTask', $this->a['task']->id, 'in_progress', [
                'in_progress' => [$this->a['task']->id],
            ]);

        $this->assertSame('in_progress', $this->a['task']->fresh()->status);
    }

    public function test_the_pm_cannot_drag_a_card_between_columns(): void
    {
        Livewire::actingAs($this->a['pm'])
            ->test(ManageTasks::class)
            ->call('moveTask', $this->a['task']->id, 'in_progress', [
                'in_progress' => [$this->a['task']->id],
            ])
            ->assertForbidden();

        $this->assertSame('pending', $this->a['task']->fresh()->status);
    }

    /**
     * The separation, stated on the screen it governs: the same PM that may
     * not cross columns may still reorder inside one, because that is
     * tasks.update and it still holds it.
     */
    public function test_the_pm_can_still_reorder_within_a_column(): void
    {
        Livewire::actingAs($this->a['pm'])
            ->test(ManageTasks::class)
            ->call('moveTask', $this->a['task']->id, 'pending', [
                'pending' => [$this->a['task']->id],
            ])
            ->assertOk();

        $this->assertSame('pending', $this->a['task']->fresh()->status);
    }

    public function test_the_writer_cannot_drag_a_card_between_columns(): void
    {
        Livewire::actingAs($this->a['writer'])
            ->test(ManageTasks::class)
            ->call('moveTask', $this->a['task']->id, 'in_progress', [
                'in_progress' => [$this->a['task']->id],
            ])
            ->assertForbidden();

        $this->assertSame('pending', $this->a['task']->fresh()->status);
    }

    // ── Cross-agency ─────────────────────────────────────────────────────────

    public function test_the_supervisor_cannot_change_the_status_of_another_agencys_task(): void
    {
        $this->assertFalse(
            Gate::forUser($this->supervisor)->allows('updateStatus', $this->b['task'])
        );
    }

    protected function setPermission(int $organizationId, string $role, string $name, bool $allowed): void
    {
        TenantContext::actingAsOrganization($organizationId, function () use ($role, $name, $allowed) {
            RolePermission::updateOrCreate(
                ['role' => $role, 'permission_id' => Permission::where('name', $name)->firstOrFail()->id],
                ['allowed' => $allowed]
            );
        });

        PermissionService::flushAll();
    }
}
