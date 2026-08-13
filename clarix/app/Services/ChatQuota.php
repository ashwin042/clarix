<?php

namespace App\Services;

use App\Models\DailyChatRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The daily Clarix AI message allowance.
 *
 * One counter per user per calendar day, in the app's timezone, resetting at
 * midnight simply because a new day is a different row. Both the Chatbot and
 * the AI Overview's "Messages Remaining" stat read through here, so the number
 * on the two pages can never disagree.
 *
 * consume() is the only method that writes. It is deliberately atomic: two
 * tabs sending at the same moment must not both pass a read-then-write check
 * and land the user on 16 messages.
 */
class ChatQuota
{
    public function limit(): int
    {
        return max(0, (int) config('services.groq.daily_limit', 15));
    }

    /** Messages this user has already sent today. */
    public function used(User $user): int
    {
        return (int) DailyChatRequest::query()
            ->where('user_id', $user->id)
            ->whereDate('date', today())
            ->value('request_count') ?? 0;
    }

    public function remaining(User $user): int
    {
        return max(0, $this->limit() - $this->used($user));
    }

    public function hasReachedLimit(User $user): bool
    {
        return $this->remaining($user) < 1;
    }

    /**
     * Claim one message for today. Returns false when the user is already at
     * the limit, in which case nothing is written and no call should be made.
     *
     * The row is created and incremented in one statement so concurrent sends
     * serialise on the (user_id, date) unique index rather than racing between
     * a read and a write.
     */
    public function consume(User $user): bool
    {
        $limit = $this->limit();

        if ($limit < 1) {
            return false;
        }

        return DB::transaction(function () use ($user, $limit): bool {
            $row = DailyChatRequest::query()
                ->where('user_id', $user->id)
                ->whereDate('date', today())
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                DailyChatRequest::create([
                    'user_id'       => $user->id,
                    'date'          => today()->toDateString(),
                    'request_count' => 1,
                ]);

                return true;
            }

            if ($row->request_count >= $limit) {
                return false;
            }

            $row->increment('request_count');

            return true;
        });
    }

    /**
     * Hand back a message that was claimed but never actually spent, so a
     * network failure or a Groq outage does not cost the user an allowance.
     */
    public function refund(User $user): void
    {
        DailyChatRequest::query()
            ->where('user_id', $user->id)
            ->whereDate('date', today())
            ->where('request_count', '>', 0)
            ->decrement('request_count');
    }
}
