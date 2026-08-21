<?php

namespace Tests\Feature\Telegram;

use App\Models\OrganizationSubscription;
use App\Services\TelegramLinkService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * No rate-limiter clearing between tests, and none needed: phpunit.xml sets
 * CACHE_STORE=array and each test builds a fresh application, so every test
 * starts with an empty limiter.
 */
class TelegramLinkApiTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    protected TelegramLinkService $service;

    /** @var array<string, mixed> */
    protected array $orgA;

    /** @var array<string, mixed> */
    protected array $orgB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);

        config()->set('services.hermes.key', 'test-key');
        config()->set('services.hermes.secret', 'test-secret');

        $this->service = app(TelegramLinkService::class);
        $this->orgA    = $this->populate($this->makeOrganization('api-a', 'Agency A'), 'A');
        $this->orgB    = $this->populate($this->makeOrganization('api-b', 'Agency B'), 'B');

        // Both agencies on Pro, because linking is gated on 'automation' and
        // config('plans.default') is 'base'. populate() deliberately creates no
        // subscription, so without this every test here would be refused with
        // 402 for a reason that has nothing to do with what it is testing.
        $this->subscribeOrganization($this->orgA['organization'], 'pro');
        $this->subscribeOrganization($this->orgB['organization'], 'pro');
    }

    /** @param array<string, mixed> $body */
    private function hit(string $path, array $body)
    {
        $payload   = json_encode($body);
        $timestamp = (string) now()->getTimestamp();

        return $this->call(
            'POST',
            $path,
            [],
            [],
            [],
            $this->transformHeadersToServerVars([
                'X-Hermes-Key'       => 'test-key',
                'X-Hermes-Timestamp' => $timestamp,
                'X-Hermes-Signature' => hash_hmac('sha256', $timestamp.'.'.$payload, 'test-secret'),
                'Content-Type'       => 'application/json',
                'Accept'             => 'application/json',
            ]),
            $payload
        );
    }

    public function test_a_valid_code_links_and_identifies_the_user(): void
    {
        $user = $this->orgA['pm'];
        $code = $this->service->issueFor($user);

        $this->hit('/api/v1/telegram/verify', ['code' => $code, 'chat_id' => 123456789012])
            ->assertOk()
            ->assertJsonPath('data.user_id', (int) $user->id)
            ->assertJsonPath('data.name', $user->name)
            ->assertJsonPath('data.role', 'pm')
            ->assertJsonPath('data.organization.slug', 'api-a')
            ->assertJsonPath('data.unit.id', (int) $user->unit_id);
    }

    /** The hash must never travel to the bot. */
    public function test_the_response_never_carries_the_code_hash(): void
    {
        $code = $this->service->issueFor($this->orgA['pm']);

        $this->hit('/api/v1/telegram/verify', ['code' => $code, 'chat_id' => 1])
            ->assertOk()
            ->assertJsonMissingPath('data.telegram_link_code_hash');
    }

    /**
     * The regression test for the whole design. The bot is authenticated as no
     * user, so if OrganizationScope ever reaches this lookup, agency B's codes
     * stop working while agency A's keep working — a failure that would look
     * like a flaky bot rather than a tenancy bug.
     */
    public function test_codes_from_any_organization_verify(): void
    {
        foreach ([[$this->orgA, 'api-a', 1001], [$this->orgB, 'api-b', 1002]] as [$org, $slug, $chatId]) {
            $code = $this->service->issueFor($org['writer']);

            $this->hit('/api/v1/telegram/verify', ['code' => $code, 'chat_id' => $chatId])
                ->assertOk()
                ->assertJsonPath('data.organization.slug', $slug);
        }
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        $code = $this->service->issueFor($this->orgA['pm']);

        $this->hit('/api/v1/telegram/verify', ['code' => $code, 'chat_id' => 2001])->assertOk();

        $this->hit('/api/v1/telegram/verify', ['code' => $code, 'chat_id' => 2002])
            ->assertStatus(422);
    }

    public function test_an_expired_code_is_refused(): void
    {
        $user = $this->orgA['pm'];
        $code = $this->service->issueFor($user);
        $user->forceFill(['telegram_link_code_expires_at' => now()->subMinute()])->save();

        $this->hit('/api/v1/telegram/verify', ['code' => $code, 'chat_id' => 3001])
            ->assertStatus(422);
    }

    public function test_a_chat_id_linked_elsewhere_is_refused_with_conflict(): void
    {
        $this->hit('/api/v1/telegram/verify', [
            'code'    => $this->service->issueFor($this->orgA['pm']),
            'chat_id' => 4001,
        ])->assertOk();

        $this->hit('/api/v1/telegram/verify', [
            'code'    => $this->service->issueFor($this->orgB['pm']),
            'chat_id' => 4001,
        ])->assertStatus(409);
    }

    public function test_a_missing_code_is_a_validation_error(): void
    {
        $this->hit('/api/v1/telegram/verify', ['chat_id' => 5001])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_a_missing_chat_id_is_a_validation_error(): void
    {
        $this->hit('/api/v1/telegram/verify', ['code' => 'ABCDEFGH'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('chat_id');
    }

    /**
     * A suspended agency's people may not link. The subscription middleware
     * cannot do this job here — it reads $request->user(), which is null on a
     * bot-authenticated route, so it would wave everything through.
     */
    public function test_a_suspended_organization_is_refused(): void
    {
        TenantContext::actingAsOrganization($this->orgA['organization']->id, function () {
            OrganizationSubscription::query()->update(['status' => 'suspended']);
        });

        $code = $this->service->issueFor($this->orgA['pm']);

        $this->hit('/api/v1/telegram/verify', ['code' => $code, 'chat_id' => 6001])
            ->assertStatus(402);
    }

    /**
     * Linking is a Pro feature, and the API has to say so too — a bot that
     * skipped the check would be a way around the card's gate.
     *
     * The downgrade works because subscribeOrganization() backdates every row
     * by a month, so this base row ties with the pro row from setUp on
     * started_at and wins on the id tiebreak PlanFeatures applies.
     */
    public function test_an_organization_without_the_plan_is_refused(): void
    {
        $this->subscribeOrganization($this->orgA['organization'], 'base');

        $code = $this->service->issueFor($this->orgA['pm']);

        $this->hit('/api/v1/telegram/verify', ['code' => $code, 'chat_id' => 6002])
            ->assertStatus(402);
    }

    public function test_resolve_returns_the_linked_user(): void
    {
        $user = $this->orgB['writer'];

        $this->hit('/api/v1/telegram/verify', [
            'code'    => $this->service->issueFor($user),
            'chat_id' => 7001,
        ])->assertOk();

        $this->hit('/api/v1/telegram/resolve', ['chat_id' => 7001])
            ->assertOk()
            ->assertJsonPath('data.user_id', (int) $user->id)
            ->assertJsonPath('data.organization.slug', 'api-b');
    }

    public function test_resolve_reports_an_unknown_chat_id_as_not_found(): void
    {
        $this->hit('/api/v1/telegram/resolve', ['chat_id' => 8001])->assertNotFound();
    }

    /** The endpoint is a guessing oracle, so it must not answer indefinitely. */
    public function test_verify_is_throttled(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->hit('/api/v1/telegram/verify', ['code' => 'ZZZZZZZZ', 'chat_id' => 9000 + $i])
                ->assertStatus(422);
        }

        $this->hit('/api/v1/telegram/verify', ['code' => 'ZZZZZZZZ', 'chat_id' => 9999])
            ->assertStatus(429);
    }

    /** The link endpoints must not be reachable without a signature. */
    public function test_an_unsigned_request_is_refused(): void
    {
        $this->postJson('/api/v1/telegram/verify', ['code' => 'ABCDEFGH', 'chat_id' => 1])
            ->assertUnauthorized();
    }
}
