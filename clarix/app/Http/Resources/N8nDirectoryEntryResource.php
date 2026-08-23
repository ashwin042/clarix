<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One thing the bot offers as a choice: an id and something to call it.
 *
 * Shared by both directory lists because both are the same shape, and their
 * being the same shape is the point — the workflow renders a unit picker and a
 * PM picker with one set of nodes rather than two.
 *
 * Narrow for the reason N8nTelegramIdentityResource is narrow: a unit carries a
 * storage cap and a user carries an email, a role and the neighbours of a
 * password hash, and none of that is needed to put a name in a Telegram reply.
 * An n8n execution log tends to be readable by more people than the database.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
class N8nDirectoryEntryResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'   => (int) $this->getKey(),
            'name' => (string) $this->name,
        ];
    }
}
