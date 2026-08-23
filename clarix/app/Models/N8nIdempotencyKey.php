<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One remembered write from the task bot's pipeline.
 *
 * Not BelongsToOrganization, for the same reason N8nTelegramLink is not: these
 * rows are read on a request that authenticates as nobody, and the tenancy that
 * matters is enforced by the composite key naming a user rather than by a
 * global scope. The user is the tenancy — a row can only ever be found by the
 * person who wrote it.
 *
 * Nothing is mass-assignable. Every write goes through N8nIdempotencyStore, and
 * a fillable key would let a crafted field claim somebody else's.
 *
 * @property int         $user_id
 * @property string      $scope
 * @property string      $key
 * @property int|null    $response_status
 * @property array|null  $response_body
 */
class N8nIdempotencyKey extends Model
{
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'response_body' => 'array',
            'expires_at'    => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether this row is a finished result rather than a claim in flight.
     *
     * A null status is the claim: the row was inserted to take the key and the
     * work has not reported back. There is deliberately no separate flag — one
     * column cannot disagree with itself.
     */
    public function isComplete(): bool
    {
        return $this->response_status !== null;
    }
}
