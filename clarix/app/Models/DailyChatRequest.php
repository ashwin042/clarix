<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per user per day, counting Clarix AI chatbot messages sent.
 * Read and written through App\Services\ChatQuota, not directly.
 */
class DailyChatRequest extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['user_id', 'date', 'request_count'];

    protected function casts(): array
    {
        return [
            'date'          => 'date',
            'request_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The acting user's organization wins, so a request can never attribute a
     * record to another agency. Console commands and queued jobs have no
     * acting user, and fall back to the user that owns this row.
     */
    protected function resolveOrganizationId(): ?int
    {
        return TenantContext::organizationId() ?? $this->user?->organization_id;
    }
}
