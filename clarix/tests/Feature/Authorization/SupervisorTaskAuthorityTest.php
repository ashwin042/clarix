<?php

namespace Tests\Feature\Authorization;

use App\Livewire\Tasks\TaskDetail;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskFile;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * The supervisor on a task's own screen: assigning writers, and files.
 *
 * Both faults here are the same one wearing different clothes — a screen that
 * asks isAdmin() and treats every other answer as the role it was written for.
 * TaskDetail::render() hands the writer list to an admin and an empty
 * collection to everyone else, and the view has no way to tell "nobody left to
 * assign" from "you are not an admin", so it prints the former either way.
 *
 * The file half is the mirror image: uploadCompletedFile() and the download
 * controller both enumerate roles by name, and a supervisor is not on either
 * list.
 *
 * Deletion runs the other way and is pinned here too. It was gated on
 * uploadFiles, so anyone who could add a file could remove one — which is not
 * what "only admins delete" means anywhere else in this codebase.
 */
class SupervisorTaskAuthorityTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $a;

    protected User $supervisor;

    /** A task nobody is assigned to — the reported case. */
    protected Task $unassignedTask;

    protected TaskFile $completedFile;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('r2');

        $this->seed(PermissionSeeder::class);
        PermissionService::flushAll();

        $this->a = $this->populate($this->makeOrganization('sta-a', 'Agency A'), 'A');
        $this->subscribeOrganization($this->a['organization']);

        TenantContext::actingAsOrganization($this->a['organization']->id, function () {
            $this->supervisor = User::factory()->create([
                'name'  => 'Supervisor A',
                'email' => 'supervisor.a@example.test',
                'role'  => 'supervisor',
            ]);

            $this->unassignedTask = Task::create([
                'title'         => 'Nobody On It',
                'task_code'     => 'STA_FREE',
                'unit_id'       => $this->a['unit']->id,
                'created_by'    => $this->a['pm']->id,
                'pm_id'         => $this->a['pm']->id,
                'priority'      => 'medium',
                'status'        => 'pending',
                'deadline'      => now()->addDays(7),
                'credit_amount' => 10.00,
            ]);

            $this->completedFile = $this->a['task']->files()->create([
                'file_path'         => 'completed/doc.pdf',
                'original_name'     => 'completed.pdf',
                'file_size'         => 100,
                'mime_type'         => 'application/pdf',
                'uploaded_by'       => $this->a['writer']->id,
                'is_completed_file' => true,
            ]);
        });

        // The download route streams the object, so the faked disk has to
        // actually hold it — otherwise every download test 500s regardless of
        // who is asking, and the authorization claim goes untested.
        Storage::disk('r2')->put($this->a['file']->file_path, 'regular contents');
        Storage::disk('r2')->put($this->completedFile->file_path, 'completed contents');
    }

    // ── Assign Writer: the list is not empty ─────────────────────────────────

    /**
     * The reported bug. Zero writers assigned, and the supervisor is told they
     * are all already assigned.
     */
    public function test_the_supervisor_is_offered_the_writers_on_an_unassigned_task(): void
    {
        $this->assertSame(0, $this->unassignedTask->assignments()->count());

        $writers = Livewire::actingAs($this->supervisor)
            ->test(TaskDetail::class, ['task' => $this->unassignedTask])
            ->viewData('availableWriters');

        $this->assertTrue(
            $writers->contains('id', $this->a['writer']->id),
            'the supervisor should be offered the agency writer'
        );
    }

    public function test_the_admin_is_still_offered_the_writers(): void
    {
        $writers = Livewire::actingAs($this->a['admin'])
            ->test(TaskDetail::class, ['task' => $this->unassignedTask])
            ->viewData('availableWriters');

        $this->assertTrue($writers->contains('id', $this->a['writer']->id));
    }

    /** The PM holds tasks.assign and owns this task, so the screen owes it the list too. */
    public function test_the_pm_is_offered_the_writers_for_its_own_units_task(): void
    {
        $this->assertTrue($this->a['pm']->hasPermission('tasks.assign'));

        $writers = Livewire::actingAs($this->a['pm'])
            ->test(TaskDetail::class, ['task' => $this->unassignedTask])
            ->viewData('availableWriters');

        $this->assertTrue($writers->contains('id', $this->a['writer']->id));
    }

    public function test_a_writer_is_offered_nobody(): void
    {
        $writers = Livewire::actingAs($this->a['writer'])
            ->test(TaskDetail::class, ['task' => $this->unassignedTask])
            ->viewData('availableWriters');

        $this->assertTrue($writers->isEmpty());
    }

    /**
     * The message is only correct when it is true. An already-assigned writer
     * still drops out of the list, so the fix is not "show everybody".
     */
    public function test_an_already_assigned_writer_is_left_out_of_the_list(): void
    {
        $writers = Livewire::actingAs($this->supervisor)
            ->test(TaskDetail::class, ['task' => $this->a['task']])
            ->viewData('availableWriters');

        $this->assertFalse(
            $writers->contains('id', $this->a['writer']->id),
            'the writer already on this task must not be offered again'
        );
    }

    // ── Assigning is an action, not just a list ──────────────────────────────

    public function test_the_supervisor_can_assign_a_writer(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(TaskDetail::class, ['task' => $this->unassignedTask])
            ->set('selectedWriters', [$this->a['writer']->id])
            ->call('assign')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('task_assignments', [
            'task_id'   => $this->unassignedTask->id,
            'writer_id' => $this->a['writer']->id,
        ]);
    }

    public function test_a_writer_cannot_assign(): void
    {
        Livewire::actingAs($this->a['writer'])
            ->test(TaskDetail::class, ['task' => $this->unassignedTask])
            ->set('selectedWriters', [$this->a['writer']->id])
            ->call('assign')
            ->assertForbidden();

        $this->assertDatabaseMissing('task_assignments', [
            'task_id'   => $this->unassignedTask->id,
            'writer_id' => $this->a['writer']->id,
        ]);
    }

    public function test_a_writer_cannot_remove_an_assignment(): void
    {
        $assignment = TenantContext::actingAsOrganization(
            $this->a['organization']->id,
            fn () => TaskAssignment::where('task_id', $this->a['task']->id)->firstOrFail()
        );

        Livewire::actingAs($this->a['writer'])
            ->test(TaskDetail::class, ['task' => $this->a['task']])
            ->call('removeAssignment', $assignment->id)
            ->assertForbidden();

        $this->assertDatabaseHas('task_assignments', ['id' => $assignment->id]);
    }

    // ── Files: the supervisor matches the admin ──────────────────────────────

    public function test_the_supervisor_may_upload_regular_files(): void
    {
        $this->actingAs($this->supervisor);

        $this->assertTrue(Gate::allows('uploadFiles', $this->a['task']));
    }

    public function test_the_supervisor_may_upload_a_completed_file(): void
    {
        $this->actingAs($this->supervisor);

        $this->assertTrue(Gate::allows('uploadCompletedFile', $this->a['task']));
    }

    public function test_the_admin_may_still_upload_a_completed_file(): void
    {
        $this->actingAs($this->a['admin']);

        $this->assertTrue(Gate::allows('uploadCompletedFile', $this->a['task']));
    }

    /** The writer's own deliverable path is untouched. */
    public function test_the_assigned_writer_may_still_upload_a_completed_file(): void
    {
        $this->actingAs($this->a['writer']);

        $this->assertTrue(Gate::allows('uploadCompletedFile', $this->a['task']));
    }

    public function test_the_pm_still_may_not_upload_a_completed_file(): void
    {
        $this->actingAs($this->a['pm']);

        $this->assertFalse(Gate::allows('uploadCompletedFile', $this->a['task']));
    }

    public function test_the_supervisor_can_download_a_regular_file(): void
    {
        $this->actingAs($this->supervisor)
            ->get(route('tasks.files.download', [$this->a['task'], $this->a['file']]))
            ->assertOk();
    }

    public function test_the_supervisor_can_download_a_completed_file(): void
    {
        $this->actingAs($this->supervisor)
            ->get(route('tasks.files.download', [$this->a['task'], $this->completedFile]))
            ->assertOk();
    }

    public function test_the_admin_can_still_download_a_completed_file(): void
    {
        $this->actingAs($this->a['admin'])
            ->get(route('tasks.files.download', [$this->a['task'], $this->completedFile]))
            ->assertOk();
    }

    // ── Deleting is the admin's, and only the admin's ────────────────────────

    /*
     * File deletion was gated on uploadFiles, so every role that could add a
     * file could remove one. That is not the rule this codebase holds anywhere
     * else — TaskPolicy::delete, UnitPolicy and UserPolicy all go through
     * administers(), which is admin-of-the-owning-agency and nothing else.
     *
     * The PM assertions below are a deliberate narrowing: a PM could delete
     * files before and cannot now.
     */

    public function test_the_supervisor_cannot_delete_a_task_file(): void
    {
        $this->actingAs($this->supervisor)
            ->delete(route('tasks.files.destroy', [$this->a['task'], $this->a['file']]))
            ->assertForbidden();

        $this->assertDatabaseHas('task_files', ['id' => $this->a['file']->id]);
    }

    public function test_the_pm_cannot_delete_a_task_file(): void
    {
        $this->actingAs($this->a['pm'])
            ->delete(route('tasks.files.destroy', [$this->a['task'], $this->a['file']]))
            ->assertForbidden();

        $this->assertDatabaseHas('task_files', ['id' => $this->a['file']->id]);
    }

    public function test_the_admin_can_still_delete_a_task_file(): void
    {
        $this->actingAs($this->a['admin'])
            ->delete(route('tasks.files.destroy', [$this->a['task'], $this->a['file']]))
            ->assertRedirect();

        $this->assertDatabaseMissing('task_files', ['id' => $this->a['file']->id]);
    }

    public function test_the_supervisor_cannot_delete_a_file_through_the_component(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(TaskDetail::class, ['task' => $this->a['task']])
            ->call('deleteFile', $this->a['file']->id)
            ->assertForbidden();

        $this->assertDatabaseHas('task_files', ['id' => $this->a['file']->id]);
    }

    public function test_the_supervisor_cannot_delete_a_completed_file(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(TaskDetail::class, ['task' => $this->a['task']])
            ->call('deleteCompletedFile', $this->completedFile->id)
            ->assertForbidden();

        $this->assertDatabaseHas('task_files', ['id' => $this->completedFile->id]);
    }

    public function test_the_admin_can_still_delete_a_file_through_the_component(): void
    {
        Livewire::actingAs($this->a['admin'])
            ->test(TaskDetail::class, ['task' => $this->a['task']])
            ->call('deleteFile', $this->a['file']->id);

        $this->assertDatabaseMissing('task_files', ['id' => $this->a['file']->id]);
    }
}
