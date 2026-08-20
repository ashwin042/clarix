<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Models\Scopes\OrganizationScope;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as tenant-scoped: reads are confined to the current actor's
 * organization, and writes are stamped with it automatically.
 *
 * organization_id is deliberately kept out of every $fillable array. A tenant
 * key that could be mass-assigned from request input would let a crafted form
 * field move a record into another agency, so the value is only ever set here,
 * from the server's own idea of who is acting.
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('organization_id') !== null) {
                return;
            }

            $model->setAttribute('organization_id', $model->resolveOrganizationId());
        });
    }

    /**
     * The column OrganizationScope filters on, table-qualified.
     *
     * Overridden by the tenant root, whose own primary key is the
     * organization — see IsTenantRoot. Everything else carries a plain
     * organization_id.
     */
    public function qualifiedOrganizationKey(): string
    {
        return $this->qualifyColumn('organization_id');
    }

    /**
     * The organization a new record belongs to.
     *
     * The acting user's organization is the authority: it is the one thing an
     * HTTP request cannot lie about. Models whose owner can also be derived
     * from a parent row override this to add a fallback for the contexts that
     * have no acting user — console commands and queued jobs — while keeping
     * the actor's organization first, so a request can never attribute a
     * record to an agency other than its own.
     */
    protected function resolveOrganizationId(): ?int
    {
        return TenantContext::organizationId();
    }

    /**
     * The owning agency. Null only for a platform superadmin, which sits above
     * every organization rather than inside one.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
