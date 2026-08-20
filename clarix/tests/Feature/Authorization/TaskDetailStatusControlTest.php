<?php

namespace Tests\Feature\Authorization;

use App\Livewire\Tasks\TaskDetail;
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
 * The status control on a task's own page.
 *
 * Fifth appearance of the same shape, and this one is not a missing feature:
 * the quick-change select has been in the markup all along, behind
 * `@if(auth()->user()->isAdmin())`. So a supervisor who could drag the card
 * across the board, and choose a status in the edit modal, was shown a plain
 * badge on the task's own page.
 *
 * updateTaskStatus() behind it was isAdmin() too, and took an unvalidated
 * string straight to applyStatusChange(), which writes it to the column as
 * given. That mattered less while one role could reach it; it is pinned here
 * because widening the door is the moment to check the frame.
 */
class TaskDetailStatusControlTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $a;

    protected User $supervisor;

    protected User $hr;

    /** Not 'pending', so a control that misreports the state is visible. */
    protected Task $movedTask;

    private const STATUS_CONTROL = 'wire:change="updateTaskStatus($event.target.value)"';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        PermissionService::flushAll();

        $this->a = $this->populate($this->makeOrganization('tdsc', 'Agency A'), 'A');
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
                'task_code'     => 'TDSC_MOVED',
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

    // ── Who is offered the control ───────────────────────────────────────────

    /** The reported gap. */
    public function test_the_supervisor_is_offered_the_status_control(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(TaskDetail::class, ['task' => $this->movedTask])
            ->assertSee(self::STATUS_CONTROL, false);
    }

    public function test_the_admin_is_still_offered_the_status_control(): void
    {
        Livewire::actingAs($this->a['admin'])
            ->test(TaskDetail::class, ['task' => $this->movedTask])
            ->assertSee(self::STATUS_CONTROL, false);
    }

    public function test_the_pm_is_not_offered_the_status_control(): void
    {
        Livewire::actingAs($this->a['pm'])
            ->test(TaskDetail::class, ['task' => $this->movedTask])
            ->assertDontSee(self::STATUS_CONTROL, false);
    }

    public function test_the_writer_is_not_offered_the_status_control(): void
    {
        Livewire::actingAs($this->a['writer'])
            ->test(TaskDetail::class, ['task' => $this->movedTask])
            ->assertDontSee(self::STATUS_CONTROL, false);
    }

    public function test_hr_is_not_offered_the_status_control(): void
    {
        Livewire::actingAs($this->hr)
            ->test(TaskDetail::class, ['task' => $this->movedTask])
            ->assertDontSee(self::STATUS_CONTROL, false);
    }

    /** Permission-driven, not a role list. */
    public function test_granting_the_permission_offers_a_pm_the_status_control(): void
    {
        $this->grant('pm', 'tasks.update_status');

        Livewire::actingAs($this->a['pm']->fresh())
            ->test(TaskDetail::class, ['task' => $this->movedTask])
            ->assertSee(self::STATUS_CONTROL, false);
    }

    /**
     * The control offered every status except the one this task is actually
     * in, so a task sent for review displayed as "Pending" — the same
     * misreporting the edit modal's locked box used to do.
     */
    public function test_the_control_can_represent_a_task_sent_for_review(): void
    {
        $this->movedTask->update(['status' => 'sent_for_review']);

        Livewire::actingAs($this->supervisor)
            ->test(TaskDetail::class, ['task' => $this->movedTask->fresh()])
            ->assertSee('value="sent_for_review" selected', false);
    }

    // ── Who may actually use it ──────────────────────────────────────────────

    public function test_the_supervisor_can_change_the_status(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(TaskDetail::class, ['task' => $this->movedTask])
            ->call('updateTaskStatus', 'completed');

        $this->assertSame('completed', $this->movedTask->fresh()->status);
    }

    public function test_the_admin_can_still_change_the_status(): void
    {
        Livewire::actingAs($this->a['admin'])
            ->test(TaskDetail::class, ['task' => $this->movedTask])
            ->call('updateTaskStatus', 'completed');

        $this->assertSame('completed', $this->movedTask->fresh()->status);
    }

    public function test_the_pm_cannot_change_the_status(): void
    {
        Livewire::actingAs($this->a['pm'])
            ->test(TaskDetail::class, ['task' => $this->movedTask])
            ->call('updateTaskStatus', 'completed')
            ->assertForbidden();

        $this->assertSame('in_progress', $this->movedTask->fresh()->status);
    }

    public function test_the_writer_cannot_change_the_status(): void
    {
        Livewire::actingAs($this->a['writer'])
            ->test(TaskDetail::class, ['task' => $this->movedTask])
            ->call('updateTaskStatus', 'completed')
            ->assertForbidden();

        $this->assertSame('in_progress', $this->movedTask->fresh()->status);
    }

    public function test_hr_cannot_change_the_status(): void
    {
        Livewire::actingAs($this->hr)
            ->test(TaskDetail::class, ['task' => $this->movedTask])
            ->call('updateTaskStatus', 'completed')
            ->assertForbidden();

        $this->assertSame('in_progress', $this->movedTask->fresh()->status);
    }

    public function test_granting_the_permission_lets_a_pm_change_the_status(): void
    {
        $this->grant('pm', 'tasks.update_status');

        Livewire::actingAs($this->a['pm']->fresh())
            ->test(TaskDetail::class, ['task' => $this->movedTask])
            ->call('updateTaskStatus', 'completed');

        $this->assertSame('completed', $this->movedTask->fresh()->status);
    }

    /**
     * applyStatusChange() writes what it is handed, and this is a public
     * Livewire method taking a bare string — so the whitelist has to be here
     * rather than in the markup that happens to offer five options.
     */
    public function test_a_status_outside_the_known_set_is_refused(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(TaskDetail::class, ['task' => $this->movedTask])
            ->call('updateTaskStatus', 'not_a_real_status')
            ->assertHasErrors('status');

        $this->assertSame('in_progress', $this->movedTask->fresh()->status);
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
