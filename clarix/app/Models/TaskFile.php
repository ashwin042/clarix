<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Services\TenantContext;
use App\Observers\TaskFileObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(TaskFileObserver::class)]
class TaskFile extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'task_id',
        'file_path',
        'original_name',
        'file_size',
        'mime_type',
        'uploaded_by',
        'is_completed_file',
    ];

    protected function casts(): array
    {
        return [
            'is_completed_file' => 'boolean',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeRegular(Builder $query): Builder
    {
        return $query->where('is_completed_file', false);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('is_completed_file', true);
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }

    /**
     * The acting user's organization wins, so a request can never attribute a
     * record to another agency. Console commands and queued jobs have no
     * acting user, and fall back to the task that owns this row.
     */
    protected function resolveOrganizationId(): ?int
    {
        return TenantContext::organizationId() ?? $this->task?->organization_id;
    }
}
