<?php

namespace App\Models\Concerns;

use App\Models\Scopes\OrganizationScope;

/**
 * Scopes the model that *is* the tenant, rather than one that belongs to it.
 *
 * Organization carries no organization_id — its own primary key is the
 * organization — so it cannot use BelongsToOrganization, which would also try
 * to stamp a column that does not exist and hang a self-referential relation
 * off it. What it can share is the part that matters: the same
 * OrganizationScope, asking TenantContext the same question, so there is one
 * place where "who may see this" is decided rather than two.
 *
 * The effect for an ordinary member is that Organization::all() returns their
 * own agency and nothing else. Another agency's name, contact number and
 * address are not theirs to read, and until this existed a direct query would
 * have handed over every one of them.
 */
trait IsTenantRoot
{
    public static function bootIsTenantRoot(): void
    {
        static::addGlobalScope(new OrganizationScope);
    }

    /**
     * The column OrganizationScope filters on: this model's own key.
     */
    public function qualifiedOrganizationKey(): string
    {
        return $this->qualifyColumn($this->getKeyName());
    }
}
