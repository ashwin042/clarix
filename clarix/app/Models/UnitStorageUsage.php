<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Running total of the R2 bytes a unit is holding. Maintained on the write
 * path by StorageUsageService and corrected nightly against real R2 state by
 * the storage:reconcile command.
 */
class UnitStorageUsage extends Model
{
    use BelongsToOrganization;

    protected $table = 'unit_storage_usage';

    protected $fillable = [
        'unit_id',
        'bytes_used',
    ];

    protected function casts(): array
    {
        return [
            'bytes_used' => 'integer',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * The acting user's organization wins, so a request can never attribute a
     * record to another agency. Console commands and queued jobs have no
     * acting user, and fall back to the unit that owns this row.
     */
    protected function resolveOrganizationId(): ?int
    {
        return TenantContext::organizationId() ?? $this->unit?->organization_id;
    }
}
