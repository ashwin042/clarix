<?php

namespace Tests\Feature\Tasks;

use App\Livewire\Tasks\TaskDetail;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskFile;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

class DownloadAllFilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        Storage::fake('r2');
    }

    private function makeTask(User $pm, Unit $unit, string $status = 'pending'): Task
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

    private function makeFile(Task $task, User $uploader, bool $completed, string $name, string $contents): TaskFile
    {
        $path = 'task-files/'.uniqid('', true).'.bin';
        Storage::disk('r2')->put($path, $contents);

        return TaskFile::create([
            'task_id'           => $task->id,
            'file_path'         => $path,
            'original_name'     => $name,
            'file_size'         => strlen($contents),
            'mime_type'         => 'application/octet-stream',
            'uploaded_by'       => $uploader->id,
            'is_completed_file' => $completed,
        ]);
    }

    /**
     * Read a streamed zip response back into a name => contents map.
     *
     * @return array<string, string>
     */
    private function zipEntries(TestResponse $response): array
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents($path, $response->streamedContent());

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true, 'Response was not a readable zip archive.');

        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
        }

        $zip->close();
        unlink($path);

        return $entries;
    }

    public function test_download_all_returns_every_regular_file_under_its_original_name(): void
    {
        $unit  = Unit::create(['name' => 'Test Unit']);
        $admin = User::factory()->create(['role' => 'admin']);
        $task  = $this->makeTask($admin, $unit);

        $this->makeFile($task, $admin, false, 'brief.pdf', 'brief-contents');
        $this->makeFile($task, $admin, false, 'reference.docx', 'reference-contents');
        $this->makeFile($task, $admin, true, 'deliverable.pdf', 'deliverable-contents');

        $response = $this->actingAs($admin)->get(route('tasks.files.download-all', $task));
        $response->assertOk();

        $this->assertSame([
            'brief.pdf'      => 'brief-contents',
            'reference.docx' => 'reference-contents',
        ], $this->zipEntries($response));
    }

    public function test_download_all_completed_returns_only_completed_files(): void
    {
        $unit  = Unit::create(['name' => 'Test Unit']);
        $admin = User::factory()->create(['role' => 'admin']);
        $task  = $this->makeTask($admin, $unit);

        $this->makeFile($task, $admin, false, 'brief.pdf', 'brief-contents');
        $this->makeFile($task, $admin, true, 'final.pdf', 'final-contents');
        $this->makeFile($task, $admin, true, 'final-notes.txt', 'notes-contents');

        $response = $this->actingAs($admin)->get(route('tasks.completed-files.download-all', $task));
        $response->assertOk();

        $this->assertSame([
            'final.pdf'       => 'final-contents',
            'final-notes.txt' => 'notes-contents',
        ], $this->zipEntries($response));
    }

    public function test_zip_filenames_are_derived_from_the_task_code(): void
    {
        $unit  = Unit::create(['name' => 'Test Unit']);
        $admin = User::factory()->create(['role' => 'admin']);
        $task  = $this->makeTask($admin, $unit);

        $this->makeFile($task, $admin, false, 'a.pdf', 'a');
        $this->makeFile($task, $admin, false, 'b.pdf', 'b');
        $this->makeFile($task, $admin, true, 'c.pdf', 'c');
        $this->makeFile($task, $admin, true, 'd.pdf', 'd');

        $this->actingAs($admin)
            ->get(route('tasks.files.download-all', $task))
            ->assertDownload('TT_001_files.zip');

        $this->actingAs($admin)
            ->get(route('tasks.completed-files.download-all', $task))
            ->assertDownload('TT_001_completed_files.zip');
    }

    public function test_files_sharing_an_original_name_stay_distinct_inside_the_zip(): void
    {
        $unit  = Unit::create(['name' => 'Test Unit']);
        $admin = User::factory()->create(['role' => 'admin']);
        $task  = $this->makeTask($admin, $unit);

        $this->makeFile($task, $admin, false, 'draft.pdf', 'first');
        $this->makeFile($task, $admin, false, 'draft.pdf', 'second');
        $this->makeFile($task, $admin, false, 'draft.pdf', 'third');

        $entries = $this->zipEntries(
            $this->actingAs($admin)->get(route('tasks.files.download-all', $task))
        );

        $this->assertSame([
            'draft.pdf'     => 'first',
            'draft (2).pdf' => 'second',
            'draft (3).pdf' => 'third',
        ], $entries);
    }

    public function test_download_all_is_not_found_when_the_section_is_empty(): void
    {
        $unit  = Unit::create(['name' => 'Test Unit']);
        $admin = User::factory()->create(['role' => 'admin']);
        $task  = $this->makeTask($admin, $unit);

        $this->makeFile($task, $admin, true, 'final.pdf', 'final');

        $this->actingAs($admin)
            ->get(route('tasks.files.download-all', $task))
            ->assertNotFound();
    }

    public function test_pm_cannot_download_all_completed_files_before_the_task_is_completed(): void
    {
        $unit = Unit::create(['name' => 'Test Unit']);
        $pm   = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
        $task = $this->makeTask($pm, $unit, 'in_progress');

        $this->makeFile($task, $pm, true, 'final.pdf', 'final');
        $this->makeFile($task, $pm, true, 'notes.txt', 'notes');

        $this->actingAs($pm)
            ->get(route('tasks.completed-files.download-all', $task))
            ->assertForbidden();
    }

    public function test_pm_can_download_all_completed_files_once_the_task_is_completed(): void
    {
        $unit = Unit::create(['name' => 'Test Unit']);
        $pm   = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
        $task = $this->makeTask($pm, $unit, 'completed');

        $this->makeFile($task, $pm, true, 'final.pdf', 'final');
        $this->makeFile($task, $pm, true, 'notes.txt', 'notes');

        $this->actingAs($pm)
            ->get(route('tasks.completed-files.download-all', $task))
            ->assertOk();
    }

    public function test_unassigned_writer_cannot_download_all_files(): void
    {
        $unit   = Unit::create(['name' => 'Test Unit']);
        $pm     = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
        $writer = User::factory()->create(['role' => 'writer']);
        $task   = $this->makeTask($pm, $unit);

        $this->makeFile($task, $pm, false, 'brief.pdf', 'brief');
        $this->makeFile($task, $pm, false, 'reference.pdf', 'reference');

        $this->actingAs($writer)
            ->get(route('tasks.files.download-all', $task))
            ->assertForbidden();
    }

    public function test_assigned_writer_can_download_all_files(): void
    {
        $unit   = Unit::create(['name' => 'Test Unit']);
        $pm     = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
        $writer = User::factory()->create(['role' => 'writer']);
        $task   = $this->makeTask($pm, $unit);
        TaskAssignment::create([
            'task_id'     => $task->id,
            'writer_id'   => $writer->id,
            'assigned_by' => $pm->id,
            'status'      => 'in_progress',
        ]);

        $this->makeFile($task, $pm, false, 'brief.pdf', 'brief');
        $this->makeFile($task, $pm, false, 'reference.pdf', 'reference');

        $this->actingAs($writer)
            ->get(route('tasks.files.download-all', $task))
            ->assertOk();
    }

    public function test_a_file_missing_from_r2_is_skipped_rather_than_failing_the_download(): void
    {
        $unit  = Unit::create(['name' => 'Test Unit']);
        $admin = User::factory()->create(['role' => 'admin']);
        $task  = $this->makeTask($admin, $unit);

        $this->makeFile($task, $admin, false, 'brief.pdf', 'brief-contents');
        $missing = $this->makeFile($task, $admin, false, 'gone.pdf', 'gone-contents');
        Storage::disk('r2')->delete($missing->file_path);

        $response = $this->actingAs($admin)->get(route('tasks.files.download-all', $task));
        $response->assertOk();

        $this->assertSame(['brief.pdf' => 'brief-contents'], $this->zipEntries($response));
    }

    public function test_download_all_button_is_hidden_for_a_section_with_one_file(): void
    {
        $unit  = Unit::create(['name' => 'Test Unit']);
        $admin = User::factory()->create(['role' => 'admin']);
        $task  = $this->makeTask($admin, $unit);

        $this->makeFile($task, $admin, false, 'brief.pdf', 'brief');
        $this->makeFile($task, $admin, true, 'final.pdf', 'final');

        Livewire::actingAs($admin)
            ->test(TaskDetail::class, ['task' => $task])
            ->assertDontSee(route('tasks.files.download-all', $task))
            ->assertDontSee(route('tasks.completed-files.download-all', $task));
    }

    public function test_download_all_button_is_hidden_for_an_empty_section(): void
    {
        $unit  = Unit::create(['name' => 'Test Unit']);
        $admin = User::factory()->create(['role' => 'admin']);
        $task  = $this->makeTask($admin, $unit);

        Livewire::actingAs($admin)
            ->test(TaskDetail::class, ['task' => $task])
            ->assertDontSee(route('tasks.files.download-all', $task))
            ->assertDontSee(route('tasks.completed-files.download-all', $task));
    }

    public function test_download_all_button_shows_for_a_section_with_more_than_one_file(): void
    {
        $unit  = Unit::create(['name' => 'Test Unit']);
        $admin = User::factory()->create(['role' => 'admin']);
        $task  = $this->makeTask($admin, $unit);

        $this->makeFile($task, $admin, false, 'brief.pdf', 'brief');
        $this->makeFile($task, $admin, false, 'reference.pdf', 'reference');
        $this->makeFile($task, $admin, true, 'final.pdf', 'final');
        $this->makeFile($task, $admin, true, 'notes.txt', 'notes');

        Livewire::actingAs($admin)
            ->test(TaskDetail::class, ['task' => $task])
            ->assertSee(route('tasks.files.download-all', $task))
            ->assertSee(route('tasks.completed-files.download-all', $task));
    }

    public function test_pm_does_not_see_the_completed_download_all_button_while_under_review(): void
    {
        $unit = Unit::create(['name' => 'Test Unit']);
        $pm   = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
        $task = $this->makeTask($pm, $unit, 'in_progress');

        $this->makeFile($task, $pm, true, 'final.pdf', 'final');
        $this->makeFile($task, $pm, true, 'notes.txt', 'notes');

        Livewire::actingAs($pm)
            ->test(TaskDetail::class, ['task' => $task])
            ->assertDontSee(route('tasks.completed-files.download-all', $task));
    }
}
