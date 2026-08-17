<?php

namespace Tests\Feature\Navigation;

use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * The sidebar after Attendance, Leave and Payroll moved into an HR section.
 *
 * Grouping is presentation, so these tests are about what each role can see
 * rather than where it sits — the point of the reorganisation was that nothing
 * about visibility changed. The one ordering assertion checks that HR really
 * does fall between Management and Finance.
 */
class SidebarSectionsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        PermissionService::flushAll();

        $this->org = $this->populate($this->makeOrganization('nav-a', 'Agency A'), 'A');

        // The HR and AI sections are plan-gated as well as permission-gated.
        // This suite is about which *roles* see which links, so the agency goes
        // on the top plan and the plan layer stays out of the way.
        // PlanSidebarTest covers the other axis.
        $this->subscribeOrganization($this->org['organization']);
    }

    protected function setPermission(string $role, string $name, bool $allowed): void
    {
        TenantContext::actingAsOrganization($this->org['organization']->id, function () use ($role, $name, $allowed) {
            RolePermission::updateOrCreate(
                ['role' => $role, 'permission_id' => Permission::where('name', $name)->firstOrFail()->id],
                ['allowed' => $allowed]
            );
        });

        PermissionService::flushAll();
    }

    /**
     * The sidebar is part of the app layout, so any page carrying it will do.
     *
     * Leave is the default host because it is the one screen that never
     * refuses anyone — see LeavePage::render().
     */
    protected function sidebarFor(User $user, string $route = 'leave.index'): string
    {
        return $this->actingAs($user)->get(route($route))->assertOk()->getContent();
    }

    // ── The sections themselves ──────────────────────────────────────────────

    public function test_the_hr_section_sits_between_management_and_finance(): void
    {
        $html = $this->sidebarFor($this->org['admin']);

        $management = strpos($html, '>Management<');
        $hr         = strpos($html, '>HR<');
        $finance    = strpos($html, '>Finance<');

        $this->assertNotFalse($management, 'Management heading present');
        $this->assertNotFalse($hr, 'HR heading present');
        $this->assertNotFalse($finance, 'Finance heading present');

        $this->assertLessThan($hr, $management, 'Management comes before HR');
        $this->assertLessThan($finance, $hr, 'HR comes before Finance');
    }

    public function test_the_hr_items_all_sit_after_the_hr_heading(): void
    {
        $html = $this->sidebarFor($this->org['admin']);

        $hr = strpos($html, '>HR<');

        foreach (['Attendance', 'Leave', 'Payroll'] as $label) {
            $this->assertLessThan(
                strpos($html, ">{$label}<"),
                $hr,
                "{$label} must be inside the HR section"
            );
        }
    }

    public function test_management_keeps_its_own_items(): void
    {
        $html = $this->sidebarFor($this->org['admin']);

        $management = strpos($html, '>Management<');
        $hr         = strpos($html, '>HR<');

        // Units, Users and Tasks stay above the HR heading; Issues does too.
        foreach (['Units', 'Users', 'Tasks', 'Issues'] as $label) {
            $position = strpos($html, ">{$label}<");

            $this->assertNotFalse($position, "{$label} present");
            $this->assertGreaterThan($management, $position, "{$label} is under Management");
            $this->assertLessThan($hr, $position, "{$label} stays out of HR");
        }
    }

    // ── Visibility is unchanged by the move ──────────────────────────────────

    /**
     * Attendance and Leave are open to everyone by design: clocking in and
     * requesting time off are structural, not granted. Moving them did not
     * change that, which is what this pins.
     */
    public function test_attendance_and_leave_stay_visible_to_every_role(): void
    {
        foreach (['admin', 'pm', 'writer'] as $role) {
            $html = $this->sidebarFor($this->org[$role]);

            $this->assertStringContainsString('>Attendance<', $html, "{$role} sees Attendance");
            $this->assertStringContainsString('>Leave<', $html, "{$role} sees Leave");
        }
    }

    public function test_attendance_and_leave_remain_visible_with_their_view_permissions_revoked(): void
    {
        $this->setPermission('writer', 'attendance.view_own', false);
        $this->setPermission('writer', 'leave.view_own', false);

        $html = $this->sidebarFor($this->org['writer']->fresh());

        // Unchanged from before the move: the links stay because the person can
        // still clock in and still request leave.
        $this->assertStringContainsString('>Attendance<', $html);
        $this->assertStringContainsString('>Leave<', $html);
    }

    /**
     * Records a mismatch that predates this reorganisation and is not caused
     * by it.
     *
     * The Attendance link is unconditional, but AttendancePage::render()
     * refuses anyone holding neither attendance.view_own nor view_all — so a
     * writer with view_own revoked is shown a link that 403s. Leave does not
     * behave this way: its page always renders and gates the sections inside,
     * which is what lets the request form stay reachable.
     *
     * Asserted as it currently behaves rather than as it should, so the suite
     * describes the application honestly. Flagged for a decision; the fix is
     * to make AttendancePage render like LeavePage.
     */
    public function test_the_attendance_link_currently_outlives_its_page(): void
    {
        $this->setPermission('writer', 'attendance.view_own', false);
        $writer = $this->org['writer']->fresh();

        $this->assertStringContainsString('>Attendance<', $this->sidebarFor($writer), 'the link is drawn');

        $this->actingAs($writer)->get(route('attendance.index'))->assertForbidden();

        // Leave, by contrast, keeps its promise.
        $this->setPermission('writer', 'leave.view_own', false);
        $this->actingAs($this->org['writer']->fresh())->get(route('leave.index'))->assertOk();
    }

    public function test_payroll_is_hidden_without_a_payroll_permission(): void
    {
        $this->setPermission('writer', 'payroll.view_own', false);

        $html = $this->sidebarFor($this->org['writer']->fresh());

        $this->assertStringNotContainsString('>Payroll<', $html);

        // And the HR section is still drawn, because Attendance and Leave
        // remain — the heading is never left hanging over nothing.
        $this->assertStringContainsString('>HR<', $html);
        $this->assertStringContainsString('>Attendance<', $html);
    }

    public function test_payroll_appears_once_the_permission_is_granted(): void
    {
        $this->setPermission('writer', 'payroll.view_own', false);
        $this->assertStringNotContainsString('>Payroll<', $this->sidebarFor($this->org['writer']->fresh()));

        $this->setPermission('writer', 'payroll.view_own', true);
        $this->assertStringContainsString('>Payroll<', $this->sidebarFor($this->org['writer']->fresh()));
    }

    /**
     * Whoever runs payroll lands on the management screen; everyone else on
     * their own history. Unchanged by the move.
     */
    public function test_the_payroll_link_points_at_the_right_screen_for_each_role(): void
    {
        $this->assertStringContainsString(route('payroll.manage'), $this->sidebarFor($this->org['admin']));
        $this->assertStringContainsString(route('payroll.index'), $this->sidebarFor($this->org['writer']));
    }

    // ── Management items keep their own gates ────────────────────────────────

    public function test_units_and_users_still_follow_their_view_permissions(): void
    {
        $writer = $this->org['writer'];

        $html = $this->sidebarFor($writer);
        $this->assertStringNotContainsString('>Units<', $html);
        $this->assertStringNotContainsString('>Users<', $html);

        $this->setPermission('writer', 'units.view', true);
        $this->setPermission('writer', 'users.view', true);

        $html = $this->sidebarFor($writer->fresh());
        $this->assertStringContainsString('>Units<', $html);
        $this->assertStringContainsString('>Users<', $html);
    }
}
