<?php

namespace Tests\Feature\Storage;

use App\Models\Task;
use App\Models\TaskFile;
use App\Models\Unit;
use App\Models\User;
use App\Services\StorageUsageService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackfillStoragePrefixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        Storage::fake('r2');
    }

    private function makeUnit(string $name = 'Unit A'): Unit
    {
        return Unit::create(['name' => $name]);
    }

    private function makeTask(Unit $unit, string $code = 'TT_001'): Task
    {
        $pm = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);

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

    /**
     * Create a file row plus its object, without letting the observer move the
     * storage counters, so totals can be asserted independently.
     */
    private function makeFileAt(Task $task, string $path, string $body = 'contents', bool $completed = false): TaskFile
    {
        Storage::disk('r2')->put($path, $body);

        // withoutEvents silences the creating hook that stamps
        // organization_id along with the observer this is here to avoid, so
        // the owner is copied off the task explicitly. forceCreate is what
        // gets it past the deliberate absence of organization_id in $fillable.
        return TaskFile::withoutEvents(fn () => $task->files()->forceCreate([
            'organization_id'   => $task->organization_id,
            'file_path'         => $path,
            'original_name'     => 'doc.pdf',
            'file_size'         => strlen($body),
            'mime_type'         => 'application/pdf',
            'uploaded_by'       => $task->pm_id,
            'is_completed_file' => $completed,
        ]));
    }

    public function test_it_moves_a_legacy_file_to_the_unit_prefix(): void
    {
        $unit = $this->makeUnit();
        $task = $this->makeTask($unit);
        $file = $this->makeFileAt($task, 'task-files/TT_001/abc.pdf', 'hello');

        $this->artisan('storage:backfill-prefix')->assertSuccessful();

        $expected = "task-files/{$unit->id}/TT_001/abc.pdf";

        $this->assertSame($expected, $file->fresh()->file_path);
        Storage::disk('r2')->assertExists($expected);
        Storage::disk('r2')->assertMissing('task-files/TT_001/abc.pdf');
        $this->assertSame('hello', Storage::disk('r2')->get($expected));
    }

    public function test_it_preserves_the_completed_segment(): void
    {
        $unit = $this->makeUnit();
        $task = $this->makeTask($unit);
        $file = $this->makeFileAt($task, 'task-files/TT_001/completed/done.pdf', 'x', true);

        $this->artisan('storage:backfill-prefix')->assertSuccessful();

        $this->assertSame(
            "task-files/{$unit->id}/TT_001/completed/done.pdf",
            $file->fresh()->file_path
        );
    }

    public function test_it_leaves_already_migrated_files_alone(): void
    {
        $unit = $this->makeUnit();
        $task = $this->makeTask($unit);
        $path = "task-files/{$unit->id}/TT_001/abc.pdf";
        $file = $this->makeFileAt($task, $path);

        $this->artisan('storage:backfill-prefix')
            ->expectsOutputToContain('already unit-scoped')
            ->assertSuccessful();

        $this->assertSame($path, $file->fresh()->file_path);
        Storage::disk('r2')->assertExists($path);
    }

    public function test_running_it_twice_is_a_no_op_the_second_time(): void
    {
        $unit = $this->makeUnit();
        $task = $this->makeTask($unit);
        $file = $this->makeFileAt($task, 'task-files/TT_001/abc.pdf', 'hello');

        $this->artisan('storage:backfill-prefix')->assertSuccessful();
        $after = $file->fresh()->file_path;

        $this->artisan('storage:backfill-prefix')
            ->expectsOutputToContain('already unit-scoped')
            ->assertSuccessful();

        $this->assertSame($after, $file->fresh()->file_path);
        $this->assertCount(1, Storage::disk('r2')->allFiles());
    }

    /**
     * An interrupted run leaves the destination copied but the row still
     * pointing at the source. Re-running has to finish the job, not duplicate
     * or fail.
     */
    public function test_it_resumes_when_the_destination_already_exists(): void
    {
        $unit = $this->makeUnit();
        $task = $this->makeTask($unit);
        $file = $this->makeFileAt($task, 'task-files/TT_001/abc.pdf', 'hello');

        $target = "task-files/{$unit->id}/TT_001/abc.pdf";
        Storage::disk('r2')->put($target, 'hello');

        $this->artisan('storage:backfill-prefix')->assertSuccessful();

        $this->assertSame($target, $file->fresh()->file_path);
        Storage::disk('r2')->assertMissing('task-files/TT_001/abc.pdf');
        $this->assertCount(1, Storage::disk('r2')->allFiles());
    }

    public function test_dry_run_changes_nothing(): void
    {
        $unit = $this->makeUnit();
        $task = $this->makeTask($unit);
        $file = $this->makeFileAt($task, 'task-files/TT_001/abc.pdf');

        $this->artisan('storage:backfill-prefix', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertSame('task-files/TT_001/abc.pdf', $file->fresh()->file_path);
        Storage::disk('r2')->assertExists('task-files/TT_001/abc.pdf');
        Storage::disk('r2')->assertMissing("task-files/{$unit->id}/TT_001/abc.pdf");
    }

    /**
     * A row whose object has already vanished must not have its path rewritten
     * to a second absent key — that would hide the problem from reconciliation.
     */
    public function test_a_file_with_no_object_is_skipped_and_reported(): void
    {
        $unit = $this->makeUnit();
        $task = $this->makeTask($unit);

        $file = TaskFile::withoutEvents(fn () => $task->files()->forceCreate([
            'organization_id' => $task->organization_id,
            'file_path'     => 'task-files/TT_001/gone.pdf',
            'original_name' => 'gone.pdf',
            'file_size'     => 10,
            'mime_type'     => 'application/pdf',
            'uploaded_by'   => $task->pm_id,
        ]));

        $this->artisan('storage:backfill-prefix')
            ->expectsOutputToContain('missing source')
            ->assertSuccessful();

        $this->assertSame('task-files/TT_001/gone.pdf', $file->fresh()->file_path);
    }

    public function test_two_units_sharing_a_task_code_separate_cleanly(): void
    {
        $unitA = $this->makeUnit('Unit A');
        $unitB = $this->makeUnit('Unit B');
        $taskA = $this->makeTask($unitA, 'SHARED');
        $taskB = $this->makeTask($unitB, 'SHARED');

        $fileA = $this->makeFileAt($taskA, 'task-files/SHARED/a.pdf', 'aaa');
        $fileB = $this->makeFileAt($taskB, 'task-files/SHARED/b.pdf', 'bbbb');

        $this->artisan('storage:backfill-prefix')->assertSuccessful();

        $this->assertSame("task-files/{$unitA->id}/SHARED/a.pdf", $fileA->fresh()->file_path);
        $this->assertSame("task-files/{$unitB->id}/SHARED/b.pdf", $fileB->fresh()->file_path);

        $this->assertSame('aaa', Storage::disk('r2')->get($fileA->fresh()->file_path));
        $this->assertSame('bbbb', Storage::disk('r2')->get($fileB->fresh()->file_path));

        $this->assertEmpty(array_intersect(
            Storage::disk('r2')->allFiles("task-files/{$unitA->id}"),
            Storage::disk('r2')->allFiles("task-files/{$unitB->id}"),
        ));
    }

    public function test_the_limit_option_stops_early(): void
    {
        $unit = $this->makeUnit();
        $task = $this->makeTask($unit);

        foreach (range(1, 4) as $i) {
            $this->makeFileAt($task, "task-files/TT_001/{$i}.pdf");
        }

        $this->artisan('storage:backfill-prefix', ['--limit' => 2])->assertSuccessful();

        $migrated = TaskFile::where('file_path', 'like', "task-files/{$unit->id}/%")->count();
        $this->assertSame(2, $migrated);
    }

    /**
     * The backfill relocates objects; it must not change what any unit is
     * counted as holding.
     */
    public function test_storage_totals_are_unchanged_by_the_move(): void
    {
        $unit = $this->makeUnit();
        $task = $this->makeTask($unit);

        $this->makeFileAt($task, 'task-files/TT_001/a.pdf', 'hello');
        $this->makeFileAt($task, 'task-files/TT_001/completed/b.pdf', 'worlds', true);

        $usage = app(StorageUsageService::class);
        $usage->set($unit->id, 11);

        $this->artisan('storage:backfill-prefix')->assertSuccessful();

        $this->assertSame(11, $usage->bytesFor($unit->id));
    }
}
