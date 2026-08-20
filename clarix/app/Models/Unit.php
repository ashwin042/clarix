<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Unit extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    protected $fillable = ['name', 'storage_cap_gb'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function storageUsage(): HasOne
    {
        return $this->hasOne(UnitStorageUsage::class);
    }

    /**
     * The cap that actually applies to this unit: its own storage_cap_gb when
     * set, otherwise the platform default from config/storage.php.
     */
    public function getEffectiveStorageCapGbAttribute(): int
    {
        return $this->storage_cap_gb ?? (int) config('storage.default_cap_gb');
    }
}
