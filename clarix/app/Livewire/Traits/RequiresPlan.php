<?php

namespace App\Livewire\Traits;

use App\Http\Middleware\EnsurePlanIncludes;

/**
 * A component's own plan check, behind the route's.
 *
 * The plan: middleware guards the page load, but a Livewire action POSTs to
 * /livewire/update and never passes through the route's middleware stack — so
 * a crafted request could mount a gated component directly and skip the gate
 * entirely. Every plan-gated component repeats the check for itself, which is
 * the same belt-and-braces MyPayroll and ManagePayroll already apply with
 * their policy checks.
 *
 * Shares abort(402) and the refusal sentence with the middleware, so it makes
 * no difference to the user which of the two locks turned them away.
 */
trait RequiresPlan
{
    protected function assertPlanIncludes(string $feature): void
    {
        $user = auth()->user();

        abort_unless(
            (bool) $user?->planAllows($feature),
            402,
            EnsurePlanIncludes::refusalFor($feature, $user?->organization_id)
        );
    }
}
