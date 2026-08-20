<?php

namespace Tests\Feature\Storage;

use App\Models\Task;
use App\Models\TaskFile;
use App\Models\Unit;
use App\Models\User;
use App\Services\R2ObjectLister;
use App\Services\StorageUsageService;
use Database\Seeders\PermissionSeeder;
use Generator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReconcileStorageUsageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    /**
     * Stand in for the real bucket listing so the command can be exercised
     * without touching object storage.
     *
     * @param  array<string, int>  $objects  key => size in bytes
     */
    private function fakeBucket(array $objects): void
    {
        $this->app->bind(R2ObjectLister::class, fn () => new class($objects) extends R2ObjectLister
        {
            /** @param array<string, int> $objects */
            public function __construct(private array $objects)
            {
            }

            public function listAll(): Generator
            {
                yield from $this->objects;
            }
        });
    }

    private function makeUnit(string $name = 'Test Unit'): Unit
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
     * Attach a file record without letting the observer adjust the running
     * total, so the test can set up drift deliberately.
     */
    private function makeUntrackedFile(Task $task, string $path, int $bytes): TaskFile
    {
        // withoutEvents also skips the organization_id stamp, so it is copied
        // off the task by hand; forceCreate gets past $fillable.
        return TaskFile::withoutEvents(fn () => $task->files()->forceCreate([
            'organization_id' => $task->organization_id,
            'file_path'     => $path,
            'original_name' => 'doc.txt',
            'file_size'     => $bytes,
            'mime_type'     => 'text/plain',
            'uploaded_by'   => $task->pm_id,
        ]));
    }

    private function tracked(Unit $unit): int
    {
        return app(StorageUsageService::class)->bytesFor($unit->id);
    }

    public function test_it_corrects_a_total_that_has_drifted_low(): void
    {
        $unit = $this->makeUnit();
        $task = $this->makeTask($unit);
        $this->makeUntrackedFile($task, 'task-files/TT_001/a.txt', 3000);

        $this->fakeBucket(['task-files/TT_001/a.txt' => 3000]);

        $this->assertSame(0, $this->tracked($unit));

        $this->artisan('storage:reconcile')->assertSuccessful();

        $this->assertSame(3000, $this->tracked($unit));
    }

    public function test_it_corrects_a_total_that_has_drifted_high(): void
    {
        $unit = $this->makeUnit();
        app(StorageUsageService::class)->increment($unit->id, 99000);

        // The bucket holds nothing for this unit, so its total should fall to
        // zero rather than stay at the stale figure.
        $this->fakeBucket([]);

        $this->artisan('storage:reconcile')->assertSuccessful();

        $this->assertSame(0, $this->tracked($unit));
    }

    public function test_objects_matching_no_task_file_are_charged_to_nobody(): void
    {
        $unit = $this->makeUnit();
        $task = $this->makeTask($unit);
        $this->makeUntrackedFile($task, 'task-files/TT_001/a.txt', 1000);

        $this->fakeBucket([
            'task-files/TT_001/a.txt'       => 1000,
            'task-files/TT_001/orphan.txt'  => 500_000,
            'task-files/GONE_999/stray.txt' => 250_000,
        ]);

        $this->artisan('storage:reconcile')
            ->expectsOutputToContain('match no task file')
            ->assertSuccessful();

        $this->assertSame(1000, $this->tracked($unit));
    }

    public function test_it_splits_totals_across_units(): void
    {
        $unitA = $this->makeUnit('Unit A');
        $unitB = $this->makeUnit('Unit B');
        $taskA = $this->makeTask($unitA, 'A_001');
        $taskB = $this->makeTask($unitB, 'B_001');

        $this->makeUntrackedFile($taskA, 'task-files/A_001/one.txt', 1000);
        $this->makeUntrackedFile($taskA, 'task-files/A_001/two.txt', 2000);
        $this->makeUntrackedFile($taskB, 'task-files/B_001/one.txt', 7000);

        $this->fakeBucket([
            'task-files/A_001/one.txt' => 1000,
            'task-files/A_001/two.txt' => 2000,
            'task-files/B_001/one.txt' => 7000,
        ]);

        $this->artisan('storage:reconcile')->assertSuccessful();

        $this->assertSame(3000, $this->tracked($unitA));
        $this->assertSame(7000, $this->tracked($unitB));
    }

    public function test_dry_run_reports_drift_without_writing(): void
    {
        $unit = $this->makeUnit();
        $task = $this->makeTask($unit);
        $this->makeUntrackedFile($task, 'task-files/TT_001/a.txt', 3000);

        $this->fakeBucket(['task-files/TT_001/a.txt' => 3000]);

        $this->artisan('storage:reconcile', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertSame(0, $this->tracked($unit));
    }

    public function test_it_reports_no_drift_when_already_in_sync(): void
    {
        $unit = $this->makeUnit();
        $task = $this->makeTask($unit);
        $this->makeUntrackedFile($task, 'task-files/TT_001/a.txt', 3000);
        app(StorageUsageService::class)->set($unit->id, 3000);

        $this->fakeBucket(['task-files/TT_001/a.txt' => 3000]);

        $this->artisan('storage:reconcile')
            ->expectsOutputToContain('no drift found')
            ->assertSuccessful();

        $this->assertSame(3000, $this->tracked($unit));
    }

    /**
     * Keys are resolved to units in batches; a bucket larger than one batch
     * has to total up the same as one that fits in a single pass.
     */
    public function test_it_totals_correctly_across_multiple_lookup_batches(): void
    {
        config(['storage.reconcile_chunk' => 2]);

        $unit = $this->makeUnit();
        $task = $this->makeTask($unit);

        $objects = [];
        foreach (range(1, 5) as $i) {
            $path = "task-files/TT_001/{$i}.txt";
            $this->makeUntrackedFile($task, $path, 100);
            $objects[$path] = 100;
        }

        $this->fakeBucket($objects);

        $this->artisan('storage:reconcile')->assertSuccessful();

        $this->assertSame(500, $this->tracked($unit));
    }

    public function test_it_creates_a_rollup_row_for_a_unit_that_never_had_one(): void
    {
        $unit = $this->makeUnit();
        $task = $this->makeTask($unit);
        $this->makeUntrackedFile($task, 'task-files/TT_001/a.txt', 1234);

        $this->assertDatabaseMissing('unit_storage_usage', ['unit_id' => $unit->id]);

        $this->fakeBucket(['task-files/TT_001/a.txt' => 1234]);
        $this->artisan('storage:reconcile')->assertSuccessful();

        $this->assertDatabaseHas('unit_storage_usage', [
            'unit_id'    => $unit->id,
            'bytes_used' => 1234,
        ]);
    }

    public function test_deleting_a_unit_removes_its_rollup_row(): void
    {
        $unit = $this->makeUnit();
        app(StorageUsageService::class)->increment($unit->id, 500);

        $this->assertDatabaseHas('unit_storage_usage', ['unit_id' => $unit->id]);

        DB::table('units')->where('id', $unit->id)->delete();

        $this->assertDatabaseMissing('unit_storage_usage', ['unit_id' => $unit->id]);
    }
}
