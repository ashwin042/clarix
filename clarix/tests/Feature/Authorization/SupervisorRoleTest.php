<?php

namespace Tests\Feature\Authorization;

use App\Livewire\Tasks\ManageTasks;
use App\Livewire\Admin\ManageUnits;
use App\Livewire\Admin\ManageUsers;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * Supervisor: broad, but not admin.
 *
 * The role exists because an agency needed somebody who runs the work across
 * every unit without being handed the agency itself. That makes it a third
 * shape of access, and the shape is the thing these tests pin:
 *
 *   - PM reaches one unit, structurally, and no grant widens that.
 *   - Admin reaches everything and may destroy it.
 *   - Supervisor reaches everything the agency owns and may destroy none of it.
 *
 * The org-wide half is tested against a *second* unit that the supervisor has
 * no membership of, because a supervisor carries no unit_id at all — a test
 * written against the one unit in the fixture would pass just as well if the
 * role had quietly inherited the PM's own-unit rule.
 */
class SupervisorRoleTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $a;

    /** @var array<string, mixed> */
    protected array $b;

    protected User $supervisor;

    /**
     * A second unit inside agency A, with its own PM and task. Nothing about
     * it touches the supervisor, which is the point.
     */
    protected Unit $otherUnit;

    protected User $otherPm;

    protected Task $otherTask;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        PermissionService::flushAll();

        $this->a = $this->populate($this->makeOrganization('sup-a', 'Agency A'), 'A');
        $this->b = $this->populate($this->makeOrganization('sup-b', 'Agency B'), 'B');

        // Payroll, leave and attendance sit behind the ERP plan as well as
        // behind permissions. This suite is about the permissions, so the
        // agency buys the plan and the plan layer stays out of the way.
        $this->subscribeOrganization($this->a['organization']);

        TenantContext::actingAsOrganization($this->a['organization']->id, function () {
            $this->supervisor = User::factory()->create([
                'name'  => 'Supervisor A',
                'email' => 'supervisor.a@example.test',
                'role'  => 'supervisor',
            ]);

            $this->otherUnit = Unit::create(['name' => 'Second Unit A']);

            $this->otherPm = User::factory()->create([
                'name'    => 'Second PM A',
                'email'   => 'pm2.a@example.test',
                'role'    => 'pm',
                'unit_id' => $this->otherUnit->id,
            ]);

            $this->otherTask = Task::create([
                'title'         => 'Second Unit Task',
                'task_code'     => 'SUP_OTHER',
                'unit_id'       => $this->otherUnit->id,
                'created_by'    => $this->otherPm->id,
                'pm_id'         => $this->otherPm->id,
                'priority'      => 'medium',
                'status'        => 'pending',
                'deadline'      => now()->addDays(7),
                'credit_amount' => 10.00,
            ]);
        });
    }

    // ── The default grants ───────────────────────────────────────────────────

    public function test_supervisor_holds_the_four_granted_capabilities_by_default(): void
    {
        foreach (['units.create', 'tasks.create', 'tasks.update', 'tasks.assign'] as $permission) {
            $this->assertTrue(
                $this->supervisor->hasPermission($permission),
                "supervisor should hold {$permission} by default"
            );
        }
    }

    public function test_supervisor_holds_the_reads_those_grants_depend_on(): void
    {
        foreach (['units.view', 'tasks.view', 'tasks.upload_files'] as $permission) {
            $this->assertTrue(
                $this->supervisor->hasPermission($permission),
                "supervisor should hold {$permission} by default"
            );
        }
    }

    public function test_supervisor_is_denied_the_areas_the_role_is_kept_out_of(): void
    {
        $denied = [
            'units.update',
            'users.view', 'users.create', 'users.update',
            'credits.view',
            'attendance.view_all', 'attendance.manage',
            'leave.view_all', 'leave.manage',
            'payroll.manage',
        ];

        foreach ($denied as $permission) {
            $this->assertFalse(
                $this->supervisor->hasPermission($permission),
                "supervisor must not hold {$permission} by default"
            );
        }
    }

    public function test_supervisor_keeps_the_personal_records_every_role_gets(): void
    {
        foreach (['attendance.view_own', 'leave.view_own', 'payroll.view_own'] as $permission) {
            $this->assertTrue(
                $this->supervisor->hasPermission($permission),
                "supervisor should hold {$permission} by default"
            );
        }
    }

    // ── Units, org-wide ──────────────────────────────────────────────────────

    public function test_supervisor_can_open_the_units_page(): void
    {
        $this->actingAs($this->supervisor)
            ->get(route('admin.units.index'))
            ->assertOk();
    }

    public function test_supervisor_can_create_a_unit(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('admin.units.store'), ['name' => 'Supervisor Unit'])
            ->assertRedirect();

        $this->assertDatabaseHas('units', [
            'name'            => 'Supervisor Unit',
            'organization_id' => $this->a['organization']->id,
        ]);
    }

    public function test_supervisor_cannot_edit_a_unit(): void
    {
        $this->actingAs($this->supervisor)
            ->put(route('admin.units.update', $this->a['unit']), ['name' => 'Renamed'])
            ->assertForbidden();

        $this->assertDatabaseHas('units', ['id' => $this->a['unit']->id, 'name' => 'Unit A']);
    }

    // ── Tasks, org-wide ──────────────────────────────────────────────────────

    /**
     * Not a gap in the supervisor's reach — the shape of that screen.
     *
     * /tasks/create files a task under the actor's own unit with the actor as
     * its PM. Neither is a field on the form; both are read straight off the
     * signed-in user, and the guard's second half refuses anyone carrying no
     * unit_id. That is a structural refusal, not a permission one, which is
     * why it catches a supervisor holding tasks.create.
     *
     * It catches the admin identically, and that is the point worth pinning:
     * there is no admin path on this screen to extend a supervisor into.
     * Giving one a unit and PM picker here would not be parity with the admin,
     * it would be more than the admin gets, built as a second implementation
     * of the modal that already does this job. So the screen stays the PM's,
     * and both roles get the same answer from it.
     *
     * The create path for both is the board's modal, which asks which unit;
     * the modal tests above cover it.
     */
    public function test_the_unit_scoped_create_screen_refuses_supervisor_and_admin_alike(): void
    {
        $this->assertTrue($this->supervisor->hasPermission('tasks.create'));

        $this->actingAs($this->supervisor)
            ->get(route('tasks.create'))
            ->assertForbidden();

        $this->actingAs($this->a['admin'])
            ->get(route('tasks.create'))
            ->assertForbidden();
    }

    /**
     * The supervisor belongs to no unit, so every unit in the agency is
     * "somebody else's" — a role that could only file work into its own would
     * be able to create nothing at all.
     */
    public function test_supervisor_can_create_a_task_in_a_unit_it_does_not_belong_to(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(ManageTasks::class)
            ->call('openCreate')
            ->set('title', 'Filed by the supervisor')
            ->set('task_code', 'SUP_NEW')
            ->set('unit_id', (string) $this->otherUnit->id)
            ->set('pm_id', (string) $this->otherPm->id)
            ->set('priority', 'medium')
            ->set('status', 'pending')
            ->set('deadline', now()->addDays(5)->toDateString())
            ->set('credit_amount', '12')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'task_code' => 'SUP_NEW',
            'unit_id'   => $this->otherUnit->id,
        ]);
    }

    public function test_supervisor_can_update_a_task_in_any_unit_of_the_agency(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(ManageTasks::class)
            ->call('openEdit', $this->otherTask->id)
            ->set('title', 'Retitled by the supervisor')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Retitled by the supervisor', $this->otherTask->fresh()->title);
    }

    public function test_supervisor_can_assign_a_writer_to_a_task_in_any_unit(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('tasks.assignments.store', $this->otherTask), [
                'task_id'    => $this->otherTask->id,
                'writer_ids' => [$this->a['writer']->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('task_assignments', [
            'task_id'   => $this->otherTask->id,
            'writer_id' => $this->a['writer']->id,
        ]);
    }

    public function test_supervisor_sees_tasks_from_every_unit_on_the_task_board(): void
    {
        // Explicitly the board: the table column renders task codes rather
        // than titles, and this is about which units a supervisor reaches.
        Livewire::actingAs($this->supervisor)
            ->test(ManageTasks::class)
            ->call('setView', 'kanban')
            ->assertSee('Task A')
            ->assertSee('Second Unit Task');
    }

    /**
     * Creating a task means choosing a unit, and the dropdown is built from
     * whatever the role may file into. A supervisor with no unit_id was handed
     * an empty list, which made the grant unusable in the only screen offering
     * it.
     */
    public function test_the_task_form_offers_the_supervisor_every_unit(): void
    {
        $units = Livewire::actingAs($this->supervisor)
            ->test(ManageTasks::class)
            ->viewData('units');

        $this->assertTrue($units->contains('id', $this->a['unit']->id));
        $this->assertTrue($units->contains('id', $this->otherUnit->id));
    }

    // ── The create modal is the admin's modal ────────────────────────────────

    /*
     * The component was already role-correct — render() hands a supervisor
     * every unit, openCreate() does not pin them to one, and save() forces
     * unit and PM only for a PM. The *view* was not: both pickers sat behind
     * an isAdmin() branch, so a supervisor got admin's data and a PM's markup
     * — two disabled boxes, no way to set unit_id or pm_id, and a form that
     * could only fail validation.
     *
     * The existing create test passes because it sets the properties
     * directly, which is precisely what a real browser cannot do. So these
     * assert on rendered HTML, and each is written twice: once for the admin,
     * to prove the markup being looked for is the markup admin actually gets,
     * and once for the supervisor, which is the claim under test.
     */

    /** The control the screenshot shows the admin using. */
    private const UNIT_PICKER = 'wire:model.live="unit_id"';

    private const PM_PICKER = 'wire:model="pm_id"';

    /** The PM-shaped fallback: unit fixed, PM forced to the actor. */
    private const PM_LOCKED = 'Automatically assigned to you';

    public function test_the_create_modal_gives_the_admin_a_unit_dropdown(): void
    {
        Livewire::actingAs($this->a['admin'])
            ->test(ManageTasks::class)
            ->call('openCreate')
            ->assertSee(self::UNIT_PICKER, false)
            ->assertSee('Second Unit A')
            ->assertDontSee(self::PM_LOCKED, false);
    }

    public function test_the_create_modal_gives_the_supervisor_the_same_unit_dropdown(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(ManageTasks::class)
            ->call('openCreate')
            ->assertSee(self::UNIT_PICKER, false)
            ->assertSee('Second Unit A')
            ->assertDontSee(self::PM_LOCKED, false);
    }

    public function test_the_create_modal_gives_the_admin_a_pm_dropdown_once_a_unit_is_chosen(): void
    {
        Livewire::actingAs($this->a['admin'])
            ->test(ManageTasks::class)
            ->call('openCreate')
            ->set('unit_id', (string) $this->otherUnit->id)
            ->assertSee(self::PM_PICKER, false)
            ->assertSee('Second PM A');
    }

    public function test_the_create_modal_gives_the_supervisor_the_same_pm_dropdown(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(ManageTasks::class)
            ->call('openCreate')
            ->set('unit_id', (string) $this->otherUnit->id)
            ->assertSee(self::PM_PICKER, false)
            ->assertSee('Second PM A');
    }

    /**
     * The PM keeps the fallback. This is the other half of the fix: widening
     * the branch must not hand a unit-bound role a unit picker it has no
     * business with, and save() would overwrite anything it chose anyway.
     */
    public function test_the_create_modal_still_pins_the_pm_to_its_own_unit(): void
    {
        Livewire::actingAs($this->a['pm'])
            ->test(ManageTasks::class)
            ->call('openCreate')
            ->assertDontSee(self::UNIT_PICKER, false)
            ->assertSee(self::PM_LOCKED, false);
    }

    /**
     * Status *is* part of this parity now, and this test used to say the
     * opposite.
     *
     * It was correct when written: updateStatus() was isAdmin() and nothing
     * else, so the disabled box was the policy showing through rather than an
     * oversight. Since then status has become tasks.update_status — a
     * permission an agency grants, held by the supervisor out of the box — and
     * the modal was the last screen still asking the role instead. Leaving the
     * assertion inverted would have pinned the bug in place.
     *
     * The narrow claim lives in EditTaskStatusFieldTest, including the roles
     * that stay locked; this keeps the supervisor's half beside the rest of
     * the create-modal parity it belongs to.
     */
    public function test_the_create_modal_hands_the_supervisor_the_status_dropdown(): void
    {
        $this->assertTrue($this->supervisor->hasPermission('tasks.update_status'));

        Livewire::actingAs($this->supervisor)
            ->test(ManageTasks::class)
            ->call('openCreate')
            ->assertSee('wire:model="status"', false);
    }

    // ── Nothing may be destroyed ─────────────────────────────────────────────

    public function test_supervisor_cannot_delete_a_task(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(ManageTasks::class)
            ->call('openDeleteModal', $this->otherTask->id)
            ->call('confirmDelete')
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $this->otherTask->id]);
    }

    public function test_supervisor_cannot_delete_a_unit(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(ManageUnits::class)
            ->call('openDeleteModal', $this->otherUnit->id)
            ->call('confirmDelete')
            ->assertForbidden();

        $this->assertDatabaseHas('units', ['id' => $this->otherUnit->id]);
    }

    public function test_supervisor_cannot_delete_a_user_over_http(): void
    {
        $this->actingAs($this->supervisor)
            ->delete(route('admin.users.destroy', $this->a['writer']))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $this->a['writer']->id]);
    }

    public function test_supervisor_cannot_delete_a_unit_over_http(): void
    {
        $this->actingAs($this->supervisor)
            ->delete(route('admin.units.destroy', $this->otherUnit))
            ->assertForbidden();

        $this->assertDatabaseHas('units', ['id' => $this->otherUnit->id]);
    }

    // ── The HR modules are not the supervisor's ──────────────────────────────

    public function test_supervisor_cannot_open_payroll_management(): void
    {
        $this->actingAs($this->supervisor)
            ->get(route('payroll.manage'))
            ->assertForbidden();
    }

    public function test_supervisor_cannot_decide_a_leave_request(): void
    {
        $this->assertFalse($this->supervisor->hasPermission('leave.manage'));

        $this->actingAs($this->supervisor);

        $this->assertFalse(
            \Illuminate\Support\Facades\Gate::forUser($this->supervisor)
                ->allows('viewAny', \App\Models\LeaveRequest::class)
        );
    }

    public function test_supervisor_cannot_correct_another_persons_attendance(): void
    {
        $this->assertFalse(
            \Illuminate\Support\Facades\Gate::forUser($this->supervisor)
                ->allows('manage', [\App\Models\Attendance::class, $this->a['writer']])
        );
    }

    // ── Users and the panel stay shut ────────────────────────────────────────

    public function test_supervisor_cannot_open_users_management(): void
    {
        $this->actingAs($this->supervisor)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_supervisor_cannot_open_the_authorization_panel(): void
    {
        $this->actingAs($this->supervisor)
            ->get(route('admin.authorization'))
            ->assertForbidden();
    }

    public function test_supervisor_cannot_open_the_per_role_staff_screens(): void
    {
        $this->actingAs($this->supervisor)
            ->get(route('admin.writers.index'))
            ->assertForbidden();
    }

    // ── The sidebar matches the grants ───────────────────────────────────────

    public function test_the_sidebar_offers_the_supervisor_units_and_tasks_but_no_admin_areas(): void
    {
        $sidebar = $this->actingAs($this->supervisor)
            ->get(route('leave.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('admin.units.index'), $sidebar);
        $this->assertStringContainsString(route('tasks.index'), $sidebar);

        $this->assertStringNotContainsString(route('admin.users.index'), $sidebar);
        $this->assertStringNotContainsString(route('admin.authorization'), $sidebar);
        $this->assertStringNotContainsString(route('credits.index'), $sidebar);
        $this->assertStringNotContainsString(route('payroll.manage'), $sidebar);
    }

    // ── Cross-agency ─────────────────────────────────────────────────────────

    public function test_supervisor_cannot_reach_another_agencys_task(): void
    {
        $this->actingAs($this->supervisor)
            ->get(route('tasks.show', $this->b['task']))
            ->assertNotFound();
    }

    public function test_supervisor_sees_no_task_belonging_to_another_agency(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(ManageTasks::class)
            ->assertDontSee('Task B');
    }

    public function test_supervisor_cannot_assign_a_writer_to_another_agencys_task(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('tasks.assignments.store', $this->b['task']), [
                'task_id'    => $this->b['task']->id,
                'writer_ids' => [$this->a['writer']->id],
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('task_assignments', [
            'task_id'   => $this->b['task']->id,
            'writer_id' => $this->a['writer']->id,
        ]);
    }

    public function test_supervisor_units_page_shows_only_its_own_agency(): void
    {
        Livewire::actingAs($this->supervisor)
            ->test(ManageUnits::class)
            ->assertSee('Unit A')
            ->assertDontSee('Unit B');
    }

    /**
     * A grant is an agency's decision about its own people. Turning tasks.view
     * off for supervisors in agency B must leave agency A's supervisor working.
     */
    public function test_a_grant_in_one_agency_does_not_follow_the_role_into_another(): void
    {
        $supervisorB = TenantContext::actingAsOrganization(
            $this->b['organization']->id,
            fn () => User::factory()->create([
                'name'  => 'Supervisor B',
                'email' => 'supervisor.b@example.test',
                'role'  => 'supervisor',
            ])
        );

        $this->setPermissionIn($this->b['organization']->id, 'supervisor', 'tasks.create', false);

        $this->assertFalse($supervisorB->fresh()->hasPermission('tasks.create'));
        $this->assertTrue($this->supervisor->fresh()->hasPermission('tasks.create'));
    }

    protected function setPermissionIn(int $organizationId, string $role, string $name, bool $allowed): void
    {
        TenantContext::actingAsOrganization($organizationId, function () use ($role, $name, $allowed) {
            \App\Models\RolePermission::updateOrCreate(
                ['role' => $role, 'permission_id' => \App\Models\Permission::where('name', $name)->firstOrFail()->id],
                ['allowed' => $allowed]
            );
        });

        PermissionService::flushAll();
    }
}
