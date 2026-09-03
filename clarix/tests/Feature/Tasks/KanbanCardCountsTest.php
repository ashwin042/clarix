<?php

namespace Tests\Feature\Tasks;

use App\Livewire\Tasks\ManageTasks;
use App\Models\Task;
use App\Models\TaskNote;
use App\Services\PermissionService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * The file and note counters on a kanban card.
 *
 * Three numbers, matching the three counts the task detail page keeps
 * separate: regular files, the writer's completed deliverables, and notes.
 *
 * Asserted through data attributes rather than the rendered icons, because
 * the numbers are the contract and the icons are decoration — a test reading
 * "2" out of the markup should not break when someone changes the glyph.
 *
 * The query count test is the one that matters over time: three counts across
 * five columns is exactly the shape that turns into an N+1 the moment someone
 * loads them off the relation instead of withCount().
 */
class KanbanCardCountsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $a;

    /** Two regular files, one completed, three notes. */
    protected Task $busyTask;

    /** Nothing attached at all. */
    protected Task $bareTask;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        PermissionService::flushAll();

        $this->a = $this->populate($this->makeOrganization('cards', 'Agency A'), 'A');
        $this->subscribeOrganization($this->a['organization']);

        TenantContext::actingAsOrganization($this->a['organization']->id, function () {
            $this->busyTask = Task::create([
                'title'         => 'Busy Task',
                'task_code'     => 'CARD_BUSY',
                'unit_id'       => $this->a['unit']->id,
                'created_by'    => $this->a['pm']->id,
                'pm_id'         => $this->a['pm']->id,
                'priority'      => 'medium',
                'status'        => 'pending',
                'deadline'      => now()->addDays(7),
                'credit_amount' => 10.00,
            ]);

            foreach (['one', 'two'] as $n) {
                $this->busyTask->files()->create([
                    'file_path'     => "task-files/busy/{$n}.pdf",
                    'original_name' => "{$n}.pdf",
                    'file_size'     => 100,
                    'mime_type'     => 'application/pdf',
                    'uploaded_by'   => $this->a['pm']->id,
                ]);
            }

            $this->busyTask->files()->create([
                'file_path'         => 'task-files/busy/done.pdf',
                'original_name'     => 'done.pdf',
                'file_size'         => 100,
                'mime_type'         => 'application/pdf',
                'uploaded_by'       => $this->a['writer']->id,
                'is_completed_file' => true,
            ]);

            foreach (range(1, 3) as $n) {
                TaskNote::create([
                    'task_id'    => $this->busyTask->id,
                    'note'       => "Note {$n}",
                    'created_by' => $this->a['pm']->id,
                ]);
            }

            $this->bareTask = Task::create([
                'title'         => 'Bare Task',
                'task_code'     => 'CARD_BARE',
                'unit_id'       => $this->a['unit']->id,
                'created_by'    => $this->a['pm']->id,
                'pm_id'         => $this->a['pm']->id,
                'priority'      => 'medium',
                'status'        => 'pending',
                'deadline'      => now()->addDays(7),
                'credit_amount' => 10.00,
            ]);
        });
    }

    private function board(): string
    {
        return Livewire::actingAs($this->a['admin'])
            ->test(ManageTasks::class)
            // The page opens on the table now, so the board has to be asked for.
            ->call('setView', 'kanban')
            ->html();
    }

    // ── The three numbers ────────────────────────────────────────────────────

    public function test_the_card_counts_regular_files(): void
    {
        $this->assertStringContainsString('data-card-files="2"', $this->board());
    }

    public function test_the_card_counts_completed_files_separately(): void
    {
        $this->assertStringContainsString('data-card-completed-files="1"', $this->board());
    }

    public function test_the_card_counts_notes(): void
    {
        $this->assertStringContainsString('data-card-notes="3"', $this->board());
    }

    /**
     * The counts belong to their own card. Written because a count resolved
     * once and reused across the loop is a plausible way to get this wrong,
     * and every card showing the same number looks right at a glance.
     */
    public function test_a_task_with_nothing_attached_shows_no_counters(): void
    {
        $html = $this->board();

        $this->assertStringNotContainsString('data-card-files="0"', $html);
        $this->assertStringNotContainsString('data-card-completed-files="0"', $html);
        $this->assertStringNotContainsString('data-card-notes="0"', $html);
    }

    public function test_the_counts_come_from_the_task_not_a_shared_total(): void
    {
        $board = Livewire::actingAs($this->a['admin'])
            ->test(ManageTasks::class)
            ->call('setView', 'kanban')
            ->viewData('board');

        $cards = $board['pending']['tasks']->keyBy('task_code');

        $this->assertSame(2, $cards['CARD_BUSY']->regular_files_count);
        $this->assertSame(1, $cards['CARD_BUSY']->completed_files_count);
        $this->assertSame(3, $cards['CARD_BUSY']->notes_count);

        $this->assertSame(0, $cards['CARD_BARE']->regular_files_count);
        $this->assertSame(0, $cards['CARD_BARE']->completed_files_count);
        $this->assertSame(0, $cards['CARD_BARE']->notes_count);
    }

    /**
     * Counted, not loaded. Adding more cards must not add more queries — the
     * board already renders five columns, so a per-card count would multiply.
     */
    public function test_the_counts_do_not_grow_the_query_count_per_card(): void
    {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();

            Livewire::actingAs($this->a['admin'])->test(ManageTasks::class)->html();

            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $before = $count();

        TenantContext::actingAsOrganization($this->a['organization']->id, function () {
            foreach (range(1, 5) as $n) {
                $task = Task::create([
                    'title'         => "Extra {$n}",
                    'task_code'     => "CARD_X{$n}",
                    'unit_id'       => $this->a['unit']->id,
                    'created_by'    => $this->a['pm']->id,
                    'pm_id'         => $this->a['pm']->id,
                    'priority'      => 'medium',
                    'status'        => 'pending',
                    'deadline'      => now()->addDays(7),
                    'credit_amount' => 10.00,
                ]);

                $task->files()->create([
                    'file_path'     => "task-files/x{$n}.pdf",
                    'original_name' => "x{$n}.pdf",
                    'file_size'     => 100,
                    'mime_type'     => 'application/pdf',
                    'uploaded_by'   => $this->a['pm']->id,
                ]);
            }
        });

        $this->assertSame(
            $before,
            $count(),
            'five more cards should not cost more queries'
        );
    }
}
