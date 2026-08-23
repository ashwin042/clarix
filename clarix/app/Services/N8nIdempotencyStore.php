<?php

namespace App\Services;

use App\Models\N8nIdempotencyKey;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;

/**
 * Claiming, completing and replaying the task bot's idempotency keys.
 *
 * The insert *is* the lock. There is no select-then-insert anywhere here,
 * because between those two statements two retries of the same submission both
 * find nothing and both proceed — which is the exact case the whole mechanism
 * exists to prevent. The unique index on (user_id, scope, key) decides the
 * winner, and the loser reads back what the winner left.
 *
 * Nothing runs inside a transaction spanning the work. It cannot: the point is
 * that the claim stays visible to a concurrent request while the work is still
 * running, and a transaction would hide it until commit.
 */
class N8nIdempotencyStore
{
    /** How long a key is remembered, in hours. */
    public const TTL_HOURS = 24;

    /**
     * Take the key, or report who already holds it.
     *
     * @return N8nIdempotencyKey|null  null when the caller now owns the claim;
     *                                 the existing row when somebody else does
     */
    public function claim(User $user, string $scope, string $key, string $fingerprint): ?N8nIdempotencyKey
    {
        // An expired row is not a holder. Clearing it before the insert is what
        // lets a key be reused after the window, rather than being poisoned
        // for ever by one submission months ago.
        N8nIdempotencyKey::query()
            ->where('user_id', $user->getKey())
            ->where('scope', $scope)
            ->where('key', $key)
            ->where('expires_at', '<=', now())
            ->delete();

        try {
            N8nIdempotencyKey::query()->insert([
                'user_id'             => $user->getKey(),
                'scope'               => $scope,
                'key'                 => $key,
                'request_fingerprint' => $fingerprint,
                'expires_at'          => now()->addHours(self::TTL_HOURS),
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->holder($user, $scope, $key);
        }

        return null;
    }

    /**
     * Record what the work answered, so a later retry can be given the same
     * thing.
     */
    public function complete(User $user, string $scope, string $key, int $status, mixed $body): void
    {
        N8nIdempotencyKey::query()
            ->where('user_id', $user->getKey())
            ->where('scope', $scope)
            ->where('key', $key)
            ->update([
                'response_status' => $status,
                'response_body'   => json_encode($body),
                'updated_at'      => now(),
            ]);
    }

    /**
     * Give the key back.
     *
     * Used when the work did not succeed. A refused or failed request has left
     * nothing behind to be duplicated, so holding the key would only stop the
     * caller fixing the problem and trying again — which, for a workflow that
     * re-fetches a file from Telegram and re-posts it, is the ordinary path
     * rather than an edge case.
     */
    public function release(User $user, string $scope, string $key): void
    {
        N8nIdempotencyKey::query()
            ->where('user_id', $user->getKey())
            ->where('scope', $scope)
            ->where('key', $key)
            ->whereNull('response_status')
            ->delete();
    }

    /**
     * The live row for this key, if there is one.
     */
    public function holder(User $user, string $scope, string $key): ?N8nIdempotencyKey
    {
        return N8nIdempotencyKey::query()
            ->where('user_id', $user->getKey())
            ->where('scope', $scope)
            ->where('key', $key)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * A stable hash of what this request is asking for.
     *
     * Files are reduced to name and size rather than hashed byte for byte. The
     * ceiling here is 50MB a file and ten files a request, so hashing the
     * contents would add half a gigabyte of reading to every submission to
     * catch a case — same name, same size, different bytes — that a workflow
     * has no way to produce by accident.
     *
     * The key itself is excluded, obviously, and so is nothing else: two
     * requests that differ in any field the caller sent are different requests.
     *
     * @param  array<string, mixed>  $input
     * @param  array<int, \Illuminate\Http\UploadedFile>  $files
     */
    public static function fingerprint(string $method, string $path, array $input, array $files): string
    {
        /*
         * Built as a flat string rather than through json_encode, because
         * json_encode returns false rather than throwing when it meets
         * something it cannot represent — and hash('sha256', false) is the hash
         * of the empty string. An UploadedFile in the input array was enough to
         * do it, which made every fingerprint in the system identical and every
         * reused key look like a legitimate replay. Silent, and exactly the
         * failure this class exists to prevent.
         *
         * Arr::dot flattens nested input to scalars, so the loop below sees
         * leaves. Anything still not scalar is skipped rather than stringified:
         * an object's default representation is not stable between requests,
         * and an unstable fingerprint would refuse honest retries.
         */
        $parts = [];

        foreach (Arr::dot($input) as $name => $value) {
            if ($value === null || is_scalar($value)) {
                $parts[] = $name.'='.var_export($value, true);
            }
        }

        sort($parts);

        $shapes = [];

        foreach ($files as $file) {
            $shapes[] = $file->getClientOriginalName().':'.$file->getSize();
        }

        // Sorted, because the order files arrive in is not part of what is
        // being asked for — the same two files in the other order is the same
        // submission, and treating it as different would refuse a legitimate
        // retry.
        sort($shapes);

        return hash('sha256', implode("\n", [
            $method,
            $path,
            implode('&', $parts),
            implode('&', $shapes),
        ]));
    }
}
