<?php

namespace Tests\Feature\Tasks;

use App\Livewire\Tasks\TaskDetail;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskDetailRoleRestrictionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUnit(): Unit
    {
        return Unit::create(['name' => 'Test Unit']);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makePm(Unit $unit): User
    {
        return User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
    }

    private function makeTask(Unit $unit, User $creator, User $pm, ?int $assignedAdminId = null): Task
    {
        return Task::create([
            'title'             => 'Test Task',
            'task_code'         => 'TT_001',
            'unit_id'           => $unit->id,
            'created_by'        => $creator->id,
            'pm_id'             => $pm->id,
            'priority'          => 'medium',
            'status'            => 'pending',
            'deadline'          => now()->addDays(7),
            'credit_amount'     => 1.00,
            'assigned_admin_id' => $assignedAdminId,
        ]);
    }

    public function test_admin_can_update_task_status(): void
    {
        $unit  = $this->makeUnit();
        $admin = $this->makeAdmin();
        $pm    = $this->makePm($unit);
        $task  = $this->makeTask($unit, $admin, $pm);

        $this->actingAs($admin);

        Livewire::test(TaskDetail::class, ['task' => $task])
            ->call('updateTaskStatus', 'completed')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'completed']);
    }

    public function test_pm_cannot_update_task_status(): void
    {
        $unit  = $this->makeUnit();
        $admin = $this->makeAdmin();
        $pm    = $this->makePm($unit);
        $task  = $this->makeTask($unit, $admin, $pm);

        $this->actingAs($pm);

        Livewire::test(TaskDetail::class, ['task' => $task])
            ->call('updateTaskStatus', 'completed')
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'pending']);
    }

    public function test_assigned_admin_name_shown_in_details(): void
    {
        $unit          = $this->makeUnit();
        $admin         = $this->makeAdmin();
        $pm            = $this->makePm($unit);
        $assignedAdmin = $this->makeAdmin();
        $task          = $this->makeTask($unit, $admin, $pm, $assignedAdmin->id);

        $this->actingAs($admin);

        Livewire::test(TaskDetail::class, ['task' => $task])
            ->assertSee($assignedAdmin->name)
            ->assertSee('Assigned To');
    }

    public function test_unassigned_task_shows_assigned_to_label(): void
    {
        $unit  = $this->makeUnit();
        $admin = $this->makeAdmin();
        $pm    = $this->makePm($unit);
        $task  = $this->makeTask($unit, $admin, $pm);

        $this->actingAs($admin);

        Livewire::test(TaskDetail::class, ['task' => $task])
            ->assertSee('Assigned To');
    }
}
