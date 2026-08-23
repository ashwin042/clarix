<?php

namespace Tests\Feature\TaskBot;

use App\Exceptions\N8nTelegramLinkException;
use App\Models\N8nTelegramLink;
use App\Services\N8nTelegramLinkService;
use App\Services\TelegramLinkService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

class N8nTelegramLinkServiceTest extends TestCase
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

        $this->service = app(N8nTelegramLinkService::class);
        $this->orgA    = $this->populate($this->makeOrganization('tbs-a', 'Agency A'), 'A');
        $this->orgB    = $this->populate($this->makeOrganization('tbs-b', 'Agency B'), 'B');
    }

    public function test_an_issued_code_uses_the_unambiguous_alphabet(): void
    {
        $code = $this->service->issueCode($this->orgA['pm']);

        $this->assertSame(N8nTelegramLinkService::CODE_LENGTH, strlen($code));
        $this->assertSame(
            0,
            preg_match('/[^'.N8nTelegramLinkService::ALPHABET.']/', $code),
            "issued code {$code} contains an ambiguous character"
        );
    }

    /** The plaintext must never be persisted. */
    public function test_only_the_hash_is_stored(): void
    {
        $user = $this->orgA['pm'];
        $code = $this->service->issueCode($user);

        $link = $this->service->linkFor($user);

        $this->assertNotSame($code, $link->link_code_hash);
        $this->assertSame(N8nTelegramLinkService::hashOf($code), $link->link_code_hash);
        $this->assertTrue($link->code_expires_at->isFuture());
    }

    public function test_issuing_again_invalidates_the_previous_code(): void
    {
        $user  = $this->orgA['pm'];
        $first = $this->service->issueCode($user);
        $this->service->issueCode($user);

        $this->expectException(N8nTelegramLinkException::class);
        $this->service->verify($first, '111');
    }

    /** Issuing twice must not leave two rows, or the older one is a live code. */
    public function test_issuing_twice_updates_one_row(): void
    {
        $user = $this->orgA['pm'];

        $this->service->issueCode($user);
        $this->service->issueCode($user);

        $this->assertSame(1, N8nTelegramLink::query()->where('user_id', $user->id)->count());
    }

    /**
     * Two clicks a millisecond apart on a user with no row yet: both updates
     * match nothing, both try to insert, and user_id is unique so one of them
     * collides. The loser must end up with a usable code rather than a
     * unique-constraint 500.
     *
     * The race is made deterministic with beforeExecuting(), which slips the
     * rival's row in between this caller's update and its insert — the exact
     * interleaving the catch exists for, rather than an approximation of it.
     */
    public function test_a_race_to_create_the_first_row_still_yields_a_usable_code(): void
    {
        $user    = $this->orgA['pm'];
        $rivalIn = false;

        DB::beforeExecuting(function (string $query) use ($user, &$rivalIn) {
            if ($rivalIn || ! str_contains($query, 'insert into "n8n_telegram_links"')) {
                return;
            }

            $rivalIn = true;

            DB::table('n8n_telegram_links')->insert([
                'user_id'    => $user->id,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $code = $this->service->issueCode($user);

        $this->assertTrue($rivalIn, 'The race was never triggered, so nothing was tested.');
        $this->assertSame((int) $user->id, (int) $this->service->verify($code, '1201')->id);
        $this->assertSame(1, N8nTelegramLink::query()->where('user_id', $user->id)->count());
    }

    /**
     * The heart of the feature: the pipeline is authenticated as nobody, so
     * TenantContext has no organization and the lookup must reach across every
     * agency. If OrganizationScope ever filtered this, every code in the
     * product would read as invalid.
     */
    public function test_a_code_verifies_with_no_authenticated_user(): void
    {
        $user = $this->orgB['writer'];
        $code = $this->service->issueCode($user);

        $this->assertGuest();

        $linked = $this->service->verify($code, '987654321012');

        $this->assertSame((int) $user->id, (int) $linked->id);
        $this->assertSame((int) $user->organization_id, (int) $linked->organization_id);
        $this->assertSame('987654321012', $this->service->linkFor($user)->chat_id);
        $this->assertNotNull($this->service->linkFor($user)->linked_at);
    }

    public function test_a_code_is_single_use(): void
    {
        $code = $this->service->issueCode($this->orgA['pm']);

        $this->service->verify($code, '2001');

        $this->expectException(N8nTelegramLinkException::class);
        $this->service->verify($code, '2002');
    }

    public function test_consuming_a_code_clears_both_code_columns(): void
    {
        $user = $this->orgA['pm'];

        $this->service->verify($this->service->issueCode($user), '2003');

        $link = $this->service->linkFor($user);

        $this->assertNull($link->link_code_hash);
        $this->assertNull($link->code_expires_at);
    }

    public function test_an_expired_code_is_refused(): void
    {
        $user = $this->orgA['pm'];
        $code = $this->service->issueCode($user);

        N8nTelegramLink::query()
            ->where('user_id', $user->id)
            ->update(['code_expires_at' => now()->subMinute()]);

        $this->expectException(N8nTelegramLinkException::class);
        $this->service->verify($code, '3001');
    }

    /**
     * An unknown code and an expired one must be indistinguishable. Telling
     * them apart tells an attacker whether a guess was ever a real code, which
     * is exactly the oracle a short human-typed code cannot afford.
     */
    public function test_unknown_and_expired_codes_report_identically(): void
    {
        $user = $this->orgA['pm'];
        $code = $this->service->issueCode($user);

        N8nTelegramLink::query()
            ->where('user_id', $user->id)
            ->update(['code_expires_at' => now()->subMinute()]);

        $expired = null;
        $unknown = null;

        try {
            $this->service->verify($code, '3002');
        } catch (N8nTelegramLinkException $e) {
            $expired = [$e->getMessage(), $e->status()];
        }

        try {
            $this->service->verify('ZZZZZZZZ', '3003');
        } catch (N8nTelegramLinkException $e) {
            $unknown = [$e->getMessage(), $e->status()];
        }

        $this->assertNotNull($expired);
        $this->assertSame($expired, $unknown);
    }

    /** Spaces, dashes and lower case are what a phone keyboard adds by itself. */
    public function test_codes_are_normalised_before_lookup(): void
    {
        $code  = $this->service->issueCode($this->orgA['pm']);
        $typed = strtolower(substr($code, 0, 4)).' - '.strtolower(substr($code, 4));

        $linked = $this->service->verify($typed, '3004');

        $this->assertSame((int) $this->orgA['pm']->id, (int) $linked->id);
    }

    /**
     * Two spellings of one chat id must not become two rows — the unique index
     * compares strings, so '007' and '7' would both be accepted and only one of
     * them would ever resolve.
     */
    public function test_chat_ids_are_normalised_before_storage(): void
    {
        $this->service->verify($this->service->issueCode($this->orgA['pm']), ' 0071234 ');

        $this->assertSame('71234', $this->service->linkFor($this->orgA['pm'])->chat_id);
        $this->assertNotNull($this->service->resolve('71234'));
        $this->assertNotNull($this->service->resolve('0071234'));
    }

    public function test_a_chat_id_bound_elsewhere_is_refused(): void
    {
        $this->service->verify($this->service->issueCode($this->orgA['pm']), '4001');

        $this->expectException(N8nTelegramLinkException::class);
        $this->service->verify($this->service->issueCode($this->orgB['pm']), '4001');
    }

    /**
     * A refused conflict must leave the loser able to try again once the other
     * end is disconnected — burning their code would strand them.
     */
    public function test_a_refused_conflict_does_not_consume_the_code(): void
    {
        $this->service->verify($this->service->issueCode($this->orgA['pm']), '4002');

        $code = $this->service->issueCode($this->orgB['pm']);

        try {
            $this->service->verify($code, '4002');
            $this->fail('The conflicting link should have been refused.');
        } catch (N8nTelegramLinkException $e) {
            $this->assertSame(409, $e->status());
        }

        $this->service->unlink($this->orgA['pm']);

        $linked = $this->service->verify($code, '4002');
        $this->assertSame((int) $this->orgB['pm']->id, (int) $linked->id);
    }

    public function test_relinking_the_same_chat_id_to_its_own_owner_is_allowed(): void
    {
        $user = $this->orgA['pm'];

        $this->service->verify($this->service->issueCode($user), '4003');
        $linked = $this->service->verify($this->service->issueCode($user), '4003');

        $this->assertSame((int) $user->id, (int) $linked->id);
    }

    /** A link already made must survive somebody minting a fresh code. */
    public function test_issuing_after_a_link_leaves_the_link_intact(): void
    {
        $user = $this->orgA['pm'];

        $this->service->verify($this->service->issueCode($user), '4004');
        $this->service->issueCode($user);

        $link = $this->service->linkFor($user);

        $this->assertSame('4004', $link->chat_id);
        $this->assertTrue($link->isLive());
        $this->assertTrue($link->code_expires_at->isFuture());
    }

    public function test_resolve_finds_the_user_behind_a_chat_id(): void
    {
        $user = $this->orgB['writer'];
        $this->service->verify($this->service->issueCode($user), '5001');

        $found = $this->service->resolve('5001');

        $this->assertSame((int) $user->id, (int) $found->id);
        $this->assertSame((int) $user->organization_id, (int) $found->organization_id);
    }

    public function test_resolve_returns_null_for_an_unknown_chat_id(): void
    {
        $this->assertNull($this->service->resolve('5002'));
    }

    /**
     * The whole point of resolving live. Someone moved to another unit files
     * their next task against the unit they are actually in, with no
     * re-linking, because nothing about the unit was ever written down here.
     */
    public function test_resolve_reflects_a_unit_change_without_relinking(): void
    {
        $user = $this->orgA['pm'];
        $this->service->verify($this->service->issueCode($user), '5003');

        $moved = \App\Services\TenantContext::actingAsOrganization(
            $this->orgA['organization']->id,
            fn () => \App\Models\Unit::create(['name' => 'Moved To'])
        );

        $user->forceFill(['unit_id' => $moved->id])->save();

        $this->assertSame((int) $moved->id, (int) $this->service->resolve('5003')->unit_id);
    }

    public function test_unlink_deactivates_the_link_and_drops_any_code(): void
    {
        $user = $this->orgA['pm'];

        $this->service->verify($this->service->issueCode($user), '6001');
        $this->service->issueCode($user);
        $this->service->unlink($user);

        $link = $this->service->linkFor($user);

        $this->assertFalse($link->is_active);
        $this->assertFalse($link->isLive());
        $this->assertNull($link->link_code_hash);
        $this->assertNull($link->code_expires_at);
        $this->assertNull($this->service->resolve('6001'));
    }

    /**
     * Deactivating keeps a record of what was linked — but a dormant row must
     * not squat the Telegram account for ever. A colleague taking over a shared
     * handset has to be able to claim it.
     */
    public function test_a_dormant_link_does_not_hold_a_chat_id_hostage(): void
    {
        $this->service->verify($this->service->issueCode($this->orgA['pm']), '6002');

        $this->assertSame('6002', $this->service->linkFor($this->orgA['pm'])->chat_id);

        $this->service->unlink($this->orgA['pm']);

        $linked = $this->service->verify($this->service->issueCode($this->orgB['pm']), '6002');

        $this->assertSame((int) $this->orgB['pm']->id, (int) $linked->id);
        $this->assertNull($this->service->linkFor($this->orgA['pm'])->chat_id);
        $this->assertSame((int) $this->orgB['pm']->id, (int) $this->service->resolve('6002')->id);
    }

    public function test_unlinking_and_relinking_the_same_user_reactivates_the_row(): void
    {
        $user = $this->orgA['pm'];

        $this->service->verify($this->service->issueCode($user), '6003');
        $this->service->unlink($user);
        $this->service->verify($this->service->issueCode($user), '6003');

        $this->assertTrue($this->service->linkFor($user)->isLive());
        $this->assertSame(1, N8nTelegramLink::query()->where('user_id', $user->id)->count());
    }

    /**
     * The two integrations must not be able to see each other's links. A code
     * minted for one bot verifying against the other would let somebody connect
     * to a pipeline they were never shown a code for.
     */
    public function test_the_two_bots_share_no_state(): void
    {
        $user   = $this->orgA['pm'];
        $hermes = app(TelegramLinkService::class);

        $taskCode   = $this->service->issueCode($user);
        $hermesCode = $hermes->issueFor($user);

        // Neither service accepts the other's code.
        try {
            $this->service->verify($hermesCode, '7001');
            $this->fail('The task bot accepted an AXOKAI code.');
        } catch (N8nTelegramLinkException) {
            // expected
        }

        try {
            $hermes->verify($taskCode, 7002);
            $this->fail('AXOKAI accepted a task bot code.');
        } catch (\App\Exceptions\TelegramLinkException) {
            // expected
        }

        // Connecting one bot leaves the other unconnected.
        $this->service->verify($this->service->issueCode($user), '7003');

        $this->assertTrue($this->service->linkFor($user)->isLive());
        $this->assertFalse($user->fresh()->hasLinkedTelegram());
    }

    /** Disconnecting one bot must not disconnect the other. */
    public function test_unlinking_one_bot_leaves_the_other_connected(): void
    {
        $user   = $this->orgA['pm'];
        $hermes = app(TelegramLinkService::class);

        $hermes->verify($hermes->issueFor($user), 7004);
        $this->service->verify($this->service->issueCode($user), '7005');

        $this->service->unlink($user);

        $this->assertFalse($this->service->linkFor($user)->isLive());
        $this->assertTrue($user->fresh()->hasLinkedTelegram());
    }
}
