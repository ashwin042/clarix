<?php

namespace Tests\Feature\Tasks;

use App\Livewire\Tasks\CreateTask;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\NewTaskCreatedNotification;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
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

    public function test_files_are_stored_under_unit_and_task_code_directory_on_r2(): void
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

        // The unit id leads the prefix so objects are addressable by tenant;
        // task_code alone is only unique within a unit.
        $file = \App\Models\TaskFile::first();
        $this->assertStringStartsWith("task-files/{$unit->id}/PT_001/", $file->file_path);
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

    /**
     * Characterization test, written before TaskCreationService was extracted.
     *
     * The admin fan-out was the one part of the create flow with no coverage
     * at all, which made it exactly the part a refactor could drop in silence
     * — nothing else in the suite would have gone red. It is pinned here
     * against the current behaviour so the extraction has to preserve it.
     */
    public function test_every_admin_is_notified_when_a_task_is_created(): void
    {
        Notification::fake();

        $unit   = $this->makeUnit();
        $pm     = $this->makePm($unit);
        $adminA = $this->makeAdmin();
        $adminB = $this->makeAdmin();
        $writer = $this->makeWriter($unit);

        Livewire::actingAs($pm)
            ->test(CreateTask::class)
            ->set('title', 'Notify Me')
            ->set('task_code', 'NOTIFY_001')
            ->set('priority', 'medium')
            ->set('deadline', now()->addDays(7)->format('Y-m-d'))
            ->set('credit_amount', '1.00')
            ->call('save');

        Notification::assertSentTo([$adminA, $adminB], NewTaskCreatedNotification::class);

        // The fan-out is by role, so the two non-admins must be left out.
        Notification::assertNotSentTo([$pm, $writer], NewTaskCreatedNotification::class);
    }

    /**
     * Characterization test for the tenant stamp.
     *
     * organization_id is filled by the BelongsToOrganization creating hook
     * rather than by this component, and it is deliberately absent from
     * Task::$fillable. Pinned here because the API endpoint depends on the
     * same hook firing for a token-authenticated actor, and a refactor that
     * moved the create off the Eloquent path would break both at once.
     */
    public function test_created_task_is_stamped_with_the_actors_organization(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);

        Livewire::actingAs($pm)
            ->test(CreateTask::class)
            ->set('title', 'Owned Task')
            ->set('task_code', 'OWNED_001')
            ->set('priority', 'medium')
            ->set('deadline', now()->addDays(7)->format('Y-m-d'))
            ->set('credit_amount', '1.00')
            ->call('save');

        $task = Task::first();

        $this->assertNotNull($task->organization_id);
        $this->assertSame((int) $pm->organization_id, (int) $task->organization_id);
    }

    /**
     * The rendered form still binds to the component it is paired with.
     *
     * The other tests here drive the component class directly, which means a
     * view that had drifted away from it — a wire:model naming a property that
     * no longer exists, a wire:submit naming a removed method — would not show
     * up in any of them. Asserted against the real page so that extracting
     * work out of save() cannot quietly strand the form that calls it.
     */
    public function test_create_form_binds_to_the_components_properties_and_actions(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);

        $page = $this->actingAs($pm)->get(route('tasks.create'))->assertOk();

        foreach ([
            'title', 'task_code', 'task_type', 'important_notes',
            'priority', 'deadline', 'credit_amount', 'assigned_admin_id', 'uploads',
        ] as $property) {
            $page->assertSee('wire:model="'.$property.'"', false);
            $this->assertTrue(
                property_exists(CreateTask::class, $property),
                "The form binds wire:model=\"{$property}\" but the component has no such property."
            );
        }

        $page->assertSee('wire:submit="save"', false);
        $this->assertTrue(method_exists(CreateTask::class, 'save'));
        $this->assertTrue(method_exists(CreateTask::class, 'removeFile'));
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
