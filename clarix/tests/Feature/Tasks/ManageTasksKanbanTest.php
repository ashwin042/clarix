<?php

namespace Tests\Feature\Tasks;

use App\Livewire\Tasks\ManageTasks;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageTasksKanbanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function makeUnit(string $name = 'Test Unit'): Unit
    {
        return Unit::create(['name' => $name]);
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

    // ── View switcher ─────────────────────────────────────────────────────────

    public function test_kanban_is_the_default_view(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ManageTasks::class)->assertSet('activeView', 'kanban');
    }

    public function test_selected_view_is_remembered_in_the_session(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ManageTasks::class)->call('setView', 'table');

        $this->assertSame('table', session(ManageTasks::VIEW_SESSION_KEY));

        Livewire::test(ManageTasks::class)->assertSet('activeView', 'table');
    }

    public function test_unknown_view_is_ignored(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ManageTasks::class)
            ->call('setView', 'gantt')
            ->assertSet('activeView', 'kanban');
    }

    public function test_the_switcher_offers_only_kanban_and_table(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->assertSame(['kanban', 'table'], ManageTasks::VIEWS);

        Livewire::test(ManageTasks::class)
            ->assertSeeHtml("setView('kanban')")
            ->assertSeeHtml("setView('table')")
            ->assertDontSeeHtml("setView('list')");
    }

    public function test_the_retired_list_view_is_not_reachable(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ManageTasks::class)
            ->call('setView', 'list')
            ->assertSet('activeView', 'kanban');

        $this->assertNotSame('list', session(ManageTasks::VIEW_SESSION_KEY));
    }

    public function test_a_session_left_on_list_falls_back_to_kanban(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        session([ManageTasks::VIEW_SESSION_KEY => 'list']);

        Livewire::test(ManageTasks::class)->assertSet('activeView', 'kanban');
    }

    // ── Board contents ────────────────────────────────────────────────────────

    public function test_board_groups_tasks_into_the_status_columns(): void
    {
        $unit = $this->makeUnit();
        $pm   = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);

        $this->makeTask($unit, $pm, ['status' => 'pending']);
        $this->makeTask($unit, $pm, ['status' => 'on_hold']);
        $this->makeTask($unit, $pm, ['status' => 'in_progress']);
        $this->makeTask($unit, $pm, ['status' => 'sent_for_review']);
        $this->makeTask($unit, $pm, ['status' => 'completed']);
        // Cancelled is not a board column, so it must not appear anywhere.
        $this->makeTask($unit, $pm, ['status' => 'cancelled']);

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ManageTasks::class)->assertViewHas('board', function ($board) {
            $this->assertSame(
                ['pending', 'on_hold', 'in_progress', 'sent_for_review', 'completed'],
                array_keys($board),
            );

            foreach ($board as $column) {
                $this->assertCount(1, $column['tasks']);
                $this->assertSame(1, $column['total']);
            }

            return true;
        });
    }

    public function test_cards_are_ordered_by_position_within_a_column(): void
    {
        $unit = $this->makeUnit();
        $pm   = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);

        $last  = $this->makeTask($unit, $pm, ['position' => 2]);
        $first = $this->makeTask($unit, $pm, ['position' => 0]);
        $mid   = $this->makeTask($unit, $pm, ['position' => 1]);

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ManageTasks::class)->assertViewHas('board', function ($board) use ($first, $mid, $last) {
            return $board['pending']['tasks']->pluck('id')->all()
                === [$first->id, $mid->id, $last->id];
        });
    }

    public function test_board_only_shows_tasks_the_user_can_already_see(): void
    {
        $ownUnit   = $this->makeUnit('Own');
        $otherUnit = $this->makeUnit('Other');

        $pm      = User::factory()->create(['role' => 'pm', 'unit_id' => $ownUnit->id]);
        $otherPm = User::factory()->create(['role' => 'pm', 'unit_id' => $otherUnit->id]);

        $visible = $this->makeTask($ownUnit, $pm);
        $this->makeTask($otherUnit, $otherPm);

        $this->actingAs($pm);

        Livewire::test(ManageTasks::class)->assertViewHas('board', function ($board) use ($visible) {
            return $board['pending']['tasks']->pluck('id')->all() === [$visible->id];
        });
    }

    public function test_filter_bar_applies_to_the_board(): void
    {
        $unit = $this->makeUnit();
        $pm   = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);

        $high = $this->makeTask($unit, $pm, ['priority' => 'high']);
        $this->makeTask($unit, $pm, ['priority' => 'low']);

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ManageTasks::class)
            ->set('filterPriority', 'high')
            ->assertViewHas('board', function ($board) use ($high) {
                return $board['pending']['tasks']->pluck('id')->all() === [$high->id];
            });
    }

    // ── Responsive layout ─────────────────────────────────────────────────────

    public function test_columns_flex_to_fill_rather_than_using_a_fixed_width(): void
    {
        $unit = $this->makeUnit();
        $pm   = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
        $this->makeTask($unit, $pm);

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $html = Livewire::test(ManageTasks::class)->html();

        $this->assertMatchesRegularExpression(
            '/wire:key="kanban-col-pending"\s+class="([^"]*\bflex-1\b[^"]*)"/',
            $html,
            'Columns must flex to share the available width.',
        );

        preg_match('/wire:key="kanban-col-pending"\s+class="([^"]*)"/', $html, $m);
        $classes = $m[1] ?? '';

        // A readable floor that still lets the columns grow, and no fixed width
        // of the kind that used to overflow the container.
        $this->assertStringContainsString('min-w-[280px]', $classes);
        $this->assertStringContainsString('xl:min-w-0', $classes);
        $this->assertDoesNotMatchRegularExpression('/\bw-(?:72|80)\b/', $classes);
        $this->assertDoesNotMatchRegularExpression('/\bflex-shrink-0\b/', $classes);
    }

    public function test_board_scrolls_horizontally_with_snap_below_xl(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $html = Livewire::test(ManageTasks::class)->html();

        preg_match('/<div data-kanban-board\s+class="([^"]*)"/', $html, $m);
        $classes = $m[1] ?? '';

        $this->assertStringContainsString('overflow-x-auto', $classes);
        $this->assertStringContainsString('snap-x', $classes);
        $this->assertStringContainsString('snap-mandatory', $classes);
        // Snap is pointless once every column already fits.
        $this->assertStringContainsString('xl:snap-none', $classes);
        // The slim scrollbar, rather than the default heavy track.
        $this->assertStringContainsString('kanban-scroll', $classes);
        // The old wrapper forced the row wider than the container every time.
        $this->assertStringNotContainsString('min-w-max', $html);
    }

    public function test_each_column_owns_its_vertical_scroll(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $html = Livewire::test(ManageTasks::class)->html();

        preg_match('/data-status="pending"\s+class="([^"]*)"/s', $html, $m);
        $classes = $m[1] ?? '';

        $this->assertStringContainsString('overflow-y-auto', $classes);
        // Without min-h-0 the flex child refuses to shrink and the page scrolls
        // instead of the column.
        $this->assertStringContainsString('min-h-0', $classes);
    }

    public function test_cards_are_snap_targets(): void
    {
        $unit = $this->makeUnit();
        $pm   = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
        $this->makeTask($unit, $pm);

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $html = Livewire::test(ManageTasks::class)->html();

        preg_match('/wire:key="kanban-col-pending"\s+class="([^"]*)"/', $html, $m);

        $this->assertStringContainsString('snap-start', $m[1] ?? '');
    }

    // ── Dragging ──────────────────────────────────────────────────────────────

    public function test_dragging_to_another_column_changes_the_status(): void
    {
        $unit = $this->makeUnit();
        $pm   = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
        $task = $this->makeTask($unit, $pm);

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ManageTasks::class)
            ->call('moveTask', $task->id, 'in_progress', ['in_progress' => [$task->id], 'pending' => []]);

        $this->assertSame('in_progress', $task->fresh()->status);
    }

    public function test_dragging_into_completed_stamps_completed_at(): void
    {
        $unit = $this->makeUnit();
        $pm   = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
        $task = $this->makeTask($unit, $pm);

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ManageTasks::class)
            ->call('moveTask', $task->id, 'completed', ['completed' => [$task->id], 'pending' => []]);

        $task = $task->fresh();

        $this->assertSame('completed', $task->status);
        $this->assertNotNull($task->completed_at);
    }

    public function test_dragging_into_completed_closes_ready_for_review_assignments(): void
    {
        // task_assignments only gained the 'completed' enum option through an
        // ALTER TABLE the sqlite branch of that migration skips, so the test
        // schema still rejects the value. Real (MySQL) environments do not.
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('task_assignments status enum is not widened on sqlite.');
        }

        $unit   = $this->makeUnit();
        $pm     = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
        $writer = User::factory()->create(['role' => 'writer']);
        $admin  = User::factory()->create(['role' => 'admin']);
        $task   = $this->makeTask($unit, $pm);

        $assignment = TaskAssignment::create([
            'task_id'     => $task->id,
            'writer_id'   => $writer->id,
            'assigned_by' => $admin->id,
            'status'      => 'ready_for_review',
        ]);

        $this->actingAs($admin);

        Livewire::test(ManageTasks::class)
            ->call('moveTask', $task->id, 'completed', ['completed' => [$task->id], 'pending' => []]);

        $this->assertSame('completed', $assignment->fresh()->status);
    }

    public function test_dragging_within_a_column_reorders_without_changing_status(): void
    {
        $unit = $this->makeUnit();
        $pm   = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);

        $a = $this->makeTask($unit, $pm, ['position' => 0]);
        $b = $this->makeTask($unit, $pm, ['position' => 1]);
        $c = $this->makeTask($unit, $pm, ['position' => 2]);

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ManageTasks::class)
            ->call('moveTask', $c->id, 'pending', ['pending' => [$c->id, $a->id, $b->id]]);

        $this->assertSame(0, $c->fresh()->position);
        $this->assertSame(1, $a->fresh()->position);
        $this->assertSame(2, $b->fresh()->position);
        $this->assertSame('pending', $c->fresh()->status);
    }

    public function test_both_columns_are_resequenced_after_a_cross_column_drag(): void
    {
        $unit = $this->makeUnit();
        $pm   = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);

        $stays = $this->makeTask($unit, $pm, ['status' => 'pending', 'position' => 0]);
        $moved = $this->makeTask($unit, $pm, ['status' => 'pending', 'position' => 1]);
        $there = $this->makeTask($unit, $pm, ['status' => 'in_progress', 'position' => 0]);

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ManageTasks::class)->call('moveTask', $moved->id, 'in_progress', [
            'in_progress' => [$moved->id, $there->id],
            'pending'     => [$stays->id],
        ]);

        $this->assertSame(0, $moved->fresh()->position);
        $this->assertSame(1, $there->fresh()->position);
        $this->assertSame(0, $stays->fresh()->position);
    }

    public function test_a_stale_id_from_another_column_is_ignored(): void
    {
        $unit = $this->makeUnit();
        $pm   = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);

        $pending  = $this->makeTask($unit, $pm, ['status' => 'pending', 'position' => 5]);
        $progress = $this->makeTask($unit, $pm, ['status' => 'in_progress', 'position' => 5]);

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        // The board claims the in_progress card sits in the pending column.
        Livewire::test(ManageTasks::class)
            ->call('moveTask', $pending->id, 'pending', ['pending' => [$progress->id, $pending->id]]);

        $this->assertSame('in_progress', $progress->fresh()->status);
        $this->assertSame(0, $pending->fresh()->position);
    }

    // ── Permissions ───────────────────────────────────────────────────────────

    public function test_pm_cannot_drag_a_card_between_columns(): void
    {
        $unit = $this->makeUnit();
        $pm   = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
        $task = $this->makeTask($unit, $pm);

        $this->actingAs($pm);

        Livewire::test(ManageTasks::class)
            ->call('moveTask', $task->id, 'completed', ['completed' => [$task->id], 'pending' => []])
            ->assertForbidden();

        $this->assertSame('pending', $task->fresh()->status);
    }

    public function test_pm_can_reorder_within_a_column(): void
    {
        $unit = $this->makeUnit();
        $pm   = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);

        $a = $this->makeTask($unit, $pm, ['position' => 0]);
        $b = $this->makeTask($unit, $pm, ['position' => 1]);

        $this->actingAs($pm);

        Livewire::test(ManageTasks::class)
            ->call('moveTask', $b->id, 'pending', ['pending' => [$b->id, $a->id]]);

        $this->assertSame(0, $b->fresh()->position);
        $this->assertSame(1, $a->fresh()->position);
    }

    public function test_a_card_outside_the_users_scope_cannot_be_moved(): void
    {
        $ownUnit   = $this->makeUnit('Own');
        $otherUnit = $this->makeUnit('Other');

        $pm      = User::factory()->create(['role' => 'pm', 'unit_id' => $ownUnit->id]);
        $otherPm = User::factory()->create(['role' => 'pm', 'unit_id' => $otherUnit->id]);

        $task = $this->makeTask($otherUnit, $otherPm);

        $this->actingAs($pm);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(ManageTasks::class)
            ->call('moveTask', $task->id, 'pending', ['pending' => [$task->id]]);
    }

    public function test_a_status_outside_the_board_is_rejected(): void
    {
        $unit = $this->makeUnit();
        $pm   = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
        $task = $this->makeTask($unit, $pm);

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ManageTasks::class)
            ->call('moveTask', $task->id, 'cancelled', ['cancelled' => [$task->id]]);

        $this->assertSame('pending', $task->fresh()->status);
    }

    // ── Table view is untouched ───────────────────────────────────────────────

    public function test_table_view_still_excludes_completed_tasks(): void
    {
        $unit = $this->makeUnit();
        $pm   = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);

        $open = $this->makeTask($unit, $pm, ['status' => 'pending']);
        $done = $this->makeTask($unit, $pm, ['status' => 'completed']);

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ManageTasks::class)
            ->call('setView', 'table')
            ->assertViewHas('tasks', function ($tasks) use ($open, $done) {
                return $tasks->pluck('id')->all() === [$open->id];
            });
    }
}
