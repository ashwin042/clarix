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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * New uploads are keyed by unit so that every object is addressable by tenant.
 * Objects written under the old task-files/{task_code}/ layout have to keep
 * working, since nothing moves them.
 */
class TaskFileStoragePathTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        Storage::fake('r2');
    }

    private function makeUnit(string $name): Unit
    {
        return Unit::create(['name' => $name]);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makePm(Unit $unit): User
    {
        return User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
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

    public function test_the_prefix_is_keyed_by_unit_then_task_code(): void
    {
        $unit = $this->makeUnit('Unit A');
        $task = $this->makeTask($unit, $this->makePm($unit));

        $this->assertSame("task-files/{$unit->id}/TT_001", $task->storagePrefix());
        $this->assertSame("task-files/{$unit->id}/TT_001/completed", $task->completedStoragePrefix());
    }

    /**
     * task_code is unique only per unit, so this is the case the old layout
     * could not tell apart.
     */
    public function test_two_units_sharing_a_task_code_upload_to_separate_prefixes(): void
    {
        $unitA = $this->makeUnit('Unit A');
        $unitB = $this->makeUnit('Unit B');
        $pmA   = $this->makePm($unitA);
        $pmB   = $this->makePm($unitB);

        $taskA = $this->makeTask($unitA, $pmA, 'SHARED_01');
        $taskB = $this->makeTask($unitB, $pmB, 'SHARED_01');

        $this->actingAs($pmA)->post(route('tasks.files.store', $taskA), [
            'files' => [UploadedFile::fake()->create('a.pdf', 4, 'application/pdf')],
        ])->assertRedirect();

        $this->actingAs($pmB)->post(route('tasks.files.store', $taskB), [
            'files' => [UploadedFile::fake()->create('b.pdf', 4, 'application/pdf')],
        ])->assertRedirect();

        $pathA = $taskA->files()->first()->file_path;
        $pathB = $taskB->files()->first()->file_path;

        $this->assertStringStartsWith("task-files/{$unitA->id}/SHARED_01/", $pathA);
        $this->assertStringStartsWith("task-files/{$unitB->id}/SHARED_01/", $pathB);

        // The whole point: neither unit's prefix contains the other's object.
        $this->assertNotSame($pathA, $pathB);
        $this->assertEmpty(array_intersect(
            Storage::disk('r2')->allFiles("task-files/{$unitA->id}"),
            Storage::disk('r2')->allFiles("task-files/{$unitB->id}"),
        ));
    }

    public function test_completed_uploads_land_under_the_unit_prefix(): void
    {
        $unit  = $this->makeUnit('Unit A');
        $pm    = $this->makePm($unit);
        $task  = $this->makeTask($unit, $pm);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('tasks.completed-files.store', $task), [
            'files' => [UploadedFile::fake()->create('done.pdf', 4, 'application/pdf')],
        ])->assertRedirect();

        $this->assertStringStartsWith(
            "task-files/{$unit->id}/TT_001/completed/",
            $task->completedFiles()->first()->file_path
        );
    }

    public function test_task_creation_uploads_land_under_the_unit_prefix(): void
    {
        $unit = $this->makeUnit('Unit A');
        $pm   = $this->makePm($unit);

        $this->actingAs($pm);

        \Livewire\Livewire::actingAs($pm)
            ->test(\App\Livewire\Tasks\CreateTask::class)
            ->set('title', 'Fresh Task')
            ->set('task_code', 'NEW_01')
            ->set('priority', 'medium')
            ->set('deadline', now()->addDays(5)->format('Y-m-d'))
            ->set('credit_amount', 1)
            ->set('uploads', [UploadedFile::fake()->create('brief.pdf', 4, 'application/pdf')])
            ->call('save');

        $task = Task::where('task_code', 'NEW_01')->firstOrFail();

        $this->assertStringStartsWith(
            "task-files/{$unit->id}/NEW_01/",
            $task->files()->first()->file_path
        );
    }

    /**
     * Nothing moves existing objects, so a legacy key has to stay readable and
     * deletable through its stored file_path.
     */
    public function test_a_legacy_path_still_downloads_and_deletes(): void
    {
        $unit = $this->makeUnit('Unit A');
        $pm   = $this->makePm($unit);
        $task = $this->makeTask($unit, $pm);

        $legacy = 'task-files/TT_001/oldhash.pdf';
        Storage::disk('r2')->put($legacy, 'legacy contents');

        $file = $task->files()->create([
            'file_path'     => $legacy,
            'original_name' => 'old.pdf',
            'file_size'     => 15,
            'mime_type'     => 'application/pdf',
            'uploaded_by'   => $pm->id,
        ]);

        $this->actingAs($pm)
            ->get(route('tasks.files.download', [$task, $file]))
            ->assertOk();

        // The PM still downloads; deleting is the admin's. The legacy path
        // is what is under test either way.
        $this->actingAs($this->makeAdmin())
            ->delete(route('tasks.files.destroy', [$task, $file]))
            ->assertRedirect();

        Storage::disk('r2')->assertMissing($legacy);
        $this->assertSame(0, app(StorageUsageService::class)->bytesFor($unit->id));
    }

    /**
     * Reconciliation matches on the exact stored key, so a bucket holding both
     * layouts at once still totals correctly.
     */
    public function test_reconcile_totals_a_bucket_holding_both_layouts(): void
    {
        $unit = $this->makeUnit('Unit A');
        $pm   = $this->makePm($unit);
        $task = $this->makeTask($unit, $pm);

        $legacy = 'task-files/TT_001/oldhash.pdf';
        $modern = "task-files/{$unit->id}/TT_001/newhash.pdf";

        foreach ([$legacy => 1000, $modern => 2500] as $path => $bytes) {
            TaskFile::withoutEvents(fn () => $task->files()->forceCreate([
                'organization_id' => $task->organization_id,
                'file_path'     => $path,
                'original_name' => 'doc.pdf',
                'file_size'     => $bytes,
                'mime_type'     => 'application/pdf',
                'uploaded_by'   => $pm->id,
            ]));
        }

        $objects = [$legacy => 1000, $modern => 2500];

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

        $this->artisan('storage:reconcile')->assertSuccessful();

        $this->assertSame(3500, app(StorageUsageService::class)->bytesFor($unit->id));
    }
}
