<?php

namespace App\Services;

use App\Exceptions\N8nTelegramLinkException;
use App\Models\N8nTelegramLink;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Issues, verifies and revokes the codes that bind a Telegram account to a
 * Clarix user *for the task-submission bot*.
 *
 * A deliberate near-twin of TelegramLinkService, sharing no code with it. The
 * two integrations serve different pipelines, present different bot tokens and
 * will be changed for different reasons; a shared base class would mean a
 * change made for one silently altering the other, and the thing being altered
 * is a credential flow. The duplication is the cheaper mistake, so the
 * constants below are copied rather than imported even though they are
 * currently identical — "same format as the AXOKAI code" is a fact about today,
 * not a constraint either service should impose on the other.
 *
 * Every lookup runs inside runWithoutScope(). That is not an optimisation, it
 * is the feature: the bot authenticates as no user at all (see EnsureN8nRequest)
 * so TenantContext has no organization to report, and a scoped query on users
 * would confine the search to nobody. Authenticating the bot as a Sanctum
 * service account instead would be worse — the token resolves to a real user in
 * one agency, so every code from every other agency would read as invalid with
 * no error anywhere to explain it.
 *
 * Single use is a property of the schema rather than of a flag. Consuming a
 * code nulls the hash column, so a replay matches zero rows: there is no
 * "already used" state for a future reader to forget to check.
 *
 * ── The organization/unit decision ──────────────────────────────────────────
 * resolve() returns the User and nothing is cached. organization_id and unit_id
 * are read off that user at request time, so a person moved between units files
 * their next task against the unit they are actually in. The alternative —
 * stamping both onto the link row at verify() time — would be one fewer join
 * and one permanent lie: a reorganised team keeps filing against the old unit,
 * and the credit lands in the wrong place with nothing in the logs to say why.
 * Flagged for confirmation before this is finalised.
 */
class N8nTelegramLinkService
{
    /**
     * No I, L, O, 0 or 1. The code is read off a screen and retyped into a
     * phone, and those five are where that goes wrong. 31 symbols over 8
     * characters is a little under 40 bits, which is safe because of the
     * fifteen-minute expiry and the throttle in front of the endpoint, and
     * would not be safe without them.
     */
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const CODE_LENGTH = 8;

    public const TTL_MINUTES = 15;

    /**
     * Mint a code for a user and return the plaintext, once.
     *
     * The caller gets the only readable copy that will ever exist; the row
     * keeps the hash. Issuing replaces any outstanding code, so a user who
     * reopens the card invalidates the code they were shown before.
     *
     * A live link survives this untouched. Somebody who mints a code while
     * already connected has not disconnected, and taking their chat away
     * because they clicked the wrong button would be a surprising way to lose
     * a working integration.
     */
    public function issueCode(User $user): string
    {
        $code      = $this->generateCode();
        $hash      = self::hashOf($code);
        $expiresAt = now()->addMinutes(self::TTL_MINUTES);

        TenantContext::runWithoutScope(function () use ($user, $hash, $expiresAt) {
            /*
             * Written through the query builder rather than through save(), and
             * that is load-bearing.
             *
             * save() writes only the attributes Eloquent judges dirty, and a
             * model held since before a verify() is stale in precisely the way
             * that breaks: verify() nulls both code columns through the
             * builder, so the row's expiry is null while an in-memory copy
             * still holds the old one — and the old one can equal the new one
             * to the second. Eloquent then sees no change, skips the column,
             * and leaves the row with a hash and no expiry. An issued code that
             * can never expire is not a code, and nothing about the call site
             * would have hinted at it.
             */
            $affected = N8nTelegramLink::query()
                ->where('user_id', $user->getKey())
                ->update([
                    'link_code_hash'  => $hash,
                    'code_expires_at' => $expiresAt,
                    'updated_at'      => now(),
                ]);

            if ($affected === 0) {
                try {
                    N8nTelegramLink::query()->insert([
                        'user_id'         => $user->getKey(),
                        'link_code_hash'  => $hash,
                        'code_expires_at' => $expiresAt,
                        'is_active'       => true,
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);
                } catch (UniqueConstraintViolationException) {
                    // Two clicks a millisecond apart on a user with no row yet:
                    // both updates matched nothing, both tried to insert, and
                    // user_id is unique so the loser lands here. The row now
                    // exists, so the update that was a no-op a moment ago is
                    // the right thing to do — and the winner's code is the one
                    // being replaced, which is what issuing again always means.
                    N8nTelegramLink::query()
                        ->where('user_id', $user->getKey())
                        ->update([
                            'link_code_hash'  => $hash,
                            'code_expires_at' => $expiresAt,
                            'updated_at'      => now(),
                        ]);
                }
            }
        });

        return $code;
    }

    /**
     * Bind a chat id to whoever holds this code, and burn the code.
     *
     * @throws N8nTelegramLinkException
     */
    public function verify(string $code, string $chatId): User
    {
        $hash   = self::hashOf($code);
        $chatId = self::normalizeChatId($chatId);

        return TenantContext::runWithoutScope(fn () => DB::transaction(function () use ($hash, $chatId) {
            // Locked for the duration so two bot calls racing the same code
            // serialise rather than both winning. sqlite ignores this, so the
            // guarantee is only truly exercised against MySQL — the affected
            // -row check below is what holds on both.
            $link = N8nTelegramLink::query()
                ->whereNotNull('link_code_hash')
                ->where('link_code_hash', $hash)
                ->lockForUpdate()
                ->first();

            if ($link === null
                || $link->code_expires_at === null
                || $link->code_expires_at->isPast()) {
                throw N8nTelegramLinkException::invalidCode();
            }

            $this->claimChatId($chatId, $link);

            $affected = N8nTelegramLink::query()
                ->whereKey($link->getKey())
                ->where('link_code_hash', $hash)
                ->update([
                    'chat_id'         => $chatId,
                    'linked_at'       => now(),
                    'is_active'       => true,
                    'link_code_hash'  => null,
                    'code_expires_at' => null,
                    'updated_at'      => now(),
                ]);

            // Belt to the lock's braces: if anything consumed the code between
            // the select and here, this wrote nothing and the caller must be
            // told the code is gone rather than handed a link that did not
            // happen.
            if ($affected !== 1) {
                throw N8nTelegramLinkException::invalidCode();
            }

            $user = User::query()->whereKey($link->user_id)->first();

            // The foreign key cascades, so a link whose user has been deleted
            // does not exist. Treated as an invalid code rather than allowed to
            // return null into a signature that promises a User.
            if ($user === null) {
                throw N8nTelegramLinkException::invalidCode();
            }

            return $user;
        }));
    }

    /**
     * Who owns a chat id, if anyone. What the pipeline asks on every message.
     *
     * Returns the User rather than a stored snapshot, which is what makes
     * organization_id and unit_id live — see the class docblock.
     */
    public function resolve(string $chatId): ?User
    {
        $chatId = self::normalizeChatId($chatId);

        return TenantContext::runWithoutScope(function () use ($chatId) {
            $link = N8nTelegramLink::query()
                ->where('chat_id', $chatId)
                ->where('is_active', true)
                ->first();

            if ($link === null) {
                return null;
            }

            return User::query()->whereKey($link->user_id)->first();
        });
    }

    /**
     * The link row behind a user, or null if they have never used the bot.
     *
     * The card needs the row itself rather than a boolean — it shows when the
     * link was made — and reads it unscoped for the same reason everything else
     * here does.
     */
    public function linkFor(User $user): ?N8nTelegramLink
    {
        return TenantContext::runWithoutScope(
            fn () => N8nTelegramLink::query()->where('user_id', $user->getKey())->first()
        );
    }

    /**
     * Deactivate the link and drop any outstanding code.
     *
     * Deactivation rather than deletion, so there is still a record that this
     * person was connected and to which chat. chat_id is kept for the same
     * reason — and because keeping it is safe: verify() releases a chat id held
     * by a dormant row (see claimChatId), so a disconnected link cannot squat
     * a Telegram account that somebody else now wants to use.
     */
    public function unlink(User $user): void
    {
        TenantContext::runWithoutScope(fn () => N8nTelegramLink::query()
            ->where('user_id', $user->getKey())
            ->update([
                'is_active'       => false,
                'link_code_hash'  => null,
                'code_expires_at' => null,
                'updated_at'      => now(),
            ]));
    }

    /**
     * Make a chat id available to $link, or refuse.
     *
     * Someone else's Telegram account may not be taken over by presenting a
     * valid code, so a *live* link elsewhere is a conflict. The refusal leaves
     * this user's code outstanding, so they can disconnect the other end and
     * retry.
     *
     * A *dormant* link elsewhere is not a conflict, it is litter: the previous
     * owner disconnected, and the chat id is theirs no longer. It is released
     * here rather than at unlink() time so that the row keeps saying what it
     * was connected to right up until somebody else needs the name.
     *
     * @throws N8nTelegramLinkException
     */
    protected function claimChatId(string $chatId, N8nTelegramLink $link): void
    {
        $holder = N8nTelegramLink::query()
            ->where('chat_id', $chatId)
            ->whereKeyNot($link->getKey())
            ->lockForUpdate()
            ->first();

        if ($holder === null) {
            return;
        }

        if ($holder->is_active) {
            throw N8nTelegramLinkException::chatAlreadyLinked();
        }

        N8nTelegramLink::query()
            ->whereKey($holder->getKey())
            ->update(['chat_id' => null, 'updated_at' => now()]);
    }

    /**
     * Codes are compared by hash, so what the user types has to be reduced to
     * one form first. Spaces, dashes and case are all things a phone keyboard
     * adds by itself.
     */
    public static function normalize(string $code): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    public static function hashOf(string $code): string
    {
        return hash('sha256', self::normalize($code));
    }

    /**
     * Chat ids are stored as strings, so two spellings of the same id would be
     * two different rows and the unique index would not catch it. Telegram
     * sends a JSON number; a leading '+', a stray space or a leading zero can
     * all arrive from a hand-written n8n node. Group and channel ids are
     * negative, so the sign is kept.
     */
    public static function normalizeChatId(string $chatId): string
    {
        $chatId = trim($chatId);

        if (preg_match('/^([+-]?)0*(\d+)$/', $chatId, $matches) !== 1) {
            return $chatId;
        }

        return ($matches[1] === '-' ? '-' : '').$matches[2];
    }

    /**
     * random_int rather than rand or str_shuffle: this is a credential, and
     * only a CSPRNG is fit to generate one.
     */
    protected function generateCode(): string
    {
        $max  = strlen(self::ALPHABET) - 1;
        $code = '';

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return $code;
    }
}
