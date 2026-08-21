<?php

namespace App\Http\Middleware;

use App\Models\OrganizationSubscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks an organization whose subscription has been suspended.
 *
 * Applied to the ordinary org-facing routes only. The superadmin portal is
 * deliberately outside it: reactivating a suspended organization is done from
 * there, so enforcing this on the platform would lock the only person who can
 * lift it out of the room with the switch in it.
 *
 * Three cases pass straight through:
 *
 *   a superadmin        never subject to an organization's billing state
 *   no subscription     an organization the platform has not set up billing
 *                       for yet is not in arrears; blocking here would take
 *                       every existing agency offline the day this shipped
 *   active / past_due   past_due is the grace period, and grace that blocks
 *                       anything is not grace
 *
 * The response is a page rather than a redirect or a logout: the user stays
 * signed in, can still reach their profile and can still sign out, they simply
 * cannot get at the work. A caller that asked for JSON gets the same refusal
 * as JSON — the middleware is shared with the API routes, and an integration
 * cannot read the suspension page.
 */
class EnsureSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->isSuperadmin()) {
            return $next($request);
        }

        // Scoped to the user's own organization by the global scope, so this
        // reads their subscription and nobody else's.
        $subscription = OrganizationSubscription::query()->latest('started_at')->first();

        if ($subscription === null || ! $subscription->isSuspended()) {
            return $next($request);
        }

        // The suspension page is a page, and an integration cannot read one.
        // Same decision, same status, in whichever form the caller asked for
        // — so a suspended agency's API stops filing work instead of getting
        // HTML it will fail to parse.
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'This organization\'s subscription is suspended.',
                'organization' => $user->organization?->name,
                'status' => $subscription->status,
            ], Response::HTTP_PAYMENT_REQUIRED);
        }

        return response()->view('errors.subscription-suspended', [
            'organization' => $user->organization,
            'subscription' => $subscription,
        ], Response::HTTP_PAYMENT_REQUIRED);
    }
}
