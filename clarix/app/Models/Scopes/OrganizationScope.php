<?php

namespace App\Models\Scopes;

use App\Models\Contracts\PlatformVisible;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Confines every query on a tenant-scoped model to what the current actor is
 * entitled to see.
 *
 * There are three outcomes, and the whole decision lives in TenantContext so
 * that nothing outside this file has to ask who is acting:
 *
 *   a member of an organization  -> rows of that organization
 *   a platform superadmin        -> every row of a PlatformVisible model,
 *                                   and *no rows at all* of anything else
 *   anything else                -> no constraint (console, queued jobs, the
 *                                   login screen, runWithoutScope)
 *
 * The middle case is the point of the second branch below. A superadmin used
 * to be simply unfiltered, which quietly meant "sees everything"; operational
 * work belonging to the agencies is now refused outright rather than merely
 * left unscoped. An impossible predicate is used rather than a guard at the
 * call sites, so it holds for reads, aggregates, updates and deletes alike,
 * including on code that has never heard of tenancy.
 */
class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (TenantContext::isUnconfinedSuperadmin()) {
            if (! $model instanceof PlatformVisible) {
                // Matches nothing, on purpose. count() is 0, find() is null,
                // update() and delete() touch no rows.
                $builder->whereRaw('1 = 0');
            }

            return;
        }

        $organizationId = TenantContext::organizationId();

        if ($organizationId === null) {
            return;
        }

        // The column is asked of the model rather than assumed: everything
        // that belongs to an organization filters on organization_id, while
        // the organization itself filters on its own key. Table-qualified
        // either way, so the constraint stays unambiguous once a query joins
        // another table carrying the same column name.
        $builder->where($model->qualifiedOrganizationKey(), $organizationId);
    }
}
