<?php

namespace Tests\Feature\Tasks;

use App\Livewire\Tasks\ManageTasks;
use App\Livewire\Tasks\TaskDetail;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * The per-task activity log.
 *
 * Written against the model events rather than the screens, because that is
 * where the logging hangs. TaskFileObserver already makes the case in its own
 * docblock — hooking the model covers the file controller, the create-task
 * form and the detail component at once, and a route added tomorrow is covered
 * without anybody remembering to add a line.
 *
 * Two things these tests are careful about:
 *
 *   - the fixtures themselves generate activity (BuildsOrganizations creates a
 *     task, a file, a note and an assignment), so every assertion scopes to a
 *     task it created itself rather than counting rows globally;
 *   - a writer must never be named. That is asserted on the rendered
 *     description for every viewer, not just a PM, because the rule chosen
 *     here is stricter than the PM-only one the rest of the page follows.
 */
class TaskActivityLogTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $a;

    protected User $supervisor;

    protected Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('r2');

        $this->seed(PermissionSeeder::class);
        PermissionService::flushAll();

        $this->a = $this->populate($this->makeOrganization('acts', 'Agency A'), 'A');
        $this->subscribeOrganization($this->a['organization']);

        TenantContext::actingAsOrganization($this->a['organization']->id, function () {
            $this->supervisor = User::factory()->create([
                'name'  => 'Supervisor A',
                'email' => 'supervisor.a@example.test',
                'role'  => 'supervisor',
            ]);
        });

        $this->actingAs($this->a['admin']);

        $this->task = Task::create([
            'title'         => 'Logged Task',
            'task_code'     => 'ACT_001',
            'unit_id'       => $this->a['unit']->id,
            'created_by'    => $this->a['admin']->id,
            'pm_id'         => $this->a['pm']->id,
            'priority'      => 'medium',
            'status'        => 'pending',
            'deadline'      => now()->addDays(7),
            'credit_amount' => 10.00,
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, TaskActivity> */
    private function activity(?string $event = null)
    {
        return TaskActivity::where('task_id', $this->task->id)
            ->when($event, fn ($q) => $q->where('event', $event))
            ->orderBy('id')
            ->get();
    }

    // ── Each event lands ─────────────────────────────────────────────────────

    public function test_creating_a_task_logs_it_against_the_creator(): void
    {
        $entry = $this->activity('created')->sole();

        $this->assertSame($this->a['admin']->id, $entry->user_id);
        $this->assertStringContainsString('Admin A', $entry->describeFor($this->a['admin']));
    }

    public function test_a_status_change_records_both_ends(): void
    {
        $this->actingAs($this->supervisor);
        $this->task->applyStatusChange('in_progress');

        $entry = $this->activity('status_changed')->sole();

        $this->assertSame('pending', $entry->details['from']);
        $this->assertSame('in_progress', $entry->details['to']);
        $this->assertSame($this->supervisor->id, $entry->user_id);
    }

    public function test_uploading_a_file_logs_its_name(): void
    {
        $this->task->files()->create([
            'file_path'     => 'task-files/act/brief.pdf',
            'original_name' => 'brief.pdf',
            'file_size'     => 100,
            'mime_type'     => 'application/pdf',
            'uploaded_by'   => $this->a['admin']->id,
        ]);

        $entry = $this->activity('file_uploaded')->sole();

        $this->assertSame('brief.pdf', $entry->details['filename']);
        $this->assertFalse($entry->details['completed']);
    }

    public function test_a_completed_file_upload_is_marked_as_one(): void
    {
        $this->actingAs($this->a['writer']);

        $this->task->files()->create([
            'file_path'         => 'task-files/act/final.pdf',
            'original_name'     => 'final.pdf',
            'file_size'         => 100,
            'mime_type'         => 'application/pdf',
            'uploaded_by'       => $this->a['writer']->id,
            'is_completed_file' => true,
        ]);

        $entry = $this->activity('file_uploaded')->sole();

        $this->assertTrue($entry->details['completed']);
    }

    public function test_deleting_a_file_logs_its_name(): void
    {
        $file = $this->task->files()->create([
            'file_path'     => 'task-files/act/gone.pdf',
            'original_name' => 'gone.pdf',
            'file_size'     => 100,
            'mime_type'     => 'application/pdf',
            'uploaded_by'   => $this->a['admin']->id,
        ]);

        $file->delete();

        $entry = $this->activity('file_deleted')->sole();

        $this->assertSame('gone.pdf', $entry->details['filename']);
    }

    public function test_assigning_a_writer_is_logged(): void
    {
        TaskAssignment::create([
            'task_id'     => $this->task->id,
            'writer_id'   => $this->a['writer']->id,
            'assigned_by' => $this->a['admin']->id,
            'status'      => 'pending',
        ]);

        $this->assertCount(1, $this->activity('writer_assigned'));
    }

    public function test_removing_a_writer_is_logged(): void
    {
        $assignment = TaskAssignment::create([
            'task_id'     => $this->task->id,
            'writer_id'   => $this->a['writer']->id,
            'assigned_by' => $this->a['admin']->id,
            'status'      => 'pending',
        ]);

        $assignment->delete();

        $this->assertCount(1, $this->activity('writer_removed'));
    }

    public function test_adding_a_note_is_logged(): void
    {
        $this->task->notes()->create([
            'note'       => 'Something worth saying',
            'created_by' => $this->a['admin']->id,
        ]);

        $this->assertCount(1, $this->activity('note_added'));
    }

    // ── Edits record field, before and after ─────────────────────────────────

    /**
     * @dataProvider trackedFields
     */
    public function test_an_edited_field_records_its_old_and_new_value(string $field, mixed $to): void
    {
        $from = $this->task->getAttribute($field);

        $this->task->update([$field => $to]);

        $entry = $this->activity('updated')->sole();

        $this->assertSame($field, $entry->details['field']);
        $this->assertSame((string) $from, (string) $entry->details['from']);
        $this->assertSame((string) $to, (string) $entry->details['to']);
    }

    public static function trackedFields(): array
    {
        return [
            'title'    => ['title', 'Renamed Task'],
            'priority' => ['priority', 'high'],
            'taskType' => ['task_type', 'content'],
        ];
    }

    public function test_reassigning_the_pm_is_recorded_as_a_field_change(): void
    {
        $newPm = TenantContext::actingAsOrganization(
            $this->a['organization']->id,
            fn () => User::factory()->create([
                'name'    => 'Second PM',
                'email'   => 'pm2@example.test',
                'role'    => 'pm',
                'unit_id' => $this->a['unit']->id,
            ])
        );

        $this->task->update(['pm_id' => $newPm->id]);

        $entry = $this->activity('updated')->sole();

        $this->assertSame('pm_id', $entry->details['field']);
        $this->assertStringContainsString('Second PM', $entry->describeFor($this->a['admin']));
    }

    public function test_changing_the_deadline_is_recorded(): void
    {
        $this->task->update(['deadline' => now()->addDays(30)]);

        $this->assertSame('deadline', $this->activity('updated')->sole()->details['field']);
    }

    public function test_changing_the_credit_amount_is_recorded(): void
    {
        $this->task->update(['credit_amount' => 25.00]);

        $this->assertSame('credit_amount', $this->activity('updated')->sole()->details['field']);
    }

    /** Two fields in one save are two entries, so each reads on its own line. */
    public function test_two_fields_changed_together_are_logged_separately(): void
    {
        $this->task->update(['title' => 'Both Changed', 'priority' => 'high']);

        $this->assertCount(2, $this->activity('updated'));
    }

    /** Position and the status column have their own handling and must not double up. */
    public function test_an_untracked_field_is_not_logged_as_an_edit(): void
    {
        $this->task->update(['position' => 42]);

        $this->assertCount(0, $this->activity('updated'));
    }

    public function test_a_status_change_is_not_also_logged_as_a_field_edit(): void
    {
        $this->task->applyStatusChange('completed');

        $this->assertCount(1, $this->activity('status_changed'));
        $this->assertCount(0, $this->activity('updated'));
    }

    // ── A writer is never named ──────────────────────────────────────────────

    /**
     * The rule chosen for this log is stricter than the PM-only masking the
     * rest of the page uses, so every viewer is checked — an admin included.
     */
    public function test_a_writers_upload_is_never_attributed_by_name(): void
    {
        $this->actingAs($this->a['writer']);

        $this->task->files()->create([
            'file_path'         => 'task-files/act/final.pdf',
            'original_name'     => 'final.pdf',
            'file_size'         => 100,
            'mime_type'         => 'application/pdf',
            'uploaded_by'       => $this->a['writer']->id,
            'is_completed_file' => true,
        ]);

        $entry = $this->activity('file_uploaded')->sole();

        foreach ([$this->a['admin'], $this->supervisor, $this->a['pm'], $this->a['writer']] as $viewer) {
            $description = $entry->describeFor($viewer);

            $this->assertStringNotContainsString(
                $this->a['writer']->name,
                $description,
                "the writer was named to a {$viewer->role}"
            );
            $this->assertStringContainsString('Assigned writer', $description);
        }
    }

    public function test_the_writer_assigned_entry_names_nobody(): void
    {
        TaskAssignment::create([
            'task_id'     => $this->task->id,
            'writer_id'   => $this->a['writer']->id,
            'assigned_by' => $this->a['admin']->id,
            'status'      => 'pending',
        ]);

        $entry = $this->activity('writer_assigned')->sole();
        $description = $entry->describeFor($this->a['admin']);

        // The actor is the admin and is named; the writer is the subject and is not.
        $this->assertStringContainsString('Admin A', $description);
        $this->assertStringNotContainsString($this->a['writer']->name, $description);
    }

    /**
     * Masking keys off the role recorded at the time, so it survives the
     * account being promoted, renamed or deleted afterwards.
     */
    public function test_masking_survives_the_writer_changing_role_later(): void
    {
        $this->actingAs($this->a['writer']);
        $this->task->notes()->create(['note' => 'From the writer', 'created_by' => $this->a['writer']->id]);

        $this->a['writer']->update(['role' => 'pm']);

        $entry = $this->activity('note_added')->sole();

        $this->assertStringNotContainsString(
            $this->a['writer']->fresh()->name,
            $entry->describeFor($this->a['admin'])
        );
    }

    /** Everyone else is named normally, which is what makes the masking meaningful. */
    public function test_non_writer_roles_are_named_normally(): void
    {
        $this->actingAs($this->supervisor);
        $this->task->applyStatusChange('in_progress');

        $entry = $this->activity('status_changed')->sole();

        $this->assertStringContainsString('Supervisor A', $entry->describeFor($this->a['admin']));
    }

    public function test_an_action_with_no_signed_in_actor_is_attributed_to_the_system(): void
    {
        auth()->logout();

        $this->task->applyStatusChange('completed');

        $entry = $this->activity('status_changed')->sole();

        $this->assertNull($entry->user_id);
        $this->assertStringContainsString('System', $entry->describeFor($this->a['admin']));
    }

    // ── The History section ──────────────────────────────────────────────────

    private function makeEntries(int $n): void
    {
        foreach (range(1, $n) as $i) {
            $this->task->update(['title' => "Title {$i}"]);
        }
    }

    public function test_the_history_section_shows_five_entries_by_default(): void
    {
        $this->makeEntries(9);

        $shown = Livewire::actingAs($this->a['admin'])
            ->test(TaskDetail::class, ['task' => $this->task])
            ->viewData('activities');

        $this->assertCount(5, $shown);
    }

    public function test_the_five_shown_are_the_most_recent(): void
    {
        $this->makeEntries(9);

        $shown = Livewire::actingAs($this->a['admin'])
            ->test(TaskDetail::class, ['task' => $this->task])
            ->viewData('activities');

        $this->assertStringContainsString('Title 9', $shown->first()->describeFor($this->a['admin']));
    }

    public function test_expanding_shows_more_than_five(): void
    {
        $this->makeEntries(9);

        $shown = Livewire::actingAs($this->a['admin'])
            ->test(TaskDetail::class, ['task' => $this->task])
            ->call('showFullHistory')
            ->viewData('activities');

        $this->assertGreaterThan(5, $shown->count());
    }

    public function test_the_expand_control_is_hidden_when_there_is_nothing_more_to_show(): void
    {
        Livewire::actingAs($this->a['admin'])
            ->test(TaskDetail::class, ['task' => $this->task])
            ->assertDontSee('wire:click="showFullHistory"', false);
    }

    public function test_the_expand_control_appears_once_there_are_more_than_five(): void
    {
        $this->makeEntries(9);

        Livewire::actingAs($this->a['admin'])
            ->test(TaskDetail::class, ['task' => $this->task])
            ->assertSee('wire:click="showFullHistory"', false);
    }

    // ── Visibility ───────────────────────────────────────────────────────────

    public function test_every_role_that_can_see_the_task_can_see_its_history(): void
    {
        $this->actingAs($this->a['admin']);
        $this->task->applyStatusChange('in_progress');

        foreach ([$this->a['admin'], $this->supervisor, $this->a['pm'], $this->a['writer']] as $viewer) {
            Livewire::actingAs($viewer)
                ->test(TaskDetail::class, ['task' => $this->task])
                ->assertSee('History');
        }
    }

    // ── Tenancy ──────────────────────────────────────────────────────────────

    public function test_an_activity_row_carries_its_organization(): void
    {
        $this->assertSame(
            $this->a['organization']->id,
            $this->activity('created')->sole()->organization_id
        );
    }
}
