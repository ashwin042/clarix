<?php

namespace Tests\Feature\Tasks;

use App\Livewire\Tasks\CreateTask;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CreateTaskTest extends TestCase
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

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makeWriter(Unit $unit): User
    {
        return User::factory()->create(['role' => 'writer', 'unit_id' => $unit->id]);
    }

    public function test_pm_can_access_create_task_page(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);

        $this->actingAs($pm)
            ->get(route('tasks.create'))
            ->assertOk();
    }

    public function test_admin_cannot_access_create_task_page(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('tasks.create'))
            ->assertForbidden();
    }

    public function test_writer_cannot_access_create_task_page(): void
    {
        $unit   = $this->makeUnit();
        $writer = $this->makeWriter($unit);

        $this->actingAs($writer)
            ->get(route('tasks.create'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('tasks.create'))
            ->assertRedirect(route('login'));
    }

    public function test_pm_can_create_task_and_is_redirected_to_task_detail(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);

        Livewire::actingAs($pm)
            ->test(CreateTask::class)
            ->set('title', 'My New Task')
            ->set('task_code', 'MNT_001')
            ->set('priority', 'medium')
            ->set('deadline', now()->addDays(7)->format('Y-m-d'))
            ->set('credit_amount', '2.50')
            ->call('save')
            ->assertRedirect(route('tasks.show', Task::first()));
    }

    public function test_task_is_created_with_correct_pm_unit_and_status(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);

        Livewire::actingAs($pm)
            ->test(CreateTask::class)
            ->set('title', 'My New Task')
            ->set('task_code', 'MNT_001')
            ->set('priority', 'medium')
            ->set('deadline', now()->addDays(7)->format('Y-m-d'))
            ->set('credit_amount', '2.50')
            ->call('save');

        $task = Task::first();
        $this->assertNotNull($task);
        $this->assertSame($pm->id, $task->pm_id);
        $this->assertSame($unit->id, $task->unit_id);
        $this->assertSame('pending', $task->status);
        $this->assertSame($pm->id, $task->created_by);
    }

    public function test_save_requires_title_task_code_deadline_and_credit_amount(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);

        Livewire::actingAs($pm)
            ->test(CreateTask::class)
            ->set('title', '')
            ->set('task_code', '')
            ->set('deadline', '')
            ->call('save')
            ->assertHasErrors(['title', 'task_code', 'deadline']);
    }

    public function test_task_code_must_be_unique_within_unit(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);

        Task::create([
            'title'         => 'Existing Task',
            'task_code'     => 'DUP_001',
            'unit_id'       => $unit->id,
            'pm_id'         => $pm->id,
            'created_by'    => $pm->id,
            'priority'      => 'medium',
            'status'        => 'pending',
            'deadline'      => now()->addDays(7),
            'credit_amount' => 1.00,
        ]);

        Livewire::actingAs($pm)
            ->test(CreateTask::class)
            ->set('title', 'Another Task')
            ->set('task_code', 'DUP_001')
            ->set('priority', 'medium')
            ->set('deadline', now()->addDays(7)->format('Y-m-d'))
            ->set('credit_amount', '1.00')
            ->call('save')
            ->assertHasErrors(['task_code']);
    }

    public function test_pm_can_create_task_with_files_stored_on_r2(): void
    {
        Storage::fake('r2');

        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);

        Livewire::actingAs($pm)
            ->test(CreateTask::class)
            ->set('title', 'Task With Files')
            ->set('task_code', 'TWF_001')
            ->set('priority', 'medium')
            ->set('deadline', now()->addDays(7)->format('Y-m-d'))
            ->set('credit_amount', '1.00')
            ->set('uploads', [
                UploadedFile::fake()->create('brief.pdf', 100),
                UploadedFile::fake()->create('notes.docx', 50),
            ])
            ->call('save');

        $task = Task::first();
        $this->assertNotNull($task);
        $this->assertCount(2, $task->files);
        $this->assertDatabaseHas('task_files', [
            'task_id'           => $task->id,
            'original_name'     => 'brief.pdf',
            'is_completed_file' => false,
            'uploaded_by'       => $pm->id,
        ]);
        $this->assertDatabaseHas('task_files', [
            'task_id'       => $task->id,
            'original_name' => 'notes.docx',
        ]);
    }

    public function test_files_are_stored_under_task_code_directory_on_r2(): void
    {
        Storage::fake('r2');

        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);

        Livewire::actingAs($pm)
            ->test(CreateTask::class)
            ->set('title', 'Path Test')
            ->set('task_code', 'PT_001')
            ->set('priority', 'medium')
            ->set('deadline', now()->addDays(7)->format('Y-m-d'))
            ->set('credit_amount', '1.00')
            ->set('uploads', [UploadedFile::fake()->create('doc.pdf', 10)])
            ->call('save');

        $file = \App\Models\TaskFile::first();
        $this->assertStringStartsWith('task-files/PT_001/', $file->file_path);
        Storage::disk('r2')->assertExists($file->file_path);
    }

    public function test_pm_can_create_task_without_files(): void
    {
        Storage::fake('r2');

        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);

        Livewire::actingAs($pm)
            ->test(CreateTask::class)
            ->set('title', 'No Files Task')
            ->set('task_code', 'NFT_001')
            ->set('priority', 'medium')
            ->set('deadline', now()->addDays(7)->format('Y-m-d'))
            ->set('credit_amount', '1.00')
            ->call('save');

        $this->assertCount(0, Task::first()->files);
        $this->assertEmpty(Storage::disk('r2')->allFiles('task-files'));
    }

    public function test_remove_upload_splices_file_from_array(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);

        $component = Livewire::actingAs($pm)
            ->test(CreateTask::class)
            ->set('uploads', [
                UploadedFile::fake()->create('a.pdf', 10),
                UploadedFile::fake()->create('b.pdf', 20),
                UploadedFile::fake()->create('c.pdf', 30),
            ])
            ->call('removeUpload', 1);

        $this->assertCount(2, $component->get('uploads'));
    }

    public function test_pm_dashboard_has_add_task_button_linking_to_create_page(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);

        $this->actingAs($pm)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('tasks.create'))
            ->assertSee('Add Task');
    }
}
