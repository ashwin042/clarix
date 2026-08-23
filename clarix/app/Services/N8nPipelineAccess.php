<?php

namespace App\Services;

use App\Models\OrganizationSubscription;
use App\Models\User;

/**
 * Whether the task pipeline may act for a given person right now.
 *
 * Extracted the moment a second caller appeared. The link endpoints asked these
 * two questions in the controller; the intake endpoints have to ask exactly the
 * same two, and a copy is how the two halves of one integration end up
 * disagreeing about what a suspended agency may do.
 *
 * Returns the refusal as data rather than as a response, so the HTTP shape
 * stays the controller's business and this stays testable without a request.
 *
 * The questions live here rather than in middleware because of how these routes
 * authenticate. EnsureSubscriptionActive and EnsurePlanIncludes both read
 * $request->user(), which is null on a key-authenticated route — attached to
 * these groups they would wave every request through while appearing to guard
 * it. So both are asked once a chat has resolved to a person, against *that*
 * person's agency.
 */
class N8nPipelineAccess
{
    /**
     * The refusal that applies to this person, or null if none does.
     *
     * @return array{message: string, status: int}|null
     */
    public function refusalFor(User $user): ?array
    {
        $organizationId = $user->organization_id === null ? null : (int) $user->organization_id;

        $subscription = TenantContext::actingAsOrganization(
            $organizationId,
            fn () => OrganizationSubscription::query()->latest('started_at')->first()
        );

        if ($subscription !== null && $subscription->isSuspended()) {
            return [
                'message' => 'This organization\'s subscription is suspended.',
                'status'  => 402,
            ];
        }

        if (! $user->planAllows('automation')) {
            return [
                'message' => 'The Task Bot is not included in this organization\'s plan.',
                'status'  => 402,
            ];
        }

        return null;
    }
}
