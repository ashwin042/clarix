<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskNote extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'task_id',
        'note',
        'created_by',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
