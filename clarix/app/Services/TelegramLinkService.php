<?php

namespace App\Services;

use App\Exceptions\TelegramLinkException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Issues, verifies and revokes the codes that bind a Telegram account to a
 * Clarix user.
 *
 * Every method the bot reaches runs inside runWithoutScope(), and that is not
 * an optimisation — it is the feature. Hermes authenticates as no user at all
 * (see EnsureHermesRequest), so TenantContext has no organization to report,
 * and a scoped query would confine the lookup to nobody. Authenticating the
 * bot as a Sanctum service account instead, the way the task API does, would be
 * worse: the token resolves to a real user in one agency, so the lookup would
 * be silently filtered to that agency and every code from every other agency
 * would read as invalid, with no error anywhere to explain it.
 *
 * Single use is a property of the schema rather than of a flag. Consuming a
 * code nulls the hash column, so a replay matches zero rows — there is no
 * "already used" state to forget to check.
 */
class TelegramLinkService
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
     */
    public function issueFor(User $user): string
    {
        $code      = $this->generateCode();
        $hash      = self::hashOf($code);
        $expiresAt = now()->addMinutes(self::TTL_MINUTES);

        /*
         * Written through the query builder rather than through save(), and
         * that is load-bearing.
         *
         * save() writes only the attributes Eloquent judges dirty, and a User
         * held since before a verify() is stale in precisely the way that
         * breaks: verify() nulls both code columns through the builder, so the
         * row's expiry is null while the in-memory copy still holds the old
         * one — and the old one can equal the new one to the second. Eloquent
         * then sees no change, skips the column, and leaves the row with a
         * hash and no expiry. An issued code that can never expire is not a
         * code, and nothing about the call site would have hinted at it.
         *
         * Unscoped and keyed on the primary key of a User the caller already
         * holds, so it behaves the same from a session, the console or a test,
         * and there is no request input anywhere in the predicate.
         */
        TenantContext::runWithoutScope(fn () => User::query()
            ->whereKey($user->getKey())
            ->update([
                'telegram_link_code_hash'       => $hash,
                'telegram_link_code_expires_at' => $expiresAt,
            ]));

        // Bring the caller's copy back in step with the row, and mark it clean
        // so a later save() on it cannot write these two columns back again.
        $user->forceFill([
            'telegram_link_code_hash'       => $hash,
            'telegram_link_code_expires_at' => $expiresAt,
        ])->syncOriginal();

        return $code;
    }

    /**
     * Bind a chat id to whoever holds this code, and burn the code.
     *
     * @throws TelegramLinkException
     */
    public function verify(string $code, int $chatId): User
    {
        $hash = self::hashOf($code);

        return TenantContext::runWithoutScope(fn () => DB::transaction(function () use ($hash, $chatId) {
            // Locked for the duration so two bot calls racing the same code
            // serialise rather than both winning. sqlite ignores this, so the
            // guarantee is only truly exercised against MySQL — the affected
            // -row check below is what holds on both.
            $user = User::query()
                ->whereNotNull('telegram_link_code_hash')
                ->where('telegram_link_code_hash', $hash)
                ->lockForUpdate()
                ->first();

            if ($user === null
                || $user->telegram_link_code_expires_at === null
                || $user->telegram_link_code_expires_at->isPast()) {
                throw TelegramLinkException::invalidCode();
            }

            // Someone else's Telegram account may not be taken over by
            // presenting a valid code. The refusal leaves this user's code
            // outstanding, so they can disconnect the other end and retry.
            $conflict = User::query()
                ->where('telegram_chat_id', $chatId)
                ->whereKeyNot($user->getKey())
                ->exists();

            if ($conflict) {
                throw TelegramLinkException::chatAlreadyLinked();
            }

            $affected = User::query()
                ->whereKey($user->getKey())
                ->where('telegram_link_code_hash', $hash)
                ->update([
                    'telegram_chat_id'              => $chatId,
                    'telegram_linked_at'            => now(),
                    'telegram_link_code_hash'       => null,
                    'telegram_link_code_expires_at' => null,
                ]);

            // Belt to the lock's braces: if anything consumed the code between
            // the select and here, this wrote nothing and the caller must be
            // told the code is gone rather than handed a link that did not
            // happen.
            if ($affected !== 1) {
                throw TelegramLinkException::invalidCode();
            }

            return $user->refresh();
        }));
    }

    /**
     * Who owns a chat id, if anyone. What Hermes asks on every later message.
     */
    public function resolve(int $chatId): ?User
    {
        return TenantContext::runWithoutScope(
            fn () => User::query()->where('telegram_chat_id', $chatId)->first()
        );
    }

    /**
     * Drop the link and any outstanding code.
     *
     * The chat id is freed rather than remembered, so the same Telegram account
     * can afterwards be claimed by anybody — including a colleague taking over
     * a shared handset.
     */
    public function unlink(User $user): void
    {
        $user->forceFill([
            'telegram_chat_id'              => null,
            'telegram_linked_at'            => null,
            'telegram_link_code_hash'       => null,
            'telegram_link_code_expires_at' => null,
        ])->save();
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
