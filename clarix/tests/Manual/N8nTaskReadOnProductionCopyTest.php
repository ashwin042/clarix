<?php

namespace Tests\Manual;

use App\Models\N8nTelegramLink;
use App\Models\Task;
use App\Models\User;
use App\Services\N8nTelegramLinkService;
use App\Services\TenantContext;
use Tests\TestCase;

/**
 * GET /api/v1/n8n/telegram/tasks against a clone of the production copy — real
 * units, real PMs, real permission rows, and a real MySQL schema.
 *
 * Deliberately outside tests/Feature so `php artisan test` never picks it up,
 * and deliberately without RefreshDatabase so the production-shaped data it
 * exists to exercise is still there. Run it with phpunit-clone.xml, which
 * points at clarix_supclone. Everything it creates, it removes in tearDown.
 *
 * The sqlite suite proves the rules are right. This proves they are right over
 * the data the agency actually has, and over the column types sqlite does not
 * have:
 *
 *   - tasks.status is a MySQL enum. sqlite accepts any string in an enum column
 *     without complaint, so the whole status filter is untested by the feature
 *     suite in the one way that matters — whether the values the endpoint
 *     accepts are the values the column actually holds.
 *   - 348 tasks across 14 units, rather than the two tasks a fixture builds, is
 *     the only way to exercise the page cap and to prove `count` stays the true
 *     total when the list does not.
 *   - The permission rows are the agency's own, not a seeder's defaults, so a
 *     rule that happened to match PermissionSeeder is not the same as a rule
 *     that matches this agency.
 */
class N8nTaskReadOnProductionCopyTest extends TestCase
{
    private const ORGANIZATION_ID = 1;

    /** A real admin of the agency. Belongs to no unit, as the role means. */
    private const ADMIN_ID = 31;

    /** A real PM carrying a real unit, with real tasks in it. */
    private const PM_ID = 60;

    private const PM_UNIT_ID = 38;

    /** Another unit of the same agency, the one holding the most work. */
    private const OTHER_UNIT_ID = 37;

    private const ADMIN_CHAT = '7100001';

    private const PM_CHAT = '7100002';

    private const KEY = 'clone-probe-n8n-key';

    /** The twin task this test creates, removed again in tearDown. */
    protected ?Task $twin = null;

    /** @var list<string> */
    protected array $chats = [];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.n8n.key', self::KEY);

        $this->linkChat(self::ADMIN_ID, self::ADMIN_CHAT);
        $this->linkChat(self::PM_ID, self::PM_CHAT);
    }

    protected function tearDown(): void
    {
        if ($this->twin) {
            TenantContext::runWithoutScope(
                fn () => Task::withoutGlobalScopes()->whereKey($this->twin->id)->forceDelete()
            );
        }

        foreach ($this->chats as $chatId) {
            TenantContext::runWithoutScope(
                fn () => N8nTelegramLink::withoutGlobalScopes()->where('chat_id', $chatId)->forceDelete()
            );
        }

        parent::tearDown();
    }

    private function linkChat(int $userId, string $chatId): void
    {
        $links = app(N8nTelegramLinkService::class);

        $user = TenantContext::runWithoutScope(
            fn () => User::withoutGlobalScopes()->findOrFail($userId)
        );

        $links->verify($links->issueCode($user), $chatId);

        $this->chats[] = $chatId;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function tasks(string $chatId, array $query = [])
    {
        return $this->getJson(
            '/api/v1/n8n/telegram/tasks?'.http_build_query($query + ['chat_id' => $chatId]),
            ['X-N8n-Key' => self::KEY]
        );
    }

    /** A direct count, deliberately not going through the model's scopes. */
    private function countRows(string $where): int
    {
        return (int) \DB::table('tasks')->whereRaw($where)->count();
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * The whole agency, as an admin sees it: the list capped, the count not.
     *
     * This is the assertion the fixture suite cannot really make. Two tasks and
     * a limit of fifty never meet, so a `count` that quietly returned the page
     * size instead of the total would pass every test in tests/Feature and be
     * wrong on the first real question an admin asks.
     */
    public function test_an_admin_gets_the_true_agency_total_behind_a_capped_list(): void
    {
        $body = $this->tasks(self::ADMIN_CHAT)->assertOk()->json();

        $expected = $this->countRows('organization_id = '.self::ORGANIZATION_ID);

        $this->assertGreaterThan(50, $expected, 'the clone must hold more than one page, or this proves nothing');
        $this->assertSame($expected, $body['count'], 'count must be the total, not the page');
        $this->assertCount(50, $body['tasks'], 'the list must stop at the cap');
        $this->assertTrue($body['truncated']);
    }

    /**
     * A real PM against a real unit. The number has to match what the board
     * would show them, which is the whole reason the ceiling is the unit.
     */
    public function test_a_real_pm_sees_exactly_their_own_units_work(): void
    {
        $body = $this->tasks(self::PM_CHAT)->assertOk()->json();

        $expected = $this->countRows('unit_id = '.self::PM_UNIT_ID);

        $this->assertGreaterThan(0, $expected);
        $this->assertSame($expected, $body['count']);

        foreach ($body['tasks'] as $task) {
            $this->assertSame(self::PM_UNIT_ID, $task['unit_id']);
        }
    }

    public function test_a_real_pm_is_refused_another_unit_of_their_own_agency(): void
    {
        $this->tasks(self::PM_CHAT, ['unit_id' => self::OTHER_UNIT_ID])->assertForbidden();
    }

    /**
     * The per-unit uniqueness question, asked with a code that genuinely exists
     * twice.
     *
     * The real data has no duplicated code across units, so the test makes one:
     * it copies an existing code into another unit, which the schema permits
     * precisely because the unique index is composite. Without this the "same
     * code, different unit" case cannot be exercised against the real index at
     * all — only against a fixture that assumes it.
     */
    public function test_the_same_code_in_two_units_is_two_different_tasks(): void
    {
        $original = TenantContext::actingAsOrganization(
            self::ORGANIZATION_ID,
            fn () => Task::query()->where('unit_id', self::PM_UNIT_ID)->firstOrFail()
        );

        $this->twin = TenantContext::actingAsOrganization(self::ORGANIZATION_ID, fn () => Task::create([
            'title'         => 'Clone Probe Twin',
            'task_code'     => $original->task_code,
            'unit_id'       => self::OTHER_UNIT_ID,
            'created_by'    => self::ADMIN_ID,
            'pm_id'         => null,
            'priority'      => 'low',
            'status'        => 'pending',
            'deadline'      => now()->addDay(),
            'credit_amount' => 1.00,
        ]));

        $inOwnUnit = $this->tasks(self::ADMIN_CHAT, [
            'task_code' => $original->task_code,
            'unit_id'   => self::PM_UNIT_ID,
        ])->assertOk()->json();

        $inOtherUnit = $this->tasks(self::ADMIN_CHAT, [
            'task_code' => $original->task_code,
            'unit_id'   => self::OTHER_UNIT_ID,
        ])->assertOk()->json();

        $this->assertSame(1, $inOwnUnit['count']);
        $this->assertSame((int) $original->id, $inOwnUnit['tasks'][0]['id']);

        $this->assertSame(1, $inOtherUnit['count']);
        $this->assertSame((int) $this->twin->id, $inOtherUnit['tasks'][0]['id']);

        $this->assertNotSame($inOwnUnit['tasks'][0]['id'], $inOtherUnit['tasks'][0]['id']);
    }

    /**
     * Every value the endpoint accepts, against the real enum.
     *
     * The point is the agreement between three lists that are written down
     * separately: Task::STATUSES, the Rule::in on the request, and the enum
     * MySQL actually declares. sqlite makes the third invisible, so a status
     * added to the model but never migrated would look fine in tests/Feature
     * and return nothing forever in production.
     */
    public function test_every_accepted_status_is_a_status_the_column_holds(): void
    {
        $declared = \DB::selectOne(
            "SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND COLUMN_NAME = 'status'"
        )->t;

        foreach (Task::STATUSES as $status) {
            $this->assertStringContainsString(
                "'{$status}'",
                $declared,
                "status '{$status}' is accepted by the endpoint but is not in the MySQL enum"
            );

            $body = $this->tasks(self::ADMIN_CHAT, ['status' => $status])->assertOk()->json();

            $this->assertSame(
                $this->countRows('organization_id = '.self::ORGANIZATION_ID." AND status = '{$status}'"),
                $body['count'],
                "the '{$status}' filter disagrees with the table"
            );
        }
    }

    public function test_an_unknown_status_is_refused_rather_than_reaching_the_enum(): void
    {
        $this->tasks(self::ADMIN_CHAT, ['status' => 'nonsense'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    /**
     * The filters that answer "how is my work doing", combined the way the bot
     * combines them, against a PM who really has tasks in more than one state.
     */
    public function test_a_pm_can_count_their_units_completed_work(): void
    {
        $body = $this->tasks(self::PM_CHAT, ['status' => 'completed'])->assertOk()->json();

        $this->assertSame(
            $this->countRows('unit_id = '.self::PM_UNIT_ID." AND status = 'completed'"),
            $body['count']
        );
    }

    public function test_a_pm_id_filter_agrees_with_the_table(): void
    {
        $body = $this->tasks(self::ADMIN_CHAT, ['pm_id' => self::PM_ID])->assertOk()->json();

        $this->assertSame(
            $this->countRows('organization_id = '.self::ORGANIZATION_ID.' AND pm_id = '.self::PM_ID),
            $body['count']
        );
    }

    /** No match is still a 200 with an empty array, on real data too. */
    public function test_an_absent_code_is_an_empty_200(): void
    {
        $body = $this->tasks(self::ADMIN_CHAT, ['task_code' => 'NO_SUCH_CODE_ANYWHERE'])
            ->assertOk()
            ->json();

        $this->assertSame([], $body['tasks']);
        $this->assertSame(0, $body['count']);
    }

    public function test_a_wrong_key_is_a_clean_401_without_an_accept_header(): void
    {
        $response = $this->get(
            '/api/v1/n8n/telegram/tasks?chat_id='.self::ADMIN_CHAT,
            ['X-N8n-Key' => 'wrong']
        );

        $response->assertUnauthorized();
        $response->assertHeader('content-type', 'application/json');
        $this->assertSame('Unauthenticated.', $response->json('message'));
    }
}
