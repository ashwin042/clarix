# Telegram Account Linking Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a signed-in Clarix user mint a single-use secret code, send it to the Hermes Telegram bot, and have the bot bind its `chat_id` to that exact user and organization.

**Architecture:** Four nullable `telegram_*` columns on `users` hold a sha256 of the code, its expiry, the linked `chat_id` and the link time. `TelegramLinkService` owns issue/verify/unlink/resolve; verification runs inside `TenantContext::runWithoutScope()` because the bot authenticates as no user and the lookup must cross organizations. Two API endpoints sit behind a new `hermes` middleware (shared key + HMAC over timestamp and raw body) rather than Sanctum, deliberately, so no tenant-bound service account filters the lookup. A `ConnectTelegram` Livewire card in Settings issues and displays codes.

**Tech Stack:** Laravel 12, Livewire 3, MySQL (prod) / sqlite (test), Sanctum already present but deliberately unused here, Tailwind.

**Spec:** No separate spec document — the design was agreed in conversation on 2026-08-21 and is restated in full in the Global Constraints and per-task Context blocks below. This plan is self-contained.

## Global Constraints

- **PHP 8.2, Laravel 12.** Follow the existing file idiom: a class-level docblock that explains *why*, not *what*.
- **`telegram_*` columns are never in `$fillable`.** Like `organization_id`, they are set only by `TelegramLinkService` via `forceFill()`. A mass-assignable link column would let a crafted profile form bind someone else's Telegram.
- **Every bot-side read or write of `users` is wrapped in `TenantContext::runWithoutScope()`.** Without it `OrganizationScope` filters the lookup and every code reads as invalid. This is the single most important invariant in this plan.
- **`telegram_chat_id` is `unsignedBigInteger`,** never `integer`. Telegram IDs use up to 52 significant bits.
- **Invalid and expired codes return the identical message.** Never reveal whether a code existed.
- **The plan gate is `automation` (Pro).** Enforced on Livewire *actions* and on the API, never on `mount()` — see Task 5.
- **Tests use `Tests\Feature\Tenancy\BuildsOrganizations`, never a bare `User::factory()->create()`.** The bare factory sets neither `role` nor `organization_id` and is the cause of 5 of the 14 pre-existing baseline failures.
- **Baseline is `14 failed, 1 skipped, 926 passed`.** Recorded 2026-08-21. Any other number means this work broke something. The baseline failures are in `SettingsTest` (5), `CreditListExportTest` (6), `AuthenticationTest` (1), `RegistrationTest` (1), `CreateTaskTest` (1).
- **Commit after every task.** Conventional-commit subject lines, lowercase, matching the existing log style (`feat:`, `test:`, `content:`).
- **String collisions were checked during planning.** The card's copy reuses two phrases the suite already asserts on — `Not included in your plan` (`PlanLivewireGuardTest:77`) and `Upgrade to Pro` (`PlanGatingTest:82`) — but both of those assertions run against gated components and throwaway `/_test/*` routes, never against `/settings`, so there is no collision. Keep it that way: if the card's copy changes, re-check both.

---

### Task 1: Schema and model

**Files:**
- Create: `database/migrations/2026_08_30_000001_add_telegram_link_columns_to_users_table.php`
- Modify: `app/Models/User.php` (casts, `$hidden`, one accessor)
- Test: `tests/Feature/Telegram/TelegramColumnsTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `users.telegram_link_code_hash` (char 64, nullable, unique), `users.telegram_link_code_expires_at` (timestamp, nullable), `users.telegram_chat_id` (unsignedBigInteger, nullable, unique), `users.telegram_linked_at` (timestamp, nullable). `User::hasLinkedTelegram(): bool`. Casts: both timestamps to `datetime`, `telegram_chat_id` to `integer`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Telegram/TelegramColumnsTest.php`:

```php
<?php

namespace Tests\Feature\Telegram;

use App\Models\User;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

class TelegramColumnsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->org = $this->populate($this->makeOrganization('tg-a', 'Agency A'), 'A');
    }

    public function test_the_columns_exist(): void
    {
        foreach ([
            'telegram_link_code_hash',
            'telegram_link_code_expires_at',
            'telegram_chat_id',
            'telegram_linked_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('users', $column),
                "users.{$column} is missing"
            );
        }
    }

    public function test_a_fresh_user_is_not_linked(): void
    {
        $this->assertFalse($this->org['pm']->hasLinkedTelegram());
    }

    public function test_a_user_with_a_chat_id_is_linked(): void
    {
        $user = $this->org['pm'];
        $user->forceFill(['telegram_chat_id' => 7654321012345])->save();

        $this->assertTrue($user->fresh()->hasLinkedTelegram());
    }

    /**
     * Telegram ids run past 32 bits. A signed int column would truncate or
     * reject this, which is the classic way this integration breaks months
     * after it ships.
     */
    public function test_a_large_chat_id_round_trips_intact(): void
    {
        $user   = $this->org['pm'];
        $chatId = 7654321012345;

        $user->forceFill(['telegram_chat_id' => $chatId])->save();

        $this->assertSame($chatId, $user->fresh()->telegram_chat_id);
    }

    /**
     * The link columns must not be mass-assignable, for the same reason
     * organization_id is not: a crafted form field must not be able to bind
     * somebody else's Telegram account.
     */
    public function test_the_link_columns_are_not_mass_assignable(): void
    {
        $user = $this->org['pm'];

        $user->fill([
            'telegram_chat_id'        => 999,
            'telegram_link_code_hash' => str_repeat('a', 64),
        ]);

        $this->assertNull($user->telegram_chat_id);
        $this->assertNull($user->telegram_link_code_hash);
    }

    /** The hash must never reach a JSON payload. */
    public function test_the_code_hash_is_hidden_from_serialisation(): void
    {
        $user = $this->org['pm'];
        $user->forceFill(['telegram_link_code_hash' => str_repeat('a', 64)])->save();

        $this->assertArrayNotHasKey('telegram_link_code_hash', $user->fresh()->toArray());
    }

    /** Two users cannot hold the same Telegram account. */
    public function test_chat_id_is_unique_across_the_platform(): void
    {
        $other = $this->populate($this->makeOrganization('tg-b', 'Agency B'), 'B');

        $this->org['pm']->forceFill(['telegram_chat_id' => 555])->save();

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        TenantContext::runWithoutScope(function () use ($other) {
            $other['pm']->forceFill(['telegram_chat_id' => 555])->save();
        });
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TelegramColumnsTest`
Expected: FAIL — `users.telegram_link_code_hash is missing`.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_08_30_000001_add_telegram_link_columns_to_users_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The per-user half of the Hermes Telegram link.
 *
 * Four columns rather than a table, because there is exactly one live code and
 * one linked account per user and no history worth keeping: consuming a code
 * *is* nulling the hash, which is what makes single use a property of the
 * schema rather than of a flag somebody has to remember to check.
 *
 * Only the sha256 of the code is stored, the way personal_access_tokens stores
 * its token. The plaintext exists for as long as it takes to render the modal
 * and never afterwards, so a database leak yields nothing anybody can link
 * with. The consequence is deliberate: reopening the modal mints a fresh code,
 * because the old one cannot be read back.
 *
 * telegram_chat_id is a *big* integer. Telegram documents ids of up to 52
 * significant bits, so a 32-bit column silently truncates or rejects newer
 * accounts — the standard way this integration fails long after release. Its
 * unique index is platform-wide rather than per-organization on purpose:
 * Telegram accounts are global, so one Telegram user is one Clarix user, and
 * MySQL permits the many nulls that leaves behind.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->char('telegram_link_code_hash', 64)->nullable()->unique()->after('unit_id');
            $table->timestamp('telegram_link_code_expires_at')->nullable()->after('telegram_link_code_hash');
            $table->unsignedBigInteger('telegram_chat_id')->nullable()->unique()->after('telegram_link_code_expires_at');
            $table->timestamp('telegram_linked_at')->nullable()->after('telegram_chat_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['telegram_link_code_hash']);
            $table->dropUnique(['telegram_chat_id']);
            $table->dropColumn([
                'telegram_link_code_hash',
                'telegram_link_code_expires_at',
                'telegram_chat_id',
                'telegram_linked_at',
            ]);
        });
    }
};
```

- [ ] **Step 4: Update the User model**

In `app/Models/User.php`, add `'telegram_link_code_hash'` to `$hidden`:

```php
    protected $hidden = [
        'password',
        'remember_token',
        // Never serialised. The hash is the only stored form of a live link
        // code, and an API resource that leaked it would hand a caller the
        // means to complete somebody else's link.
        'telegram_link_code_hash',
    ];
```

Extend `casts()`:

```php
    protected function casts(): array
    {
        return [
            'email_verified_at'             => 'datetime',
            'password'                      => 'hashed',
            'telegram_link_code_expires_at' => 'datetime',
            'telegram_linked_at'            => 'datetime',
            'telegram_chat_id'              => 'integer',
        ];
    }
```

Add this method next to the other role predicates:

```php
    /**
     * Whether this person has bound a Telegram account to Clarix.
     *
     * Keyed on the chat id rather than on telegram_linked_at, because the chat
     * id is the thing Hermes actually resolves against; a timestamp without
     * one would be a link that cannot be used.
     */
    public function hasLinkedTelegram(): bool
    {
        return $this->telegram_chat_id !== null;
    }
```

Leave `$fillable` untouched — that is what the mass-assignment test pins.

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=TelegramColumnsTest`
Expected: PASS, 7 tests.

- [ ] **Step 6: Confirm nothing else moved**

Run: `php artisan test`
Expected: `14 failed, 1 skipped, 926 passed` plus the 7 new passes — that is `933 passed`, still 14 failed.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_30_000001_add_telegram_link_columns_to_users_table.php app/Models/User.php tests/Feature/Telegram/TelegramColumnsTest.php
git commit -m "feat: telegram link columns on users, hashed and single-use by shape"
```

---

### Task 2: TelegramLinkService

**Files:**
- Create: `app/Exceptions/TelegramLinkException.php`
- Create: `app/Services/TelegramLinkService.php`
- Test: `tests/Feature/Telegram/TelegramLinkServiceTest.php`

**Interfaces:**
- Consumes: the columns and `User::hasLinkedTelegram()` from Task 1.
- Produces:
  - `TelegramLinkService::ALPHABET` (string, 31 chars), `::CODE_LENGTH` (int 8), `::TTL_MINUTES` (int 15)
  - `TelegramLinkService::normalize(string $code): string` (static)
  - `TelegramLinkService::hashOf(string $code): string` (static)
  - `TelegramLinkService->issueFor(User $user): string` — returns plaintext once
  - `TelegramLinkService->verify(string $code, int $chatId): User` — throws `TelegramLinkException`
  - `TelegramLinkService->resolve(int $chatId): ?User`
  - `TelegramLinkService->unlink(User $user): void`
  - `TelegramLinkException::invalidCode(): self`, `::chatAlreadyLinked(): self`, `->status(): int` (422 / 409)

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Telegram/TelegramLinkServiceTest.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TelegramLinkServiceTest`
Expected: FAIL — `Class "App\Services\TelegramLinkService" does not exist`.

- [ ] **Step 3: Write the exception**

Create `app/Exceptions/TelegramLinkException.php`:

```php
<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The two ways linking a Telegram account can be refused.
 *
 * Modelled as one exception with a status rather than two classes because the
 * controller's only job is to turn a refusal into a status code, and a single
 * type keeps the catch site from growing a branch per outcome.
 *
 * invalidCode() deliberately covers three distinct facts — no such code, the
 * code expired, the code was already used — behind one sentence. Telling them
 * apart would tell an attacker whether a guess had ever been a real code,
 * which is exactly the oracle a short human-typed code cannot afford.
 */
class TelegramLinkException extends RuntimeException
{
    protected function __construct(string $message, protected int $status)
    {
        parent::__construct($message);
    }

    public static function invalidCode(): self
    {
        return new self('That code is not valid. It may have expired or already been used.', 422);
    }

    public static function chatAlreadyLinked(): self
    {
        return new self('This Telegram account is already linked to another Clarix user. Disconnect it there first.', 409);
    }

    public function status(): int
    {
        return $this->status;
    }
}
```

- [ ] **Step 4: Write the service**

Create `app/Services/TelegramLinkService.php`:

```php
<?php

namespace App\Services;

use App\Exceptions\TelegramLinkException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Issues, verifies and revokes the codes that bind a Telegram account to a
 * Clarix user.
 *
 * Every method that the bot reaches runs inside runWithoutScope(), and that is
 * not an optimisation — it is the feature. Hermes authenticates as no user at
 * all (see EnsureHermesRequest), so TenantContext has no organization to
 * report, and a scoped query would confine the lookup to nobody. Authenticating
 * the bot as a Sanctum service account instead, the way the task API does,
 * would be worse: the token resolves to a real user in one agency, so the
 * lookup would be silently filtered to that agency and every code from every
 * other agency would read as invalid, with no error anywhere to explain it.
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
     * reopens the modal invalidates the code they were shown before.
     */
    public function issueFor(User $user): string
    {
        $code = $this->generateCode();

        $user->forceFill([
            'telegram_link_code_hash'       => self::hashOf($code),
            'telegram_link_code_expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ])->save();

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
     * The chat id is freed rather than remembered, so the same Telegram
     * account can afterwards be claimed by anybody — including a colleague
     * taking over a shared handset.
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
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=TelegramLinkServiceTest`
Expected: PASS, 15 tests.

- [ ] **Step 6: Commit**

```bash
git add app/Exceptions/TelegramLinkException.php app/Services/TelegramLinkService.php tests/Feature/Telegram/TelegramLinkServiceTest.php
git commit -m "feat: issue, verify and revoke telegram link codes"
```

---

### Task 3: The `hermes` middleware, config and rate limiter

**Files:**
- Create: `app/Http/Middleware/EnsureHermesRequest.php`
- Modify: `config/services.php` (append a `hermes` block)
- Modify: `bootstrap/app.php` (alias `hermes`)
- Modify: `app/Providers/AppServiceProvider.php` (named rate limiters)
- Modify: `.env.example` (three keys)
- Test: `tests/Feature/Telegram/HermesAuthTest.php`

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: middleware alias `hermes`; rate limiters named `hermes-verify` (10/min per IP) and `hermes-resolve` (60/min per IP); config keys `services.hermes.key`, `services.hermes.secret`, `services.hermes.bot_username`. Request headers: `X-Hermes-Key`, `X-Hermes-Timestamp` (unix seconds), `X-Hermes-Signature` (hex hmac-sha256 of `"{timestamp}.{rawBody}"`).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Telegram/HermesAuthTest.php`. It registers its own throwaway route so this task stands on its own — the middleware is what is under test, not any particular endpoint, and the real routes arrive in Task 4.

```php
<?php

namespace Tests\Feature\Telegram;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The bot's half of the handshake.
 *
 * Tested against a route defined here rather than against a real endpoint, so
 * that a failure can only mean the middleware: there is no controller, no
 * validation and no database behind it to produce a status of their own.
 */
class HermesAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.hermes.key', 'test-key');
        config()->set('services.hermes.secret', 'test-secret');

        Route::middleware('hermes')->post('/test-hermes', fn () => response()->json(['ok' => true]));
    }

    /**
     * Headers for a correctly signed request, before any override is applied.
     *
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function signed(string $payload, array $overrides = []): array
    {
        $timestamp = (string) now()->getTimestamp();

        return array_merge([
            'X-Hermes-Key'       => 'test-key',
            'X-Hermes-Timestamp' => $timestamp,
            'X-Hermes-Signature' => hash_hmac('sha256', $timestamp.'.'.$payload, 'test-secret'),
            'Content-Type'       => 'application/json',
            'Accept'             => 'application/json',
        ], $overrides);
    }

    /**
     * Send a request whose signature covers $signedBody but whose wire body is
     * $sentBody. They differ only in the tampering test.
     *
     * @param  array<string, string>  $overrides
     */
    private function hit(array $signedBody, array $overrides = [], ?array $sentBody = null)
    {
        $signedPayload = json_encode($signedBody);
        $sentPayload   = $sentBody === null ? $signedPayload : json_encode($sentBody);

        return $this->call(
            'POST',
            '/test-hermes',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($this->signed($signedPayload, $overrides)),
            $sentPayload
        );
    }

    public function test_a_correctly_signed_request_passes(): void
    {
        $this->hit(['chat_id' => 12345])
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_a_wrong_key_is_refused(): void
    {
        $this->hit(['chat_id' => 12345], ['X-Hermes-Key' => 'nope'])
            ->assertUnauthorized();
    }

    public function test_a_wrong_signature_is_refused(): void
    {
        $this->hit(['chat_id' => 12345], ['X-Hermes-Signature' => str_repeat('a', 64)])
            ->assertUnauthorized();
    }

    public function test_a_missing_signature_is_refused(): void
    {
        $this->hit(['chat_id' => 12345], ['X-Hermes-Signature' => ''])
            ->assertUnauthorized();
    }

    /** A captured request must not stay replayable for ever. */
    public function test_a_stale_timestamp_is_refused(): void
    {
        $payload = json_encode(['chat_id' => 12345]);
        $stale   = (string) now()->subMinutes(10)->getTimestamp();

        $this->hit(['chat_id' => 12345], [
            'X-Hermes-Timestamp' => $stale,
            'X-Hermes-Signature' => hash_hmac('sha256', $stale.'.'.$payload, 'test-secret'),
        ])->assertUnauthorized();
    }

    public function test_a_non_numeric_timestamp_is_refused(): void
    {
        $this->hit(['chat_id' => 12345], ['X-Hermes-Timestamp' => 'not-a-time'])
            ->assertUnauthorized();
    }

    /**
     * Signing one body and sending another must not pass — otherwise the key
     * alone would authorise any body at all.
     */
    public function test_a_tampered_body_is_refused(): void
    {
        $this->hit(['chat_id' => 12345], [], ['chat_id' => 99999])
            ->assertUnauthorized();
    }

    /**
     * An unconfigured deploy must reject everything rather than accept
     * everything. Failing open here would publish an unauthenticated endpoint
     * that hands out user identities.
     */
    public function test_an_unconfigured_secret_refuses_every_request(): void
    {
        config()->set('services.hermes.key', null);
        config()->set('services.hermes.secret', null);

        $this->hit(['chat_id' => 12345])->assertUnauthorized();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=HermesAuthTest`
Expected: FAIL — `Target class [hermes] does not exist`, because the alias is not registered yet.

- [ ] **Step 3: Add the config block**

Append to `config/services.php` before the closing `];`:

```php
    /*
    |--------------------------------------------------------------------------
    | Hermes (Telegram bot)
    |--------------------------------------------------------------------------
    |
    | The bot that links Telegram accounts to Clarix users. Unlike the task API,
    | Hermes does not authenticate as a user: it presents a static key and signs
    | each request, and the link code inside the body is what identifies the
    | person. See EnsureHermesRequest for why.
    |
    | Both key and secret are required in any environment where the endpoints
    | are reachable; the middleware refuses every request while either is unset,
    | so a half-configured deploy is closed rather than open.
    |
    */
    'hermes' => [
        'key'          => env('HERMES_API_KEY'),
        'secret'       => env('HERMES_SIGNING_SECRET'),

        // Only used to build the t.me deep link shown in the connect card.
        'bot_username' => env('TELEGRAM_BOT_USERNAME', 'ClarixHermesBot'),
    ],
```

Append to `.env.example`:

```
HERMES_API_KEY=
HERMES_SIGNING_SECRET=
TELEGRAM_BOT_USERNAME=ClarixHermesBot
```

- [ ] **Step 4: Write the middleware**

Create `app/Http/Middleware/EnsureHermesRequest.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the Hermes bot, and deliberately authenticates no user.
 *
 * This is the one endpoint group in Clarix that must not go through Sanctum.
 * Every Sanctum token resolves to a real user row, and that user's organization
 * is what TenantContext reports — which would confine the link-code lookup to
 * a single agency and make every other agency's code read as invalid. Leaving
 * the request unauthenticated is what keeps TenantContext null, and null means
 * "do not filter", which is exactly what a platform-wide bot needs.
 *
 * The trade is that the endpoint has no user to derive authority from, so the
 * authority has to be in the request itself:
 *
 *   X-Hermes-Key        names the caller
 *   X-Hermes-Timestamp  bounds how long a captured request stays usable
 *   X-Hermes-Signature  hmac-sha256 over "{timestamp}.{raw body}"
 *
 * Signing the body as well as the timestamp is what stops a captured request
 * being edited into a different one — without it, the key alone would authorise
 * any body at all.
 *
 * No nonce store, on purpose. Replaying a verify call after the code is burned
 * simply fails, and replaying a resolve call reveals nothing the original
 * caller did not already have; the timestamp window covers the rest, and a
 * nonce table would be a write on every bot message for no gain.
 */
class EnsureHermesRequest
{
    /** How far out of date a signed request may be, in seconds. */
    protected const TOLERANCE = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $key    = (string) config('services.hermes.key');
        $secret = (string) config('services.hermes.secret');

        // Fail closed. An environment that has not been given credentials must
        // reject every caller rather than accept every caller — the opposite
        // mistake publishes an open endpoint that hands out user identities.
        if ($key === '' || $secret === '') {
            return $this->refuse();
        }

        $presented = (string) $request->header('X-Hermes-Key', '');

        if (! hash_equals($key, $presented)) {
            return $this->refuse();
        }

        $timestamp = (string) $request->header('X-Hermes-Timestamp', '');

        if (! ctype_digit($timestamp)
            || abs(time() - (int) $timestamp) > self::TOLERANCE) {
            return $this->refuse();
        }

        $expected  = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);
        $signature = (string) $request->header('X-Hermes-Signature', '');

        if (! hash_equals($expected, $signature)) {
            return $this->refuse();
        }

        return $next($request);
    }

    /**
     * One shape for every refusal. Which check failed is not the caller's
     * business, and telling them would help an attacker tune a forgery.
     */
    protected function refuse(): Response
    {
        return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
    }
}
```

- [ ] **Step 5: Register the alias**

In `bootstrap/app.php`, inside the `$middleware->alias([...])` array, after the `'ability'` entry:

```php
            // The Telegram bot's own handshake. Deliberately not Sanctum —
            // see EnsureHermesRequest for why a user-authenticated token would
            // break the cross-organization code lookup.
            'hermes'       => \App\Http\Middleware\EnsureHermesRequest::class,
```

- [ ] **Step 6: Register the rate limiters**

In `app/Providers/AppServiceProvider.php`, add `use Illuminate\Cache\RateLimiting\Limit;`, `use Illuminate\Http\Request;` and `use Illuminate\Support\Facades\RateLimiter;` to the imports, then call a new method from `boot()`:

```php
    public function boot(): void
    {
        Paginator::useTailwind();

        $this->registerRateLimiters();

         if (config('app.env') === 'production') {
            URL::forceScheme('https');

            $this->assertRealObjectStorage();
        }
    }

    /**
     * Throttles for the Hermes endpoints.
     *
     * Named here because the api middleware group carries no throttle at all —
     * the task API is protected by needing a bearer token, and nothing else in
     * routes/api.php has ever needed a limit. The link endpoint is different in
     * kind: it answers "is this code real", which makes it a guessing oracle,
     * and an eight-character code is only safe behind a limit.
     *
     * Keyed on IP rather than on the key, because every Hermes request carries
     * the same key by definition — keying on it would be one global bucket that
     * a single noisy caller could exhaust for everyone.
     */
    protected function registerRateLimiters(): void
    {
        RateLimiter::for('hermes-verify', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        // Resolve is not a guessing oracle — the caller already holds the chat
        // id — so it is limited only to bound abuse, not to protect a secret.
        RateLimiter::for('hermes-resolve', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
    }
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `php artisan test --filter=HermesAuthTest`
Expected: PASS, 8 tests.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Middleware/EnsureHermesRequest.php config/services.php bootstrap/app.php app/Providers/AppServiceProvider.php .env.example tests/Feature/Telegram/HermesAuthTest.php
git commit -m "feat: authenticate the hermes bot by signed request rather than sanctum"
```

---

### Task 4: The API endpoints

**Files:**
- Create: `app/Http/Controllers/Api/TelegramLinkController.php`
- Create: `app/Http/Resources/TelegramIdentityResource.php`
- Create: `app/Http/Requests/Api/VerifyTelegramLinkRequest.php`
- Create: `app/Http/Requests/Api/ResolveTelegramChatRequest.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Telegram/TelegramLinkApiTest.php`

**Interfaces:**
- Consumes: `TelegramLinkService` and `TelegramLinkException` (Task 2), the `hermes` alias and both limiters (Task 3).
- Produces: `POST /api/v1/telegram/verify` (`code`, `chat_id`) and `POST /api/v1/telegram/resolve` (`chat_id`), both returning a `TelegramIdentityResource` envelope.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Telegram/TelegramLinkApiTest.php`:

```php
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

    /** The link endpoints must not be reachable with a Sanctum token. */
    public function test_an_unsigned_request_is_refused(): void
    {
        $this->postJson('/api/v1/telegram/verify', ['code' => 'ABCDEFGH', 'chat_id' => 1])
            ->assertUnauthorized();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TelegramLinkApiTest`
Expected: FAIL — 404 on every request, the routes do not exist.

- [ ] **Step 3: Write the form requests**

Create `app/Http/Requests/Api/VerifyTelegramLinkRequest.php`:

```php
<?php

namespace App\Http\Requests\Api;

use App\Services\TelegramLinkService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorisation is the middleware's job here, not this class's: the caller is
 * the bot, already proven by signature, and the code inside the body is what
 * names the person. So this validates shape only.
 */
class VerifyTelegramLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Length is checked after normalisation, so a code typed with a
            // dash or a stray space is not rejected before it is cleaned up.
            'code' => ['required', 'string', 'max:64'],

            // Telegram ids exceed 32 bits, so this is bounded as a big integer
            // rather than left to PHP's default integer rule.
            'chat_id' => ['required', 'integer', 'min:1', 'max:9223372036854775807'],
        ];
    }

    public function code(): string
    {
        return TelegramLinkService::normalize((string) $this->input('code'));
    }

    public function chatId(): int
    {
        return (int) $this->input('chat_id');
    }
}
```

Create `app/Http/Requests/Api/ResolveTelegramChatRequest.php`:

```php
<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ResolveTelegramChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'chat_id' => ['required', 'integer', 'min:1', 'max:9223372036854775807'],
        ];
    }

    public function chatId(): int
    {
        return (int) $this->input('chat_id');
    }
}
```

- [ ] **Step 4: Write the resource**

Create `app/Http/Resources/TelegramIdentityResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Who a Telegram chat belongs to, as Hermes needs to know it.
 *
 * Deliberately narrow. The bot needs enough to address the person and to scope
 * what it shows them — an id, a name, a role, an agency, a unit — and nothing
 * more. Serialising the whole user would put the password hash's neighbours,
 * the link-code hash and every future column on the wire by default, so the
 * fields are listed rather than inherited.
 *
 * @mixin \App\Models\User
 */
class TelegramIdentityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => (int) $this->id,
            'name'    => $this->name,
            'email'   => $this->email,
            'role'    => $this->role,

            'organization' => [
                'id'   => (int) $this->organization_id,
                'name' => $this->organization?->name,
                'slug' => $this->organization?->slug,
            ],

            'unit' => $this->unit_id === null ? null : [
                'id'   => (int) $this->unit_id,
                'name' => $this->unit?->name,
            ],

            'chat_id'   => $this->telegram_chat_id,
            'linked_at' => $this->telegram_linked_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 5: Write the controller**

Create `app/Http/Controllers/Api/TelegramLinkController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\TelegramLinkException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ResolveTelegramChatRequest;
use App\Http\Requests\Api\VerifyTelegramLinkRequest;
use App\Http\Resources\TelegramIdentityResource;
use App\Models\OrganizationSubscription;
use App\Models\User;
use App\Services\TelegramLinkService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;

/**
 * What Hermes calls: "whose code is this" and "whose chat is this".
 *
 * The commercial checks live here rather than in middleware, and that is
 * forced by the route's own design. EnsureSubscriptionActive and
 * EnsurePlanIncludes both read $request->user(), which is null on a
 * bot-authenticated route — attached to this group they would wave every
 * request through while appearing to guard it. So both questions are asked
 * once the code has resolved to a person, against *that* person's agency.
 */
class TelegramLinkController extends Controller
{
    public function __construct(protected TelegramLinkService $links)
    {
    }

    /**
     * Bind a chat id to whoever holds the code, and burn the code.
     */
    public function verify(VerifyTelegramLinkRequest $request): JsonResponse
    {
        try {
            $user = $this->links->verify($request->code(), $request->chatId());
        } catch (TelegramLinkException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status());
        }

        if (($refusal = $this->commercialRefusalFor($user)) !== null) {
            return $refusal;
        }

        return $this->identity($user);
    }

    /**
     * Who owns a chat id. What the bot asks on every later message.
     */
    public function resolve(ResolveTelegramChatRequest $request): JsonResponse
    {
        $user = $this->links->resolve($request->chatId());

        if ($user === null) {
            return response()->json(['message' => 'No Clarix user is linked to that chat.'], JsonResponse::HTTP_NOT_FOUND);
        }

        if (($refusal = $this->commercialRefusalFor($user)) !== null) {
            return $refusal;
        }

        return $this->identity($user);
    }

    /**
     * The relations are loaded unscoped for the same reason the lookup is: no
     * user is authenticated, and organization is not a tenant-scoped read this
     * request could otherwise perform.
     */
    protected function identity(User $user): JsonResponse
    {
        TenantContext::runWithoutScope(fn () => $user->loadMissing(['organization', 'unit']));

        return (new TelegramIdentityResource($user))->response();
    }

    /**
     * Both commercial gates, asked of the resolved person's agency.
     *
     * A suspended agency's integrations stop, exactly as the task API's do; and
     * linking is part of what 'automation' buys, so an agency below Pro is
     * refused here as well as in the card. Refusing in only one of the two
     * places would make the bot a way around the other.
     */
    protected function commercialRefusalFor(User $user): ?JsonResponse
    {
        $subscription = TenantContext::actingAsOrganization(
            $user->organization_id === null ? null : (int) $user->organization_id,
            fn () => OrganizationSubscription::query()->latest('started_at')->first()
        );

        if ($subscription !== null && $subscription->isSuspended()) {
            return response()->json([
                'message' => 'This organization\'s subscription is suspended.',
            ], JsonResponse::HTTP_PAYMENT_REQUIRED);
        }

        if (! $user->planAllows('automation')) {
            return response()->json([
                'message' => 'Telegram linking is not included in this organization\'s plan.',
            ], JsonResponse::HTTP_PAYMENT_REQUIRED);
        }

        return null;
    }
}
```

- [ ] **Step 6: Register the routes**

Append to `routes/api.php`, after the existing `Route::middleware([...])` group:

```php
/*
|--------------------------------------------------------------------------
| Hermes (Telegram) routes
|--------------------------------------------------------------------------
|
| A separate group because it authenticates a different kind of caller. The
| group above authenticates *as a user*, through Sanctum, which is what gives
| TenantContext an organization to scope by. This one deliberately does not:
| the whole point of the link endpoint is to find a user across every agency,
| and a token that resolved to one agency's service account would silently
| confine that search to it — see EnsureHermesRequest and TelegramLinkService.
|
| 'subscription' is absent for the same reason: it reads $request->user(),
| which is null here, so it would pass everything through while looking like a
| guard. The controller asks the question itself, of the person the code
| resolves to.
|
| The throttles are not optional. Nothing else in this file has one — the task
| endpoints are guarded by needing a bearer token — but verify answers "is this
| code real", and an eight-character code needs a limit in front of it.
|
*/
Route::middleware('hermes')->prefix('v1/telegram')->name('api.v1.telegram.')->group(function () {
    Route::post('/verify', [TelegramLinkController::class, 'verify'])
        ->middleware('throttle:hermes-verify')
        ->name('verify');

    Route::post('/resolve', [TelegramLinkController::class, 'resolve'])
        ->middleware('throttle:hermes-resolve')
        ->name('resolve');
});
```

Add the import at the top of `routes/api.php`:

```php
use App\Http\Controllers\Api\TelegramLinkController;
```

- [ ] **Step 7: Run both API test files**

Run: `php artisan test --filter="TelegramLinkApiTest|HermesAuthTest"`
Expected: PASS — 14 in `TelegramLinkApiTest`, 8 in `HermesAuthTest`.

If `test_an_organization_without_the_plan_is_refused` comes back 200, the downgrade did not take: `subscribeOrganization()` calls `PlanFeatures::flush()`, and without that flush the request-lifetime memo still holds 'pro'.

- [ ] **Step 8: Verify the routes are shaped as intended**

Run: `php artisan route:list --path=api/v1/telegram --json`
Expected: both routes list middleware `["api", "App\\Http\\Middleware\\EnsureHermesRequest", "throttle:hermes-verify"]` (and `hermes-resolve`) — and **no** `Authenticate:sanctum` and **no** `EnsureSubscriptionActive`.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Api/TelegramLinkController.php app/Http/Resources/TelegramIdentityResource.php app/Http/Requests/Api/ routes/api.php tests/Feature/Telegram/TelegramLinkApiTest.php
git commit -m "feat: telegram verify and resolve endpoints for the hermes bot"
```

---

### Task 5: The Connect Telegram card

**Files:**
- Create: `app/Livewire/Profile/ConnectTelegram.php`
- Create: `resources/views/livewire/profile/connect-telegram.blade.php`
- Modify: `resources/views/settings.blade.php` (one new section)
- Modify: `app/Livewire/AI/McpPlugins.php` (Telegram blurb points at Settings)
- Test: `tests/Feature/Telegram/ConnectTelegramTest.php`

**Interfaces:**
- Consumes: `TelegramLinkService::issueFor()`, `->unlink()`, `::TTL_MINUTES` (Task 2); `services.hermes.bot_username` (Task 3).
- Produces: Livewire component `profile.connect-telegram` with public `?string $code`, `?string $expiresAt`, and actions `generate()` and `disconnect()`.

**Context — read before writing this task.** Every plan-gated component in Clarix today (`AttendancePage`, `Chatbot`, `McpPlugins`, …) calls `assertPlanIncludes()` in `mount()`, because each one owns a whole plan-gated route. This card does not: it is embedded in `/settings`, a page every user must reach to change their password or close their account. Aborting on mount would 402 the entire settings page for every Base and Standard agency and break the three passing tests in `tests/Feature/Profile/SettingsRouteTest.php`. So the gate moves to the actions, and the card renders a locked state instead. This follows `ProfileOverview`, whose docblock already sets the precedent: *"The page itself is never refused… a section the viewer may not read is replaced with a short note rather than a 403."*

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Telegram/ConnectTelegramTest.php`:

```php
<?php

namespace Tests\Feature\Telegram;

use App\Livewire\Profile\ConnectTelegram;
use App\Models\User;
use App\Services\TelegramLinkService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

class ConnectTelegramTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);

        config()->set('services.hermes.bot_username', 'ClarixHermesBot');

        $this->org = $this->populate($this->makeOrganization('card-a', 'Agency A'), 'A');
        $this->subscribeOrganization($this->org['organization'], 'pro');
    }

    public function test_generating_shows_a_code_and_stores_only_its_hash(): void
    {
        $user = $this->org['pm'];

        $component = Livewire::actingAs($user)->test(ConnectTelegram::class)
            ->call('generate');

        $code = $component->get('code');

        $this->assertNotNull($code);
        $this->assertSame(TelegramLinkService::CODE_LENGTH, strlen($code));

        $user->refresh();
        $this->assertSame(TelegramLinkService::hashOf($code), $user->telegram_link_code_hash);
        $this->assertNotSame($code, $user->telegram_link_code_hash);
    }

    public function test_the_card_shows_the_bot_deep_link(): void
    {
        Livewire::actingAs($this->org['pm'])->test(ConnectTelegram::class)
            ->call('generate')
            ->assertSee('https://t.me/ClarixHermesBot?start=', false);
    }

    public function test_generating_again_replaces_the_previous_code(): void
    {
        $user = $this->org['pm'];

        $component = Livewire::actingAs($user)->test(ConnectTelegram::class);

        $first = $component->call('generate')->get('code');
        $second = $component->call('generate')->get('code');

        $this->assertNotSame($first, $second);
        $this->assertSame(TelegramLinkService::hashOf($second), $user->fresh()->telegram_link_code_hash);
    }

    public function test_a_linked_user_sees_the_connected_state(): void
    {
        $user = $this->org['pm'];
        app(TelegramLinkService::class)->verify(
            app(TelegramLinkService::class)->issueFor($user),
            5150
        );

        Livewire::actingAs($user->fresh())->test(ConnectTelegram::class)
            ->assertSee('Connected')
            ->assertSee('Disconnect');
    }

    public function test_disconnecting_clears_the_link(): void
    {
        $user    = $this->org['pm'];
        $service = app(TelegramLinkService::class);
        $service->verify($service->issueFor($user), 5151);

        Livewire::actingAs($user->fresh())->test(ConnectTelegram::class)
            ->call('disconnect');

        $fresh = TenantContext::runWithoutScope(fn () => User::find($user->id));

        $this->assertNull($fresh->telegram_chat_id);
        $this->assertFalse($fresh->hasLinkedTelegram());
    }

    /**
     * The card is embedded in a page every user must reach, so a plan it does
     * not cover has to read as a locked panel rather than a 402 over the whole
     * of /settings.
     */
    public function test_a_base_plan_sees_a_locked_card_rather_than_a_refusal(): void
    {
        $base = $this->populate($this->makeOrganization('card-b', 'Base Co'), 'B');
        $this->subscribeOrganization($base['organization'], 'base');

        Livewire::actingAs($base['pm'])->test(ConnectTelegram::class)
            ->assertOk()
            ->assertSee('Upgrade to Pro')
            ->assertDontSee('Generate code');
    }

    /**
     * The locked state is presentation. A crafted POST to /livewire/update
     * skips the route stack entirely, so the action itself must refuse.
     *
     * Asserted on "in your Base plan" rather than on the whole refusal
     * sentence for two reasons. The sentence EnsurePlanIncludes builds
     * contains "isn't", which Blade escapes to &#039; — assertSee would look
     * for a straight apostrophe and never find it. And the locked card's own
     * copy already says "Upgrade to Pro", so asserting on that would pass even
     * if the refusal were never set; naming the plan is what distinguishes the
     * two.
     */
    public function test_a_base_plan_cannot_generate_a_code_by_calling_the_action(): void
    {
        $base = $this->populate($this->makeOrganization('card-c', 'Base Two'), 'C');
        $this->subscribeOrganization($base['organization'], 'base');

        Livewire::actingAs($base['pm'])->test(ConnectTelegram::class)
            ->call('generate')
            ->assertSee('in your Base plan');

        $this->assertNull($base['pm']->fresh()->telegram_link_code_hash);
    }

    public function test_a_base_plan_cannot_disconnect_by_calling_the_action(): void
    {
        $base    = $this->populate($this->makeOrganization('card-d', 'Base Three'), 'D');
        $service = app(TelegramLinkService::class);

        $this->subscribeOrganization($base['organization'], 'pro');
        $service->verify($service->issueFor($base['pm']), 5152);

        // Backdated identically by the helper, so this ties with the pro row on
        // started_at and wins on the id tiebreak — the agency is now Base.
        $this->subscribeOrganization($base['organization'], 'base');

        Livewire::actingAs($base['pm']->fresh())->test(ConnectTelegram::class)
            ->call('disconnect')
            ->assertSee('in your Base plan');

        $fresh = TenantContext::runWithoutScope(fn () => User::find($base['pm']->id));
        $this->assertSame(5152, $fresh->telegram_chat_id);
    }

    /** Minting codes must not be an unbounded operation either. */
    public function test_code_generation_is_rate_limited(): void
    {
        $component = Livewire::actingAs($this->org['pm'])->test(ConnectTelegram::class);

        for ($i = 0; $i < 5; $i++) {
            $component->call('generate');
        }

        $component->call('generate')->assertSee('Too many codes');
    }

    /** The settings page must stay reachable for every plan. */
    public function test_the_settings_page_still_renders_for_a_base_plan(): void
    {
        $base = $this->populate($this->makeOrganization('card-e', 'Base Four'), 'E');
        $this->subscribeOrganization($base['organization'], 'base');

        $this->actingAs($base['pm'])
            ->get('/settings')
            ->assertOk()
            ->assertSee('Danger Zone')
            ->assertSee('Telegram');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ConnectTelegramTest`
Expected: FAIL — `Class "App\Livewire\Profile\ConnectTelegram" does not exist`.

- [ ] **Step 3: Write the component**

Create `app/Livewire/Profile/ConnectTelegram.php`:

```php
<?php

namespace App\Livewire\Profile;

use App\Http\Middleware\EnsurePlanIncludes;
use App\Services\TelegramLinkService;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * "Connect Telegram", in the Settings page.
 *
 * Unlike every other plan-gated component in the app, this one does not abort
 * in mount(). Those each own a whole gated route; this one is embedded in
 * /settings, which is where a user changes their password and closes their
 * account. Aborting here would 402 the entire page for every agency below Pro
 * and lock them out of their own account controls over a feature they never
 * asked for. So the plan decides what the card *renders*, and the actions
 * refuse on their own behalf — which is also what keeps a crafted POST to
 * /livewire/update from being a way round the lock.
 *
 * ProfileOverview settled this shape already: the page is never refused, and a
 * section the viewer may not use is replaced with a note rather than an error.
 *
 * The plaintext code lives in a public property for as long as it is on screen,
 * so it travels in Livewire's component payload. That is the same trust
 * boundary as rendering it into the HTML — it goes to the browser either way —
 * but it is worth knowing that the code exists somewhere other than the user's
 * eyes for those fifteen minutes.
 */
class ConnectTelegram extends Component
{
    /** The plaintext code, readable only until the page is left. */
    public ?string $code = null;

    /** When the shown code lapses, for the countdown line. */
    public ?string $expiresAt = null;

    /** Set when an action refuses, so the card can say why. */
    public ?string $refusal = null;

    /** Codes one user may mint per quarter hour. */
    protected const CODES_PER_WINDOW = 5;

    public function generate(TelegramLinkService $links): void
    {
        if (! $this->planAllows()) {
            return;
        }

        $key = 'telegram-code|'.auth()->id();

        // Minting is cheap but not free: each code invalidates the last, so an
        // unbounded loop is a way to keep a user permanently unable to finish
        // linking, and it writes to the users table every time.
        if (RateLimiter::tooManyAttempts($key, self::CODES_PER_WINDOW)) {
            $this->refusal = 'Too many codes requested. Try again in '
                .ceil(RateLimiter::availableIn($key) / 60).' minute(s).';

            return;
        }

        RateLimiter::hit($key, TelegramLinkService::TTL_MINUTES * 60);

        $this->refusal   = null;
        $this->code      = $links->issueFor(auth()->user());
        $this->expiresAt = now()->addMinutes(TelegramLinkService::TTL_MINUTES)->toIso8601String();
    }

    public function disconnect(TelegramLinkService $links): void
    {
        if (! $this->planAllows()) {
            return;
        }

        $links->unlink(auth()->user());

        $this->code      = null;
        $this->expiresAt = null;
        $this->refusal   = null;

        $this->dispatch('notify', message: 'Telegram disconnected.');
    }

    /**
     * The plan check, in the form the actions need it.
     *
     * Sets the refusal rather than aborting, for the reason in the class
     * docblock — but it is the same sentence the middleware and every other
     * component guard use, so a user refused here and a user refused at a
     * gated route read the same words.
     */
    protected function planAllows(): bool
    {
        $user = auth()->user();

        if ($user?->planAllows('automation')) {
            return true;
        }

        $this->refusal = EnsurePlanIncludes::refusalFor('automation', $user?->organization_id);

        return false;
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.profile.connect-telegram', [
            'linked'      => $user->hasLinkedTelegram(),
            'linkedAt'    => $user->telegram_linked_at,
            'planAllows'  => (bool) $user->planAllows('automation'),
            'botUsername' => (string) config('services.hermes.bot_username'),
            'ttlMinutes'  => TelegramLinkService::TTL_MINUTES,
        ]);
    }
}
```

- [ ] **Step 4: Write the view**

Create `resources/views/livewire/profile/connect-telegram.blade.php`:

```blade
{{--
    Three states, and only one of them is ever on screen: locked (the agency's
    plan does not include it), connected, or a code waiting to be sent.

    wire:poll runs only while a code is outstanding — the card flips to
    "Connected" by itself when the bot completes the link, and stops polling
    the moment there is nothing to wait for.
--}}
<div @if ($code && ! $linked) wire:poll.5s @endif>

    @if ($refusal)
        <div class="mb-4 rounded-lg bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 px-4 py-3">
            <p class="text-sm text-amber-800 dark:text-amber-300">{{ $refusal }}</p>
        </div>
    @endif

    @if (! $planAllows)

        <div class="flex items-start gap-3">
            <div class="flex-1">
                <p class="text-sm text-gray-600 dark:text-slate-400">
                    Link your Telegram account so Hermes knows who you are and can act on your tasks from your phone.
                </p>
                <p class="mt-2 text-xs font-medium text-gray-500 dark:text-slate-500">
                    Not included in your plan. Upgrade to Pro to unlock Telegram linking.
                </p>
            </div>
        </div>

    @elseif ($linked)

        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-emerald-700 dark:text-emerald-400">Connected</p>
                <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">
                    Linked {{ $linkedAt?->diffForHumans() }}. Hermes recognises you on Telegram.
                </p>
            </div>

            <button
                type="button"
                wire:click="disconnect"
                wire:confirm="Disconnect Telegram? Hermes will stop recognising you until you link again."
                class="shrink-0 px-3 py-1.5 text-xs font-semibold rounded-lg border border-red-200 dark:border-red-500/30 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10"
            >
                Disconnect
            </button>
        </div>

    @elseif ($code)

        <p class="text-sm text-gray-600 dark:text-slate-400">
            Send this code to Hermes on Telegram. It works once, and lapses in {{ $ttlMinutes }} minutes.
        </p>

        <div class="mt-3 flex items-center gap-3">
            <code class="px-4 py-2.5 rounded-lg bg-gray-100 dark:bg-slate-800 font-mono text-lg tracking-[0.3em] text-gray-900 dark:text-slate-100">{{ $code }}</code>

            <a
                href="https://t.me/{{ $botUsername }}?start={{ $code }}"
                target="_blank"
                rel="noopener noreferrer"
                class="px-3 py-2 text-xs font-semibold rounded-lg bg-[#26A5E4] text-white hover:opacity-90"
            >
                Open Telegram
            </a>
        </div>

        <p class="mt-3 text-xs text-gray-500 dark:text-slate-400">
            Send it in a direct message to the bot — never in a group, where everyone could read it.
        </p>

        <button
            type="button"
            wire:click="generate"
            class="mt-3 text-xs font-medium text-gray-500 dark:text-slate-400 hover:underline"
        >
            Generate a new code
        </button>

    @else

        <p class="text-sm text-gray-600 dark:text-slate-400">
            Link your Telegram account so Hermes knows who you are and can act on your tasks from your phone.
        </p>

        <button
            type="button"
            wire:click="generate"
            class="mt-3 px-3 py-2 text-xs font-semibold rounded-lg bg-[#26A5E4] text-white hover:opacity-90"
        >
            Generate code
        </button>

    @endif

</div>
```

- [ ] **Step 5: Add the section to the settings page**

In `resources/views/settings.blade.php`, insert between the Security section and the Danger Zone:

```blade
        {{-- Connected Accounts --}}
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm dark:shadow-none">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-800/60">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 24 24"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-slate-100">Telegram</h2>
                </div>
                <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">Connect your Telegram account to Hermes.</p>
            </div>
            <div class="p-6">
                <livewire:profile.connect-telegram />
            </div>
        </div>
```

- [ ] **Step 6: Point the plugins card at Settings**

In `app/Livewire/AI/McpPlugins.php`, change the Telegram entry's blurb so the library stops implying its own configuration:

```php
                'name' => 'Telegram', 'category' => 'Communication', 'colour' => '#26A5E4',
                'blurb' => 'Link your own account to Hermes in Settings → Telegram',
```

Leave its `fields` and `logo` untouched — the card is still presentational, and the class docblock already says so.

- [ ] **Step 7: Run the card tests**

Run: `php artisan test --filter=ConnectTelegramTest`
Expected: PASS, 10 tests.

- [ ] **Step 8: Confirm the settings page did not regress**

Run: `php artisan test --filter="SettingsRouteTest|SettingsTest"`
Expected: `SettingsRouteTest` — 3 passed. `SettingsTest` — 5 failed, exactly as in the baseline, and failing with the same `PermissionService::allowedFor(): Argument #1 ($role) must be of type string, null given` as before. **If the SettingsTest failure message has changed, the card is implicated — stop and investigate.**

- [ ] **Step 9: Commit**

```bash
git add app/Livewire/Profile/ConnectTelegram.php resources/views/livewire/profile/connect-telegram.blade.php resources/views/settings.blade.php app/Livewire/AI/McpPlugins.php tests/Feature/Telegram/ConnectTelegramTest.php
git commit -m "feat: a connect telegram card that locks rather than refuses the page"
```

---

### Task 6: Full verification

**Files:** none created. This task proves the work.

- [ ] **Step 1: Full suite against the baseline**

Run: `php artisan test`
Expected: `14 failed, 1 skipped` and `926 + 54 = 980 passed` — 7 from Task 1, 15 from Task 2, 8 from Task 3, 14 from Task 4, 10 from Task 5.

Any failure outside the 14 baseline names listed in Global Constraints is a regression introduced by this work. Compare against the recorded list rather than trusting the count alone.

- [ ] **Step 2: Confirm .env has not drifted**

Run: `grep -n "^DB_DATABASE" .env`
Expected: `DB_DATABASE=clarix`. If it names anything else, stop — a previous session left it pointing at a scratch database, and the clone below would be built from the wrong source.

- [ ] **Step 3: Build a clone and migrate it**

The sqlite suite proves nothing about MySQL DDL. Confirm MySQL is running first (it was down during planning).

```bash
mysqladmin -h 127.0.0.1 -u root status
mysql -h 127.0.0.1 -u root -e "DROP DATABASE IF EXISTS clarix_supclone; CREATE DATABASE clarix_supclone;"
mysqldump -h 127.0.0.1 -u root clarix | mysql -h 127.0.0.1 -u root clarix_supclone
```

Never run migrations against `clarix` itself — it is a production copy.

- [ ] **Step 4: Run the migration against the clone**

```bash
DB_DATABASE=clarix_supclone php artisan migrate --force
DB_DATABASE=clarix_supclone php artisan db:table users
```

Expected: the migration applies cleanly against real production data, and `db:table users` lists all four `telegram_*` columns with `telegram_chat_id` as `bigint unsigned`. Confirm the two unique indexes exist:

```bash
mysql -h 127.0.0.1 -u root -e "SHOW INDEX FROM clarix_supclone.users WHERE Key_name LIKE 'users_telegram%';"
```

- [ ] **Step 5: Confirm the rollback works**

```bash
DB_DATABASE=clarix_supclone php artisan migrate:rollback --step=1
DB_DATABASE=clarix_supclone php artisan migrate --force
```

Expected: both directions succeed. A migration that cannot be rolled back on MySQL is one that cannot be undone in production — dropping a column that carries a unique index needs the index dropped first, which is why `down()` is written the way it is.

- [ ] **Step 6: Real HTTP smoke test**

Serve the app against the clone, in one shell:

```bash
DB_DATABASE=clarix_supclone HERMES_API_KEY=smoke-key HERMES_SIGNING_SECRET=smoke-secret php artisan serve --port=8000
```

Mint a code for a real user in the clone:

```bash
DB_DATABASE=clarix_supclone php artisan tinker --execute="
\$u = App\Services\TenantContext::runWithoutScope(fn () => App\Models\User::whereNotNull('organization_id')->first());
echo \$u->id.' '.\$u->email.' '.app(App\Services\TelegramLinkService::class)->issueFor(\$u).PHP_EOL;
"
```

Then sign and send a real request — substitute the code printed above:

```bash
CODE=PASTE_CODE_HERE
BODY="{\"code\":\"$CODE\",\"chat_id\":123456789012}"
TS=$(date +%s)
SIG=$(printf '%s' "$TS.$BODY" | openssl dgst -sha256 -hmac "smoke-secret" -r | cut -d' ' -f1)

curl -s -i -X POST http://127.0.0.1:8000/api/v1/telegram/verify \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-Hermes-Key: smoke-key" \
  -H "X-Hermes-Timestamp: $TS" \
  -H "X-Hermes-Signature: $SIG" \
  --data "$BODY"
```

Expected: `200` with the identity envelope, naming that user's real organization.

- [ ] **Step 7: Prove single use over real HTTP**

Re-send the exact same request (new timestamp and signature, same code):

```bash
TS=$(date +%s)
SIG=$(printf '%s' "$TS.$BODY" | openssl dgst -sha256 -hmac "smoke-secret" -r | cut -d' ' -f1)
curl -s -o /dev/null -w '%{http_code}\n' -X POST http://127.0.0.1:8000/api/v1/telegram/verify \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -H "X-Hermes-Key: smoke-key" -H "X-Hermes-Timestamp: $TS" -H "X-Hermes-Signature: $SIG" \
  --data "$BODY"
```

Expected: `422`. This is the single-use guarantee proven end to end over real HTTP against real data, which no sqlite test can establish.

- [ ] **Step 8: Prove an unsigned request is refused**

```bash
curl -s -o /dev/null -w '%{http_code}\n' -X POST http://127.0.0.1:8000/api/v1/telegram/verify \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  --data "$BODY"
```

Expected: `401`.

- [ ] **Step 9: Drop the clone and restore .env**

```bash
mysql -h 127.0.0.1 -u root -e "DROP DATABASE IF EXISTS clarix_supclone;"
grep -n "^DB_DATABASE" .env
```

Expected: `.env` still reads `DB_DATABASE=clarix`. The clone commands above all pass `DB_DATABASE` inline rather than editing `.env`, precisely so this cannot drift — confirm it anyway.

- [ ] **Step 10: Commit any fixes**

If steps 3–8 required changes, commit them:

```bash
git add -A
git commit -m "fix: corrections from clone and http verification"
```

---

## Outstanding, and not this repo's to close

The bot half of two safeguards lives in Hermes, not in Clarix, and the user has taken both on:

1. Hermes must accept linking **in private chats only**.
2. Hermes must **delete or clear the message carrying the code** once verification succeeds.

The card's deep link makes Telegram send a visible `/start CODE` message. In a direct message that is fine; posted in a group it exposes a live code to everyone there until it lapses. No Clarix-side change can close that — do not report the feature as fully secured on the strength of this suite alone.
