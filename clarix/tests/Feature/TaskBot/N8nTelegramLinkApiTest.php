<?php

namespace Tests\Feature\TaskBot;

use App\Models\OrganizationSubscription;
use App\Services\N8nTelegramLinkService;
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
class N8nTelegramLinkApiTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    protected N8nTelegramLinkService $service;

    /** @var array<string, mixed> */
    protected array $orgA;

    /** @var array<string, mixed> */
    protected array $orgB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);

        config()->set('services.n8n.key', 'test-n8n-key');

        $this->service = app(N8nTelegramLinkService::class);
        $this->orgA    = $this->populate($this->makeOrganization('tba-a', 'Agency A'), 'A');
        $this->orgB    = $this->populate($this->makeOrganization('tba-b', 'Agency B'), 'B');

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
        return $this->postJson($path, $body, ['X-N8n-Key' => 'test-n8n-key']);
    }

    public function test_a_valid_code_links_and_identifies_the_user(): void
    {
        $user = $this->orgA['pm'];
        $code = $this->service->issueCode($user);

        $this->hit('/api/v1/n8n/telegram/verify', ['code' => $code, 'chat_id' => '123456789012'])
            ->assertOk()
            ->assertExactJson([
                'user_id'         => (int) $user->id,
                'organization_id' => (int) $user->organization_id,
                'unit_id'         => (int) $user->unit_id,
            ]);
    }

    /**
     * The pipeline addresses fields by path in a visual editor, so the envelope
     * is flat rather than wrapped in 'data'. Asserted because changing it is a
     * silent break: every node downstream would read null.
     */
    public function test_the_identity_envelope_is_flat_and_narrow(): void
    {
        $code = $this->service->issueCode($this->orgA['pm']);

        $body = $this->hit('/api/v1/n8n/telegram/verify', ['code' => $code, 'chat_id' => '1'])
            ->assertOk()
            ->json();

        $this->assertSame(['user_id', 'organization_id', 'unit_id'], array_keys($body));
    }

    /** Nothing about the credential may travel to the pipeline. */
    public function test_the_response_never_carries_the_code_hash(): void
    {
        $code = $this->service->issueCode($this->orgA['pm']);

        $this->hit('/api/v1/n8n/telegram/verify', ['code' => $code, 'chat_id' => '2'])
            ->assertOk()
            ->assertJsonMissingPath('link_code_hash')
            ->assertJsonMissingPath('password');
    }

    /**
     * The regression test for the whole design. The pipeline is authenticated
     * as no user, so if OrganizationScope ever reaches this lookup, agency B's
     * codes stop working while agency A's keep working — a failure that would
     * look like a flaky bot rather than a tenancy bug.
     */
    public function test_codes_from_any_organization_verify(): void
    {
        foreach ([[$this->orgA, '1001'], [$this->orgB, '1002']] as [$org, $chatId]) {
            $code = $this->service->issueCode($org['writer']);

            $this->hit('/api/v1/n8n/telegram/verify', ['code' => $code, 'chat_id' => $chatId])
                ->assertOk()
                ->assertJsonPath('organization_id', (int) $org['organization']->id);
        }
    }

    /**
     * Cross-organization isolation, stated as the thing that must never happen:
     * a chat linked in agency A must never come back carrying agency B's ids.
     */
    public function test_a_chat_linked_in_one_agency_never_resolves_to_another(): void
    {
        $a = $this->orgA['pm'];
        $b = $this->orgB['pm'];

        $this->hit('/api/v1/n8n/telegram/verify', [
            'code' => $this->service->issueCode($a), 'chat_id' => '1101',
        ])->assertOk();

        $this->hit('/api/v1/n8n/telegram/verify', [
            'code' => $this->service->issueCode($b), 'chat_id' => '1102',
        ])->assertOk();

        $this->hit('/api/v1/n8n/telegram/resolve', ['chat_id' => '1101'])
            ->assertOk()
            ->assertJsonPath('user_id', (int) $a->id)
            ->assertJsonPath('organization_id', (int) $a->organization_id)
            ->assertJsonPath('unit_id', (int) $a->unit_id);

        $this->hit('/api/v1/n8n/telegram/resolve', ['chat_id' => '1102'])
            ->assertOk()
            ->assertJsonPath('user_id', (int) $b->id)
            ->assertJsonPath('organization_id', (int) $b->organization_id)
            ->assertJsonPath('unit_id', (int) $b->unit_id);

        $this->assertNotSame((int) $a->organization_id, (int) $b->organization_id);
    }

    /** A code minted in agency A must not link a chat already held in agency B. */
    public function test_a_chat_id_linked_in_another_agency_is_refused_with_conflict(): void
    {
        $this->hit('/api/v1/n8n/telegram/verify', [
            'code' => $this->service->issueCode($this->orgA['pm']), 'chat_id' => '4001',
        ])->assertOk();

        $this->hit('/api/v1/n8n/telegram/verify', [
            'code' => $this->service->issueCode($this->orgB['pm']), 'chat_id' => '4001',
        ])->assertStatus(409);

        // And the chat still answers for its original owner.
        $this->hit('/api/v1/n8n/telegram/resolve', ['chat_id' => '4001'])
            ->assertOk()
            ->assertJsonPath('user_id', (int) $this->orgA['pm']->id);
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        $code = $this->service->issueCode($this->orgA['pm']);

        $this->hit('/api/v1/n8n/telegram/verify', ['code' => $code, 'chat_id' => '2001'])->assertOk();

        $this->hit('/api/v1/n8n/telegram/verify', ['code' => $code, 'chat_id' => '2002'])
            ->assertStatus(422);
    }

    public function test_an_expired_code_is_refused(): void
    {
        $user = $this->orgA['pm'];
        $code = $this->service->issueCode($user);

        \App\Models\N8nTelegramLink::query()
            ->where('user_id', $user->id)
            ->update(['code_expires_at' => now()->subMinute()]);

        $this->hit('/api/v1/n8n/telegram/verify', ['code' => $code, 'chat_id' => '3001'])
            ->assertStatus(422);
    }

    public function test_a_missing_code_is_a_validation_error(): void
    {
        $this->hit('/api/v1/n8n/telegram/verify', ['chat_id' => '5001'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_a_missing_chat_id_is_a_validation_error(): void
    {
        $this->hit('/api/v1/n8n/telegram/verify', ['code' => 'ABCDEFGH'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('chat_id');
    }

    public function test_a_non_numeric_chat_id_is_a_validation_error(): void
    {
        $this->hit('/api/v1/n8n/telegram/resolve', ['chat_id' => 'not-a-chat'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('chat_id');
    }

    /**
     * Telegram sends chat_id as a JSON number and an n8n node may forward it
     * unquoted. Rejecting that would be a validation error nobody could debug
     * from the workflow editor.
     */
    public function test_a_numeric_chat_id_is_accepted_as_well_as_a_string(): void
    {
        $this->hit('/api/v1/n8n/telegram/verify', [
            'code' => $this->service->issueCode($this->orgA['pm']), 'chat_id' => 55501,
        ])->assertOk();

        $this->hit('/api/v1/n8n/telegram/resolve', ['chat_id' => 55501])
            ->assertOk()
            ->assertJsonPath('user_id', (int) $this->orgA['pm']->id);
    }

    /** Group and channel ids are negative, and the bot may be added to one. */
    public function test_a_negative_chat_id_is_accepted(): void
    {
        $this->hit('/api/v1/n8n/telegram/verify', [
            'code' => $this->service->issueCode($this->orgA['pm']), 'chat_id' => '-1001234567890',
        ])->assertOk();

        $this->hit('/api/v1/n8n/telegram/resolve', ['chat_id' => '-1001234567890'])
            ->assertOk();
    }

    public function test_resolve_returns_the_linked_user(): void
    {
        $user = $this->orgB['writer'];

        $this->hit('/api/v1/n8n/telegram/verify', [
            'code' => $this->service->issueCode($user), 'chat_id' => '7001',
        ])->assertOk();

        $this->hit('/api/v1/n8n/telegram/resolve', ['chat_id' => '7001'])
            ->assertOk()
            ->assertJsonPath('user_id', (int) $user->id)
            ->assertJsonPath('organization_id', (int) $user->organization_id);
    }

    /**
     * A writer belongs to no unit in these fixtures, and the pipeline has to be
     * told so rather than handed a zero it would file work against.
     */
    public function test_a_user_without_a_unit_resolves_with_a_null_unit_id(): void
    {
        $this->hit('/api/v1/n8n/telegram/verify', [
            'code' => $this->service->issueCode($this->orgB['writer']), 'chat_id' => '7002',
        ])->assertOk();

        $this->hit('/api/v1/n8n/telegram/resolve', ['chat_id' => '7002'])
            ->assertOk()
            ->assertJsonPath('unit_id', null);
    }

    public function test_resolve_reports_an_unknown_chat_id_as_not_found(): void
    {
        $this->hit('/api/v1/n8n/telegram/resolve', ['chat_id' => '8001'])
            ->assertNotFound()
            ->assertJsonPath('linked', false);
    }

    /** A disconnected link must stop answering, not keep filing work. */
    public function test_resolve_reports_a_disconnected_link_as_not_found(): void
    {
        $this->hit('/api/v1/n8n/telegram/verify', [
            'code' => $this->service->issueCode($this->orgA['pm']), 'chat_id' => '8002',
        ])->assertOk();

        $this->service->unlink($this->orgA['pm']);

        $this->hit('/api/v1/n8n/telegram/resolve', ['chat_id' => '8002'])->assertNotFound();
    }

    /**
     * A suspended agency's people may not link. The subscription middleware
     * cannot do this job here — it reads $request->user(), which is null on a
     * key-authenticated route, so it would wave everything through.
     */
    public function test_a_suspended_organization_is_refused_on_verify(): void
    {
        TenantContext::actingAsOrganization($this->orgA['organization']->id, function () {
            OrganizationSubscription::query()->update(['status' => 'suspended']);
        });

        $this->hit('/api/v1/n8n/telegram/verify', [
            'code' => $this->service->issueCode($this->orgA['pm']), 'chat_id' => '6001',
        ])->assertStatus(402);
    }

    /**
     * And on resolve, which is the gate that matters: checking only at link
     * time would let an agency that lapsed six months ago keep filing tasks off
     * a link it made while it was paying.
     */
    public function test_a_suspended_organization_is_refused_on_resolve(): void
    {
        $this->hit('/api/v1/n8n/telegram/verify', [
            'code' => $this->service->issueCode($this->orgA['pm']), 'chat_id' => '6002',
        ])->assertOk();

        TenantContext::actingAsOrganization($this->orgA['organization']->id, function () {
            OrganizationSubscription::query()->update(['status' => 'suspended']);
        });

        $this->hit('/api/v1/n8n/telegram/resolve', ['chat_id' => '6002'])->assertStatus(402);
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

        $this->hit('/api/v1/n8n/telegram/verify', [
            'code' => $this->service->issueCode($this->orgA['pm']), 'chat_id' => '6003',
        ])->assertStatus(402);
    }

    /** The endpoint is a guessing oracle, so it must not answer indefinitely. */
    public function test_verify_is_throttled(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->hit('/api/v1/n8n/telegram/verify', ['code' => 'ZZZZZZZZ', 'chat_id' => (string) (9000 + $i)])
                ->assertStatus(422);
        }

        $this->hit('/api/v1/n8n/telegram/verify', ['code' => 'ZZZZZZZZ', 'chat_id' => '9999'])
            ->assertStatus(429);
    }

    /** Resolve runs on every message, so its ceiling is higher but still real. */
    public function test_resolve_is_throttled(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->hit('/api/v1/n8n/telegram/resolve', ['chat_id' => (string) (10000 + $i)])
                ->assertNotFound();
        }

        $this->hit('/api/v1/n8n/telegram/resolve', ['chat_id' => '19999'])
            ->assertStatus(429);
    }

    /**
     * Separate buckets. A busy pipeline must not be able to spend the AXOKAI
     * bot's allowance — sharing a limiter name would make one integration's
     * load into the other's outage.
     */
    public function test_the_two_bots_do_not_share_a_throttle(): void
    {
        config()->set('services.hermes.key', 'hermes-key');
        config()->set('services.hermes.secret', 'hermes-secret');

        for ($i = 0; $i < 11; $i++) {
            $this->hit('/api/v1/n8n/telegram/verify', ['code' => 'ZZZZZZZZ', 'chat_id' => (string) (20000 + $i)]);
        }

        $this->hit('/api/v1/n8n/telegram/verify', ['code' => 'ZZZZZZZZ', 'chat_id' => '29999'])
            ->assertStatus(429);

        // The other bot's bucket is untouched: this gets past the throttle and
        // fails on its own signature check instead.
        $this->postJson('/api/v1/telegram/verify', ['code' => 'ZZZZZZZZ', 'chat_id' => 1])
            ->assertUnauthorized();
    }

    /** The endpoints must not be reachable without the key. */
    public function test_an_unauthenticated_request_is_refused(): void
    {
        $this->postJson('/api/v1/n8n/telegram/verify', ['code' => 'ABCDEFGH', 'chat_id' => '1'])
            ->assertUnauthorized();

        $this->postJson('/api/v1/n8n/telegram/resolve', ['chat_id' => '1'])
            ->assertUnauthorized();
    }

    /**
     * The link endpoints do not carry ResolveN8nActor, and must not: verify()
     * is how a chat becomes known, so requiring the chat to already be known
     * would make the first link impossible.
     */
    public function test_the_link_endpoints_do_not_require_a_known_chat(): void
    {
        $routes = app('router')->getRoutes();

        foreach (['api.v1.n8n.telegram.verify', 'api.v1.n8n.telegram.resolve'] as $name) {
            $this->assertNotContains(
                'n8n.actor',
                $routes->getByName($name)->gatherMiddleware(),
                "{$name} must not require an already-linked chat."
            );
        }
    }
}
