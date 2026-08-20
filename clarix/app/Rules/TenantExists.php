<?php

namespace App\Rules;

use App\Services\TenantContext;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * An "exists" rule confined to the current organization.
 *
 * The validator builds its queries straight on the query builder, so it never
 * sees Eloquent's global scopes. A plain `exists:units,id` therefore answers
 * "does this id exist anywhere on the platform", which would let a request
 * from one agency pass another agency's unit id — a cross-tenant write that
 * the read scope cannot catch, because by then the value is already trusted.
 *
 * Adding the organization to the rule closes that gap at the point the id
 * enters the system. When there is no current organization — a superadmin, or
 * the console — the rule falls back to a plain existence check, which is the
 * right answer for an actor who is not confined to one agency.
 */
class TenantExists
{
    public static function in(string $table, string $column = 'id'): Exists
    {
        $rule = Rule::exists($table, $column);

        $organizationId = TenantContext::organizationId();

        if ($organizationId !== null) {
            $rule->where('organization_id', $organizationId);
        }

        return $rule;
    }
}
