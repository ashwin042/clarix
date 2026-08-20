<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one person was paid for one month.
 *
 * Tenant-scoped and emphatically not PlatformVisible. Salary is the most
 * sensitive thing an agency keeps in Clarix, and running the platform grants
 * no sight of it: OrganizationScope answers a superadmin with an impossible
 * predicate, so reads, aggregates, updates and deletes all return nothing.
 *
 * user_id and created_by are both kept out of $fillable. Whose pay a record
 * describes, and who wrote it down, are decided by the server — a
 * mass-assignable user_id would let a crafted form move a salary figure onto
 * somebody else.
 */
class PayrollRecord extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    /**
     * @var array<string, string>
     */
    public const STATUSES = [
        'draft'     => 'Draft',
        'finalized' => 'Finalized',
        'paid'      => 'Paid',
    ];

    protected $fillable = ['month', 'base_amount', 'deductions', 'notes'];

    /**
     * Defaults held on the model as well as on the column.
     *
     * A database default only applies on insert and never reaches the instance
     * in memory, so a freshly created record had a null status until it was
     * reloaded — and PayrollLifecycle, reading that null, found no legal
     * transition and refused to finalise a record that had only just been
     * entered. Declaring them here makes a new record consistent from the
     * moment it exists.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status'     => 'draft',
        'deductions' => 0,
    ];

    protected function casts(): array
    {
        return [
            // Pinned to Y-m-d for the same reason the other date columns are:
            // a bare date cast round-trips as a timestamp and then fails to
            // match a plain date in a lookup.
            'month'       => 'date:Y-m-d',
            'base_amount' => 'decimal:2',
            'deductions'  => 'decimal:2',
            'net_amount'  => 'decimal:2',
            'paid_at'     => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        /*
         * net_amount is stored, but never written by a caller. Recomputing it
         * on every save is what makes storing it safe: the column is always
         * base minus deductions, so it can be summed in SQL and read back on a
         * finalised record without any chance of it disagreeing with the two
         * figures it comes from.
         */
        static::saving(function (PayrollRecord $record): void {
            $record->net_amount = round((float) $record->base_amount - (float) $record->deductions, 2);
        });
    }

    /**
     * Months are held as the first of the month, whatever was handed in.
     *
     * Normalised here rather than at the call sites so that the unique key on
     * (user_id, month) actually means "one record per month" — two admins
     * entering the 1st and the 15th of the same month would otherwise create
     * two records the constraint could not see as duplicates.
     */
    public function setMonthAttribute($value): void
    {
        $this->attributes['month'] = \Illuminate\Support\Carbon::parse($value)->startOfMonth()->toDateString();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForMonth(Builder $query, string $month): Builder
    {
        return $query->whereDate('month', \Illuminate\Support\Carbon::parse($month)->startOfMonth()->toDateString());
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Whether the money figures may still be changed.
     *
     * Only a draft is editable. Finalising is the point at which the numbers
     * stop moving; correcting a finalised record means deliberately reverting
     * it to draft first, which is a decision rather than a slip.
     */
    public function amountsAreEditable(): bool
    {
        return $this->isDraft();
    }
}
