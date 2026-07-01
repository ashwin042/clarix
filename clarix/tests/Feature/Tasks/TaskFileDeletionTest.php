<?php

namespace Tests\Feature\Tasks;

use App\Livewire\Tasks\TaskDetail;
use App\Models\Task;
use App\Models\TaskFile;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TaskFileDeletionTest extends TestCase
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

    public function test_deleting_regular_file_removes_record_and_r2_object(): void
    {
        $unit = Unit::create(['name' => 'Test Unit']);
        $admin = User::factory()->create(['role' => 'admin']);
        $task = $this->makeTask($admin, $unit);
        $file = $this->makeFile($task, $admin, completed: false);

        Storage::disk('r2')->assertExists($file->file_path);

        Livewire::actingAs($admin)
            ->test(TaskDetail::class, ['task' => $task])
            ->call('deleteFile', $file->id);

        $this->assertDatabaseMissing('task_files', ['id' => $file->id]);
        Storage::disk('r2')->assertMissing($file->file_path);
    }

    public function test_deleting_completed_file_removes_record_and_r2_object(): void
    {
        $unit = Unit::create(['name' => 'Test Unit']);
        $admin = User::factory()->create(['role' => 'admin']);
        $task = $this->makeTask($admin, $unit);
        $file = $this->makeFile($task, $admin, completed: true);

        Storage::disk('r2')->assertExists($file->file_path);

        Livewire::actingAs($admin)
            ->test(TaskDetail::class, ['task' => $task])
            ->call('deleteCompletedFile', $file->id);

        $this->assertDatabaseMissing('task_files', ['id' => $file->id]);
        Storage::disk('r2')->assertMissing($file->file_path);
    }
}
