<?php

namespace Tests\Feature\TaskBot;

use App\Models\N8nIdempotencyKey;
use App\Models\Task;
use App\Models\TaskFile;
use App\Models\User;
use App\Services\N8nIdempotencyStore;
use App\Services\N8nTelegramLinkService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * The idempotency key on the file attach endpoint.
 *
 * A replayed create is answered by the schema — task_code is unique per unit —
 * but the same file posted twice is two perfectly valid attachments, so this is
 * the one write in the integration that needs a key. Signing was rejected
 * deliberately: an HMAC is a function an n8n workflow cannot perform without a
 * code node.
 */
class N8nIdempotencyTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    protected N8nTelegramLinkService $links;

    /** @var array<string, mixed> */
    protected array $orgA;

    /** @var array<string, mixed> */
    protected array $orgB;

    protected const CHAT_A = '7100001';

    protected const CHAT_B = '7100002';

    protected const KEY = 'submission-0f6c1a2b-4d5e';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        Storage::fake('r2');

        config()->set('services.n8n.key', 'test-n8n-key');

        $this->links = app(N8nTelegramLinkService::class);
        $this->orgA  = $this->populate($this->makeOrganization('idem-a', 'Agency A'), 'A');
        $this->orgB  = $this->populate($this->makeOrganization('idem-b', 'Agency B'), 'B');

        $this->subscribeOrganization($this->orgA['organization'], 'pro');
        $this->subscribeOrganization($this->orgB['organization'], 'pro');

        $this->link($this->orgA['pm'], self::CHAT_A);
        $this->link($this->orgB['pm'], self::CHAT_B);
    }

    private function link(User $user, string $chatId): void
    {
        $this->links->verify($this->links->issueCode($user), $chatId);
    }

    /** @param array<int, mixed>|null $files */
    private function attach(
        ?string $key = self::KEY,
        ?array $files = null,
        ?Task $task = null,
        string $chatId = self::CHAT_A,
    ) {
        $task  ??= $this->orgA['task'];
        $files ??= [UploadedFile::fake()->create('brief.pdf', 10)];

        $headers = ['X-N8n-Key' => 'test-n8n-key', 'Accept' => 'application/json'];

        if ($key !== null) {
            $headers['Idempotency-Key'] = $key;
        }

        return $this->post(
            '/api/v1/n8n/telegram/tasks/'.$task->id.'/files',
            ['chat_id' => $chatId, 'files' => $files],
            $headers
        );
    }

    private function fileCount(): int
    {
        return TenantContext::runWithoutScope(fn () => TaskFile::query()->count());
    }

    // ── The key is required ──────────────────────────────────────────────────

    public function test_a_missing_key_is_refused(): void
    {
        $before = $this->fileCount();

        $this->attach(key: null)
            ->assertStatus(400)
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'Idempotency-Key'));

        $this->assertSame($before, $this->fileCount(), 'Nothing may be stored without a key.');
    }

    public function test_an_empty_key_is_refused(): void
    {
        $this->attach(key: '   ')->assertStatus(400);
    }

    public function test_a_too_short_key_is_refused(): void
    {
        $this->attach(key: 'abc')->assertStatus(400);
    }

    public function test_a_key_with_illegal_characters_is_refused(): void
    {
        $this->attach(key: 'has spaces and/slashes')->assertStatus(400);
    }

    public function test_an_over_long_key_is_refused(): void
    {
        $this->attach(key: str_repeat('a', 129))->assertStatus(400);
    }

    /** A uuid is what a workflow will actually send. */
    public function test_a_uuid_is_an_acceptable_key(): void
    {
        $this->attach(key: (string) \Illuminate\Support\Str::uuid())->assertCreated();
    }

    // ── Replay ───────────────────────────────────────────────────────────────

    /** The gap this exists to close. */
    public function test_replaying_the_same_submission_stores_the_file_once(): void
    {
        $before = $this->fileCount();

        $first = $this->attach()->assertCreated();

        $this->assertSame($before + 1, $this->fileCount());

        $second = $this->attach()->assertCreated();

        $this->assertSame($before + 1, $this->fileCount(), 'The replay attached a second copy.');
        $this->assertSame($first->json(), $second->json(), 'The replay must return the original answer.');
    }

    /**
     * The retry gets the original answer rather than a 409, because a retry
     * usually means the first call timed out — the work may well have happened,
     * and the workflow needs the same body to carry on with.
     */
    public function test_a_replay_is_marked_as_one(): void
    {
        $this->attach()->assertCreated()->assertHeaderMissing('Idempotent-Replay');

        $this->attach()
            ->assertCreated()
            ->assertHeader('Idempotent-Replay', 'true');
    }

    public function test_a_replay_writes_no_second_object_to_storage(): void
    {
        // The fixtures already put one file on this task, so the baseline is
        // read rather than assumed.
        $onTask = fn () => TenantContext::runWithoutScope(
            fn () => TaskFile::query()->where('task_id', $this->orgA['task']->id)->count()
        );

        $before = $onTask();

        $this->attach()->assertCreated();

        $file = TenantContext::runWithoutScope(fn () => TaskFile::query()->latest('id')->first());

        $this->attach()->assertCreated();

        $this->assertSame($before + 1, $onTask(), 'The replay attached a second copy.');

        Storage::disk('r2')->assertExists($file->file_path);
    }

    public function test_a_different_key_attaches_a_second_file(): void
    {
        $before = $this->fileCount();

        $this->attach(key: 'submission-one-aaaa')->assertCreated();
        $this->attach(key: 'submission-two-bbbb')->assertCreated();

        $this->assertSame($before + 2, $this->fileCount());
    }

    // ── The dangerous misuse ─────────────────────────────────────────────────

    /**
     * A workflow reusing one key across two submissions would otherwise be
     * handed the first one's response for the second file, silently, and the
     * second file would never be stored — a lost attachment that looks like a
     * success in every log.
     */
    public function test_reusing_a_key_for_a_different_file_is_refused(): void
    {
        $this->attach(files: [UploadedFile::fake()->create('first.pdf', 10)])->assertCreated();

        $before = $this->fileCount();

        $this->attach(files: [UploadedFile::fake()->create('second.pdf', 10)])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'different request'));

        $this->assertSame($before, $this->fileCount());
    }

    public function test_reusing_a_key_against_a_different_task_is_refused(): void
    {
        $other = TenantContext::actingAsOrganization(
            $this->orgA['organization']->id,
            fn () => Task::create([
                'title'         => 'Second task',
                'task_code'     => 'IDEM_SECOND',
                'unit_id'       => $this->orgA['unit']->id,
                'created_by'    => $this->orgA['pm']->id,
                'pm_id'         => $this->orgA['pm']->id,
                'priority'      => 'medium',
                'status'        => 'pending',
                'deadline'      => now()->addDays(7),
                'credit_amount' => 10.00,
            ])
        );

        $this->attach()->assertCreated();

        $this->attach(task: $other)->assertStatus(422);
    }

    /** The same two files in the other order is the same submission. */
    public function test_file_order_does_not_make_it_a_different_request(): void
    {
        $this->attach(files: [
            UploadedFile::fake()->create('one.pdf', 5),
            UploadedFile::fake()->create('two.pdf', 6),
        ])->assertCreated();

        $this->attach(files: [
            UploadedFile::fake()->create('two.pdf', 6),
            UploadedFile::fake()->create('one.pdf', 5),
        ])
            ->assertCreated()
            ->assertHeader('Idempotent-Replay', 'true');
    }

    // ── Scoping ──────────────────────────────────────────────────────────────

    /**
     * Keys are scoped per user, and that is confidentiality rather than
     * tidiness: the stored body names task and file ids, so a shared namespace
     * would hand agency B agency A's response for a key they happened to pick.
     */
    public function test_two_agencies_may_use_the_same_key_independently(): void
    {
        $before = $this->fileCount();

        $a = $this->attach()->assertCreated();
        $b = $this->attach(task: $this->orgB['task'], chatId: self::CHAT_B)->assertCreated();

        $this->assertSame($before + 2, $this->fileCount(), 'One agency\'s key blocked another\'s.');
        $this->assertNotSame($a->json('0.id'), $b->json('0.id'));
        $this->assertNotSame($a->json('0.task_id'), $b->json('0.task_id'));

        // And neither was served the other's body.
        $this->assertSame((int) $this->orgA['task']->id, $a->json('0.task_id'));
        $this->assertSame((int) $this->orgB['task']->id, $b->json('0.task_id'));
    }

    // ── Failures release the key ─────────────────────────────────────────────

    /**
     * A refusal has left nothing behind to duplicate, so the key goes back and
     * the caller can correct the request and try again — which, for a workflow
     * re-fetching a file from Telegram, is the ordinary path.
     */
    public function test_a_rejected_upload_does_not_burn_the_key(): void
    {
        $this->attach(files: [UploadedFile::fake()->create('payload.exe', 5)])
            ->assertStatus(422);

        $this->assertSame(
            0,
            N8nIdempotencyKey::query()->where('key', self::KEY)->count(),
            'A refused request must give the key back.'
        );

        $this->attach(files: [UploadedFile::fake()->create('brief.pdf', 10)])
            ->assertCreated();
    }

    public function test_a_forbidden_request_does_not_burn_the_key(): void
    {
        $this->attach(task: $this->orgB['task'], chatId: self::CHAT_A)->assertNotFound();

        $this->assertSame(0, N8nIdempotencyKey::query()->where('key', self::KEY)->count());
    }

    // ── The claim, directly ──────────────────────────────────────────────────

    /**
     * A second request arriving while the first is still running gets 409, not
     * a duplicate. Simulated by leaving a claim in flight, which is exactly the
     * row the first request would have written.
     */
    public function test_a_claim_still_in_flight_answers_409(): void
    {
        $store = app(N8nIdempotencyStore::class);

        // Claimed with the fingerprint the real request will produce, because
        // that is what the first of two concurrent retries would have written.
        // A *different* fingerprint is a different situation and answers 422 —
        // see the test below.
        $this->assertNull($store->claim(
            $this->orgA['pm'],
            'tasks.files',
            self::KEY,
            $this->fingerprintOfTheDefaultAttach()
        ));

        $before = $this->fileCount();

        $this->attach()->assertStatus(409);

        $this->assertSame($before, $this->fileCount());
    }

    /**
     * A key reused for a different request is refused even while the first is
     * still running. The fingerprint is recorded at claim time, so the mismatch
     * is knowable immediately and will never resolve into a valid replay —
     * saying so now beats a 409 that turns into a 422 a second later.
     */
    public function test_a_mismatched_fingerprint_is_refused_even_in_flight(): void
    {
        app(N8nIdempotencyStore::class)->claim(
            $this->orgA['pm'],
            'tasks.files',
            self::KEY,
            str_repeat('0', 64)
        );

        $this->attach()->assertStatus(422);
    }

    /**
     * The fingerprint must actually reflect the request.
     *
     * This is a regression test for a real bug rather than a tautology: built
     * with json_encode, an UploadedFile in the input made encoding fail, and
     * json_encode returns false rather than throwing — so every fingerprint in
     * the system was hash('sha256', '') and every reused key looked like a
     * legitimate replay. Silent, and exactly the failure the fingerprint exists
     * to prevent.
     */
    public function test_the_fingerprint_reflects_what_was_asked_for(): void
    {
        $empty = hash('sha256', '');

        $one = N8nIdempotencyStore::fingerprint('POST', 'p', ['chat_id' => '1'], []);
        $two = N8nIdempotencyStore::fingerprint('POST', 'p', ['chat_id' => '2'], []);

        $this->assertNotSame($empty, $one, 'The fingerprint silently hashed nothing.');
        $this->assertNotSame($one, $two, 'Different input must fingerprint differently.');

        $this->assertNotSame(
            $one,
            N8nIdempotencyStore::fingerprint('POST', 'other', ['chat_id' => '1'], []),
            'A different path must fingerprint differently.'
        );

        $withFile = N8nIdempotencyStore::fingerprint(
            'POST',
            'p',
            ['chat_id' => '1'],
            [UploadedFile::fake()->create('a.pdf', 10)]
        );

        $this->assertNotSame($one, $withFile);
        $this->assertNotSame(
            $withFile,
            N8nIdempotencyStore::fingerprint(
                'POST',
                'p',
                ['chat_id' => '1'],
                [UploadedFile::fake()->create('b.pdf', 10)]
            ),
            'A different file must fingerprint differently.'
        );
    }

    /** What attach() with its defaults produces, for the in-flight test. */
    private function fingerprintOfTheDefaultAttach(): string
    {
        return N8nIdempotencyStore::fingerprint(
            'POST',
            'api/v1/n8n/telegram/tasks/'.$this->orgA['task']->id.'/files',
            ['chat_id' => self::CHAT_A],
            [UploadedFile::fake()->create('brief.pdf', 10)]
        );
    }

    /** The insert is the lock: a second claim on the same key never wins. */
    public function test_a_second_claim_on_the_same_key_reports_the_holder(): void
    {
        $store       = app(N8nIdempotencyStore::class);
        $fingerprint = str_repeat('a', 64);

        $this->assertNull($store->claim($this->orgA['pm'], 'tasks.files', self::KEY, $fingerprint));

        $holder = $store->claim($this->orgA['pm'], 'tasks.files', self::KEY, $fingerprint);

        $this->assertNotNull($holder);
        $this->assertFalse($holder->isComplete());
        $this->assertSame(1, N8nIdempotencyKey::query()->where('key', self::KEY)->count());
    }

    /** One operation's key must never satisfy another's. */
    public function test_a_key_is_scoped_to_its_operation(): void
    {
        $store       = app(N8nIdempotencyStore::class);
        $fingerprint = str_repeat('a', 64);

        $this->assertNull($store->claim($this->orgA['pm'], 'tasks.files', self::KEY, $fingerprint));
        $this->assertNull($store->claim($this->orgA['pm'], 'tasks.create', self::KEY, $fingerprint));
    }

    // ── Expiry and pruning ───────────────────────────────────────────────────

    /** A key is not poisoned for ever by one submission months ago. */
    public function test_an_expired_key_can_be_used_again(): void
    {
        $this->attach()->assertCreated();

        N8nIdempotencyKey::query()->update(['expires_at' => now()->subMinute()]);

        $before = $this->fileCount();

        $this->attach()
            ->assertCreated()
            ->assertHeaderMissing('Idempotent-Replay');

        $this->assertSame($before + 1, $this->fileCount());
    }

    /**
     * Completing a claim must not move its expiry.
     *
     * Declared as a MySQL `timestamp`, this column silently gained an implicit
     * "ON UPDATE CURRENT_TIMESTAMP" — so storing the response reset the expiry
     * to now, the row read as expired, and the replay this whole mechanism
     * exists to catch attached the file a second time. sqlite has no such rule,
     * so it passed here while being inert in production; the column is a
     * `dateTime` now. This assertion is cheap and would catch a regression on
     * the MySQL clone.
     */
    public function test_completing_a_claim_does_not_move_its_expiry(): void
    {
        $this->attach()->assertCreated();

        $row = N8nIdempotencyKey::query()->where('key', self::KEY)->firstOrFail();

        $this->assertSame(201, $row->response_status, 'The claim was never completed.');
        $this->assertTrue(
            $row->expires_at->isFuture(),
            'Storing the response moved the expiry into the past, so replays will not be caught.'
        );
    }

    public function test_the_prune_command_removes_only_expired_keys(): void
    {
        $this->attach(key: 'live-key-aaaaaaaa')->assertCreated();
        $this->attach(key: 'dead-key-bbbbbbbb')->assertCreated();

        N8nIdempotencyKey::query()
            ->where('key', 'dead-key-bbbbbbbb')
            ->update(['expires_at' => now()->subDay()]);

        $this->artisan('n8n:prune-idempotency-keys')->assertSuccessful();

        $this->assertSame(1, N8nIdempotencyKey::query()->where('key', 'live-key-aaaaaaaa')->count());
        $this->assertSame(0, N8nIdempotencyKey::query()->where('key', 'dead-key-bbbbbbbb')->count());
    }

    /**
     * Deleting a person takes their keys with them.
     *
     * Asserted against a user with nothing else attached, because the fixtures'
     * PM owns tasks and cannot be deleted at all — which would have made this
     * a test of the tasks foreign key rather than of this one.
     */
    public function test_keys_are_removed_with_their_user(): void
    {
        $spare = TenantContext::actingAsOrganization(
            $this->orgA['organization']->id,
            fn () => User::factory()->create([
                'name'  => 'Spare',
                'email' => 'spare@example.test',
                'role'  => 'pm',
            ])
        );

        app(N8nIdempotencyStore::class)->claim($spare, 'tasks.files', 'spare-key-aaaaaa', str_repeat('a', 64));

        $this->assertSame(1, N8nIdempotencyKey::query()->where('user_id', $spare->id)->count());

        TenantContext::runWithoutScope(fn () => User::query()->whereKey($spare->id)->delete());

        $this->assertSame(0, N8nIdempotencyKey::query()->where('user_id', $spare->id)->count());
    }

    // ── The create endpoint is deliberately not covered ──────────────────────

    /**
     * Create needs no key, and asserting it stays that way is worth a test: the
     * schema already refuses a replay through task_code, and demanding a header
     * there would be ceremony that buys nothing.
     */
    public function test_the_create_endpoint_needs_no_idempotency_key(): void
    {
        $this->postJson('/api/v1/n8n/telegram/tasks', [
            'chat_id'       => self::CHAT_A,
            'title'         => 'No key needed',
            'task_code'     => 'IDEM_NOKEY',
            'priority'      => 'low',
            'deadline'      => now()->addDays(3)->format('Y-m-d'),
            'credit_amount' => '1.00',
        ], ['X-N8n-Key' => 'test-n8n-key'])->assertCreated();
    }
}
