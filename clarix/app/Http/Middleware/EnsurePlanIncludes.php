<?php

namespace App\Http\Middleware;

use App\Services\PlanFeatures;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route on the organization's plan rather than on the viewer's role.
 *
 * The commercial layer, and the coarser of the two: it asks what the agency
 * bought, before the permission layer inside the component asks what this
 * person may do. Both have to pass, and neither can stand in for the other —
 * an admin with every permission in the panel is still on whatever plan their
 * agency pays for.
 *
 * Refuses with 402 rather than 403 because the answer is "not purchased", not
 * "not permitted", and the two want different pages. abort() is used rather
 * than a bespoke response so that this middleware and the component-level
 * guards behind it cannot drift into answering the same question two ways.
 */
class EnsurePlanIncludes
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        if ($user->planAllows($feature)) {
            return $next($request);
        }

        abort(402, self::refusalFor($feature, $user->organization_id));
    }

    /**
     * The sentence shown to somebody who has just been refused.
     *
     * Built here rather than written out per feature so that adding a gated
     * area cannot introduce a fifth way of phrasing the same refusal. Shared
     * with the component guards for the same reason.
     */
    public static function refusalFor(string $feature, ?int $organizationId): string
    {
        $plans   = app(PlanFeatures::class);
        $label   = $plans->labelFor($feature);
        $current = ucfirst($plans->planFor($organizationId === null ? null : (int) $organizationId));
        $needed  = $plans->minimumPlanFor($feature);

        if ($needed === null) {
            return "This feature isn't included in your {$current} plan.";
        }

        return "{$label} isn't included in your {$current} plan. "
            .'Upgrade to '.ucfirst($needed)." to unlock {$label}.";
    }
}
