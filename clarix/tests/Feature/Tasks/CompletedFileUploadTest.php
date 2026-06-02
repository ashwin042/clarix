<?php

namespace Tests\Feature\Tasks;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\CompletedFileUploadedNotification;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompletedFileUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        Storage::fake('r2');
        Notification::fake();
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

    private function makeTask(Unit $unit, User $pm, ?User $assignedAdmin = null): Task
    {
        return Task::create([
            'title'             => 'Test Task',
            'task_code'         => 'TT_001',
            'unit_id'           => $unit->id,
            'created_by'        => $pm->id,
            'pm_id'             => $pm->id,
            'priority'          => 'medium',
            'status'            => 'pending',
            'deadline'          => now()->addDays(7),
            'credit_amount'     => 1.00,
            'assigned_admin_id' => $assignedAdmin?->id,
        ]);
    }

    private function assignWriter(Task $task, User $writer): TaskAssignment
    {
        return TaskAssignment::create([
            'task_id'     => $task->id,
            'writer_id'   => $writer->id,
            'assigned_by' => $task->created_by,
            'status'      => 'in_progress',
        ]);
    }

    public function test_assigned_writer_can_upload_completed_file(): void
    {
        $unit   = $this->makeUnit();
        $pm     = $this->makePm($unit);
        $writer = $this->makeWriter();
        $task   = $this->makeTask($unit, $pm);
        $this->assignWriter($task, $writer);

        $this->actingAs($writer)
            ->post(route('tasks.completed-files.store', $task), [
                'files' => [UploadedFile::fake()->create('deliverable.pdf', 100)],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('task_files', [
            'task_id'           => $task->id,
            'original_name'     => 'deliverable.pdf',
            'is_completed_file' => true,
        ]);
    }

    public function test_unassigned_writer_cannot_upload_completed_file(): void
    {
        $unit   = $this->makeUnit();
        $pm     = $this->makePm($unit);
        $writer = $this->makeWriter();
        $task   = $this->makeTask($unit, $pm);

        $this->actingAs($writer)
            ->post(route('tasks.completed-files.store', $task), [
                'files' => [UploadedFile::fake()->create('deliverable.pdf', 100)],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('task_files', ['task_id' => $task->id]);
    }

    public function test_pm_cannot_upload_completed_file(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);
        $task = $this->makeTask($unit, $pm);

        $this->actingAs($pm)
            ->post(route('tasks.completed-files.store', $task), [
                'files' => [UploadedFile::fake()->create('deliverable.pdf', 100)],
            ])
            ->assertForbidden();
    }

    public function test_admin_can_upload_completed_file(): void
    {
        $unit  = $this->makeUnit();
        $pm    = $this->makePm($unit);
        $admin = $this->makeAdmin();
        $task  = $this->makeTask($unit, $pm);

        $this->actingAs($admin)
            ->post(route('tasks.completed-files.store', $task), [
                'files' => [UploadedFile::fake()->create('deliverable.pdf', 100)],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('task_files', [
            'task_id'           => $task->id,
            'is_completed_file' => true,
        ]);
    }

    public function test_upload_flips_all_assignments_to_ready_for_review(): void
    {
        $unit    = $this->makeUnit();
        $pm      = $this->makePm($unit);
        $writer1 = $this->makeWriter();
        $writer2 = $this->makeWriter();
        $task    = $this->makeTask($unit, $pm);
        $a1 = $this->assignWriter($task, $writer1);
        $a2 = $this->assignWriter($task, $writer2);

        $this->actingAs($writer1)
            ->post(route('tasks.completed-files.store', $task), [
                'files' => [UploadedFile::fake()->create('deliverable.pdf', 100)],
            ]);

        $this->assertDatabaseHas('task_assignments', ['id' => $a1->id, 'status' => 'ready_for_review']);
        $this->assertDatabaseHas('task_assignments', ['id' => $a2->id, 'status' => 'ready_for_review']);
    }

    public function test_upload_notifies_pm_and_assigned_admin(): void
    {
        $unit          = $this->makeUnit();
        $pm            = $this->makePm($unit);
        $assignedAdmin = $this->makeAdmin();
        $writer        = $this->makeWriter();
        $task          = $this->makeTask($unit, $pm, $assignedAdmin);
        $this->assignWriter($task, $writer);

        $this->actingAs($writer)
            ->post(route('tasks.completed-files.store', $task), [
                'files' => [UploadedFile::fake()->create('deliverable.pdf', 100)],
            ]);

        Notification::assertSentTo($pm, CompletedFileUploadedNotification::class);
        Notification::assertSentTo($assignedAdmin, CompletedFileUploadedNotification::class);
    }

    public function test_upload_does_not_notify_when_no_pm_or_admin_assigned(): void
    {
        $unit   = $this->makeUnit();
        $pm     = $this->makePm($unit);
        $writer = $this->makeWriter();
        $task   = Task::create([
            'title'         => 'No PM Task',
            'task_code'     => 'TT_002',
            'unit_id'       => $unit->id,
            'created_by'    => $pm->id,
            'pm_id'         => null,
            'priority'      => 'medium',
            'status'        => 'pending',
            'deadline'      => now()->addDays(7),
            'credit_amount' => 1.00,
        ]);
        $this->assignWriter($task, $writer);

        $this->actingAs($writer)
            ->post(route('tasks.completed-files.store', $task), [
                'files' => [UploadedFile::fake()->create('deliverable.pdf', 100)],
            ]);

        Notification::assertNothingSent();
    }
}
