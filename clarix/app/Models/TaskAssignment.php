<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Observers\TaskAssignmentActivityObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(TaskAssignmentActivityObserver::class)]
class TaskAssignment extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'task_id',
        'writer_id',
        'assigned_by',
        'status',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function writer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'writer_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeReadyForReview(Builder $query): Builder
    {
        return $query->where('status', 'ready_for_review');
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
