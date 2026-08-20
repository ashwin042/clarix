<?php

namespace Tests\Feature\Tasks;

use App\Http\Requests\UpdateTaskRequest;
use App\Livewire\Tasks\ManageTasks;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use App\Services\PermissionService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A PM may edit the work; what the work is worth is an admin's call.
 *
 * The two halves are tested separately on purpose. That a PM can change a
 * title proves the tasks.update grant is real, and that the credit figure
 * survives the same request proves the exception is real — a test that only
 * checked the refusal could pass with PMs locked out of editing entirely.
 */
class TaskCreditAmountPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected Unit $unit;

    protected User $pm;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        PermissionService::flushAll();

        $this->unit  = Unit::create(['name' => 'Credit Unit']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->pm    = User::factory()->create(['role' => 'pm', 'unit_id' => $this->unit->id]);
    }

    protected function makeTask(float $credit = 250.00): Task
    {
        return Task::create([
            'title'         => 'Original title',
            'task_code'     => 'CR_001',
            'unit_id'       => $this->unit->id,
            'created_by'    => $this->pm->id,
            'pm_id'         => $this->pm->id,
            'priority'      => 'medium',
            'status'        => 'pending',
            'deadline'      => now()->addDays(7),
            'credit_amount' => $credit,
        ]);
    }

    /**
     * The grant this whole feature rests on. If the seeded default ever flips
     * off, every other assertion here would still pass while PMs quietly lost
     * the ability to edit at all.
     */
    public function test_the_pm_role_has_tasks_update_and_not_tasks_delete_by_default(): void
    {
        $this->assertTrue($this->pm->hasPermission('tasks.update'), 'PMs must be able to edit tasks');
        $this->assertFalse($this->pm->hasPermission('tasks.delete'), 'deletion is not implied by editing');
    }

    // ── The headline case ────────────────────────────────────────────────────

    public function test_a_pm_edits_a_task_while_the_credit_amount_stands(): void
    {
        $task = $this->makeTask(250.00);

        Livewire::actingAs($this->pm)
            ->test(ManageTasks::class)
            ->call('openEdit', $task)
            ->set('title', 'Edited by the PM')
            ->set('deadline', now()->addDays(30)->format('Y-m-d'))
            // The payload carries a different figure, exactly as a crafted
            // Livewire request would.
            ->set('credit_amount', '9999')
            ->call('save')
            ->assertHasNoErrors();

        $task->refresh();

        $this->assertSame('Edited by the PM', $task->title, 'the legitimate edit goes through');
        $this->assertSame(
            now()->addDays(30)->format('Y-m-d'),
            $task->deadline->format('Y-m-d'),
            'and so does the deadline'
        );
        $this->assertEquals(250.00, (float) $task->credit_amount, 'the credit figure is untouched');
    }

    public function test_an_admin_updates_the_credit_amount_normally(): void
    {
        $task = $this->makeTask(250.00);

        Livewire::actingAs($this->admin)
            ->test(ManageTasks::class)
            ->call('openEdit', $task)
            ->set('credit_amount', '480.50')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(480.50, (float) $task->refresh()->credit_amount);
    }

    // ── The model-level backstop ─────────────────────────────────────────────

    /**
     * The rule lives on the model, so it holds for any write path — including
     * the ones that do not exist yet. TaskController@update is currently not
     * even routed; this is what will protect it if it ever is.
     */
    public function test_the_model_reverts_a_credit_change_made_by_a_non_admin(): void
    {
        $task = $this->makeTask(100.00);

        $this->actingAs($this->pm);

        $task->update(['title' => 'Still editable', 'credit_amount' => 4242.00]);

        $task->refresh();
        $this->assertSame('Still editable', $task->title);
        $this->assertEquals(100.00, (float) $task->credit_amount);
    }

    public function test_the_model_allows_an_admin_to_change_the_credit(): void
    {
        $task = $this->makeTask(100.00);

        $this->actingAs($this->admin);
        $task->update(['credit_amount' => 4242.00]);

        $this->assertEquals(4242.00, (float) $task->refresh()->credit_amount);
    }

    /**
     * Nobody is authenticated in a console command, a queued job or a seeder,
     * and those must keep working — the guard applies to acting users, not to
     * the column.
     */
    public function test_an_unauthenticated_context_may_still_write_the_credit(): void
    {
        $task = $this->makeTask(100.00);

        Auth::logout();
        $task->update(['credit_amount' => 777.00]);

        $this->assertEquals(777.00, (float) $task->refresh()->credit_amount);
    }

    // ── A PM setting the figure in the first place is fine ───────────────────

    public function test_a_pm_may_still_set_the_credit_when_filing_a_new_task(): void
    {
        $this->actingAs($this->pm);

        $task = Task::create([
            'title'         => 'Newly filed',
            'task_code'     => 'CR_NEW',
            'unit_id'       => $this->unit->id,
            'created_by'    => $this->pm->id,
            'pm_id'         => $this->pm->id,
            'priority'      => 'low',
            'status'        => 'pending',
            'deadline'      => now()->addDays(3),
            'credit_amount' => 320.00,
        ]);

        $this->assertEquals(320.00, (float) $task->refresh()->credit_amount, 'the rule binds edits, not creation');
    }

    // ── The form request ─────────────────────────────────────────────────────

    public function test_the_update_request_only_validates_credit_amount_for_an_admin(): void
    {
        $task = $this->makeTask();

        $request = UpdateTaskRequest::create("/tasks/{$task->id}", 'PUT');
        $request->setRouteResolver(fn () => tap(
            new \Illuminate\Routing\Route('PUT', '/tasks/{task}', []),
            fn ($route) => $route->bind($request)->setParameter('task', $task)
        ));

        $this->actingAs($this->pm);
        $this->assertArrayNotHasKey('credit_amount', $request->rules());

        $this->actingAs($this->admin);
        $this->assertArrayHasKey('credit_amount', $request->rules());
    }

    // ── Deletion stays separate from editing ─────────────────────────────────

    public function test_a_pm_with_tasks_update_still_cannot_delete_a_task(): void
    {
        $task = $this->makeTask();

        $this->assertTrue($this->pm->hasPermission('tasks.update'));

        Livewire::actingAs($this->pm)
            ->test(ManageTasks::class)
            ->call('openDeleteModal', $task->id)
            ->call('confirmDelete')
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_an_admin_can_delete_a_task(): void
    {
        $task = $this->makeTask();

        Livewire::actingAs($this->admin)
            ->test(ManageTasks::class)
            ->call('openDeleteModal', $task->id)
            ->call('confirmDelete');

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    /**
     * Inverted from the previous change, which had deletion as a grantable
     * toggle. It no longer is: the policies ask whether the user administers
     * the agency and never look at the permission table, so forcing a grant
     * straight into the database — past the panel, which will not even offer
     * it — still changes nothing.
     */
    public function test_a_forced_delete_grant_does_not_let_a_pm_delete(): void
    {
        $task = $this->makeTask();

        // The permission no longer exists as a concept, so it has to be
        // manufactured to make the point at all.
        $permission = Permission::create([
            'name'   => 'tasks.delete',
            'module' => 'tasks',
            'action' => 'delete',
            'label'  => 'Delete Tasks',
        ]);

        RolePermission::updateOrCreate(
            ['role' => 'pm', 'permission_id' => $permission->id],
            ['allowed' => true]
        );
        PermissionService::flushAll();

        $this->assertTrue(
            $this->pm->fresh()->hasPermission('tasks.delete'),
            'the forced grant really is in place — the refusal below is not an accident'
        );

        Livewire::actingAs($this->pm)
            ->test(ManageTasks::class)
            ->call('openDeleteModal', $task->id)
            ->call('confirmDelete')
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }
}
