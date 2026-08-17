<?php

namespace App\Models;

use App\Models\Concerns\IsTenantRoot;
use App\Models\Contracts\PlatformVisible;
use App\Observers\OrganizationObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A tenant: one agency using Clarix.
 *
 * Every agency-specific record carries an organization_id pointing here, and
 * this row is scoped on the same terms as the records that point at it — a
 * member reads their own agency and no other. The column differs because the
 * tenant root has no organization_id; its own key is the organization. See
 * IsTenantRoot.
 *
 * PlatformVisible: a superadmin administers the organizations themselves, so
 * they read all of them.
 */
#[ObservedBy(OrganizationObserver::class)]
class Organization extends Model implements PlatformVisible
{
    use HasFactory;
    use IsTenantRoot;

    /**
     * The tiers an organization can be on. Kept here rather than in a database
     * enum so adding one is a code change with no migration.
     *
     * @var list<string>
     */
    public const SUBSCRIPTION_TYPES = ['base', 'standard', 'pro'];

    protected $fillable = [
        'name',
        'contact_number',
        'email',
        'address',
        'subscription_type',
        'storage_cap_override_gb',
        'slug',
    ];

    protected function casts(): array
    {
        return [
            // Null means "use the plan's cap"; zero would mean "no allowance".
            // Readers must distinguish the two.
            'storage_cap_override_gb' => 'integer',
        ];
    }

    /**
     * Route model binding on the public-facing handle rather than the id.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Every plan this organization has been on, newest first. Plan changes are
     * kept as history rather than overwriting one row.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(OrganizationSubscription::class)->latest('started_at');
    }

    /**
     * The plan currently in force — the most recently started one.
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(OrganizationSubscription::class)->latestOfMany('started_at');
    }

    /**
     * The organization's full payment history toward Clarix, newest first.
     */
    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(OrganizationSubscriptionPayment::class)->latest('paid_at');
    }
}
