<?php

namespace Tests\Feature\Tasks;

use App\Livewire\Tasks\ManageTasks;
use App\Livewire\Tasks\TaskDetail;
use App\Models\Task;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * 'on_hold', end to end.
 *
 * The point of Task::STATUSES was that adding a status should be one edit.
 * These tests are what makes that claim checkable: each surface that offers a
 * status is asserted to offer this one, so a list that was quietly written out
 * by hand somewhere fails here rather than in production.
 *
 * Two such lists were still hand-written when this was added — the board's own
 * filter and the assigned-tasks filter, the latter having already drifted
 * (it was missing 'sent_for_review' entirely).
 *
 * The database half cannot be checked here at all. status is a MySQL enum and
 * the suite runs on sqlite, which accepts any string, so a green run says
 * nothing about whether the column takes the value. That is the migration's
 * job and a clone's.
 */
class OnHoldStatusTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $a;

    protected User $supervisor;

    protected Task $heldTask;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        PermissionService::flushAll();

        $this->a = $this->populate($this->makeOrganization('onhold', 'Agency A'), 'A');
        $this->subscribeOrganization($this->a['organization']);

        TenantContext::actingAsOrganization($this->a['organization']->id, function () {
            $this->supervisor = User::factory()->create([
                'name'  => 'Supervisor A',
                'email' => 'supervisor.a@example.test',
                'role'  => 'supervisor',
            ]);

            $this->heldTask = Task::create([
                'title'         => 'Waiting On The Client',
                'task_code'     => 'HOLD_001',
                'unit_id'       => $this->a['unit']->id,
                'created_by'    => $this->a['pm']->id,
                'pm_id'         => $this->a['pm']->id,
                'priority'      => 'medium',
                'status'        => 'on_hold',
                'deadline'      => now()->addDays(7),
                'credit_amount' => 10.00,
            ]);
        });
    }

    // ── The canonical lists ──────────────────────────────────────────────────

    public function test_on_hold_is_a_known_status(): void
    {
        $this->assertContains('on_hold', Task::STATUSES);
    }

    public function test_on_hold_has_a_board_column(): void
    {
        $this->assertArrayHasKey('on_hold', Task::BOARD_COLUMNS);
    }

    /**
     * The board reads left to right as progress, so a not-progressing state
     * sits left of the actively-worked ones and the three that are genuinely
     * moving stay contiguous.
     */
    public function test_on_hold_sits_between_pending_and_in_progress(): void
    {
        $this->assertSame(
            ['pending', 'on_hold', 'in_progress', 'sent_for_review', 'completed'],
            array_keys(Task::BOARD_COLUMNS)
        );
    }

    /** 'cancelled' is still deliberately absent from the board. */
    public function test_cancelled_still_has_no_column(): void
    {
        $this->assertContains('cancelled', Task::STATUSES);
        $this->assertArrayNotHasKey('cancelled', Task::BOARD_COLUMNS);
    }

    // ── The board ────────────────────────────────────────────────────────────

    public function test_the_board_renders_an_on_hold_column(): void
    {
        $board = Livewire::actingAs($this->a['admin'])
            ->test(ManageTasks::class)
            ->viewData('board');

        $this->assertArrayHasKey('on_hold', $board);
    }

    public function test_a_held_task_appears_in_the_on_hold_column(): void
    {
        $board = Livewire::actingAs($this->a['admin'])
            ->test(ManageTasks::class)
            ->viewData('board');

        $this->assertTrue(
            $board['on_hold']['tasks']->contains('id', $this->heldTask->id)
        );
    }

    /**
     * moveTask() refuses any column not in BOARD_COLUMNS, so dragging into a
     * newly added one only works if the constant really is the single source.
     */
    public function test_a_card_can_be_dragged_into_the_on_hold_column(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(ManageTasks::class)
            ->call('moveTask', $this->a['task']->id, 'on_hold', [
                'on_hold' => [$this->a['task']->id],
            ]);

        $this->assertSame('on_hold', $this->a['task']->fresh()->status);
    }

    public function test_a_held_card_can_be_dragged_back_into_progress(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(ManageTasks::class)
            ->call('moveTask', $this->heldTask->id, 'in_progress', [
                'in_progress' => [$this->heldTask->id],
            ]);

        $this->assertSame('in_progress', $this->heldTask->fresh()->status);
    }

    // ── The three places a status is chosen ──────────────────────────────────

    public function test_the_edit_modal_offers_on_hold(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(ManageTasks::class)
            ->call('openEdit', $this->a['task']->id)
            ->assertSee('value="on_hold"', false);
    }

    public function test_the_task_detail_control_offers_on_hold(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(TaskDetail::class, ['task' => $this->a['task']])
            ->assertSee('value="on_hold"', false);
    }

    public function test_the_edit_modal_can_set_a_task_to_on_hold(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(ManageTasks::class)
            ->call('openEdit', $this->a['task']->id)
            ->set('status', 'on_hold')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('on_hold', $this->a['task']->fresh()->status);
    }

    public function test_the_detail_control_can_set_a_task_to_on_hold(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(TaskDetail::class, ['task' => $this->a['task']])
            ->call('updateTaskStatus', 'on_hold')
            ->assertHasNoErrors();

        $this->assertSame('on_hold', $this->a['task']->fresh()->status);
    }

    // ── The filters ──────────────────────────────────────────────────────────

    public function test_the_board_filter_offers_on_hold(): void
    {
        Livewire::actingAs($this->a['admin'])
            ->test(ManageTasks::class)
            ->assertSee('value="on_hold"', false);
    }

    public function test_filtering_the_board_by_on_hold_finds_the_held_task(): void
    {
        Livewire::actingAs($this->a['admin'])
            ->test(ManageTasks::class)
            ->set('activeView', 'table')
            ->set('filterStatus', 'on_hold')
            ->assertSee('Waiting On The Client')
            ->assertDontSee('Task A');
    }

    /**
     * This filter had drifted before on_hold existed — it never offered
     * 'sent_for_review'. Both are asserted so the list cannot silently fall
     * behind the constant again.
     */
    public function test_the_assigned_tasks_filter_offers_every_status(): void
    {
        $html = $this->actingAs($this->a['admin'])
            ->get(route('tasks.assigned'))
            ->assertOk()
            ->getContent();

        foreach (Task::STATUSES as $status) {
            $this->assertStringContainsString(
                'value="' . $status . '"',
                $html,
                "the assigned-tasks filter should offer {$status}"
            );
        }
    }

    // ── No transition rules to satisfy ───────────────────────────────────────

    /*
     * There are none in the codebase: applyStatusChange() special-cases only
     * 'completed' (stamping completed_at and rolling ready-for-review
     * assignments up), and every status is otherwise freely selectable. So
     * on_hold needs no rule of its own — these pin that it behaves like the
     * rest rather than acquiring one by accident.
     */

    public function test_holding_a_task_stamps_no_completion_date(): void
    {
        $this->a['task']->applyStatusChange('on_hold');

        $this->assertNull($this->a['task']->fresh()->completed_at);
    }

    public function test_a_held_task_can_still_be_completed_directly(): void
    {
        $this->heldTask->applyStatusChange('completed');

        $fresh = $this->heldTask->fresh();

        $this->assertSame('completed', $fresh->status);
        $this->assertNotNull($fresh->completed_at);
    }

    public function test_a_completed_task_can_be_put_back_on_hold(): void
    {
        $this->a['task']->applyStatusChange('completed');
        $this->a['task']->fresh()->applyStatusChange('on_hold');

        $this->assertSame('on_hold', $this->a['task']->fresh()->status);
    }
}
