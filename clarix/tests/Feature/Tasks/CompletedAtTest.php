<?php

namespace Tests\Feature\Tasks;

use App\Exports\CreditListExport;
use App\Livewire\CreditList;
use App\Livewire\Tasks\ManageTasks;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * completed_at is what the credit list bills against, so it has two
 * properties worth pinning down: it is written exactly once, and it is the
 * value the credit list actually shows.
 */
class CompletedAtTest extends TestCase
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

    private function makeTask(Unit $unit, User $pm, array $attributes = []): Task
    {
        static $sequence = 0;
        $sequence++;

        return Task::create(array_merge([
            'title'         => "Task {$sequence}",
            'task_code'     => sprintf('CA_%03d', $sequence),
            'unit_id'       => $unit->id,
            'created_by'    => $pm->id,
            'pm_id'         => $pm->id,
            'priority'      => 'medium',
            'status'        => 'pending',
            'deadline'      => now()->addDays(7),
            'credit_amount' => 1.00,
        ], $attributes));
    }

    // ── Write-once ────────────────────────────────────────────────────────────

    public function test_completed_at_is_stamped_when_a_task_first_completes(): void
    {
        $unit = $this->makeUnit();
        $pm   = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
        $task = $this->makeTask($unit, $pm);

        $this->assertNull($task->completed_at);

        $task->applyStatusChange('completed');

        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_reopening_a_task_does_not_clear_completed_at(): void
    {
        $unit = $this->makeUnit();
        $pm   = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
        $task = $this->makeTask($unit, $pm, [
            'status'       => 'completed',
            'completed_at' => '2026-02-11 08:00:00',
        ]);

        $task->applyStatusChange('in_progress');

        $this->assertSame(
            '2026-02-11 08:00:00',
            $task->fresh()->completed_at->format('Y-m-d H:i:s'),
        );
    }

    public function test_re_completing_a_task_keeps_the_original_date(): void
    {
        $unit = $this->makeUnit();
        $pm   = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
        $task = $this->makeTask($unit, $pm, [
            'status'       => 'completed',
            'completed_at' => '2026-02-11 08:00:00',
        ]);

        $task->applyStatusChange('in_progress');
        $task->fresh()->applyStatusChange('completed');

        $this->assertSame(
            '2026-02-11 08:00:00',
            $task->fresh()->completed_at->format('Y-m-d H:i:s'),
        );
    }

    public function test_the_edit_modal_does_not_rewrite_completed_at(): void
    {
        $unit  = $this->makeUnit();
        $pm    = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
        $task  = $this->makeTask($unit, $pm, [
            'status'       => 'completed',
            'completed_at' => '2026-02-11 08:00:00',
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        foreach (['in_progress', 'completed'] as $status) {
            Livewire::test(ManageTasks::class)
                ->call('openEdit', $task->id)
                ->set('status', $status)
                ->call('save');
        }

        $this->assertSame(
            '2026-02-11 08:00:00',
            $task->fresh()->completed_at->format('Y-m-d H:i:s'),
        );
    }

    public function test_a_task_created_as_completed_is_stamped(): void
    {
        $unit  = $this->makeUnit();
        $pm    = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin);

        Livewire::test(ManageTasks::class)
            ->call('openCreate')
            ->set('title', 'Born complete')
            ->set('task_code', 'CA_NEW')
            ->set('unit_id', (string) $unit->id)
            ->set('pm_id', (string) $pm->id)
            ->set('status', 'completed')
            ->set('deadline', now()->addDay()->format('Y-m-d'))
            ->set('credit_amount', '2')
            ->call('save');

        $this->assertNotNull(Task::where('task_code', 'CA_NEW')->first()?->completed_at);
    }

    // ── The credit list reads completed_at ────────────────────────────────────

    public function test_credit_list_shows_completed_at_not_updated_at(): void
    {
        $unit = $this->makeUnit();
        $pm   = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);

        $task = $this->makeTask($unit, $pm, [
            'status'       => 'completed',
            'completed_at' => '2026-02-11 08:00:00',
        ]);

        // Touching the row moves updated_at to today, so a view rendering the
        // wrong column shows today's date instead of the completion date.
        $task->touch();
        $this->assertNotSame(
            $task->fresh()->updated_at->format('d M Y'),
            '11 Feb 2026',
        );

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(CreditList::class)
            ->assertSee('11 Feb 2026')
            ->assertDontSee($task->fresh()->updated_at->format('d M Y'));
    }

    public function test_credit_list_shows_a_distinct_date_per_task(): void
    {
        $unit = $this->makeUnit();
        $pm   = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);

        $this->makeTask($unit, $pm, ['status' => 'completed', 'completed_at' => '2026-06-04 10:00:00']);
        $this->makeTask($unit, $pm, ['status' => 'completed', 'completed_at' => '2026-06-12 10:00:00']);
        $this->makeTask($unit, $pm, ['status' => 'completed', 'completed_at' => '2026-08-14 10:00:00']);

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(CreditList::class)
            ->assertSee('04 Jun 2026')
            ->assertSee('12 Jun 2026')
            ->assertSee('14 Aug 2026');
    }

    public function test_credit_export_completed_date_column_reads_completed_at(): void
    {
        $unit = $this->makeUnit();
        $pm   = User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);

        $task = $this->makeTask($unit, $pm, [
            'status'       => 'completed',
            'completed_at' => '2026-02-11 08:00:00',
        ]);
        $task->touch();

        $admin = User::factory()->create(['role' => 'admin']);

        $rows   = (new CreditListExport(['viewMode' => 'unified'], $admin))->collection();
        $header = $rows->first();
        $index  = array_search('Completed Date', $header, true);

        $this->assertNotFalse($index, 'Export is missing a Completed Date column.');

        $row = $rows->first(fn ($r) => ($r[0] ?? '') === $task->task_code);

        $this->assertSame('11 Feb 2026', $row[$index]);
    }
}
