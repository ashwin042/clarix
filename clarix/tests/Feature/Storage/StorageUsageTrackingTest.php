<?php

namespace Tests\Feature\Storage;

use App\Models\Task;
use App\Models\TaskFile;
use App\Models\Unit;
use App\Models\User;
use App\Services\StorageUsageService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageUsageTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        Storage::fake('r2');
    }

    private function makeUnit(string $name = 'Test Unit'): Unit
    {
        return Unit::create(['name' => $name]);
    }

    private function makePm(Unit $unit): User
    {
        return User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makeTask(Unit $unit, User $pm, string $code = 'TT_001'): Task
    {
        return Task::create([
            'title'         => 'Test Task',
            'task_code'     => $code,
            'unit_id'       => $unit->id,
            'created_by'    => $pm->id,
            'pm_id'         => $pm->id,
            'priority'      => 'medium',
            'status'        => 'pending',
            'deadline'      => now()->addDays(7),
            'credit_amount' => 1.00,
        ]);
    }

    private function makeFile(Task $task, User $uploader, int $bytes): TaskFile
    {
        return $task->files()->create([
            'file_path'     => 'task-files/'.$task->task_code.'/'.uniqid().'.txt',
            'original_name' => 'doc.txt',
            'file_size'     => $bytes,
            'mime_type'     => 'text/plain',
            'uploaded_by'   => $uploader->id,
        ]);
    }

    private function tracked(Unit $unit): int
    {
        return app(StorageUsageService::class)->bytesFor($unit->id);
    }

    public function test_creating_a_task_file_increments_the_units_usage(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);
        $task = $this->makeTask($unit, $pm);

        $this->makeFile($task, $pm, 1500);

        $this->assertSame(1500, $this->tracked($unit));
    }

    public function test_multiple_files_accumulate(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);
        $task = $this->makeTask($unit, $pm);

        $this->makeFile($task, $pm, 1000);
        $this->makeFile($task, $pm, 2500);

        $this->assertSame(3500, $this->tracked($unit));
    }

    public function test_deleting_a_task_file_decrements_the_units_usage(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);
        $task = $this->makeTask($unit, $pm);

        $keep   = $this->makeFile($task, $pm, 1000);
        $remove = $this->makeFile($task, $pm, 2500);

        $remove->delete();

        $this->assertSame(1000, $this->tracked($unit));
        $this->assertDatabaseHas('task_files', ['id' => $keep->id]);
    }

    public function test_usage_is_tracked_separately_per_unit(): void
    {
        $unitA = $this->makeUnit('Unit A');
        $unitB = $this->makeUnit('Unit B');
        $pmA   = $this->makePm($unitA);
        $pmB   = $this->makePm($unitB);

        $this->makeFile($this->makeTask($unitA, $pmA, 'A_001'), $pmA, 1000);
        $this->makeFile($this->makeTask($unitB, $pmB, 'B_001'), $pmB, 4000);

        $this->assertSame(1000, $this->tracked($unitA));
        $this->assertSame(4000, $this->tracked($unitB));
    }

    /**
     * deleteWithFiles mass-deletes the file rows, which fires no model events,
     * so the decrement has to be accounted for by the method itself.
     */
    public function test_deleting_a_task_with_files_decrements_by_the_whole_total(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);
        $task = $this->makeTask($unit, $pm);

        $this->makeFile($task, $pm, 1000);
        $this->makeFile($task, $pm, 2500);

        $this->assertSame(3500, $this->tracked($unit));

        $task->deleteWithFiles();

        $this->assertSame(0, $this->tracked($unit));
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_deleting_one_task_leaves_another_tasks_usage_intact(): void
    {
        $unit  = $this->makeUnit();
        $pm    = $this->makePm($unit);
        $taskA = $this->makeTask($unit, $pm, 'TT_001');
        $taskB = $this->makeTask($unit, $pm, 'TT_002');

        $this->makeFile($taskA, $pm, 1000);
        $this->makeFile($taskB, $pm, 2500);

        $taskA->deleteWithFiles();

        $this->assertSame(2500, $this->tracked($unit));
    }

    /**
     * bytes_used is unsigned, so an over-large decrement has to floor at zero
     * rather than push the column negative and fail the write.
     */
    public function test_decrement_clamps_at_zero(): void
    {
        $unit  = $this->makeUnit();
        $usage = app(StorageUsageService::class);

        $usage->increment($unit->id, 500);
        $usage->decrement($unit->id, 5000);

        $this->assertSame(0, $this->tracked($unit));
    }

    public function test_uploading_through_the_file_route_increments_usage(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);
        $task = $this->makeTask($unit, $pm);

        $upload = UploadedFile::fake()->create('brief.pdf', 12, 'application/pdf');

        $this->actingAs($pm)
            ->post(route('tasks.files.store', $task), ['files' => [$upload]])
            ->assertRedirect();

        $this->assertSame((int) $task->files()->sum('file_size'), $this->tracked($unit));
        $this->assertGreaterThan(0, $this->tracked($unit));
    }

    public function test_deleting_through_the_file_route_decrements_usage(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);
        $task = $this->makeTask($unit, $pm);
        $file = $this->makeFile($task, $pm, 2000);

        // Deleting a file is admin-only, like every other deletion here, so
        // the actor is an admin. What this test is about is the byte count
        // coming back down, not who may press the button.
        $this->actingAs($this->makeAdmin())
            ->delete(route('tasks.files.destroy', [$task, $file]))
            ->assertRedirect();

        $this->assertSame(0, $this->tracked($unit));
    }

    public function test_a_unit_with_no_files_tracks_zero(): void
    {
        $unit = $this->makeUnit();

        $this->assertSame(0, $this->tracked($unit));
    }
}
