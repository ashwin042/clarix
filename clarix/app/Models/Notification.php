<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Services\TenantContext;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Clarix's own database notification.
 *
 * Laravel writes notifications through whatever model Notifiable::notifications()
 * returns, so subclassing it and overriding that relation on User is what puts
 * the notifications table under the same tenant rules as everything else — both
 * the organization filter on read and the organization stamp on write.
 *
 * In practice notifications are always reached through
 * auth()->user()->notifications(), which is already confined to one user. The
 * scope here is defence in depth; the auto-fill is not optional, since
 * organization_id is NOT NULL.
 */
class Notification extends DatabaseNotification
{
    use BelongsToOrganization;

    /**
     * A notification belongs to the organization of the person receiving it.
     *
     * Reading it from the recipient rather than the actor keeps the row
     * correct even when it is raised outside a request, and the two are the
     * same organization in every path that exists today.
     */
    protected function resolveOrganizationId(): ?int
    {
        if ($this->notifiable_type === User::class && $this->notifiable_id !== null) {
            $recipient = User::withoutGlobalScopes()
                ->whereKey($this->notifiable_id)
                ->first();

            if ($recipient?->organization_id !== null) {
                return (int) $recipient->organization_id;
            }
        }

        return TenantContext::organizationId();
    }
}
