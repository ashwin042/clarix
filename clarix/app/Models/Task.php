<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class Task extends Model
{
    protected $fillable = [
        'title',
        'task_code',
        'task_type',
        'important_notes',
        'unit_id',
        'created_by',
        'pm_id',
        'assigned_admin_id',
        'priority',
        'status',
        'deadline',
        'credit_amount',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'deadline'     => 'date',
            'completed_at' => 'datetime',
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

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
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

    public function regularFiles(): HasMany
    {
        return $this->hasMany(TaskFile::class)->regular();
    }

    public function completedFiles(): HasMany
    {
        return $this->hasMany(TaskFile::class)->completed();
    }

    /**
     * Delete the task along with every attached file. Each file (regular and
     * completed) is removed from R2 first, then the file records, then the
     * task itself. A failed R2 delete is logged but does not stop the rest of
     * the cleanup, so nothing gets stuck.
     */
    public function deleteWithFiles(): void
    {
        foreach ($this->files as $file) {
            $this->deleteFileFromR2($file->file_path);
        }

        $this->files()->delete();
        $this->delete();
    }

    /**
     * Delete a single stored object from R2, logging (but swallowing) any
     * failure so callers can continue their cleanup.
     */
    public function deleteFileFromR2(string $filePath): void
    {
        try {
            // The r2 disk is configured with 'throw' => false, so a failed
            // delete returns false rather than raising an exception.
            if (! Storage::disk('r2')->delete($filePath)) {
                Log::error('R2 storage reported failure deleting file.', [
                    'file_path' => $filePath,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to delete file from R2 storage.', [
                'file_path' => $filePath,
                'exception' => $e->getMessage(),
            ]);
        }
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
