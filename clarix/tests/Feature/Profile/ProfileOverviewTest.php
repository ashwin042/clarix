<?php

namespace Tests\Feature\Profile;

use App\Livewire\Profile\ProfileOverview;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollRecord;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\Task;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\PersonalTaskStats;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * The personal profile page.
 *
 * The whole point of this screen is that it is one person's own view of
 * themselves, so most of what follows is about what must NOT appear on it: a
 * colleague's salary, another agency's anything, a unit's figures dressed up
 * as the viewer's own. The page has no parameter, so there is deliberately no
 * "view someone else's profile" case to test — only the absence of one.
 *
 * Every "is withheld" test carries a positive assertion about the withheld
 * state as well as a negative one about the data. Without it the test would
 * pass against a page that simply had no such section, which proves nothing.
 */
class ProfileOverviewTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /**
     * The copy a section shows in place of data the viewer may not see.
     */
    protected const WITHHELD = 'your role does not have this turned on';

    /** @var array<string, mixed> */
    protected array $a;

    /** @var array<string, mixed> */
    protected array $b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        PermissionService::flushAll();

        $this->a = $this->populate($this->makeOrganization('prof-a', 'Agency A'), 'A');
        $this->b = $this->populate($this->makeOrganization('prof-b', 'Agency B'), 'B');
    }

    // ── Fixtures ─────────────────────────────────────────────────────────────

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

    protected function payrollFor(array $org, User $user, float $base): PayrollRecord
    {
        return TenantContext::actingAsOrganization($org['organization']->id, function () use ($org, $user, $base) {
            $record = new PayrollRecord([
                'month'       => now()->startOfMonth()->toDateString(),
                'base_amount' => $base,
                'deductions'  => 0,
            ]);

            $record->user_id    = $user->id;
            $record->created_by = $org['admin']->id;
            $record->save();

            return $record;
        });
    }

    protected function leaveTypeFor(array $org, string $name, ?int $allowance = 12): LeaveType
    {
        return TenantContext::actingAsOrganization(
            $org['organization']->id,
            fn () => LeaveType::create(['name' => $name, 'default_annual_allowance' => $allowance])
        );
    }

    protected function attendanceFor(array $org, User $user, string $status, string $date): Attendance
    {
        return TenantContext::actingAsOrganization($org['organization']->id, function () use ($user, $status, $date) {
            $record = new Attendance(['date' => $date, 'status' => $status]);
            $record->user_id = $user->id;
            $record->save();

            return $record;
        });
    }

    // ── Basic info ───────────────────────────────────────────────────────────

    public function test_the_page_shows_the_signed_in_persons_own_details(): void
    {
        $this->actingAs($this->a['pm'])
            ->get('/profile')
            ->assertOk()
            ->assertSee('pm.A@example.test')
            ->assertSee('Unit A');
    }

    public function test_someone_with_no_unit_is_shown_as_unassigned_rather_than_blank(): void
    {
        // Writer A is created without a unit_id.
        $this->actingAs($this->a['writer'])
            ->get('/profile')
            ->assertOk()
            ->assertSee('Unassigned');
    }

    public function test_the_joined_date_is_shown(): void
    {
        $pm = $this->a['pm'];

        $this->actingAs($pm)
            ->get('/profile')
            ->assertOk()
            ->assertSee($pm->created_at->format('j M Y'));
    }

    // ── The absence of anyone else ───────────────────────────────────────────

    public function test_a_colleagues_payroll_never_appears(): void
    {
        $this->payrollFor($this->a, $this->a['writer'], base: 4321);
        $this->payrollFor($this->a, $this->a['pm'], base: 1234);

        $this->actingAs($this->a['pm'])
            ->get('/profile')
            ->assertOk()
            ->assertSee('1,234.00')
            ->assertDontSee('4,321.00');
    }

    public function test_an_admin_sees_only_their_own_payroll_here(): void
    {
        $this->payrollFor($this->a, $this->a['writer'], base: 4321);
        $this->payrollFor($this->a, $this->a['admin'], base: 1111);

        // An admin holds every permission, including payroll.manage. This page
        // is still personal: it is not an admin lookup tool.
        $this->actingAs($this->a['admin'])
            ->get('/profile')
            ->assertOk()
            ->assertSee('1,111.00')
            ->assertDontSee('4,321.00')
            ->assertDontSee('Writer A');
    }

    public function test_a_colleagues_leave_never_appears(): void
    {
        $type = $this->leaveTypeFor($this->a, 'Sabbatical');

        TenantContext::actingAsOrganization($this->a['organization']->id, function () use ($type) {
            $request = new LeaveRequest([
                'leave_type_id' => $type->id,
                'start_date'    => now()->startOfYear()->addDays(10)->toDateString(),
                'end_date'      => now()->startOfYear()->addDays(14)->toDateString(),
                'reason'        => 'Colleague time off',
            ]);
            $request->user_id = $this->a['writer']->id;
            $request->status  = 'approved';
            $request->save();
        });

        $this->actingAs($this->a['pm'])
            ->get('/profile')
            ->assertOk()
            ->assertSee('Sabbatical')
            ->assertDontSee('Colleague time off');
    }

    public function test_a_leave_balance_counts_only_the_viewers_own_approved_days(): void
    {
        $type = $this->leaveTypeFor($this->a, 'Sabbatical', allowance: 12);

        // Five approved days belonging to a colleague.
        TenantContext::actingAsOrganization($this->a['organization']->id, function () use ($type) {
            $request = new LeaveRequest([
                'leave_type_id' => $type->id,
                'start_date'    => now()->startOfYear()->addDays(10)->toDateString(),
                'end_date'      => now()->startOfYear()->addDays(14)->toDateString(),
            ]);
            $request->user_id = $this->a['writer']->id;
            $request->status  = 'approved';
            $request->save();
        });

        $balances = $this->actingAsInOrg($this->a, $this->a['pm'], 'leaveBalances');

        // A new agency is provisioned with default leave types, so Sabbatical
        // is one row among several rather than the only one.
        $sabbatical = collect($balances)->firstWhere(fn ($row) => $row['type']->name === 'Sabbatical');

        $this->assertNotNull($sabbatical);
        $this->assertSame(0, $sabbatical['used']);
        $this->assertSame(12, $sabbatical['remaining']);
    }

    public function test_your_own_undecided_requests_are_counted(): void
    {
        $type = $this->leaveTypeFor($this->a, 'Sabbatical');

        TenantContext::actingAsOrganization($this->a['organization']->id, function () use ($type) {
            // One of the viewer's own, and one of a colleague's. Only the
            // first is theirs to be told about.
            foreach ([$this->a['pm'], $this->a['writer']] as $owner) {
                $request = new LeaveRequest([
                    'leave_type_id' => $type->id,
                    'start_date'    => now()->addDays(20)->toDateString(),
                    'end_date'      => now()->addDays(21)->toDateString(),
                ]);
                $request->user_id = $owner->id;
                $request->status  = 'pending';
                $request->save();
            }
        });

        $this->actingAs($this->a['pm'])
            ->get('/profile')
            ->assertOk()
            ->assertSee('1 request awaiting a decision');
    }

    public function test_another_agencys_records_never_reach_the_page(): void
    {
        $this->payrollFor($this->b, $this->b['pm'], base: 9876);
        $this->leaveTypeFor($this->b, 'Agency B Only Leave');

        $this->payrollFor($this->a, $this->a['pm'], base: 1234);
        $this->leaveTypeFor($this->a, 'Agency A Only Leave');

        $this->actingAs($this->a['pm'])
            ->get('/profile')
            ->assertOk()
            ->assertSee('1,234.00')
            ->assertSee('Agency A Only Leave')
            ->assertDontSee('9,876.00')
            ->assertDontSee('Agency B Only Leave');
    }

    public function test_the_page_takes_no_parameter_naming_another_person(): void
    {
        $victim = $this->a['writer'];

        $this->actingAs($this->a['admin'])
            ->get('/profile/'.$victim->id)
            ->assertNotFound();
    }

    // ── Permission toggles degrade, never error ──────────────────────────────

    public function test_the_payroll_section_is_withheld_when_payroll_view_own_is_off(): void
    {
        $this->payrollFor($this->a, $this->a['writer'], base: 4321);
        $this->setPermission($this->a, 'writer', 'payroll.view_own', false);

        $this->actingAs($this->a['writer'])
            ->get('/profile')
            ->assertOk()
            ->assertSee(self::WITHHELD)
            ->assertDontSee('4,321.00');
    }

    public function test_the_leave_section_is_withheld_when_leave_view_own_is_off(): void
    {
        $this->leaveTypeFor($this->a, 'Sabbatical');
        $this->setPermission($this->a, 'writer', 'leave.view_own', false);

        $this->actingAs($this->a['writer'])
            ->get('/profile')
            ->assertOk()
            ->assertSee(self::WITHHELD)
            ->assertDontSee('Sabbatical');
    }

    public function test_the_attendance_section_is_withheld_when_attendance_view_own_is_off(): void
    {
        $this->attendanceFor($this->a, $this->a['writer'], 'half_day', now()->startOfMonth()->toDateString());
        $this->setPermission($this->a, 'writer', 'attendance.view_own', false);

        $this->actingAs($this->a['writer'])
            ->get('/profile')
            ->assertOk()
            ->assertSee(self::WITHHELD)
            ->assertDontSee('Half day');
    }

    public function test_the_task_section_is_withheld_when_tasks_view_is_off(): void
    {
        $this->setPermission($this->a, 'writer', 'tasks.view', false);

        $this->actingAs($this->a['writer'])
            ->get('/profile')
            ->assertOk()
            ->assertSee(self::WITHHELD)
            ->assertDontSee('Assigned to me');
    }

    public function test_a_person_holding_none_of_the_toggles_still_gets_the_page(): void
    {
        foreach (['payroll.view_own', 'leave.view_own', 'attendance.view_own', 'tasks.view'] as $name) {
            $this->setPermission($this->a, 'writer', $name, false);
        }

        // Their own name and email are structural — they are not behind a
        // toggle, and the page must still be worth opening.
        $this->actingAs($this->a['writer'])
            ->get('/profile')
            ->assertOk()
            ->assertSee('writer.A@example.test')
            ->assertSee(self::WITHHELD);
    }

    public function test_a_writer_holding_every_toggle_sees_no_withheld_notice(): void
    {
        $this->leaveTypeFor($this->a, 'Sabbatical');

        // The baseline already grants a writer all four. This pins that the
        // withheld notice is a response to the toggles rather than permanent
        // furniture on the page.
        $this->actingAs($this->a['writer'])
            ->get('/profile')
            ->assertOk()
            ->assertDontSee(self::WITHHELD);
    }

    public function test_a_superadmin_gets_their_details_and_four_withheld_sections(): void
    {
        // A superadmin belongs to no agency, so they hold no permission map at
        // all. The page must still open — it is reachable from their user menu
        // like anyone else's — showing who they are and nothing operational.
        $superadmin = User::factory()->create([
            'name'            => 'Platform Super',
            'email'           => 'super@example.test',
            'role'            => 'superadmin',
            'organization_id' => null,
        ]);

        $this->actingAs($superadmin)
            ->get('/profile')
            ->assertOk()
            ->assertSee('super@example.test')
            ->assertSee('Unassigned')
            ->assertSee(self::WITHHELD);
    }

    // ── Attendance figures ───────────────────────────────────────────────────

    public function test_the_attendance_summary_counts_this_month_only(): void
    {
        $writer = $this->a['writer'];

        $this->attendanceFor($this->a, $writer, 'present', now()->startOfMonth()->toDateString());
        $this->attendanceFor($this->a, $writer, 'present', now()->startOfMonth()->addDay()->toDateString());
        $this->attendanceFor($this->a, $writer, 'absent', now()->startOfMonth()->addDays(2)->toDateString());

        // Last month is outside the window and must not be counted.
        $this->attendanceFor($this->a, $writer, 'present', now()->subMonth()->startOfMonth()->toDateString());

        $summary = $this->actingAsInOrg($this->a, $writer, 'attendanceSummary');

        $this->assertSame(2, $summary['present']);
        $this->assertSame(1, $summary['absent']);
        $this->assertSame(0, $summary['half_day']);
        $this->assertSame(0, $summary['on_leave']);
    }

    public function test_the_attendance_summary_ignores_a_colleagues_days(): void
    {
        $this->attendanceFor($this->a, $this->a['writer'], 'present', now()->startOfMonth()->toDateString());
        $this->attendanceFor($this->a, $this->a['pm'], 'present', now()->startOfMonth()->toDateString());

        $summary = $this->actingAsInOrg($this->a, $this->a['pm'], 'attendanceSummary');

        $this->assertSame(1, $summary['present']);
    }

    // ── Personal task figures ────────────────────────────────────────────────

    public function test_a_pms_task_count_is_their_own_not_their_units(): void
    {
        $pm = $this->a['pm'];

        // A second task in the same unit, run by somebody else. A unit-wide
        // count would pick it up; a personal one must not.
        TenantContext::actingAsOrganization($this->a['organization']->id, fn () => Task::create([
            'title'         => "Not the PM's task",
            'task_code'     => 'OTHER_A',
            'unit_id'       => $this->a['unit']->id,
            'created_by'    => $this->a['admin']->id,
            'pm_id'         => $this->a['admin']->id,
            'priority'      => 'medium',
            'status'        => 'pending',
            'deadline'      => now()->addDays(7),
            'credit_amount' => 10.00,
        ]));

        $stats = TenantContext::actingAsOrganization(
            $this->a['organization']->id,
            fn () => app(PersonalTaskStats::class)->for($pm)
        );

        $this->assertSame(1, $stats['total']);
    }

    public function test_a_writers_task_count_follows_their_assignments(): void
    {
        $stats = TenantContext::actingAsOrganization(
            $this->a['organization']->id,
            fn () => app(PersonalTaskStats::class)->for($this->a['writer'])
        );

        $this->assertSame(1, $stats['total']);
        $this->assertSame(1, $stats['breakdown']['pending']);
        $this->assertSame(0, $stats['breakdown']['completed']);
    }

    public function test_an_admins_task_count_is_what_was_assigned_to_them(): void
    {
        $admin = $this->a['admin'];

        TenantContext::actingAsOrganization($this->a['organization']->id, fn () => Task::create([
            'title'             => 'Admin owned',
            'task_code'         => 'ADMIN_A',
            'unit_id'           => $this->a['unit']->id,
            'created_by'        => $admin->id,
            'pm_id'             => $this->a['pm']->id,
            'assigned_admin_id' => $admin->id,
            'priority'          => 'medium',
            'status'            => 'completed',
            'deadline'          => now()->addDays(7),
            'credit_amount'     => 10.00,
        ]));

        $stats = TenantContext::actingAsOrganization(
            $this->a['organization']->id,
            fn () => app(PersonalTaskStats::class)->for($admin)
        );

        // The agency has two tasks; exactly one is this admin's.
        $this->assertSame(1, $stats['total']);
        $this->assertSame(1, $stats['breakdown']['completed']);
    }

    public function test_task_counts_do_not_cross_agencies(): void
    {
        // Writer B has an assignment in agency B. Asked about writer B from
        // inside agency A, the tenant scope must answer with nothing.
        $stats = TenantContext::actingAsOrganization(
            $this->a['organization']->id,
            fn () => app(PersonalTaskStats::class)->for($this->b['writer'])
        );

        $this->assertSame(0, $stats['total']);
    }

    public function test_a_superadmin_has_no_personal_tasks_rather_than_an_error(): void
    {
        $superadmin = User::factory()->create([
            'name'            => 'Platform Super',
            'email'           => 'super@example.test',
            'role'            => 'superadmin',
            'organization_id' => null,
        ]);

        $stats = app(PersonalTaskStats::class)->for($superadmin);

        $this->assertSame(0, $stats['total']);
    }

    // ── Links out to the full screens ────────────────────────────────────────

    public function test_each_section_links_to_its_full_page(): void
    {
        // Asserted on the link text rather than the href: every one of these
        // routes is already in the sidebar, so an href assertion would pass
        // whether or not the page itself offered the link.
        $this->actingAs($this->a['pm'])
            ->get('/profile')
            ->assertOk()
            ->assertSee('Full attendance history')
            ->assertSee('Leave history &amp; requests', escape: false)
            ->assertSee('Full payroll history')
            ->assertSee('All my tasks');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * One key of the component's view data, resolved as the given person.
     */
    protected function actingAsInOrg(array $org, User $user, string $key): mixed
    {
        return TenantContext::actingAsOrganization(
            $org['organization']->id,
            fn () => Livewire::actingAs($user)->test(ProfileOverview::class)->viewData($key)
        );
    }
}
