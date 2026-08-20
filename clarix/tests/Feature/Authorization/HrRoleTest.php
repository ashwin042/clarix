<?php

namespace Tests\Feature\Authorization;

use App\Livewire\Attendance\AttendancePage;
use App\Livewire\Leave\LeavePage;
use App\Livewire\Payroll\ManagePayroll;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollRecord;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * HR: three modules, all the way down, and nothing else.
 *
 * The role is the mirror image of Supervisor. Where a supervisor runs the work
 * and never touches people's records, HR runs the records and never touches
 * the work — so the denials here are the whole of Management, and the grants
 * are attendance, leave and payroll at the level an admin holds them.
 *
 * "All the way down" is the part worth pinning. attendance.manage on its own
 * would have left HR correcting nobody's record but their own, because the
 * reach half of those policies is structural and knew only about admins and
 * PMs. A permission that resolves true while the action still fails is exactly
 * the decorative-toggle failure this codebase has fixed once already.
 */
class HrRoleTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $a;

    /** @var array<string, mixed> */
    protected array $b;

    protected User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        PermissionService::flushAll();

        $this->a = $this->populate($this->makeOrganization('hr-a', 'Agency A'), 'A');
        $this->b = $this->populate($this->makeOrganization('hr-b', 'Agency B'), 'B');

        $this->subscribeOrganization($this->a['organization']);
        $this->subscribeOrganization($this->b['organization']);

        $this->hr = TenantContext::actingAsOrganization(
            $this->a['organization']->id,
            fn () => User::factory()->create([
                'name'  => 'HR A',
                'email' => 'hr.a@example.test',
                'role'  => 'hr',
            ])
        );
    }

    protected function typeFor(array $org, string $name = 'Annual Leave'): LeaveType
    {
        return LeaveType::withoutGlobalScopes()
            ->where('organization_id', $org['organization']->id)
            ->where('name', $name)
            ->firstOrFail();
    }

    protected function requestFor(array $org, User $user, string $start, string $end): LeaveRequest
    {
        return TenantContext::actingAsOrganization($org['organization']->id, function () use ($org, $user, $start, $end) {
            $request = new LeaveRequest([
                'leave_type_id' => $this->typeFor($org)->id,
                'start_date'    => $start,
                'end_date'      => $end,
                'reason'        => 'Because',
            ]);

            $request->user_id = $user->id;
            $request->status  = 'pending';
            $request->save();

            return $request;
        });
    }

    // ── The default grants ───────────────────────────────────────────────────

    public function test_hr_holds_the_three_modules_at_management_level(): void
    {
        $granted = [
            'attendance.view_own', 'attendance.view_all', 'attendance.manage',
            'leave.view_own', 'leave.view_all', 'leave.manage',
            'payroll.view_own', 'payroll.manage',
        ];

        foreach ($granted as $permission) {
            $this->assertTrue(
                $this->hr->hasPermission($permission),
                "hr should hold {$permission} by default"
            );
        }
    }

    public function test_hr_holds_nothing_outside_those_three_modules(): void
    {
        $denied = [
            'units.view', 'units.create', 'units.update',
            'users.view', 'users.create', 'users.update',
            'tasks.view', 'tasks.create', 'tasks.update', 'tasks.assign', 'tasks.upload_files',
            'credits.view',
        ];

        foreach ($denied as $permission) {
            $this->assertFalse(
                $this->hr->hasPermission($permission),
                "hr must not hold {$permission} by default"
            );
        }
    }

    // ── Attendance, org-wide ─────────────────────────────────────────────────

    public function test_hr_may_reach_anybodys_attendance_in_the_agency(): void
    {
        foreach ([$this->a['writer'], $this->a['pm'], $this->a['admin']] as $subject) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Gate::forUser($this->hr)
                    ->allows('manage', [Attendance::class, $subject]),
                "hr should be able to correct {$subject->name}'s attendance"
            );
        }
    }

    public function test_hr_can_mark_attendance_for_somebody_in_another_unit(): void
    {
        Livewire::actingAs($this->hr)
            ->test(AttendancePage::class)
            ->call('openMark', $this->a['writer']->id)
            ->set('status', 'present')
            ->set('notes', 'Marked by HR')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('attendances', [
            'user_id'         => $this->a['writer']->id,
            'status'          => 'present',
            'organization_id' => $this->a['organization']->id,
        ]);
    }

    public function test_the_attendance_team_table_shows_hr_the_whole_agency(): void
    {
        $roster = Livewire::actingAs($this->hr)
            ->test(AttendancePage::class)
            ->viewData('roster')
            ->pluck('id');

        foreach ([$this->a['writer'], $this->a['pm'], $this->a['admin']] as $subject) {
            $this->assertTrue(
                $roster->contains($subject->id),
                "hr should see {$subject->name} in the attendance roster"
            );
        }

        $this->assertFalse($roster->contains($this->b['writer']->id));
    }

    // ── Leave, org-wide ──────────────────────────────────────────────────────

    public function test_hr_can_approve_a_leave_request_from_anybody_in_the_agency(): void
    {
        $request = $this->requestFor($this->a, $this->a['writer'], now()->addWeek()->toDateString(), now()->addWeek()->addDay()->toDateString());

        Livewire::actingAs($this->hr)
            ->test(LeavePage::class)
            ->call('approve', $request->id);

        $this->assertSame('approved', $request->fresh()->status);
    }

    public function test_hr_can_reject_a_leave_request(): void
    {
        $request = $this->requestFor($this->a, $this->a['pm'], now()->addWeeks(2)->toDateString(), now()->addWeeks(2)->addDay()->toDateString());

        Livewire::actingAs($this->hr)
            ->test(LeavePage::class)
            ->call('reject', $request->id);

        $this->assertSame('rejected', $request->fresh()->status);
    }

    public function test_the_leave_queue_shows_hr_the_whole_agency(): void
    {
        $mine = $this->requestFor($this->a, $this->a['writer'], now()->addWeek()->toDateString(), now()->addWeek()->toDateString());

        $queue = Livewire::actingAs($this->hr)
            ->test(LeavePage::class)
            ->viewData('queue')
            ->pluck('id');

        $this->assertTrue($queue->contains($mine->id));
    }

    // ── Payroll, at management level ─────────────────────────────────────────

    public function test_hr_can_open_payroll_management(): void
    {
        $this->actingAs($this->hr)
            ->get(route('payroll.manage'))
            ->assertOk();
    }

    public function test_hr_can_create_finalize_and_mark_a_payroll_record_paid(): void
    {
        $component = Livewire::actingAs($this->hr)
            ->test(ManagePayroll::class)
            ->call('openRecord', $this->a['writer']->id)
            ->set('base_amount', '1000')
            ->set('deductions', '100')
            ->call('save')
            ->assertHasNoErrors();

        $record = PayrollRecord::withoutGlobalScopes()
            ->where('user_id', $this->a['writer']->id)
            ->firstOrFail();

        $this->assertSame('draft', $record->status);

        $component->call('finalize', $record->id);
        $this->assertSame('finalized', $record->fresh()->status);

        $component->call('markPaid', $record->id);
        $this->assertSame('paid', $record->fresh()->status);
    }

    public function test_hr_cannot_delete_a_payroll_record(): void
    {
        $record = TenantContext::actingAsOrganization($this->a['organization']->id, function () {
            $record = new PayrollRecord(['month' => now()->startOfMonth()->toDateString(), 'base_amount' => 500, 'deductions' => 0]);
            $record->user_id    = $this->a['writer']->id;
            $record->created_by = $this->a['admin']->id;
            $record->save();

            return $record;
        });

        Livewire::actingAs($this->hr)
            ->test(ManagePayroll::class)
            ->call('openDeleteModal', $record->id)
            ->call('confirmDelete')
            ->assertForbidden();

        $this->assertDatabaseHas('payroll_records', ['id' => $record->id]);
    }

    // ── Everything else stays shut ───────────────────────────────────────────

    public function test_hr_cannot_open_units_tasks_users_credits_or_the_panel(): void
    {
        $this->actingAs($this->hr)->get(route('admin.units.index'))->assertForbidden();
        $this->actingAs($this->hr)->get(route('tasks.index'))->assertForbidden();
        $this->actingAs($this->hr)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($this->hr)->get(route('credits.index'))->assertForbidden();
        $this->actingAs($this->hr)->get(route('admin.authorization'))->assertForbidden();
    }

    public function test_hr_cannot_create_a_unit_or_a_task(): void
    {
        $this->actingAs($this->hr)
            ->post(route('admin.units.store'), ['name' => 'HR Unit'])
            ->assertForbidden();

        $this->assertDatabaseMissing('units', ['name' => 'HR Unit']);

        $this->actingAs($this->hr)->get(route('tasks.create'))->assertForbidden();
    }

    public function test_hr_cannot_delete_anything(): void
    {
        $this->actingAs($this->hr)
            ->delete(route('admin.users.destroy', $this->a['writer']))
            ->assertForbidden();

        $this->actingAs($this->hr)
            ->delete(route('admin.units.destroy', $this->a['unit']))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $this->a['writer']->id]);
        $this->assertDatabaseHas('units', ['id' => $this->a['unit']->id]);
    }

    // ── The sidebar matches the grants ───────────────────────────────────────

    public function test_the_sidebar_offers_hr_only_the_three_modules(): void
    {
        $sidebar = $this->actingAs($this->hr)
            ->get(route('leave.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('attendance.index'), $sidebar);
        $this->assertStringContainsString(route('leave.index'), $sidebar);
        $this->assertStringContainsString(route('payroll.manage'), $sidebar);

        $this->assertStringNotContainsString(route('admin.units.index'), $sidebar);
        $this->assertStringNotContainsString(route('tasks.index'), $sidebar);
        $this->assertStringNotContainsString(route('admin.users.index'), $sidebar);
        $this->assertStringNotContainsString(route('credits.index'), $sidebar);
        $this->assertStringNotContainsString(route('admin.authorization'), $sidebar);
    }

    // ── Cross-agency ─────────────────────────────────────────────────────────

    public function test_hr_cannot_reach_another_agencys_person(): void
    {
        $this->assertFalse(
            \Illuminate\Support\Facades\Gate::forUser($this->hr)
                ->allows('manage', [Attendance::class, $this->b['writer']])
        );
    }

    public function test_hr_sees_no_other_agency_in_the_payroll_member_list(): void
    {
        $members = Livewire::actingAs($this->hr)
            ->test(ManagePayroll::class)
            ->viewData('members')
            ->pluck('id');

        $this->assertTrue($members->contains($this->a['writer']->id));
        $this->assertFalse($members->contains($this->b['writer']->id));
    }

    public function test_hr_cannot_decide_another_agencys_leave_request(): void
    {
        $foreign = $this->requestFor($this->b, $this->b['writer'], now()->addWeek()->toDateString(), now()->addWeek()->toDateString());

        // The lookup is scoped to the people this viewer reaches, and agency
        // B's writer is not among them, so the request is not refused — it is
        // not found. Reaching "everyone" means everyone in one's own agency.
        try {
            Livewire::actingAs($this->hr)
                ->test(LeavePage::class)
                ->call('approve', $foreign->id);

            $this->fail('HR should not have been able to resolve another agency’s leave request.');
        } catch (ModelNotFoundException) {
            // expected
        }

        $this->assertSame('pending', $foreign->fresh()->status);
    }

    public function test_a_grant_in_one_agency_does_not_follow_the_role_into_another(): void
    {
        $hrB = TenantContext::actingAsOrganization(
            $this->b['organization']->id,
            fn () => User::factory()->create([
                'name'  => 'HR B',
                'email' => 'hr.b@example.test',
                'role'  => 'hr',
            ])
        );

        TenantContext::actingAsOrganization($this->b['organization']->id, function () {
            RolePermission::updateOrCreate(
                ['role' => 'hr', 'permission_id' => Permission::where('name', 'payroll.manage')->firstOrFail()->id],
                ['allowed' => false]
            );
        });

        PermissionService::flushAll();

        $this->assertFalse($hrB->fresh()->hasPermission('payroll.manage'));
        $this->assertTrue($this->hr->fresh()->hasPermission('payroll.manage'));
    }
}
