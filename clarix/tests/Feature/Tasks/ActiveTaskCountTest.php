<?php

namespace Tests\Feature\Tasks;

use App\Livewire\Tasks\TaskDetail;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActiveTaskCountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function makeTask(User $pm, Unit $unit, string $status): Task
    {
        static $seq = 0;
        $seq++;

        return Task::create([
            'title'         => "Task {$status} {$seq}",
            'task_code'     => "TT_{$seq}",
            'unit_id'       => $unit->id,
            'created_by'    => $pm->id,
            'pm_id'         => $pm->id,
            'priority'      => 'medium',
            'status'        => $status,
            'deadline'      => now()->addDays(7),
            'credit_amount' => 1.00,
        ]);
    }

    private function assign(Task $task, User $writer, User $admin): void
    {
        TaskAssignment::create([
            'task_id'     => $task->id,
            'writer_id'   => $writer->id,
            'assigned_by' => $admin->id,
            'status'      => 'in_progress',
        ]);
    }

    public function test_active_count_includes_only_pending_and_in_progress_tasks(): void
    {
        $unit  = Unit::create(['name' => 'Test Unit']);
        $admin = User::factory()->create(['role' => 'admin']);
        $pm    = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);

        // The writer we assert on — 1 pending + 1 in_progress + 1 completed + 1 cancelled.
        $writer = User::factory()->create(['role' => 'writer', 'name' => 'Active Writer']);
        $this->assign($this->makeTask($pm, $unit, 'pending'), $writer, $admin);
        $this->assign($this->makeTask($pm, $unit, 'in_progress'), $writer, $admin);
        $this->assign($this->makeTask($pm, $unit, 'completed'), $writer, $admin);
        $this->assign($this->makeTask($pm, $unit, 'cancelled'), $writer, $admin);

        // A writer with only completed/cancelled tasks should read 0 active.
        $doneWriter = User::factory()->create(['role' => 'writer', 'name' => 'Done Writer']);
        $this->assign($this->makeTask($pm, $unit, 'completed'), $doneWriter, $admin);
        $this->assign($this->makeTask($pm, $unit, 'cancelled'), $doneWriter, $admin);

        // A separate task the admin opens the assign modal from (unassigned writers listed).
        $viewTask = $this->makeTask($pm, $unit, 'pending');

        $component = Livewire::actingAs($admin)->test(TaskDetail::class, ['task' => $viewTask]);

        $writers = $component->viewData('availableWriters')->keyBy('name');

        $this->assertSame(2, (int) $writers['Active Writer']->active_tasks);
        $this->assertSame(0, (int) $writers['Done Writer']->active_tasks);
    }
}
