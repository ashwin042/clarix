<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A category of leave, defined by one agency for itself.
 *
 * Tenant-scoped and not PlatformVisible, like everything else operational: how
 * an agency structures its leave is its own business.
 */
class LeaveType extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    protected $fillable = ['name', 'default_annual_allowance'];

    protected function casts(): array
    {
        return [
            'default_annual_allowance' => 'integer',
        ];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * Whether this category has an allowance to measure against at all.
     */
    public function isTracked(): bool
    {
        return $this->default_annual_allowance !== null;
    }
}
