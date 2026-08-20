<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Contracts\PlatformVisible;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payment made toward an organization's Clarix subscription.
 *
 * Not to be confused with Payment, which is an agency billing its own clients
 * and stays invisible to the platform.
 */
class OrganizationSubscriptionPayment extends Model implements PlatformVisible
{
    use BelongsToOrganization;

    protected $fillable = [
        'subscription_id',
        'amount',
        'paid_at',
        'method',
    ];

    protected function casts(): array
    {
        return [
            'amount'  => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(OrganizationSubscription::class, 'subscription_id');
    }

    /**
     * A payment belongs to the organization of the subscription it is against.
     * Recorded from the platform side, where the actor is a superadmin with no
     * organization of their own, so the parent is what settles it.
     */
    protected function resolveOrganizationId(): ?int
    {
        return TenantContext::organizationId() ?? $this->subscription?->organization_id;
    }
}
