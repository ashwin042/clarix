<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One person's attendance for one day.
 *
 * Tenant-scoped like every other operational model, and deliberately not
 * PlatformVisible: who turned up for work is an agency's own business, so a
 * platform superadmin reads no rows of it at all.
 *
 * user_id is absent from $fillable alongside organization_id. Whose attendance
 * a record belongs to is decided by the server — either the person clocking in
 * or an admin naming someone in their own organization — and a mass-assignable
 * user_id would let a crafted form post attendance against a colleague.
 */
class Attendance extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    /**
     * The statuses a day can carry.
     *
     * Clocking in records `present`. The other three are only ever set by an
     * admin marking someone manually, so the column always reflects a decision
     * somebody actually made rather than a threshold guessed from hours.
     *
     * @var array<string, string>
     */
    public const STATUSES = [
        'present'  => 'Present',
        'absent'   => 'Absent',
        'half_day' => 'Half day',
        'on_leave' => 'On leave',
    ];

    /**
     * The timezone attendance is read and dated in.
     *
     * The app runs on UTC and the columns keep it — an instant is an instant,
     * and every other timestamp in the system is stored the same way. But
     * nobody clocks in at an instant: they clock in at nine in the morning in
     * Kathmandu, and that is what the record has to say back to them.
     *
     * So the conversion lives here, on the boundary between what is stored and
     * what is shown, rather than in each of the screens that shows it. Nepal
     * is UTC+05:45 year-round with no daylight saving, which is why a whole
     * agency can share one constant.
     */
    public const TIMEZONE = 'Asia/Kathmandu';

    protected $fillable = ['date', 'clock_in', 'clock_out', 'status', 'notes'];

    protected function casts(): array
    {
        return [
            // Pinned to Y-m-d. Left as a bare 'date' cast the value round-trips
            // as a full timestamp, and a lookup for "2026-08-17" then fails to
            // find the row stored as "2026-08-17 00:00:00".
            'date'      => 'date:Y-m-d',
            'clock_in'  => 'datetime',
            'clock_out' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Nepal time ───────────────────────────────────────────────────────────

    /**
     * The current moment, in the timezone the agency works in.
     *
     * Used wherever "now" has to be read as a wall clock rather than as an
     * instant — dating a card, opening a table on today.
     */
    public static function localNow(): Carbon
    {
        return Carbon::now(self::TIMEZONE);
    }

    /**
     * Today's date in Nepal, as the `date` column spells it.
     *
     * The distinction is not academic. Between 18:15 and 23:59 UTC it is
     * already tomorrow in Kathmandu, so a night shift starting at half past
     * midnight was filing itself under the previous day — and then reading
     * back as a second, impossible clock-in against the unique index.
     */
    public static function localToday(): string
    {
        return self::localNow()->toDateString();
    }

    /**
     * Clock-in as a Carbon in Nepal time, or null if it was never recorded.
     *
     * The stored value is untouched: setTimezone only changes how the same
     * instant is read.
     */
    public function getClockInLocalAttribute(): ?Carbon
    {
        return $this->clock_in?->copy()->setTimezone(self::TIMEZONE);
    }

    public function getClockOutLocalAttribute(): ?Carbon
    {
        return $this->clock_out?->copy()->setTimezone(self::TIMEZONE);
    }

    /**
     * "09:00", or a dash on a day with no clock-in — an absence, a leave day,
     * or a day still ahead of the person.
     *
     * Every screen showing a clock time goes through this rather than
     * formatting the column itself, because a view that reaches for clock_in
     * directly gets UTC and no error to say so.
     */
    public function clockInForHumans(): string
    {
        return $this->clock_in_local?->format('H:i') ?? '—';
    }

    public function clockOutForHumans(): string
    {
        return $this->clock_out_local?->format('H:i') ?? '—';
    }

    /**
     * Records belonging to the members of one unit.
     *
     * Used by the PM view, whose reach is their own unit rather than the whole
     * agency. Expressed as a subquery on users so it composes with the tenant
     * scope already on this model instead of needing a join.
     */
    public function scopeForUnit(Builder $query, ?int $unitId): Builder
    {
        return $query->whereIn(
            'user_id',
            User::withoutGlobalScopes()->where('unit_id', $unitId)->select('id')
        );
    }

    public function scopeOnDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('date', $date);
    }

    /**
     * Whether this record is still open — clocked in, not yet clocked out.
     */
    public function isOpen(): bool
    {
        return $this->clock_in !== null && $this->clock_out === null;
    }

    /**
     * Time on the clock, or null while the day is still open.
     */
    public function workedMinutes(): ?int
    {
        if ($this->clock_in === null || $this->clock_out === null) {
            return null;
        }

        return $this->clock_in->diffInMinutes($this->clock_out);
    }

    /**
     * "7h 20m", or a dash when there is nothing to report.
     */
    public function workedForHumans(): string
    {
        $minutes = $this->workedMinutes();

        if ($minutes === null) {
            return '—';
        }

        return intdiv($minutes, 60).'h '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT).'m';
    }
}
