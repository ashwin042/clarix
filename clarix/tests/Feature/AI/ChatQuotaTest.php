<?php

namespace Tests\Feature\AI;

use App\Models\DailyChatRequest;
use App\Models\User;
use App\Services\ChatQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatQuotaTest extends TestCase
{
    use RefreshDatabase;

    private ChatQuota $quota;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.groq.daily_limit', 15);
        $this->quota = app(ChatQuota::class);
    }

    public function test_a_fresh_user_has_the_full_allowance(): void
    {
        $user = User::factory()->create();

        $this->assertSame(15, $this->quota->remaining($user));
        $this->assertSame(0, $this->quota->used($user));
        $this->assertFalse($this->quota->hasReachedLimit($user));
    }

    public function test_consuming_decrements_what_is_remaining(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($this->quota->consume($user));
        $this->assertSame(1, $this->quota->used($user));
        $this->assertSame(14, $this->quota->remaining($user));
    }

    public function test_the_limit_is_enforced_and_stops_at_exactly_the_limit(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 15; $i++) {
            $this->assertTrue($this->quota->consume($user), "Message {$i} should have been allowed.");
        }

        $this->assertTrue($this->quota->hasReachedLimit($user));
        $this->assertFalse($this->quota->consume($user), 'The 16th message must be refused.');
        $this->assertSame(15, $this->quota->used($user), 'A refused message must not increment the counter.');
        $this->assertSame(0, $this->quota->remaining($user));
    }

    public function test_the_allowance_is_per_user(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->quota->consume($a);

        $this->assertSame(1, $this->quota->used($a));
        $this->assertSame(0, $this->quota->used($b));
        $this->assertSame(15, $this->quota->remaining($b));
    }

    /** Yesterday's usage must not count against today. */
    public function test_the_allowance_resets_on_a_new_day(): void
    {
        $user = User::factory()->create();

        DailyChatRequest::create([
            'user_id'       => $user->id,
            'date'          => today()->subDay()->toDateString(),
            'request_count' => 15,
        ]);

        $this->assertSame(0, $this->quota->used($user));
        $this->assertSame(15, $this->quota->remaining($user));
        $this->assertFalse($this->quota->hasReachedLimit($user));
    }

    public function test_a_refund_returns_the_message_to_the_allowance(): void
    {
        $user = User::factory()->create();

        $this->quota->consume($user);
        $this->quota->refund($user);

        $this->assertSame(0, $this->quota->used($user));
        $this->assertSame(15, $this->quota->remaining($user));
    }

    public function test_a_refund_never_drives_the_counter_below_zero(): void
    {
        $user = User::factory()->create();

        $this->quota->refund($user);
        $this->quota->refund($user);

        $this->assertSame(0, $this->quota->used($user));
    }
}
