<?php

namespace Tests\Feature\Authorization;

use App\Livewire\Tasks\ManageTasks;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\Task;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * The Status field in the create/edit task modal.
 *
 * Fourth appearance of the same shape: the field is drawn behind
 * `@if(auth()->user()->isAdmin())`, with a disabled box reading "Pending" for
 * everyone else. The supervisor can already move the very same task across
 * columns on the board — the two screens disagreed about one permission.
 *
 * The markup is only half of it. save() gates on `update` and then writes
 * whatever `status` arrived, so the field's real rule lived nowhere: a role
 * holding tasks.update could set any status through this form, and a PM had a
 * line forcing status back to 'pending' on *every* save — which silently
 * reverted a task the PM merely renamed.
 *
 * So the tests come in pairs: what the screen offers, and what the save
 * actually honours. moveTask() already draws this distinction (`update` to
 * reorder, `updateStatus` to cross a column); this brings the modal in line.
 */
class EditTaskStatusFieldTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $a;

    protected User $supervisor;

    protected User $hr;

    /** Deliberately not 'pending', so a reset to it is visible. */
    protected Task $movedTask;

    private const STATUS_PICKER = 'wire:model="status"';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        PermissionService::flushAll();

        $this->a = $this->populate($this->makeOrganization('etsf', 'Agency A'), 'A');
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

            $this->movedTask = Task::create([
                'title'         => 'Already Moving',
                'task_code'     => 'ETSF_MOVED',
                'unit_id'       => $this->a['unit']->id,
                'created_by'    => $this->a['pm']->id,
                'pm_id'         => $this->a['pm']->id,
                'priority'      => 'medium',
                'status'        => 'in_progress',
                'deadline'      => now()->addDays(7),
                'credit_amount' => 10.00,
            ]);
        });
    }

    // ── What the modal offers ────────────────────────────────────────────────

    /** The reported bug. */
    public function test_the_supervisor_is_given_the_status_dropdown_when_editing(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(ManageTasks::class)
            ->call('openEdit', $this->movedTask->id)
            ->assertSee(self::STATUS_PICKER, false);
    }

    public function test_the_admin_still_gets_the_status_dropdown_when_editing(): void
    {
        Livewire::actingAs($this->a['admin'])
            ->test(ManageTasks::class)
            ->call('openEdit', $this->movedTask->id)
            ->assertSee(self::STATUS_PICKER, false);
    }

    /**
     * Consistency with the board is the point of the fix, so the create half
     * of the same modal is pinned too.
     */
    public function test_the_supervisor_is_given_the_status_dropdown_when_creating(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(ManageTasks::class)
            ->call('openCreate')
            ->assertSee(self::STATUS_PICKER, false);
    }

    public function test_the_pm_is_not_given_the_status_dropdown(): void
    {
        Livewire::actingAs($this->a['pm'])
            ->test(ManageTasks::class)
            ->call('openEdit', $this->movedTask->id)
            ->assertDontSee(self::STATUS_PICKER, false);
    }

    public function test_the_writer_is_not_given_the_status_dropdown(): void
    {
        Livewire::actingAs($this->a['writer'])
            ->test(ManageTasks::class)
            ->call('openEdit', $this->movedTask->id)
            ->assertDontSee(self::STATUS_PICKER, false);
    }

    /**
     * HR is refused the board itself, holding no tasks.view, so the question
     * never reaches the field. Asserted at the point it is actually decided —
     * a render that aborts leaves no snapshot for a chained call, so phrasing
     * this as "does not see the dropdown" would only ever have been testing
     * the harness.
     */
    public function test_hr_cannot_reach_the_task_board_at_all(): void
    {
        $this->assertFalse($this->hr->hasPermission('tasks.view'));

        Livewire::actingAs($this->hr)
            ->test(ManageTasks::class)
            ->assertForbidden();
    }

    /**
     * The locked box read "Pending" no matter what the task actually was, so a
     * PM opening an in-progress task was shown a state it was not in.
     */
    /**
     * Asserted on the disabled input's own value attribute.
     *
     * This first read `assertSee('In Progress')`, which passed against the
     * status *filter* at the top of the same page rather than the locked box —
     * so it would have kept passing had the box gone on saying "Pending".
     * Rebuilding those lists from Task::BOARD_COLUMNS changed the casing and
     * exposed it.
     */
    public function test_the_locked_box_shows_the_tasks_real_status(): void
    {
        Livewire::actingAs($this->a['pm'])
            ->test(ManageTasks::class)
            ->call('openEdit', $this->movedTask->id)
            ->assertSee('value="In progress" disabled', false)
            ->assertDontSee('value="Pending" disabled', false);
    }

    /** Permission-driven, not a role list: grant it and the PM gets the field. */
    public function test_granting_the_permission_gives_a_pm_the_status_dropdown(): void
    {
        $this->grant('pm', 'tasks.update_status');

        Livewire::actingAs($this->a['pm']->fresh())
            ->test(ManageTasks::class)
            ->call('openEdit', $this->movedTask->id)
            ->assertSee(self::STATUS_PICKER, false);
    }

    // ── What the save honours ────────────────────────────────────────────────

    public function test_the_supervisor_can_change_the_status_through_the_modal(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(ManageTasks::class)
            ->call('openEdit', $this->movedTask->id)
            ->set('status', 'completed')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('completed', $this->movedTask->fresh()->status);
    }

    public function test_the_admin_can_still_change_the_status_through_the_modal(): void
    {
        Livewire::actingAs($this->a['admin'])
            ->test(ManageTasks::class)
            ->call('openEdit', $this->movedTask->id)
            ->set('status', 'completed')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('completed', $this->movedTask->fresh()->status);
    }

    /**
     * The bug behind the locked field: a PM saving this form forced status to
     * 'pending' regardless of what the task was, so renaming an in-progress
     * task quietly moved it back to the first column.
     */
    public function test_a_pm_renaming_a_task_does_not_move_it_back_to_pending(): void
    {
        Livewire::actingAs($this->a['pm'])
            ->test(ManageTasks::class)
            ->call('openEdit', $this->movedTask->id)
            ->set('title', 'Renamed By The PM')
            ->call('save')
            ->assertHasNoErrors();

        $fresh = $this->movedTask->fresh();

        $this->assertSame('Renamed By The PM', $fresh->title);
        $this->assertSame('in_progress', $fresh->status, 'the PM must not have moved the task');
    }

    /** A posted status is ignored rather than refused, so the rest of the edit lands. */
    public function test_a_pm_posting_a_status_it_may_not_set_is_ignored(): void
    {
        Livewire::actingAs($this->a['pm'])
            ->test(ManageTasks::class)
            ->call('openEdit', $this->movedTask->id)
            ->set('title', 'Still Renamed')
            ->set('status', 'completed')
            ->call('save')
            ->assertHasNoErrors();

        $fresh = $this->movedTask->fresh();

        $this->assertSame('Still Renamed', $fresh->title);
        $this->assertSame('in_progress', $fresh->status);
    }

    public function test_a_writer_granted_task_update_still_cannot_change_the_status(): void
    {
        $this->grant('writer', 'tasks.update');

        Livewire::actingAs($this->a['writer']->fresh())
            ->test(ManageTasks::class)
            ->call('openEdit', $this->movedTask->id)
            ->set('status', 'completed')
            ->call('save');

        $this->assertSame('in_progress', $this->movedTask->fresh()->status);
    }

    public function test_granting_the_permission_lets_a_pm_change_the_status(): void
    {
        $this->grant('pm', 'tasks.update_status');

        Livewire::actingAs($this->a['pm']->fresh())
            ->test(ManageTasks::class)
            ->call('openEdit', $this->movedTask->id)
            ->set('status', 'completed')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('completed', $this->movedTask->fresh()->status);
    }

    /** Creating still starts a task at pending for anyone who may not choose. */
    public function test_a_pm_creating_a_task_still_starts_it_at_pending(): void
    {
        Livewire::actingAs($this->a['pm'])
            ->test(ManageTasks::class)
            ->call('openCreate')
            ->set('title', 'Filed By The PM')
            ->set('task_code', 'ETSF_NEW')
            ->set('status', 'completed')
            ->set('priority', 'medium')
            ->set('deadline', now()->addDays(5)->toDateString())
            ->set('credit_amount', '5')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'task_code' => 'ETSF_NEW',
            'status'    => 'pending',
        ]);
    }

    protected function grant(string $role, string $name): void
    {
        TenantContext::actingAsOrganization($this->a['organization']->id, function () use ($role, $name) {
            RolePermission::updateOrCreate(
                ['role' => $role, 'permission_id' => Permission::where('name', $name)->firstOrFail()->id],
                ['allowed' => true]
            );
        });

        PermissionService::flushAll();
    }
}
