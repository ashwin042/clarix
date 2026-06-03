<?php

namespace Tests\Unit;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\Unit;
use App\Models\User;
use App\Services\CreditQueryBuilder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditQueryBuilderTest extends TestCase
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

    private function makePm(Unit $unit): User
    {
        return User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
    }

    private function makeWriter(): User
    {
        return User::factory()->create(['role' => 'writer']);
    }

    private function makeTask(array $overrides = []): Task
    {
        $unit    = Unit::create(['name' => 'Unit ' . uniqid()]);
        $creator = $this->makeAdmin();

        return Task::create(array_merge([
            'title'         => 'Test Task',
            'task_code'     => 'TC_' . uniqid(),
            'unit_id'       => $unit->id,
            'created_by'    => $creator->id,
            'priority'      => 'medium',
            'status'        => 'completed',
            'deadline'      => now()->addDays(7),
            'credit_amount' => 5.00,
        ], $overrides));
    }

    public function test_admin_sees_all_completed_tasks(): void
    {
        $admin = $this->makeAdmin();
        $task  = $this->makeTask();
        $this->makeTask(['status' => 'pending']); // excluded

        $results = (new CreditQueryBuilder())->build($admin)->get();

        $this->assertCount(1, $results);
        $this->assertEquals($task->id, $results->first()->id);
    }

    public function test_admin_filter_by_unit(): void
    {
        $admin = $this->makeAdmin();
        $unit1 = Unit::create(['name' => 'Unit A']);
        $unit2 = Unit::create(['name' => 'Unit B']);
        $task1 = $this->makeTask(['unit_id' => $unit1->id, 'created_by' => $admin->id]);
        $this->makeTask(['unit_id' => $unit2->id, 'created_by' => $admin->id]);

        $results = (new CreditQueryBuilder())
            ->build($admin, filterUnit: (string) $unit1->id)
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals($task1->id, $results->first()->id);
    }

    public function test_admin_filter_by_pm(): void
    {
        $admin = $this->makeAdmin();
        $unit  = Unit::create(['name' => 'Unit A']);
        $pm1   = $this->makePm($unit);
        $pm2   = $this->makePm($unit);
        $task1 = $this->makeTask(['created_by' => $pm1->id, 'unit_id' => $unit->id]);
        $this->makeTask(['created_by' => $pm2->id, 'unit_id' => $unit->id]);

        $results = (new CreditQueryBuilder())
            ->build($admin, filterPm: (string) $pm1->id)
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals($task1->id, $results->first()->id);
    }

    public function test_admin_filter_by_date_from(): void
    {
        $admin = $this->makeAdmin();
        $task  = $this->makeTask(['completed_at' => now()]);
        $old   = $this->makeTask(['completed_at' => '2024-01-15 00:00:00']);

        $results = (new CreditQueryBuilder())
            ->build($admin, dateFrom: '2026-01-01')
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals($task->id, $results->first()->id);
    }

    public function test_admin_filter_by_date_to(): void
    {
        $admin = $this->makeAdmin();
        $task  = $this->makeTask(['completed_at' => '2024-06-01 00:00:00']);
        $this->makeTask(['completed_at' => now()]); // recent, excluded

        $results = (new CreditQueryBuilder())
            ->build($admin, dateTo: '2024-12-31')
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals($task->id, $results->first()->id);
    }

    public function test_pm_sees_only_their_unit(): void
    {
        $unit1 = Unit::create(['name' => 'PM Unit']);
        $unit2 = Unit::create(['name' => 'Other Unit']);
        $pm    = $this->makePm($unit1);
        $task1 = $this->makeTask(['unit_id' => $unit1->id]);
        $this->makeTask(['unit_id' => $unit2->id]);

        $results = (new CreditQueryBuilder())->build($pm)->get();

        $this->assertCount(1, $results);
        $this->assertEquals($task1->id, $results->first()->id);
    }

    public function test_writer_sees_only_assigned_tasks(): void
    {
        $writer = $this->makeWriter();
        $admin  = $this->makeAdmin();
        $task1  = $this->makeTask();
        $this->makeTask(); // not assigned

        TaskAssignment::create([
            'task_id'     => $task1->id,
            'writer_id'   => $writer->id,
            'assigned_by' => $admin->id,
            'status'      => 'pending',
        ]);

        $results = (new CreditQueryBuilder())->build($writer)->get();

        $this->assertCount(1, $results);
        $this->assertEquals($task1->id, $results->first()->id);
    }
}
