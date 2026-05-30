<?php

namespace Tests\Feature\Tasks;

use App\Livewire\Tasks\ManageTasks;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageTasksAssignedAdminTest extends TestCase
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

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makePm(Unit $unit): User
    {
        return User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
    }

    private function adminTaskFields(Unit $unit, User $pm): array
    {
        return [
            'title'         => 'Test Task',
            'task_code'     => 'TT_001',
            'unit_id'       => (string) $unit->id,
            'pm_id'         => (string) $pm->id,
            'priority'      => 'medium',
            'status'        => 'pending',
            'deadline'      => now()->addDays(7)->format('Y-m-d'),
            'credit_amount' => '1.00',
        ];
    }

    public function test_admin_can_save_task_with_assigned_admin(): void
    {
        $unit          = $this->makeUnit();
        $admin         = $this->makeAdmin();
        $pm            = $this->makePm($unit);
        $assignedAdmin = $this->makeAdmin();

        $this->actingAs($admin);

        $fields = $this->adminTaskFields($unit, $pm);
        $fields['assigned_admin_id'] = (string) $assignedAdmin->id;

        Livewire::test(ManageTasks::class)
            ->set($fields)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'task_code'         => 'TT_001',
            'assigned_admin_id' => $assignedAdmin->id,
        ]);
    }

    public function test_pm_can_save_task_with_assigned_admin(): void
    {
        $unit          = $this->makeUnit();
        $pm            = $this->makePm($unit);
        $assignedAdmin = $this->makeAdmin();

        $this->actingAs($pm);

        Livewire::test(ManageTasks::class)
            ->set('assigned_admin_id', (string) $assignedAdmin->id)
            ->set('title', 'PM Task')
            ->set('task_code', 'PM_001')
            ->set('priority', 'medium')
            ->set('deadline', now()->addDays(7)->format('Y-m-d'))
            ->set('credit_amount', '1.00')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'task_code'         => 'PM_001',
            'assigned_admin_id' => $assignedAdmin->id,
        ]);
    }

    public function test_assigned_admin_id_is_optional(): void
    {
        $unit  = $this->makeUnit();
        $admin = $this->makeAdmin();
        $pm    = $this->makePm($unit);

        $this->actingAs($admin);

        Livewire::test(ManageTasks::class)
            ->set($this->adminTaskFields($unit, $pm))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'task_code'         => 'TT_001',
            'assigned_admin_id' => null,
        ]);
    }

    public function test_non_admin_user_is_rejected_as_assigned_admin(): void
    {
        $unit      = $this->makeUnit();
        $admin     = $this->makeAdmin();
        $pm        = $this->makePm($unit);
        $anotherPm = $this->makePm($unit);

        $this->actingAs($admin);

        $fields = $this->adminTaskFields($unit, $pm);
        $fields['assigned_admin_id'] = (string) $anotherPm->id;

        Livewire::test(ManageTasks::class)
            ->set($fields)
            ->call('save')
            ->assertHasErrors(['assigned_admin_id']);
    }

    public function test_admin_users_are_passed_to_view(): void
    {
        $admin      = $this->makeAdmin();
        $otherAdmin = $this->makeAdmin();
        $unit       = $this->makeUnit();
        $pm         = $this->makePm($unit);

        $this->actingAs($admin);

        Livewire::test(ManageTasks::class)
            ->assertViewHas('adminUsers', function ($adminUsers) use ($admin, $otherAdmin, $pm) {
                return $adminUsers->contains('id', $admin->id)
                    && $adminUsers->contains('id', $otherAdmin->id)
                    && ! $adminUsers->contains('id', $pm->id);
            });
    }

    public function test_edit_populates_assigned_admin_id(): void
    {
        $unit          = $this->makeUnit();
        $admin         = $this->makeAdmin();
        $pm            = $this->makePm($unit);
        $assignedAdmin = $this->makeAdmin();

        $task = Task::create([
            'title'             => 'Existing Task',
            'task_code'         => 'EX_001',
            'unit_id'           => $unit->id,
            'created_by'        => $admin->id,
            'pm_id'             => $pm->id,
            'priority'          => 'medium',
            'status'            => 'pending',
            'deadline'          => now()->addDays(7),
            'credit_amount'     => 1.00,
            'assigned_admin_id' => $assignedAdmin->id,
        ]);

        $this->actingAs($admin);

        Livewire::test(ManageTasks::class)
            ->call('openEdit', $task)
            ->assertSet('assigned_admin_id', (string) $assignedAdmin->id);
    }
}
