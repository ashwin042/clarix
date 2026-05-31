<?php

namespace Tests\Unit;

use App\Exports\CreditListExport;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditListExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makeWriter(): User
    {
        return User::factory()->create(['role' => 'writer']);
    }

    private function makeCompletedTask(Unit $unit, User $creator, float $credits = 5.00): Task
    {
        return Task::create([
            'title'         => 'Test Task',
            'task_code'     => 'TC_' . uniqid(),
            'unit_id'       => $unit->id,
            'created_by'    => $creator->id,
            'priority'      => 'medium',
            'status'        => 'completed',
            'deadline'      => now()->addDays(7),
            'credit_amount' => $credits,
        ]);
    }

    public function test_unified_header_row_has_seven_columns(): void
    {
        $admin  = $this->makeAdmin();
        $export = new CreditListExport(['viewMode' => 'unified'], $admin);
        $rows   = $export->collection()->toArray();

        $this->assertEquals(
            ['Code', 'Task Title', 'Unit', 'Priority', 'Assigned Supervisor', 'Completed Date', 'Credits'],
            $rows[0]
        );
    }

    public function test_unified_export_includes_task_row_and_grand_total(): void
    {
        $admin = $this->makeAdmin();
        $unit  = Unit::create(['name' => 'Alpha Unit']);
        $task  = $this->makeCompletedTask($unit, $admin, 3.50);

        $export = new CreditListExport(['viewMode' => 'unified'], $admin);
        $rows   = $export->collection()->toArray();

        // Row 1: task data
        $this->assertEquals($task->task_code, $rows[1][0]);
        $this->assertEquals('Alpha Unit', $rows[1][2]);
        $this->assertEquals('3.50', $rows[1][6]);

        // Last row: grand total
        $lastRow = end($rows);
        $this->assertEquals('Grand Total', $lastRow[5]);
        $this->assertEquals('3.50', $lastRow[6]);
    }

    public function test_grouped_export_has_unit_header_then_col_header(): void
    {
        $admin = $this->makeAdmin();
        $unit  = Unit::create(['name' => 'Beta Unit']);
        $this->makeCompletedTask($unit, $admin, 4.00);

        $export = new CreditListExport(['viewMode' => 'grouped'], $admin);
        $rows   = $export->collection()->toArray();

        // Row 0: unit header (merged cell content)
        $this->assertEquals('Beta Unit', $rows[0][0]);

        // Row 1: column headers
        $this->assertEquals(
            ['Code', 'Task Title', 'Priority', 'Assigned Supervisor', 'Completed Date', 'Credits'],
            $rows[1]
        );
    }

    public function test_grouped_export_subtotal_and_grand_total_are_correct(): void
    {
        $admin = $this->makeAdmin();
        $unit  = Unit::create(['name' => 'Gamma Unit']);
        $this->makeCompletedTask($unit, $admin, 4.00);
        $this->makeCompletedTask($unit, $admin, 6.00);

        $export = new CreditListExport(['viewMode' => 'grouped'], $admin);
        $rows   = $export->collection()->toArray();

        $subtotalRow = collect($rows)->first(fn ($r) => $r[4] === 'Unit Subtotal');
        $this->assertNotNull($subtotalRow);
        $this->assertEquals('10.00', $subtotalRow[5]);

        $lastRow = end($rows);
        $this->assertEquals('Grand Total', $lastRow[4]);
        $this->assertEquals('10.00', $lastRow[5]);
    }

    public function test_writer_always_gets_unified_format_regardless_of_viewmode(): void
    {
        $writer = $this->makeWriter();
        $admin  = $this->makeAdmin();
        $unit   = Unit::create(['name' => 'Writer Unit']);
        $task   = $this->makeCompletedTask($unit, $admin);

        TaskAssignment::create([
            'task_id'     => $task->id,
            'writer_id'   => $writer->id,
            'assigned_by' => $admin->id,
            'status'      => 'pending',
        ]);

        // Even with viewMode=grouped, writers get 7-column unified format
        $export = new CreditListExport(['viewMode' => 'grouped'], $writer);
        $rows   = $export->collection()->toArray();

        $this->assertCount(7, $rows[0]);
        $this->assertEquals('Unit', $rows[0][2]);
    }

    public function test_empty_result_returns_header_and_zero_grand_total(): void
    {
        $admin  = $this->makeAdmin();
        $export = new CreditListExport(['viewMode' => 'unified'], $admin);
        $rows   = $export->collection()->toArray();

        $this->assertCount(2, $rows);
        $this->assertEquals('0.00', end($rows)[6]);
    }
}
