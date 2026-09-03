<?php

namespace Tests\Feature\Tasks;

use App\Livewire\Tasks\ManageTasks;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Opening a task is a read, and is not the same right as moving its card.
 *
 * The board expressed "you may not reorder this" through SortableJS's `filter`
 * option, whose `preventOnFilter` default calls preventDefault() on the
 * pointerdown anywhere inside a filtered card — the title link included. The
 * two permissions have to stay apart: everyone who can see a card can open it,
 * and only the existing rules decide who can drag it.
 */
class BoardDetailNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function makeTask(Unit $unit, User $pm, array $attributes = []): Task
    {
        static $sequence = 0;
        $sequence++;

        return Task::create(array_merge([
            'title'         => "Task {$sequence}",
            'task_code'     => sprintf('TT_%03d', $sequence),
            'unit_id'       => $unit->id,
            'created_by'    => $pm->id,
            'pm_id'         => $pm->id,
            'priority'      => 'medium',
            'status'        => 'pending',
            'position'      => 0,
            'deadline'      => now()->addDays(7),
            'credit_amount' => 1.00,
        ], $attributes));
    }

    private function board(User $user): string
    {
        return Livewire::actingAs($user)
            ->test(ManageTasks::class)
            ->call('setView', 'kanban')
            ->html();
    }

    // ── The card links into the detail page for every role that sees it ───────

    public function test_pm_gets_a_detail_link_on_their_units_cards(): void
    {
        $unit = Unit::create(['name' => 'Test Unit']);
        $pm   = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
        $task = $this->makeTask($unit, $pm);

        $this->assertStringContainsString(
            route('tasks.show', $task),
            $this->board($pm),
        );
    }

    public function test_writer_gets_a_detail_link_on_a_card_assigned_to_them(): void
    {
        $unit   = Unit::create(['name' => 'Test Unit']);
        $pm     = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
        $writer = User::factory()->create(['role' => 'writer']);
        $task   = $this->makeTask($unit, $pm);
        TaskAssignment::create([
            'task_id'     => $task->id,
            'writer_id'   => $writer->id,
            'assigned_by' => $pm->id,
            'status'      => 'in_progress',
        ]);

        $this->assertStringContainsString(
            route('tasks.show', $task),
            $this->board($writer),
        );
    }

    public function test_pm_and_writer_can_open_the_detail_page_itself(): void
    {
        $unit   = Unit::create(['name' => 'Test Unit']);
        $pm     = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
        $writer = User::factory()->create(['role' => 'writer']);
        $task   = $this->makeTask($unit, $pm);
        TaskAssignment::create([
            'task_id'     => $task->id,
            'writer_id'   => $writer->id,
            'assigned_by' => $pm->id,
            'status'      => 'in_progress',
        ]);

        $this->actingAs($pm)->get(route('tasks.show', $task))->assertOk();
        $this->actingAs($writer)->get(route('tasks.show', $task))->assertOk();
    }

    /**
     * The board's own drag wiring must not suppress the browser's default on a
     * card it filters, or the title link inside that card stops responding for
     * exactly the users who cannot reorder.
     */
    public function test_filtering_a_card_out_of_the_drag_does_not_swallow_its_clicks(): void
    {
        $unit = Unit::create(['name' => 'Test Unit']);
        $pm   = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
        $this->makeTask($unit, $pm);

        $html = $this->board(User::factory()->create(['role' => 'admin']));

        $this->assertStringContainsString('preventOnFilter: false', $html);
    }

    // ── The drag restriction itself is untouched ─────────────────────────────

    public function test_a_writer_card_is_still_not_draggable(): void
    {
        $unit   = Unit::create(['name' => 'Test Unit']);
        $pm     = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
        $writer = User::factory()->create(['role' => 'writer']);
        $task   = $this->makeTask($unit, $pm);
        TaskAssignment::create([
            'task_id'     => $task->id,
            'writer_id'   => $writer->id,
            'assigned_by' => $pm->id,
            'status'      => 'in_progress',
        ]);

        $html = $this->board($writer);

        $this->assertStringContainsString('data-draggable="0"', $html);
        $this->assertStringNotContainsString('data-draggable="1"', $html);
    }

    public function test_an_admin_card_is_still_draggable_and_may_change_status(): void
    {
        $unit  = Unit::create(['name' => 'Test Unit']);
        $pm    = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->makeTask($unit, $pm);

        $html = $this->board($admin);

        $this->assertStringContainsString('data-draggable="1"', $html);
        $this->assertStringContainsString('data-can-change-status="1"', $html);
    }

    public function test_a_supervisor_card_is_still_draggable_and_may_change_status(): void
    {
        $unit       = Unit::create(['name' => 'Test Unit']);
        $pm         = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $this->makeTask($unit, $pm);

        $html = $this->board($supervisor);

        $this->assertStringContainsString('data-draggable="1"', $html);
        $this->assertStringContainsString('data-can-change-status="1"', $html);
    }

    /**
     * A writer's card carries no status-change right either, so the drag rules
     * are exactly as they were.
     */
    public function test_a_writer_still_cannot_move_a_card_between_columns(): void
    {
        $unit   = Unit::create(['name' => 'Test Unit']);
        $pm     = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
        $writer = User::factory()->create(['role' => 'writer']);
        $task   = $this->makeTask($unit, $pm);
        TaskAssignment::create([
            'task_id'     => $task->id,
            'writer_id'   => $writer->id,
            'assigned_by' => $pm->id,
            'status'      => 'in_progress',
        ]);

        Livewire::actingAs($writer)
            ->test(ManageTasks::class)
            ->call('moveTask', $task->id, 'in_progress', ['in_progress' => [$task->id]])
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'pending']);
    }
}
