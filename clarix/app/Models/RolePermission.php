<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One agency's decision about what a role may do.
 *
 * The permissions catalogue is identical for every agency — the same list of
 * things that can be permitted — but the mapping from role to permission is
 * each agency's own, so this table is tenant-scoped and the catalogue is not.
 *
 * Deliberately not PlatformVisible. A role's permission map is operational
 * configuration belonging to the agency, so a platform superadmin reads no
 * rows of it at all: OrganizationScope answers them with an impossible
 * predicate rather than merely leaving the query unfiltered, which holds for
 * reads, aggregates, updates and deletes alike.
 */
class RolePermission extends Model
{
    use BelongsToOrganization;

    /**
     * organization_id is absent on purpose — BelongsToOrganization stamps it
     * from the acting user, and a mass-assignable tenant key would let a
     * crafted request move a permission row into another agency.
     */
    protected $fillable = ['role', 'permission_id', 'allowed'];

    protected $casts = [
        'allowed' => 'boolean',
    ];

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }
}
