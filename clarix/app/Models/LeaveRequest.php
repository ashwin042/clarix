<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's request to be away for a range of days.
 *
 * user_id is kept out of $fillable alongside organization_id. Who a request
 * belongs to is decided by the server — always the person submitting it — so a
 * crafted form cannot book leave in a colleague's name. reviewed_by and
 * reviewed_at are likewise set only by the approval service, never assigned
 * from input.
 */
class LeaveRequest extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    /**
     * @var array<string, string>
     */
    public const STATUSES = [
        'pending'   => 'Pending',
        'approved'  => 'Approved',
        'rejected'  => 'Rejected',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = ['leave_type_id', 'start_date', 'end_date', 'reason'];

    protected function casts(): array
    {
        return [
            // Pinned to Y-m-d for the same reason attendances.date is: a bare
            // date cast round-trips as a timestamp and then fails to match a
            // plain date in a lookup.
            'start_date'  => 'date:Y-m-d',
            'end_date'    => 'date:Y-m-d',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    /**
     * Requests overlapping a date range, for the same person.
     *
     * Two ranges overlap unless one ends before the other starts. Written as
     * the negation so a single pair of comparisons covers every case.
     */
    public function scopeOverlapping(Builder $query, string $start, string $end): Builder
    {
        return $query->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start);
    }

    /**
     * Every calendar day the request covers, inclusive of both ends.
     *
     * Calendar days, deliberately. Clarix has no working-week or holiday
     * calendar, so excluding weekends would mean inventing one — and an agency
     * working Sunday to Thursday would find the wrong days skipped. Counting
     * every day is the assumption that is visible rather than hidden.
     *
     * @return \Carbon\CarbonPeriod
     */
    public function period(): CarbonPeriod
    {
        return CarbonPeriod::create($this->start_date, $this->end_date);
    }

    /**
     * How many days the request covers.
     */
    public function dayCount(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
