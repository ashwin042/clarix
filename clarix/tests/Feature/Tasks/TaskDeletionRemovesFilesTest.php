<?php

namespace Tests\Feature\Tasks;

use App\Livewire\Tasks\ManageTasks;
use App\Models\Task;
use App\Models\TaskFile;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TaskDeletionRemovesFilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        Storage::fake('r2');
    }

    private function makeTask(User $pm, Unit $unit): Task
    {
        return Task::create([
            'title'         => 'Test Task',
            'task_code'     => 'TT_001',
            'unit_id'       => $unit->id,
            'created_by'    => $pm->id,
            'pm_id'         => $pm->id,
            'priority'      => 'medium',
            'status'        => 'pending',
            'deadline'      => now()->addDays(7),
            'credit_amount' => 1.00,
        ]);
    }

    private function makeFile(Task $task, User $uploader, bool $completed): TaskFile
    {
        $path = 'task-files/'.uniqid().'.pdf';
        Storage::disk('r2')->put($path, 'dummy-contents');

        return TaskFile::create([
            'task_id'           => $task->id,
            'file_path'         => $path,
            'original_name'     => 'doc.pdf',
            'file_size'         => 100,
            'mime_type'         => 'application/pdf',
            'uploaded_by'       => $uploader->id,
            'is_completed_file' => $completed,
        ]);
    }

    public function test_deleting_task_removes_all_files_from_r2_and_database(): void
    {
        $unit  = Unit::create(['name' => 'Test Unit']);
        $admin = User::factory()->create(['role' => 'admin']);
        $task  = $this->makeTask($admin, $unit);

        $regular   = $this->makeFile($task, $admin, completed: false);
        $completed = $this->makeFile($task, $admin, completed: true);

        Storage::disk('r2')->assertExists($regular->file_path);
        Storage::disk('r2')->assertExists($completed->file_path);

        Livewire::actingAs($admin)
            ->test(ManageTasks::class)
            ->call('openDeleteModal', $task->id)
            ->call('confirmDelete');

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
        $this->assertDatabaseMissing('task_files', ['id' => $regular->id]);
        $this->assertDatabaseMissing('task_files', ['id' => $completed->id]);

        Storage::disk('r2')->assertMissing($regular->file_path);
        Storage::disk('r2')->assertMissing($completed->file_path);
    }
}
