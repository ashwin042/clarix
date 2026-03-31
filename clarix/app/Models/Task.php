<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $fillable = [
        'title',
        'task_code',
        'important_notes',
        'unit_id',
        'created_by',
        'pm_id',
        'priority',
        'status',
        'deadline',
        'credit_amount',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pm(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pm_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    public function writers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignments', 'task_id', 'writer_id')
            ->withPivot('status', 'assigned_by')
            ->withTimestamps();
    }

    public function files(): HasMany
    {
        return $this->hasMany(TaskFile::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(TaskNote::class)->latest();
    }

    public function scopeForAdmin(Builder $query): Builder
    {
        return $query->with(['unit', 'creator']);
    }

    public function scopeForPm(Builder $query, int $unitId): Builder
    {
        return $query->where('unit_id', $unitId)->with(['unit']);
    }

    public function scopeForWriter(Builder $query, int $writerId): Builder
    {
        return $query->whereHas('assignments', fn ($q) => $q->where('writer_id', $writerId))
            ->with(['files']);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeByPriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }
}
