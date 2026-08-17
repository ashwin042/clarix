<?php

namespace Tests\Feature\Leave;

use App\Livewire\Leave\LeavePage;
use App\Livewire\Leave\ManageLeaveTypes;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\LeaveApproval;
use App\Services\LeaveBalance;
use App\Services\PermissionService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * Leave management, phase 2 of the ERP work.
 *
 * The rules being pinned: you ask for your own leave and nobody else's; nobody
 * decides their own request, an admin included; deciding is a permission and
 * whose requests you reach is structural; an approval writes through to
 * attendance; and none of it crosses an agency boundary or reaches the
 * platform.
 */
class LeaveManagementTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $a;

    /** @var array<string, mixed> */
    protected array $b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        PermissionService::flushAll();

        $this->a = $this->populate($this->makeOrganization('lv-a', 'Agency A'), 'A');
        $this->b = $this->populate($this->makeOrganization('lv-b', 'Agency B'), 'B');

        // Leave is ERP, which the plan layer sells from Standard up. This suite
        // is about the permission layer, so both agencies go on a plan that
        // includes ERP.
        $this->subscribeOrganization($this->a['organization']);
        $this->subscribeOrganization($this->b['organization']);
    }

    protected function typeFor(array $org, string $name = 'Annual Leave'): LeaveType
    {
        return LeaveType::withoutGlobalScopes()
            ->where('organization_id', $org['organization']->id)
            ->where('name', $name)
            ->firstOrFail();
    }

    protected function setPermission(array $org, string $role, string $name, bool $allowed): void
    {
        TenantContext::actingAsOrganization($org['organization']->id, function () use ($role, $name, $allowed) {
            RolePermission::updateOrCreate(
                ['role' => $role, 'permission_id' => Permission::where('name', $name)->firstOrFail()->id],
                ['allowed' => $allowed]
            );
        });

        PermissionService::flushAll();
    }

    /**
     * A pending request, created the way the page creates one.
     */
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

    // ── Default leave types ──────────────────────────────────────────────────

    public function test_a_new_organization_receives_the_default_leave_types(): void
    {
        $fresh = Organization::create([
            'name' => 'Fresh', 'contact_number' => '0', 'email' => 'fresh@example.test',
            'address' => 'x', 'subscription_type' => 'base', 'slug' => 'lv-fresh',
        ]);

        $names = LeaveType::withoutGlobalScopes()
            ->where('organization_id', $fresh->id)
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['Annual Leave', 'Casual Leave', 'Sick Leave'], $names);

        // No invented allowances: an agency sets its own policy.
        $this->assertTrue(
            LeaveType::withoutGlobalScopes()
                ->where('organization_id', $fresh->id)
                ->whereNotNull('default_annual_allowance')
                ->doesntExist()
        );
    }

    public function test_leave_types_are_scoped_to_their_organization(): void
    {
        $this->actingAs($this->a['admin']);

        $names = LeaveType::pluck('name');
        $this->assertCount(3, $names, 'only agency A\'s own types');

        $this->assertNull(LeaveType::find($this->typeFor($this->b)->id));
    }

    // ── Requesting ───────────────────────────────────────────────────────────

    public function test_a_user_submits_a_request_for_themselves(): void
    {
        $writer = $this->a['writer'];

        Livewire::actingAs($writer)
            ->test(LeavePage::class)
            ->set('leave_type_id', (string) $this->typeFor($this->a)->id)
            ->set('start_date', today()->addDays(3)->toDateString())
            ->set('end_date', today()->addDays(5)->toDateString())
            ->set('reason', 'Family')
            ->call('submit')
            ->assertHasNoErrors();

        $request = LeaveRequest::withoutGlobalScopes()->where('user_id', $writer->id)->firstOrFail();

        $this->assertSame('pending', $request->status);
        $this->assertSame(3, $request->dayCount());
        $this->assertSame($this->a['organization']->id, $request->organization_id);
        $this->assertNull($request->reviewed_by);
    }

    /**
     * The form has no user field, so there is nothing to tamper with. This
     * asserts the record really lands against the actor and nobody else.
     */
    public function test_submitting_only_ever_records_the_acting_user(): void
    {
        Livewire::actingAs($this->a['writer'])
            ->test(LeavePage::class)
            ->set('leave_type_id', (string) $this->typeFor($this->a)->id)
            ->set('start_date', today()->toDateString())
            ->set('end_date', today()->toDateString())
            ->call('submit');

        $this->assertSame(1, DB::table('leave_requests')->where('user_id', $this->a['writer']->id)->count());
        $this->assertSame(0, DB::table('leave_requests')->where('user_id', $this->a['pm']->id)->count());
    }

    public function test_a_request_cannot_use_another_organizations_leave_type(): void
    {
        Livewire::actingAs($this->a['writer'])
            ->test(LeavePage::class)
            ->set('leave_type_id', (string) $this->typeFor($this->b)->id)
            ->set('start_date', today()->toDateString())
            ->set('end_date', today()->toDateString())
            ->call('submit')
            ->assertHasErrors('leave_type_id');

        $this->assertSame(0, DB::table('leave_requests')->count());
    }

    public function test_overlapping_requests_are_refused(): void
    {
        $writer = $this->a['writer'];
        $this->requestFor($this->a, $writer, today()->toDateString(), today()->addDays(4)->toDateString());

        Livewire::actingAs($writer)
            ->test(LeavePage::class)
            ->set('leave_type_id', (string) $this->typeFor($this->a)->id)
            ->set('start_date', today()->addDays(2)->toDateString())
            ->set('end_date', today()->addDays(6)->toDateString())
            ->call('submit')
            ->assertHasErrors('start_date');

        $this->assertSame(1, DB::table('leave_requests')->count());
    }

    public function test_end_date_must_not_precede_start_date(): void
    {
        Livewire::actingAs($this->a['writer'])
            ->test(LeavePage::class)
            ->set('leave_type_id', (string) $this->typeFor($this->a)->id)
            ->set('start_date', today()->addDays(5)->toDateString())
            ->set('end_date', today()->addDays(2)->toDateString())
            ->call('submit')
            ->assertHasErrors('end_date');
    }

    public function test_a_user_withdraws_their_own_pending_request(): void
    {
        $request = $this->requestFor($this->a, $this->a['writer'], today()->toDateString(), today()->toDateString());

        Livewire::actingAs($this->a['writer'])
            ->test(LeavePage::class)
            ->call('withdraw', $request->id);

        $this->assertSame('cancelled', $request->refresh()->status);
    }

    public function test_a_user_cannot_withdraw_somebody_elses_request(): void
    {
        $request = $this->requestFor($this->a, $this->a['pm'], today()->toDateString(), today()->toDateString());

        Livewire::actingAs($this->a['writer'])
            ->test(LeavePage::class)
            ->call('withdraw', $request->id)
            ->assertForbidden();

        $this->assertSame('pending', $request->refresh()->status);
    }

    // ── Deciding ─────────────────────────────────────────────────────────────

    public function test_an_admin_approves_a_request_in_their_organization(): void
    {
        $request = $this->requestFor($this->a, $this->a['writer'], today()->toDateString(), today()->addDay()->toDateString());

        Livewire::actingAs($this->a['admin'])
            ->test(LeavePage::class)
            ->call('approve', $request->id);

        $request->refresh();

        $this->assertSame('approved', $request->status);
        $this->assertSame($this->a['admin']->id, $request->reviewed_by);
        $this->assertNotNull($request->reviewed_at);
    }

    public function test_an_admin_rejects_a_request(): void
    {
        $request = $this->requestFor($this->a, $this->a['writer'], today()->toDateString(), today()->toDateString());

        Livewire::actingAs($this->a['admin'])
            ->test(LeavePage::class)
            ->call('reject', $request->id);

        $this->assertSame('rejected', $request->refresh()->status);
        $this->assertSame(0, DB::table('attendances')->count(), 'a rejection writes no attendance');
    }

    /**
     * Structural, and it holds for an admin. Being the most senior person in
     * an agency is not a reason to sign off your own time away.
     */
    public function test_nobody_decides_their_own_request_not_even_an_admin(): void
    {
        $request = $this->requestFor($this->a, $this->a['admin'], today()->toDateString(), today()->toDateString());

        $this->actingAs($this->a['admin']);

        $this->expectException(ValidationException::class);
        app(LeaveApproval::class)->approve($request, $this->a['admin']);
    }

    public function test_a_decided_request_cannot_be_decided_again(): void
    {
        $request = $this->requestFor($this->a, $this->a['writer'], today()->toDateString(), today()->toDateString());

        $this->actingAs($this->a['admin']);
        app(LeaveApproval::class)->approve($request, $this->a['admin']);

        $this->expectException(ValidationException::class);
        app(LeaveApproval::class)->reject($request->refresh(), $this->a['admin']);
    }

    public function test_a_writer_cannot_decide_anything(): void
    {
        // Even handed the permission, a writer reaches nobody but themselves.
        $this->setPermission($this->a, 'writer', 'leave.manage', true);
        $this->setPermission($this->a, 'writer', 'leave.view_all', true);

        $request = $this->requestFor($this->a, $this->a['pm'], today()->toDateString(), today()->toDateString());

        $this->assertThrows(
            fn () => Livewire::actingAs($this->a['writer'])
                ->test(LeavePage::class)
                ->call('approve', $request->id),
            ModelNotFoundException::class
        );

        $this->assertSame('pending', $request->refresh()->status);
    }

    public function test_a_pm_without_manage_cannot_decide_their_units_request(): void
    {
        $member = TenantContext::actingAsOrganization(
            $this->a['organization']->id,
            fn () => User::factory()->create([
                'role' => 'writer', 'unit_id' => $this->a['unit']->id, 'email' => 'unit.a@example.test',
            ])
        );

        $request = $this->requestFor($this->a, $member, today()->toDateString(), today()->toDateString());

        $this->assertTrue($this->a['pm']->fresh()->hasPermission('leave.view_all'));
        $this->assertFalse($this->a['pm']->fresh()->hasPermission('leave.manage'));

        Livewire::actingAs($this->a['pm'])
            ->test(LeavePage::class)
            ->call('approve', $request->id)
            ->assertForbidden();

        $this->assertSame('pending', $request->refresh()->status);
    }

    public function test_a_pm_granted_manage_decides_their_own_unit_only(): void
    {
        $member = TenantContext::actingAsOrganization(
            $this->a['organization']->id,
            fn () => User::factory()->create([
                'role' => 'writer', 'unit_id' => $this->a['unit']->id, 'email' => 'unit.b@example.test',
            ])
        );

        $this->setPermission($this->a, 'pm', 'leave.manage', true);

        $inUnit = $this->requestFor($this->a, $member, today()->toDateString(), today()->toDateString());

        Livewire::actingAs($this->a['pm'])->test(LeavePage::class)->call('approve', $inUnit->id);
        $this->assertSame('approved', $inUnit->refresh()->status);

        // The agency's writer sits in no unit, so the PM cannot reach them.
        $outsideUnit = $this->requestFor($this->a, $this->a['writer'], today()->toDateString(), today()->toDateString());

        $this->assertThrows(
            fn () => Livewire::actingAs($this->a['pm'])->test(LeavePage::class)->call('approve', $outsideUnit->id),
            ModelNotFoundException::class
        );

        $this->assertSame('pending', $outsideUnit->refresh()->status);
    }

    // ── The connection to attendance ─────────────────────────────────────────

    public function test_approving_marks_every_day_as_on_leave_in_attendance(): void
    {
        $writer = $this->a['writer'];
        $start  = today();
        $end    = today()->addDays(2);

        $request = $this->requestFor($this->a, $writer, $start->toDateString(), $end->toDateString());

        Livewire::actingAs($this->a['admin'])->test(LeavePage::class)->call('approve', $request->id);

        $records = Attendance::withoutGlobalScopes()->where('user_id', $writer->id)->get();

        $this->assertCount(3, $records, 'one row per calendar day, inclusive');

        foreach ($records as $record) {
            $this->assertSame('on_leave', $record->status);
            $this->assertNull($record->clock_in);
            $this->assertSame($this->a['organization']->id, $record->organization_id);
        }
    }

    /**
     * An approval is a deliberate statement that the person is away, so it
     * takes precedence over a day already clocked — a row claiming both hours
     * worked and leave would contradict itself.
     */
    public function test_approving_overwrites_a_day_already_clocked(): void
    {
        $writer = $this->a['writer'];

        $this->actingAs($writer);
        app(\App\Services\AttendanceClock::class)->clockIn($writer);

        $request = $this->requestFor($this->a, $writer, today()->toDateString(), today()->toDateString());

        Livewire::actingAs($this->a['admin'])->test(LeavePage::class)->call('approve', $request->id);

        $record = Attendance::withoutGlobalScopes()->where('user_id', $writer->id)->firstOrFail();

        $this->assertSame('on_leave', $record->status);
        $this->assertNull($record->clock_in, 'the clock times come off');
        $this->assertSame(1, DB::table('attendances')->count(), 'and no duplicate row is created');
    }

    // ── Balances ─────────────────────────────────────────────────────────────

    public function test_used_days_are_derived_from_approved_requests_only(): void
    {
        $writer = $this->a['writer'];
        $type   = $this->typeFor($this->a);

        $approved = $this->requestFor($this->a, $writer, today()->toDateString(), today()->addDay()->toDateString());
        $this->actingAs($this->a['admin']);
        app(LeaveApproval::class)->approve($approved, $this->a['admin']);

        // A pending request must not count against the balance.
        $this->requestFor($this->a, $writer, today()->addDays(10)->toDateString(), today()->addDays(14)->toDateString());

        $this->actingAs($writer);
        $used = app(LeaveBalance::class)->usedByType($writer);

        $this->assertSame(2, $used[$type->id] ?? 0);
    }

    // ── Cross-organization isolation ─────────────────────────────────────────

    public function test_an_admin_cannot_see_another_organizations_requests(): void
    {
        $foreign = $this->requestFor($this->b, $this->b['writer'], today()->toDateString(), today()->toDateString());

        $this->actingAs($this->a['admin']);

        $this->assertSame(0, LeaveRequest::count());
        $this->assertNull(LeaveRequest::find($foreign->id));

        $queue = Livewire::actingAs($this->a['admin'])
            ->test(LeavePage::class)
            ->viewData('queue')
            ->pluck('id');

        $this->assertFalse($queue->contains($foreign->id));
    }

    public function test_an_admin_cannot_decide_another_organizations_request(): void
    {
        $foreign = $this->requestFor($this->b, $this->b['writer'], today()->toDateString(), today()->toDateString());

        $this->assertThrows(
            fn () => Livewire::actingAs($this->a['admin'])->test(LeavePage::class)->call('approve', $foreign->id),
            ModelNotFoundException::class
        );

        $this->assertSame('pending', $foreign->refresh()->status);
        $this->assertSame(0, DB::table('attendances')->count());
    }

    // ── The platform sees nothing ────────────────────────────────────────────

    public function test_a_superadmin_reads_no_leave_at_all(): void
    {
        $request = $this->requestFor($this->a, $this->a['writer'], today()->toDateString(), today()->toDateString());

        $this->actingAs(User::withoutGlobalScopes()->where('role', 'superadmin')->firstOrFail());

        $this->assertSame(0, LeaveRequest::count());
        $this->assertNull(LeaveRequest::find($request->id));
        $this->assertSame(0, LeaveType::count());
        $this->assertFalse(LeaveRequest::query()->exists());
    }

    public function test_a_superadmin_cannot_write_to_leave(): void
    {
        $request = $this->requestFor($this->a, $this->a['writer'], today()->toDateString(), today()->toDateString());
        $total   = DB::table('leave_requests')->count();

        $this->actingAs(User::withoutGlobalScopes()->where('role', 'superadmin')->firstOrFail());

        LeaveRequest::query()->update(['status' => 'approved']);
        LeaveRequest::query()->delete();
        LeaveType::query()->delete();

        $this->assertSame($total, DB::table('leave_requests')->count());
        $this->assertSame('pending', $request->refresh()->status);
        // Three each for the two agencies here, plus three for the founding
        // organization the migrations provision.
        $this->assertSame(9, DB::table('leave_types')->count());
    }

    // ── Permission gating ────────────────────────────────────────────────────

    /**
     * Revoking the view permission hides the records, and only the records.
     * The form has to survive it — the page is the only place to ask for leave,
     * so refusing the whole screen would turn a view toggle into a block on
     * requesting time off.
     */
    public function test_revoking_view_own_hides_history_but_not_the_request_form(): void
    {
        $this->requestFor($this->a, $this->a['writer'], today()->addDays(20)->toDateString(), today()->addDays(21)->toDateString());

        $this->setPermission($this->a, 'writer', 'leave.view_own', false);

        $this->actingAs($this->a['writer'])->get(route('leave.index'))->assertOk();

        $this->assertCount(
            0,
            Livewire::actingAs($this->a['writer'])->test(LeavePage::class)->viewData('mine'),
            'their own history is withheld'
        );

        // Asking for leave is structural and keeps working.
        Livewire::actingAs($this->a['writer'])
            ->test(LeavePage::class)
            ->set('leave_type_id', (string) $this->typeFor($this->a)->id)
            ->set('start_date', today()->toDateString())
            ->set('end_date', today()->toDateString())
            ->call('submit')
            ->assertHasNoErrors();

        // The one seeded above, plus the one just submitted without the
        // permission that had been revoked.
        $this->assertSame(2, DB::table('leave_requests')->count());
    }

    public function test_the_toggle_opens_the_queue_for_a_writer(): void
    {
        Livewire::actingAs($this->a['writer'])->test(LeavePage::class)->assertSet('canViewTeam', false);

        $this->setPermission($this->a, 'writer', 'leave.view_all', true);

        Livewire::actingAs($this->a['writer']->fresh())->test(LeavePage::class)->assertSet('canViewTeam', true);
    }

    public function test_the_authorization_panel_offers_the_leave_toggles(): void
    {
        $matrix = Livewire::actingAs($this->a['admin'])
            ->test(\App\Livewire\Admin\AuthorizationPanel::class)
            ->get('matrix');

        foreach (['leave.view_own', 'leave.view_all', 'leave.manage'] as $name) {
            $this->assertArrayHasKey($name, $matrix['pm'], "{$name} must be toggleable");
            $this->assertArrayHasKey($name, $matrix['writer'], "{$name} must be toggleable");
        }
    }

    // ── Leave type management ────────────────────────────────────────────────

    public function test_an_admin_creates_and_edits_leave_types(): void
    {
        Livewire::actingAs($this->a['admin'])
            ->test(ManageLeaveTypes::class)
            ->call('openCreate')
            ->set('name', 'Study Leave')
            ->set('default_annual_allowance', '5')
            ->call('save')
            ->assertHasNoErrors();

        $type = LeaveType::withoutGlobalScopes()
            ->where('organization_id', $this->a['organization']->id)
            ->where('name', 'Study Leave')
            ->firstOrFail();

        $this->assertSame(5, $type->default_annual_allowance);
    }

    public function test_a_pm_cannot_manage_leave_types_even_with_manage_granted(): void
    {
        $this->setPermission($this->a, 'pm', 'leave.manage', true);

        Livewire::actingAs($this->a['pm'])
            ->test(ManageLeaveTypes::class)
            ->assertForbidden();
    }

    public function test_a_leave_type_name_is_unique_within_an_organization_only(): void
    {
        // Agency B already has "Annual Leave"; agency A creating one must not
        // collide with it.
        Livewire::actingAs($this->a['admin'])
            ->test(ManageLeaveTypes::class)
            ->call('openCreate')
            ->set('name', 'Annual Leave')
            ->call('save')
            ->assertHasErrors('name');

        // Agency A already has its own "Annual Leave", which is the collision
        // that should be reported — not agency B's.
        $this->assertSame(
            1,
            DB::table('leave_types')
                ->where('organization_id', $this->a['organization']->id)
                ->where('name', 'Annual Leave')
                ->count()
        );
    }

    public function test_a_leave_type_in_use_cannot_be_removed(): void
    {
        $type = $this->typeFor($this->a);
        $this->requestFor($this->a, $this->a['writer'], today()->toDateString(), today()->toDateString());

        Livewire::actingAs($this->a['admin'])
            ->test(ManageLeaveTypes::class)
            ->call('openDeleteModal', $type->id, $type->name)
            ->call('confirmDelete');

        $this->assertDatabaseHas('leave_types', ['id' => $type->id]);
    }
}
