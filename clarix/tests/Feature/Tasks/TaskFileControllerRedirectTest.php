<?php

namespace Tests\Feature\Tasks;

use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskFileControllerRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        Storage::fake('r2');
    }

    private function makeUnit(): Unit
    {
        return Unit::create(['name' => 'Test Unit']);
    }

    private function makePm(Unit $unit): User
    {
        return User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
    }

    private function makeTask(Unit $unit, User $pm): Task
    {
        return Task::create([
            'title'         => 'Test Task',
            'task_code'     => 'TT_001',
            'unit_id'       => $unit->id,
            'created_by'    => $pm->id,
            'pm_id'         => $pm->id,
            'priority'      => 'medium',
            'status'        => 'pending',
            'deadline'      => now()->addDays(7),
            'credit_amount' => 1.00,
        ]);
    }

    public function test_store_redirects_back_to_task_list_when_uploaded_from_list(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);
        $task = $this->makeTask($unit, $pm);

        $this->actingAs($pm)
            ->from(route('tasks.index'))
            ->post(route('tasks.files.store', $task), [
                'files' => [UploadedFile::fake()->create('report.pdf', 100)],
            ])
            ->assertRedirect(route('tasks.index'));
    }

    public function test_store_redirects_back_to_task_detail_when_uploaded_from_detail(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);
        $task = $this->makeTask($unit, $pm);

        $this->actingAs($pm)
            ->from(route('tasks.show', $task))
            ->post(route('tasks.files.store', $task), [
                'files' => [UploadedFile::fake()->create('report.pdf', 100)],
            ])
            ->assertRedirect(route('tasks.show', $task));
    }
}
