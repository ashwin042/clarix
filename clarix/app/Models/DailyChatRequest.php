<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per user per day, counting Clarix AI chatbot messages sent.
 * Read and written through App\Services\ChatQuota, not directly.
 */
class DailyChatRequest extends Model
{
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
}
