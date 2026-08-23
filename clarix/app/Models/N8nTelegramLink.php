<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's link to the task-submission bot.
 *
 * Pointedly not BelongsToOrganization. Every other tenant-owned model carries
 * organization_id and is filtered by OrganizationScope, and that is right for
 * them — but this table's whole job is to answer "who is this chat" for a bot
 * that is authenticated as nobody. A global scope over it would filter the
 * lookup to an organization that does not exist on such a request, which
 * TenantContext reports as null and therefore does not filter at all — so the
 * scope would be inert here in the happy path and actively wrong the moment
 * anything ran this query while a user *was* signed in. Leaving it unscoped
 * makes that explicit instead of accidental.
 *
 * The tenancy that matters is still enforced, just not here: the owning
 * organization is read from the linked user at request time, so a link can only
 * ever speak for the agency its user is currently in.
 *
 * Nothing is mass-assignable. Every write goes through N8nTelegramLinkService,
 * and a fillable chat_id would be a way to bind somebody else's Telegram
 * account from a crafted form field.
 *
 * @property int         $id
 * @property int         $user_id
 * @property string|null $chat_id
 * @property string|null $link_code_hash
 * @property bool        $is_active
 */
class N8nTelegramLink extends Model
{
    protected $fillable = [];

    protected $hidden = [
        // Never serialised. The hash is the only stored form of a live code,
        // and leaking it hands a caller the means to finish somebody's link.
        'link_code_hash',
    ];

    protected function casts(): array
    {
        return [
            'code_expires_at' => 'datetime',
            'linked_at'       => 'datetime',
            'is_active'       => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether this row currently speaks for a Telegram chat.
     *
     * Both halves are required. is_active alone is true of a row that has only
     * ever held an unused code — the column defaults to true at mint time,
     * before any chat exists — and chat_id alone is true of a link somebody
     * has since disconnected.
     */
    public function isLive(): bool
    {
        return $this->is_active && $this->chat_id !== null;
    }
}
