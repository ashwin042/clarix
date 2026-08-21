<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Who a Telegram chat belongs to, as Hermes needs to know it.
 *
 * Deliberately narrow. The bot needs enough to address the person and to scope
 * what it shows them — an id, a name, a role, an agency, a unit — and nothing
 * more. Serialising the whole user would put the password hash's neighbours,
 * the link-code hash and every future column on the wire by default, so the
 * fields are listed here rather than inherited.
 *
 * @mixin \App\Models\User
 */
class TelegramIdentityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => (int) $this->id,
            'name'    => $this->name,
            'email'   => $this->email,
            'role'    => $this->role,

            'organization' => [
                'id'   => (int) $this->organization_id,
                'name' => $this->organization?->name,
                'slug' => $this->organization?->slug,
            ],

            'unit' => $this->unit_id === null ? null : [
                'id'   => (int) $this->unit_id,
                'name' => $this->unit?->name,
            ],

            'chat_id'   => $this->telegram_chat_id,
            'linked_at' => $this->telegram_linked_at?->toIso8601String(),
        ];
    }
}
