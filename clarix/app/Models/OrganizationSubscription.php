<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Contracts\PlatformVisible;
use App\Services\PlanFeatures;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An organization's Clarix plan, and the state machine that governs whether
 * its people can still get in.
 *
 *   active     paid up, or inside the renewal date
 *   past_due   the renewal date has passed, still inside the grace period.
 *              Nothing is blocked — this exists so the nightly job knows when
 *              the window closes and so the organization can be warned.
 *   suspended  the grace period expired. Access is blocked until a superadmin
 *              renews or reactivates.
 *   cancelled  deliberately ended rather than unpaid. Kept from the original
 *              design; it does not block access on its own.
 *
 * Renewal dates are derived, never typed in: a cycle is a month or a year from
 * the date it starts, and letting those two disagree is how billing drifts.
 *
 * Tenant-scoped like everything else, so an agency's admin sees their own and
 * nobody else's — and PlatformVisible, because what an organization pays for
 * the product is the platform's business as much as theirs.
 */
class OrganizationSubscription extends Model implements PlatformVisible
{
    use BelongsToOrganization;

    /** @var list<string> */
    public const STATUSES = ['active', 'past_due', 'suspended', 'cancelled'];

    /** @var list<string> */
    public const BILLING_CYCLES = ['monthly', 'yearly'];

    protected $fillable = [
        'plan',
        'price',
        'billing_cycle',
        'started_at',
        'next_renewal_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price'           => 'decimal:2',
            'started_at'      => 'date',
            'next_renewal_at' => 'date',
        ];
    }

    /**
     * Writing a subscription invalidates the resolved-plan memo.
     *
     * PlanFeatures memoizes the plan for the lifetime of the request, which is
     * right for a request — one page load, one plan. It is wrong the moment a
     * plan changes *during* a request: the superadmin who just saved an
     * upgrade would re-render against the old tier, and so would anything
     * reading the storage cap after it.
     *
     * Invalidating here rather than at each call site means no future caller
     * has to remember. Deletes are covered as well, since removing the newest
     * subscription promotes the one behind it.
     */
    protected static function booted(): void
    {
        static::saved(fn () => PlanFeatures::flush());
        static::deleted(fn () => PlanFeatures::flush());
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrganizationSubscriptionPayment::class, 'subscription_id')->latest('paid_at');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }

    /**
     * The one status that blocks access. Checked by EnsureSubscriptionActive.
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * One billing cycle after the given date.
     *
     * The single place a renewal date is worked out, so the form, the renew
     * action and the reactivate action cannot disagree about what a cycle is.
     */
    public function renewalDateFrom(CarbonInterface|string $from): Carbon
    {
        $from = $from instanceof CarbonInterface ? Carbon::parse($from) : Carbon::parse($from);

        return $this->billing_cycle === 'yearly'
            ? $from->copy()->startOfDay()->addYear()
            : $from->copy()->startOfDay()->addMonth();
    }

    /**
     * Move the renewal on by one cycle, as when a payment has been received.
     *
     * Extends from the existing renewal date so that paying on time does not
     * cost the organization the days it had left — but from today if the date
     * has already gone by, so a long-lapsed plan does not renew into the past.
     * Reaching here always means the organization is paid up, so the status
     * returns to active.
     */
    public function renew(): void
    {
        $base = $this->next_renewal_at && $this->next_renewal_at->isFuture()
            ? $this->next_renewal_at
            : Carbon::now();

        $this->forceFill([
            'next_renewal_at' => $this->renewalDateFrom($base),
            'status'          => 'active',
        ])->save();
    }

    /**
     * Restore access without recording a payment.
     *
     * The renewal date is pulled forward if it has already passed, because a
     * status flipped to active behind a date in the past would be moved
     * straight back to past_due by the nightly job — the reactivation would
     * appear to work and then quietly undo itself overnight.
     */
    public function reactivate(): void
    {
        $attributes = ['status' => 'active'];

        if ($this->next_renewal_at === null || ! $this->next_renewal_at->isFuture()) {
            $attributes['next_renewal_at'] = $this->renewalDateFrom(Carbon::now());
        }

        $this->forceFill($attributes)->save();
    }

    public function suspend(): void
    {
        $this->forceFill(['status' => 'suspended'])->save();
    }

    /**
     * The date on which a past_due subscription runs out of grace.
     */
    public function graceEndsAt(): ?Carbon
    {
        return $this->next_renewal_at?->copy()->addDays((int) config('subscription.grace_days'));
    }

    /**
     * Whole days until renewal: negative once overdue, null when there is no
     * renewal to wait for.
     */
    public function daysUntilRenewal(): ?int
    {
        if ($this->next_renewal_at === null) {
            return null;
        }

        // startOfDay on both sides so the answer is a count of dates, not a
        // fraction that flips depending on the time of day it is read.
        return (int) now()->startOfDay()->diffInDays($this->next_renewal_at->startOfDay(), false);
    }

    /**
     * How the renewal reads on both billing screens.
     */
    public function renewalSummary(): string
    {
        if ($this->status === 'cancelled') {
            return 'Cancelled';
        }

        if ($this->status === 'suspended') {
            return 'Suspended';
        }

        $days = $this->daysUntilRenewal();

        if ($days === null) {
            return 'No renewal scheduled';
        }

        return match (true) {
            $days < 0   => 'Overdue by '.abs($days).' '.(abs($days) === 1 ? 'day' : 'days'),
            $days === 0 => 'Renews today',
            default     => 'Renews in '.$days.' '.($days === 1 ? 'day' : 'days'),
        };
    }

    /**
     * Active subscriptions whose renewal date has gone by.
     */
    public function scopeLapsed(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereNotNull('next_renewal_at')
            ->whereDate('next_renewal_at', '<', now()->startOfDay());
    }

    /**
     * past_due subscriptions whose grace period has run out.
     *
     * Measured from the renewal date rather than from whenever the row became
     * past_due, so the outcome depends only on the calendar. A job that misses
     * a week still suspends exactly the right rows when it next runs, instead
     * of granting an accidental extension.
     */
    public function scopeOutOfGrace(Builder $query): Builder
    {
        $cutoff = now()->startOfDay()->subDays((int) config('subscription.grace_days'));

        return $query->where('status', 'past_due')
            ->whereNotNull('next_renewal_at')
            ->whereDate('next_renewal_at', '<', $cutoff);
    }
}
