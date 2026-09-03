<?php

namespace Tests\Feature\Tasks;

use App\Livewire\Tasks\ManageTasks;
use App\Models\Task;
use App\Models\TaskFile;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageTasksFilesColumnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function makeUnit(): Unit
    {
        return Unit::create(['name' => 'Test Unit']);
    }

    private function makePm(Unit $unit): User
    {
        return User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
    }

    private function makeTask(Unit $unit, User $pm): Task
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

    /**
     * The table is the default view, but this asks for it explicitly rather
     * than leaning on that — these assertions are about the table itself, not
     * about which view happens to load first.
     */
    private function tableView(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(ManageTasks::class)->call('setView', 'table');
    }

    private function makeFile(Task $task, User $uploader): TaskFile
    {
        return TaskFile::create([
            'task_id'       => $task->id,
            'file_path'     => 'task-files/TT_001/report.pdf',
            'original_name' => 'report.pdf',
            'file_size'     => 102400,
            'mime_type'     => 'application/pdf',
            'uploaded_by'   => $uploader->id,
        ]);
    }

    public function test_files_relation_is_eager_loaded_on_tasks(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);
        $task = $this->makeTask($unit, $pm);
        $this->makeFile($task, $pm);

        $this->actingAs($pm);

        $this->tableView()
            ->assertViewHas('tasks', function ($tasks) {
                return $tasks->first()->relationLoaded('files');
            });
    }

    public function test_unit_column_header_is_absent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $unit = $this->makeUnit();
        $pm = $this->makePm($unit);
        $task = $this->makeTask($unit, $pm);
        $this->actingAs($admin);

        $this->tableView()
            ->assertDontSeeHtml('tracking-wider">Unit</th>');
    }

    public function test_files_column_header_is_present(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $unit = $this->makeUnit();
        $pm = $this->makePm($unit);
        $task = $this->makeTask($unit, $pm);
        $this->actingAs($admin);

        $this->tableView()
            ->assertSeeHtml('tracking-wider">Files</th>');
    }

    public function test_file_name_appears_in_html_when_task_has_files(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);
        $task = $this->makeTask($unit, $pm);
        $this->makeFile($task, $pm);

        $this->actingAs($pm);

        $this->tableView()
            ->assertSee('report.pdf');
    }

    public function test_upload_dispatch_present_for_pm_with_no_files(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);
        $this->makeTask($unit, $pm);

        $this->actingAs($pm);

        $this->tableView()
            ->assertSeeHtml('open-upload-modal');
    }
}
