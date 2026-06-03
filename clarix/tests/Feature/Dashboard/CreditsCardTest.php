<?php

namespace Tests\Feature\Dashboard;

use App\Livewire\Dashboard\CreditsCard;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreditsCardTest extends TestCase
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

    private function makeCompletedTask(array $overrides = []): Task
    {
        $unit  = Unit::create(['name' => 'Unit ' . uniqid()]);
        $admin = $this->makeAdmin();

        return Task::create(array_merge([
            'title'         => 'Test Task',
            'task_code'     => 'TC_' . uniqid(),
            'unit_id'       => $unit->id,
            'created_by'    => $admin->id,
            'priority'      => 'medium',
            'status'        => 'completed',
            'deadline'      => now()->addDays(7),
            'credit_amount' => 100.00,
            'completed_at'  => now(),
        ], $overrides));
    }

    public function test_defaults_to_total_period(): void
    {
        $admin = $this->makeAdmin();

        Livewire::actingAs($admin)
            ->test(CreditsCard::class, ['role' => 'admin'])
            ->assertSet('period', 'total');
    }

    public function test_set_period_updates_state(): void
    {
        $admin = $this->makeAdmin();

        Livewire::actingAs($admin)
            ->test(CreditsCard::class, ['role' => 'admin'])
            ->call('setPeriod', 'today')
            ->assertSet('period', 'today')
            ->call('setPeriod', 'month')
            ->assertSet('period', 'month')
            ->call('setPeriod', 'total')
            ->assertSet('period', 'total');
    }

    public function test_invalid_period_is_ignored(): void
    {
        $admin = $this->makeAdmin();

        Livewire::actingAs($admin)
            ->test(CreditsCard::class, ['role' => 'admin'])
            ->call('setPeriod', 'hacked')
            ->assertSet('period', 'total');
    }

    public function test_admin_total_sums_all_completed_credits(): void
    {
        $admin = $this->makeAdmin();
        $this->makeCompletedTask(['credit_amount' => 100.00]);
        $this->makeCompletedTask(['credit_amount' => 50.00]);
        $this->makeCompletedTask(['status' => 'pending', 'completed_at' => null]);

        Livewire::actingAs($admin)
            ->test(CreditsCard::class, ['role' => 'admin'])
            ->call('setPeriod', 'total')
            ->assertSee('150');
    }

    public function test_admin_today_only_counts_today_credits(): void
    {
        $admin = $this->makeAdmin();
        $this->makeCompletedTask(['credit_amount' => 80.00, 'completed_at' => now()]);
        $this->makeCompletedTask(['credit_amount' => 200.00, 'completed_at' => now()->subMonth()]);

        Livewire::actingAs($admin)
            ->test(CreditsCard::class, ['role' => 'admin'])
            ->call('setPeriod', 'today')
            ->assertSee('80');
    }

    public function test_admin_month_only_counts_this_month_credits(): void
    {
        $admin = $this->makeAdmin();
        $this->makeCompletedTask(['credit_amount' => 60.00, 'completed_at' => now()]);
        $this->makeCompletedTask(['credit_amount' => 40.00, 'completed_at' => now()->subMonths(2)]);

        Livewire::actingAs($admin)
            ->test(CreditsCard::class, ['role' => 'admin'])
            ->call('setPeriod', 'month')
            ->assertSee('60');
    }

    public function test_writer_only_sees_own_assigned_task_credits(): void
    {
        $writer = $this->makeWriter();
        $admin  = $this->makeAdmin();

        $myTask = $this->makeCompletedTask(['credit_amount' => 100.00]);
        $this->makeCompletedTask(['credit_amount' => 999.00]);

        TaskAssignment::create([
            'task_id'     => $myTask->id,
            'writer_id'   => $writer->id,
            'assigned_by' => $admin->id,
            'status'      => 'ready_for_review',
        ]);

        Livewire::actingAs($writer)
            ->test(CreditsCard::class, ['role' => 'writer'])
            ->call('setPeriod', 'total')
            ->assertSee('100')
            ->assertDontSee('999');
    }

    public function test_writer_today_scoped_to_own_tasks(): void
    {
        $writer    = $this->makeWriter();
        $admin     = $this->makeAdmin();
        $todayTask = $this->makeCompletedTask(['credit_amount' => 75.00, 'completed_at' => now()]);
        $oldTask   = $this->makeCompletedTask(['credit_amount' => 300.00, 'completed_at' => now()->subMonth()]);

        TaskAssignment::create([
            'task_id'     => $todayTask->id,
            'writer_id'   => $writer->id,
            'assigned_by' => $admin->id,
            'status'      => 'ready_for_review',
        ]);
        TaskAssignment::create([
            'task_id'     => $oldTask->id,
            'writer_id'   => $writer->id,
            'assigned_by' => $admin->id,
            'status'      => 'ready_for_review',
        ]);

        Livewire::actingAs($writer)
            ->test(CreditsCard::class, ['role' => 'writer'])
            ->call('setPeriod', 'today')
            ->assertSee('75')
            ->assertDontSee('300');
    }
}
