<?php

namespace Tests\Feature\Telegram;

use App\Exceptions\TelegramLinkException;
use App\Models\User;
use App\Services\TelegramLinkService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

class TelegramLinkServiceTest extends TestCase
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

        $this->service = app(TelegramLinkService::class);
        $this->orgA    = $this->populate($this->makeOrganization('svc-a', 'Agency A'), 'A');
        $this->orgB    = $this->populate($this->makeOrganization('svc-b', 'Agency B'), 'B');
    }

    public function test_an_issued_code_uses_the_unambiguous_alphabet(): void
    {
        $code = $this->service->issueFor($this->orgA['pm']);

        $this->assertSame(TelegramLinkService::CODE_LENGTH, strlen($code));
        $this->assertSame(
            0,
            preg_match('/[^'.TelegramLinkService::ALPHABET.']/', $code),
            "issued code {$code} contains an ambiguous character"
        );
    }

    /** The plaintext must never be persisted. */
    public function test_only_the_hash_is_stored(): void
    {
        $user = $this->orgA['pm'];
        $code = $this->service->issueFor($user);

        $user->refresh();

        $this->assertNotSame($code, $user->telegram_link_code_hash);
        $this->assertSame(TelegramLinkService::hashOf($code), $user->telegram_link_code_hash);
        $this->assertTrue($user->telegram_link_code_expires_at->isFuture());
    }

    public function test_issuing_again_invalidates_the_previous_code(): void
    {
        $user  = $this->orgA['pm'];
        $first = $this->service->issueFor($user);
        $this->service->issueFor($user);

        $this->expectException(TelegramLinkException::class);
        $this->service->verify($first, 111);
    }

    /**
     * The heart of the feature: the bot is authenticated as nobody, so
     * TenantContext has no organization and the lookup must reach across every
     * agency. If OrganizationScope ever filtered this, every code in the
     * product would read as invalid.
     */
    public function test_a_code_verifies_with_no_authenticated_user(): void
    {
        $user = $this->orgB['writer'];
        $code = $this->service->issueFor($user);

        $this->assertGuest();

        $linked = $this->service->verify($code, 987654321012);

        $this->assertSame((int) $user->id, (int) $linked->id);
        $this->assertSame((int) $user->organization_id, (int) $linked->organization_id);
        $this->assertSame(987654321012, $linked->telegram_chat_id);
        $this->assertNotNull($linked->telegram_linked_at);
    }

    public function test_a_code_is_single_use(): void
    {
        $code = $this->service->issueFor($this->orgA['pm']);

        $this->service->verify($code, 222);

        $this->expectException(TelegramLinkException::class);
        $this->service->verify($code, 333);
    }

    public function test_consuming_a_code_clears_both_code_columns(): void
    {
        $user = $this->orgA['pm'];
        $code = $this->service->issueFor($user);

        $this->service->verify($code, 444);

        $fresh = TenantContext::runWithoutScope(fn () => User::find($user->id));

        $this->assertNull($fresh->telegram_link_code_hash);
        $this->assertNull($fresh->telegram_link_code_expires_at);
    }

    public function test_an_expired_code_is_refused(): void
    {
        $user = $this->orgA['pm'];
        $code = $this->service->issueFor($user);

        $user->forceFill([
            'telegram_link_code_expires_at' => now()->subMinute(),
        ])->save();

        $this->expectException(TelegramLinkException::class);
        $this->service->verify($code, 555);
    }

    /** An unknown code and an expired one must be indistinguishable. */
    public function test_unknown_and_expired_codes_report_identically(): void
    {
        $user = $this->orgA['pm'];
        $code = $this->service->issueFor($user);
        $user->forceFill(['telegram_link_code_expires_at' => now()->subMinute()])->save();

        $expiredMessage = null;
        $unknownMessage = null;

        try {
            $this->service->verify($code, 1);
        } catch (TelegramLinkException $e) {
            $expiredMessage = $e->getMessage();
        }

        try {
            $this->service->verify('ZZZZZZZZ', 1);
        } catch (TelegramLinkException $e) {
            $unknownMessage = $e->getMessage();
        }

        $this->assertNotNull($expiredMessage);
        $this->assertSame($expiredMessage, $unknownMessage);
    }

    public function test_codes_are_normalised_before_lookup(): void
    {
        $user = $this->orgA['pm'];
        $code = $this->service->issueFor($user);

        $linked = $this->service->verify(' '.strtolower($code).' ', 666);

        $this->assertSame((int) $user->id, (int) $linked->id);
    }

    public function test_a_chat_id_bound_elsewhere_is_refused(): void
    {
        $this->service->verify($this->service->issueFor($this->orgA['pm']), 777);

        $code = $this->service->issueFor($this->orgB['pm']);

        try {
            $this->service->verify($code, 777);
            $this->fail('expected the conflicting chat id to be refused');
        } catch (TelegramLinkException $e) {
            $this->assertSame(409, $e->status());
        }
    }

    /** A refused conflict must leave the original link untouched. */
    public function test_a_refused_conflict_does_not_consume_the_code(): void
    {
        $owner = $this->orgA['pm'];
        $this->service->verify($this->service->issueFor($owner), 888);

        $challenger = $this->orgB['pm'];
        $code       = $this->service->issueFor($challenger);

        try {
            $this->service->verify($code, 888);
        } catch (TelegramLinkException) {
            // expected
        }

        $freshOwner      = TenantContext::runWithoutScope(fn () => User::find($owner->id));
        $freshChallenger = TenantContext::runWithoutScope(fn () => User::find($challenger->id));

        $this->assertSame(888, $freshOwner->telegram_chat_id);
        $this->assertNull($freshChallenger->telegram_chat_id);
        $this->assertNotNull($freshChallenger->telegram_link_code_hash);
    }

    /**
     * Regression: issuing must not depend on the caller's model being fresh.
     *
     * verify() writes through the query builder, so any User held from before
     * it is stale. Issuing again through save() would compare the new expiry
     * against the stale in-memory one, find them equal to the second, skip the
     * column, and leave the row holding a hash with no expiry — a code that
     * could never lapse.
     */
    public function test_issuing_after_a_link_still_sets_a_fresh_expiry(): void
    {
        $user = $this->orgA['pm'];

        $this->service->verify($this->service->issueFor($user), 3141);

        // $user is deliberately not refreshed here: this is the stale model.
        $code = $this->service->issueFor($user);

        $row = TenantContext::runWithoutScope(fn () => User::find($user->id));

        $this->assertSame(TelegramLinkService::hashOf($code), $row->telegram_link_code_hash);
        $this->assertNotNull($row->telegram_link_code_expires_at);
        $this->assertTrue($row->telegram_link_code_expires_at->isFuture());
    }

    public function test_relinking_the_same_chat_id_to_its_own_owner_is_allowed(): void
    {
        $user = $this->orgA['pm'];
        $this->service->verify($this->service->issueFor($user), 999);

        $linked = $this->service->verify($this->service->issueFor($user), 999);

        $this->assertSame((int) $user->id, (int) $linked->id);
    }

    public function test_resolve_finds_the_user_behind_a_chat_id(): void
    {
        $user = $this->orgB['writer'];
        $this->service->verify($this->service->issueFor($user), 1234);

        $this->assertSame((int) $user->id, (int) $this->service->resolve(1234)->id);
        $this->assertNull($this->service->resolve(4321));
    }

    public function test_unlink_clears_every_telegram_column(): void
    {
        $user = $this->orgA['pm'];
        $this->service->verify($this->service->issueFor($user), 5678);

        $this->service->unlink($user);

        $fresh = TenantContext::runWithoutScope(fn () => User::find($user->id));

        $this->assertNull($fresh->telegram_chat_id);
        $this->assertNull($fresh->telegram_linked_at);
        $this->assertNull($fresh->telegram_link_code_hash);
        $this->assertFalse($fresh->hasLinkedTelegram());
    }

    /** An unlinked chat id must be free for somebody else to claim. */
    public function test_an_unlinked_chat_id_can_be_claimed_by_another_user(): void
    {
        $first = $this->orgA['pm'];
        $this->service->verify($this->service->issueFor($first), 4242);
        $this->service->unlink($first);

        $second = $this->orgB['pm'];
        $linked = $this->service->verify($this->service->issueFor($second), 4242);

        $this->assertSame((int) $second->id, (int) $linked->id);
    }
}
