<?php

namespace Tests\Feature\Attendance;

use App\Livewire\Attendance\AttendancePage;
use App\Livewire\Attendance\ClockWidget;
use App\Livewire\Profile\ProfileOverview;
use App\Models\Attendance;
use App\Services\AttendanceClock;
use App\Services\PermissionService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * Attendance reads in Nepal time.
 *
 * Storage stays UTC — that is what the column holds and what every other
 * timestamp in the app does. What is pinned here is the other half: a person
 * in Kathmandu reads their clock times at +05:45, and the day a record belongs
 * to is their calendar day rather than UTC's.
 *
 * Every clock in this suite is frozen deliberately. 03:15 UTC is 09:00 in
 * Kathmandu, and 18:30 UTC is already 00:15 of the *next* Nepali day — the two
 * cases that tell a correct conversion apart from a lucky one.
 */
class AttendanceTimezoneTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $a;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        PermissionService::flushAll();

        $this->a = $this->populate($this->makeOrganization('tz-a', 'Agency A'), 'A');
        $this->subscribeOrganization($this->a['organization']);
    }

    /**
     * A record written at known UTC instants.
     */
    protected function record(string $date, ?string $in, ?string $out = null): Attendance
    {
        $writer = $this->a['writer'];
        $this->actingAs($writer);

        $attendance = new Attendance([
            'date'      => $date,
            'clock_in'  => $in,
            'clock_out' => $out,
            'status'    => 'present',
        ]);

        $attendance->user_id = $writer->id;
        $attendance->save();

        return $attendance;
    }

    // -- The conversion itself ------------------------------------------------

    public function test_clock_times_convert_to_nepal_time(): void
    {
        $record = $this->record('2026-09-21', '2026-09-21 03:15:00', '2026-09-21 11:30:00');

        $this->assertSame('09:00', $record->clockInForHumans(), '03:15 UTC is 09:00 in Kathmandu');
        $this->assertSame('17:15', $record->clockOutForHumans(), '11:30 UTC is 17:15 in Kathmandu');

        $this->assertSame('Asia/Kathmandu', $record->clock_in_local->getTimezone()->getName());
        $this->assertSame('2026-09-21 09:00:00', $record->clock_in_local->format('Y-m-d H:i:s'));
    }

    public function test_a_time_past_the_nepali_midnight_reads_as_the_next_day(): void
    {
        $record = $this->record('2026-09-21', '2026-09-21 18:30:00');

        $this->assertSame('00:15', $record->clockInForHumans());
        $this->assertSame('2026-09-22', $record->clock_in_local->toDateString());
    }

    public function test_missing_times_read_as_a_dash(): void
    {
        $record = $this->record('2026-09-21', null);

        $this->assertNull($record->clock_in_local);
        $this->assertSame('—', $record->clockInForHumans());
        $this->assertSame('—', $record->clockOutForHumans());
    }

    public function test_storage_stays_utc(): void
    {
        $this->travelTo('2026-09-21 03:15:00');

        Livewire::actingAs($this->a['writer'])->test(ClockWidget::class)->call('clockIn');

        $raw = DB::table('attendances')->where('user_id', $this->a['writer']->id)->first();

        $this->assertStringStartsWith('2026-09-21 03:15:00', $raw->clock_in, 'the column holds UTC, untouched');
    }

    /**
     * Hours worked is a difference between two instants, so the conversion
     * must not change it — a fix that shifted only one end would invent 5h 45m
     * of overtime.
     */
    public function test_hours_worked_are_unaffected_by_the_conversion(): void
    {
        $record = $this->record('2026-09-21', '2026-09-21 03:15:00', '2026-09-21 11:30:00');

        $this->assertSame('8h 15m', $record->workedForHumans());
    }

    // -- What the screens render ----------------------------------------------

    public function test_the_clock_widget_renders_nepal_time(): void
    {
        $this->travelTo('2026-09-21 06:00:00');
        $this->record('2026-09-21', '2026-09-21 03:15:00', '2026-09-21 11:30:00');

        Livewire::actingAs($this->a['writer'])
            ->test(ClockWidget::class)
            ->assertSee('09:00')
            ->assertSee('17:15')
            ->assertDontSee('03:15')
            ->assertDontSee('11:30');
    }

    public function test_the_attendance_page_renders_nepal_time(): void
    {
        $this->travelTo('2026-09-21 06:00:00');
        $this->record('2026-09-21', '2026-09-21 03:15:00', '2026-09-21 11:30:00');

        Livewire::actingAs($this->a['admin'])
            ->test(AttendancePage::class)
            ->set('date', '2026-09-21')
            ->assertSee('09:00')
            ->assertSee('17:15')
            ->assertDontSee('03:15');
    }

    /**
     * The widget's header dates the card. During the 18:15-23:59 UTC window it
     * is already tomorrow in Kathmandu, which is exactly when a UTC header
     * names the wrong day to the person reading it.
     */
    public function test_the_widget_header_dates_the_card_in_nepal_time(): void
    {
        $this->travelTo('2026-09-21 19:00:00');

        Livewire::actingAs($this->a['writer'])
            ->test(ClockWidget::class)
            ->assertSee('Tuesday, 22 Sep 2026');
    }

    // -- Day boundaries -------------------------------------------------------

    /**
     * 19:00 UTC on the 21st is 00:45 on the 22nd in Kathmandu. Someone
     * starting a late shift then belongs on the 22nd's row.
     */
    public function test_clocking_in_after_the_nepali_midnight_files_under_the_new_day(): void
    {
        $this->travelTo('2026-09-21 19:00:00');

        Livewire::actingAs($this->a['writer'])->test(ClockWidget::class)->call('clockIn');

        $raw = DB::table('attendances')->where('user_id', $this->a['writer']->id)->first();

        $this->assertStringStartsWith('2026-09-22', $raw->date, 'the record belongs to the Nepali day');
    }

    /**
     * And the same instant has to find that record again, or the widget offers
     * a second clock-in and the unique index rejects the write.
     */
    public function test_todays_lookup_agrees_with_the_day_it_filed(): void
    {
        $this->travelTo('2026-09-21 19:00:00');
        $this->actingAs($this->a['writer']);

        $clock = app(AttendanceClock::class);
        $clock->clockIn($this->a['writer']);

        $this->assertNotNull($clock->today($this->a['writer']), 'the day just filed is the day found');

        $clock->clockOut($this->a['writer']);

        $this->assertSame(1, DB::table('attendances')->where('user_id', $this->a['writer']->id)->count());
    }

    public function test_the_team_table_opens_on_the_nepali_date(): void
    {
        $this->travelTo('2026-09-21 19:00:00');

        Livewire::actingAs($this->a['admin'])
            ->test(AttendancePage::class)
            ->assertSet('date', '2026-09-22');
    }

    /**
     * The month summary on the profile counts by date string. On the first of
     * the month in Kathmandu it is still the last of the previous month in
     * UTC, and a UTC lower bound then starts the window a day late.
     */
    public function test_the_month_summary_uses_the_nepali_month(): void
    {
        $this->travelTo('2026-09-30 19:00:00');
        $this->record('2026-10-01', '2026-09-30 19:00:00');

        $counts = Livewire::actingAs($this->a['writer'])
            ->test(ProfileOverview::class)
            ->viewData('attendanceSummary');

        $this->assertSame(1, $counts['present'], 'the 1st of October counts in October');
    }
}
