<?php

namespace Tests\Feature\Attendance;

use App\Livewire\Attendance\AttendancePage;
use App\Livewire\Attendance\ClockWidget;
use App\Models\Attendance;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\AttendanceClock;
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
 * Attendance, phase 1 of the ERP work.
 *
 * The rules being pinned here: you may record your own day and nobody else's;
 * seeing beyond your own record is a permission, and which records you then
 * see is structural; an agency's attendance is invisible to another agency and
 * to the platform.
 */
class AttendanceTest extends TestCase
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

        $this->a = $this->populate($this->makeOrganization('att-a', 'Agency A'), 'A');
        $this->b = $this->populate($this->makeOrganization('att-b', 'Agency B'), 'B');

        // Attendance is ERP, which the plan layer sells from Standard up. This
        // suite is about the permission layer, so both agencies go on a plan
        // that includes ERP and the commercial question never arises here.
        $this->subscribeOrganization($this->a['organization']);
        $this->subscribeOrganization($this->b['organization']);
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

    // ── Clocking yourself in and out ─────────────────────────────────────────

    public function test_a_user_clocks_themselves_in_and_out(): void
    {
        $writer = $this->a['writer'];

        Livewire::actingAs($writer)->test(ClockWidget::class)->call('clockIn');

        $record = Attendance::withoutGlobalScopes()->where('user_id', $writer->id)->firstOrFail();
        $this->assertNotNull($record->clock_in);
        $this->assertNull($record->clock_out);
        $this->assertSame('present', $record->status, 'clocking in records present');
        $this->assertSame(
            $this->a['organization']->id,
            $record->organization_id,
            'the record is stamped with the clocker\'s agency'
        );

        Livewire::actingAs($writer)->test(ClockWidget::class)->call('clockOut');

        $this->assertNotNull($record->refresh()->clock_out);
    }

    public function test_clocking_in_twice_is_refused(): void
    {
        $writer = $this->a['writer'];
        $clock  = app(AttendanceClock::class);

        $this->actingAs($writer);
        $clock->clockIn($writer);

        $this->expectException(ValidationException::class);
        $clock->clockIn($writer);
    }

    public function test_clocking_out_without_clocking_in_is_refused(): void
    {
        $this->actingAs($this->a['writer']);

        $this->expectException(ValidationException::class);
        app(AttendanceClock::class)->clockOut($this->a['writer']);
    }

    public function test_only_one_record_exists_per_person_per_day(): void
    {
        $writer = $this->a['writer'];
        $this->actingAs($writer);

        app(AttendanceClock::class)->clockIn($writer);
        app(AttendanceClock::class)->clockOut($writer);

        $this->assertSame(
            1,
            DB::table('attendances')->where('user_id', $writer->id)->count()
        );
    }

    /**
     * The widget carries no user parameter, so there is no property a crafted
     * Livewire request could set to clock a colleague in. This asserts the
     * record really is created against the actor and nobody else.
     */
    public function test_clocking_in_only_ever_records_the_acting_user(): void
    {
        $writer = $this->a['writer'];
        $pm     = $this->a['pm'];

        Livewire::actingAs($writer)->test(ClockWidget::class)->call('clockIn');

        $this->assertSame(1, DB::table('attendances')->where('user_id', $writer->id)->count());
        $this->assertSame(0, DB::table('attendances')->where('user_id', $pm->id)->count());
    }

    // ── Marking somebody else ────────────────────────────────────────────────

    public function test_an_admin_marks_a_member_of_their_organization(): void
    {
        $writer = $this->a['writer'];

        Livewire::actingAs($this->a['admin'])
            ->test(AttendancePage::class)
            ->call('openMark', $writer->id)
            ->set('status', 'on_leave')
            ->set('notes', 'Booked holiday')
            ->call('save');

        $record = Attendance::withoutGlobalScopes()->where('user_id', $writer->id)->firstOrFail();

        $this->assertSame('on_leave', $record->status);
        $this->assertSame('Booked holiday', $record->notes);
    }

    public function test_marking_absent_clears_any_clock_times(): void
    {
        $writer = $this->a['writer'];

        Livewire::actingAs($writer)->test(ClockWidget::class)->call('clockIn')->call('clockOut');

        Livewire::actingAs($this->a['admin'])
            ->test(AttendancePage::class)
            ->call('openMark', $writer->id)
            ->set('status', 'absent')
            ->call('save');

        $record = Attendance::withoutGlobalScopes()->where('user_id', $writer->id)->firstOrFail();

        $this->assertNull($record->clock_in, 'an absent day cannot also show hours worked');
        $this->assertNull($record->clock_out);
    }

    public function test_a_writer_cannot_mark_anybody(): void
    {
        // Even handed the permission, a writer reaches nobody but themselves.
        $this->setPermission($this->a, 'writer', 'attendance.manage', true);
        $this->setPermission($this->a, 'writer', 'attendance.view_all', true);

        /*
         * Stronger than a 403: subjects() returns only the writer themselves,
         * so a colleague's id cannot even be resolved. They learn nothing about
         * whether the person exists.
         */
        $this->assertThrows(
            fn () => Livewire::actingAs($this->a['writer'])
                ->test(AttendancePage::class)
                ->call('openMark', $this->a['pm']->id),
            ModelNotFoundException::class
        );

        $this->assertSame(0, DB::table('attendances')->where('user_id', $this->a['pm']->id)->count());
    }

    public function test_a_pm_without_manage_cannot_mark_their_unit(): void
    {
        $writer = TenantContext::actingAsOrganization(
            $this->a['organization']->id,
            fn () => User::factory()->create([
                'role'    => 'writer',
                'unit_id' => $this->a['unit']->id,
                'email'   => 'unit.writer@example.test',
            ])
        );

        // view_all is on by default for a PM; manage is not.
        $this->assertTrue($this->a['pm']->fresh()->hasPermission('attendance.view_all'));
        $this->assertFalse($this->a['pm']->fresh()->hasPermission('attendance.manage'));

        Livewire::actingAs($this->a['pm'])
            ->test(AttendancePage::class)
            ->call('openMark', $writer->id)
            ->assertForbidden();
    }

    public function test_a_pm_granted_manage_can_mark_their_own_unit_only(): void
    {
        $inUnit = TenantContext::actingAsOrganization(
            $this->a['organization']->id,
            fn () => User::factory()->create([
                'role'    => 'writer',
                'unit_id' => $this->a['unit']->id,
                'email'   => 'in.unit@example.test',
            ])
        );

        $this->setPermission($this->a, 'pm', 'attendance.manage', true);

        Livewire::actingAs($this->a['pm'])
            ->test(AttendancePage::class)
            ->call('openMark', $inUnit->id)
            ->set('status', 'half_day')
            ->call('save');

        $this->assertSame(
            'half_day',
            Attendance::withoutGlobalScopes()->where('user_id', $inUnit->id)->firstOrFail()->status
        );

        // The agency's writer sits in no unit, so the PM cannot reach them:
        // subjects() does not return them and the lookup fails outright.
        $this->assertThrows(
            fn () => Livewire::actingAs($this->a['pm'])
                ->test(AttendancePage::class)
                ->call('openMark', $this->a['writer']->id),
            ModelNotFoundException::class
        );
    }

    // ── Viewing ──────────────────────────────────────────────────────────────

    public function test_a_writer_sees_no_team_table_by_default(): void
    {
        Livewire::actingAs($this->a['writer'])
            ->test(AttendancePage::class)
            ->assertSet('canViewTeam', false)
            ->assertOk();
    }

    public function test_a_pm_sees_the_team_table_and_only_their_unit(): void
    {
        $inUnit = TenantContext::actingAsOrganization(
            $this->a['organization']->id,
            fn () => User::factory()->create([
                'role'    => 'writer',
                'unit_id' => $this->a['unit']->id,
                'email'   => 'roster.in@example.test',
            ])
        );

        $component = Livewire::actingAs($this->a['pm'])->test(AttendancePage::class);

        $roster = $component->viewData('roster')->pluck('id');

        $this->assertTrue($roster->contains($inUnit->id), 'their own unit is listed');
        $this->assertFalse($roster->contains($this->a['writer']->id), 'a unit-less colleague is not');
        $this->assertFalse($roster->contains($this->b['pm']->id), 'and certainly not another agency');
    }

    public function test_revoking_view_own_closes_the_page_but_not_the_clock(): void
    {
        $this->setPermission($this->a, 'writer', 'attendance.view_own', false);

        $this->actingAs($this->a['writer'])->get(route('attendance.index'))->assertForbidden();

        // Recording your own day is structural and keeps working.
        Livewire::actingAs($this->a['writer'])->test(ClockWidget::class)->call('clockIn');

        $this->assertSame(1, DB::table('attendances')->where('user_id', $this->a['writer']->id)->count());
    }

    public function test_the_panel_toggle_opens_the_team_table_for_a_writer(): void
    {
        Livewire::actingAs($this->a['writer'])
            ->test(AttendancePage::class)
            ->assertSet('canViewTeam', false);

        $this->setPermission($this->a, 'writer', 'attendance.view_all', true);

        Livewire::actingAs($this->a['writer']->fresh())
            ->test(AttendancePage::class)
            ->assertSet('canViewTeam', true);
    }

    // ── Cross-organization isolation ─────────────────────────────────────────

    public function test_an_admin_cannot_see_another_organizations_attendance(): void
    {
        Livewire::actingAs($this->b['writer'])->test(ClockWidget::class)->call('clockIn');

        $foreign = Attendance::withoutGlobalScopes()->where('user_id', $this->b['writer']->id)->firstOrFail();

        $this->actingAs($this->a['admin']);

        $this->assertSame(0, Attendance::count(), 'agency A sees none of agency B\'s records');
        $this->assertNull(Attendance::find($foreign->id));

        $roster = Livewire::actingAs($this->a['admin'])
            ->test(AttendancePage::class)
            ->viewData('roster')
            ->pluck('id');

        $this->assertFalse($roster->contains($this->b['writer']->id));
    }

    public function test_an_admin_cannot_mark_another_organizations_member(): void
    {
        $this->assertThrows(
            fn () => Livewire::actingAs($this->a['admin'])
                ->test(AttendancePage::class)
                ->call('openMark', $this->b['writer']->id),
            ModelNotFoundException::class
        );

        $this->assertSame(0, DB::table('attendances')->where('user_id', $this->b['writer']->id)->count());
    }

    public function test_an_admin_cannot_delete_another_organizations_record(): void
    {
        Livewire::actingAs($this->b['writer'])->test(ClockWidget::class)->call('clockIn');
        $foreign = Attendance::withoutGlobalScopes()->where('user_id', $this->b['writer']->id)->firstOrFail();

        $this->assertFalse($this->a['admin']->administers($foreign));

        $this->assertThrows(
            fn () => Livewire::actingAs($this->a['admin'])
                ->test(AttendancePage::class)
                ->call('openDeleteModal', $foreign->id)
                ->call('confirmDelete'),
            ModelNotFoundException::class
        );

        $this->assertDatabaseHas('attendances', ['id' => $foreign->id]);
    }

    // ── The platform sees nothing ────────────────────────────────────────────

    public function test_a_superadmin_reads_no_attendance_at_all(): void
    {
        Livewire::actingAs($this->a['writer'])->test(ClockWidget::class)->call('clockIn');
        $real = Attendance::withoutGlobalScopes()->firstOrFail();

        $this->actingAs(User::withoutGlobalScopes()->where('role', 'superadmin')->firstOrFail());

        $this->assertSame(0, Attendance::count());
        $this->assertNull(Attendance::find($real->id));
        $this->assertFalse(Attendance::query()->exists());
        $this->assertSame(0, Attendance::where('id', $real->id)->count());
    }

    public function test_a_superadmin_cannot_write_or_delete_attendance(): void
    {
        Livewire::actingAs($this->a['writer'])->test(ClockWidget::class)->call('clockIn');
        $total = DB::table('attendances')->count();

        $this->actingAs(User::withoutGlobalScopes()->where('role', 'superadmin')->firstOrFail());

        Attendance::query()->update(['status' => 'absent']);
        Attendance::query()->delete();

        $this->assertSame($total, DB::table('attendances')->count());
        $this->assertSame(0, DB::table('attendances')->where('status', 'absent')->count());
    }

    // ── Defaults reach new organizations ─────────────────────────────────────

    public function test_a_new_organization_receives_the_attendance_defaults(): void
    {
        $fresh = $this->makeOrganization('att-fresh', 'Fresh Agency');

        $pm = TenantContext::actingAsOrganization(
            $fresh->id,
            fn () => User::factory()->create(['role' => 'pm', 'email' => 'fresh.pm@example.test'])
        );

        $writer = TenantContext::actingAsOrganization(
            $fresh->id,
            fn () => User::factory()->create(['role' => 'writer', 'email' => 'fresh.writer@example.test'])
        );

        PermissionService::flushAll();

        $this->assertTrue($pm->hasPermission('attendance.view_own'));
        $this->assertTrue($pm->hasPermission('attendance.view_all'));
        $this->assertFalse($pm->hasPermission('attendance.manage'));

        $this->assertTrue($writer->hasPermission('attendance.view_own'));
        $this->assertFalse($writer->hasPermission('attendance.view_all'));
    }

    public function test_the_authorization_panel_offers_the_attendance_toggles(): void
    {
        $matrix = Livewire::actingAs($this->a['admin'])
            ->test(\App\Livewire\Admin\AuthorizationPanel::class)
            ->get('matrix');

        foreach (['attendance.view_own', 'attendance.view_all', 'attendance.manage'] as $name) {
            $this->assertArrayHasKey($name, $matrix['pm'], "{$name} must be toggleable");
            $this->assertArrayHasKey($name, $matrix['writer'], "{$name} must be toggleable");
        }
    }
}
