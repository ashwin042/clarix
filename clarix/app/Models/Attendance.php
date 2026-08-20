<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
