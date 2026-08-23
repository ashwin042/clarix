<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Who a Telegram chat belongs to, as the task pipeline needs to know it.
 *
 * Narrower than TelegramIdentityResource, because this consumer needs less. A
 * task row needs a creator, a unit and an owning agency; the name and role the
 * AXOKAI bot uses to address someone are not part of filing work, so they are
 * not on the wire. Serialising the whole user would put the password hash's
 * neighbours, the link-code hash and every future column into a workflow's
 * execution log by default, which is a log that tends to be readable by more
 * people than the database is.
 *
 * All three values are read off the User at render time. Nothing here comes
 * from the link row, which is the point of not storing them on it — see
 * N8nTelegramLinkService.
 *
 * $wrap is null so the body is exactly { user_id, organization_id, unit_id }.
 * n8n addresses fields by path in a visual editor, and a 'data.' prefix is one
 * more thing for every node downstream to get wrong.
 *
 * @mixin \App\Models\User
 */
class N8nTelegramIdentityResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'user_id'         => (int) $this->id,
            'organization_id' => $this->organization_id === null ? null : (int) $this->organization_id,

            // Genuinely nullable: admins and superadmins belong to no unit.
            // The pipeline has to handle it rather than assume an integer.
            'unit_id'         => $this->unit_id === null ? null : (int) $this->unit_id,
        ];
    }
}
