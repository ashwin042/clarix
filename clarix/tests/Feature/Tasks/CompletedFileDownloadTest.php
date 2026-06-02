<?php

namespace Tests\Feature\Tasks;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskFile;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompletedFileDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        Storage::fake('r2');
    }

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

    private function makeWriter(): User
    {
        return User::factory()->create(['role' => 'writer']);
    }

    private function makeTask(Unit $unit, User $pm, string $status = 'pending'): Task
    {
        return Task::create([
            'title'         => 'Test Task',
            'task_code'     => 'TT_001',
            'unit_id'       => $unit->id,
            'created_by'    => $pm->id,
            'pm_id'         => $pm->id,
            'priority'      => 'medium',
            'status'        => $status,
            'deadline'      => now()->addDays(7),
            'credit_amount' => 1.00,
        ]);
    }

    private function makeCompletedFile(Task $task, User $uploader): TaskFile
    {
        $fakePath = 'task-files/TT_001/completed/test.pdf';
        Storage::disk('r2')->put($fakePath, 'content');

        return TaskFile::create([
            'task_id'           => $task->id,
            'file_path'         => $fakePath,
            'original_name'     => 'deliverable.pdf',
            'file_size'         => 1024,
            'mime_type'         => 'application/pdf',
            'uploaded_by'       => $uploader->id,
            'is_completed_file' => true,
        ]);
    }

    private function makeRegularFile(Task $task, User $uploader): TaskFile
    {
        $fakePath = 'task-files/TT_001/brief.pdf';
        Storage::disk('r2')->put($fakePath, 'content');

        return TaskFile::create([
            'task_id'           => $task->id,
            'file_path'         => $fakePath,
            'original_name'     => 'brief.pdf',
            'file_size'         => 1024,
            'mime_type'         => 'application/pdf',
            'uploaded_by'       => $uploader->id,
            'is_completed_file' => false,
        ]);
    }

    public function test_admin_can_download_completed_file_when_task_pending(): void
    {
        $unit  = $this->makeUnit();
        $pm    = $this->makePm($unit);
        $admin = $this->makeAdmin();
        $task  = $this->makeTask($unit, $pm, 'pending');
        $file  = $this->makeCompletedFile($task, $admin);

        $this->actingAs($admin)
            ->get(route('tasks.files.download', [$task, $file]))
            ->assertOk();
    }

    public function test_assigned_writer_can_download_completed_file_when_task_pending(): void
    {
        $unit   = $this->makeUnit();
        $pm     = $this->makePm($unit);
        $writer = $this->makeWriter();
        $task   = $this->makeTask($unit, $pm, 'pending');
        TaskAssignment::create([
            'task_id'     => $task->id,
            'writer_id'   => $writer->id,
            'assigned_by' => $pm->id,
            'status'      => 'in_progress',
        ]);
        $file = $this->makeCompletedFile($task, $writer);

        $this->actingAs($writer)
            ->get(route('tasks.files.download', [$task, $file]))
            ->assertOk();
    }

    public function test_pm_cannot_download_completed_file_when_task_not_completed(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);
        $task = $this->makeTask($unit, $pm, 'in_progress');
        $file = $this->makeCompletedFile($task, $pm);

        $this->actingAs($pm)
            ->get(route('tasks.files.download', [$task, $file]))
            ->assertForbidden();
    }

    public function test_pm_can_download_completed_file_when_task_is_completed(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);
        $task = $this->makeTask($unit, $pm, 'completed');
        $file = $this->makeCompletedFile($task, $pm);

        $this->actingAs($pm)
            ->get(route('tasks.files.download', [$task, $file]))
            ->assertOk();
    }

    public function test_pm_can_always_download_regular_file(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);
        $task = $this->makeTask($unit, $pm, 'pending');
        $file = $this->makeRegularFile($task, $pm);

        $this->actingAs($pm)
            ->get(route('tasks.files.download', [$task, $file]))
            ->assertOk();
    }
}
