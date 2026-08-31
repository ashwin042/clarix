<?php

namespace Tests\Feature\TaskBot;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\Unit;
use App\Models\User;
use App\Services\N8nTelegramLinkService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * GET /api/v1/n8n/telegram/tasks — the read half of the task bot.
 *
 * Three conversations share this one endpoint: "does this code already exist
 * here" before a create, "how are my tasks doing" for a PM, and "how much is
 * pending" for an admin. One route with optional filters rather than three,
 * because the three differ only in which filters they set.
 *
 * The question underneath nearly every test here is the same one: the pipeline
 * presents a static shared key, so the key says nothing about *who* is asking.
 * Everything about who is asking comes from the chat_id, which ResolveN8nActor
 * turns into a real Clarix user before this endpoint runs. So the filters in
 * the query string may only ever *narrow* what that person could already see —
 * never widen it. A filter that would widen is refused rather than honoured,
 * and refused identically whether the id names another agency's row or nothing
 * at all.
 */
class N8nTaskReadTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    protected N8nTelegramLinkService $links;

    /** @var array<string, mixed> */
    protected array $orgA;

    /** @var array<string, mixed> */
    protected array $orgB;

    protected const ADMIN_A = '7000001';

    protected const ADMIN_B = '7000002';

    protected const PM_A = '7000003';

    protected const PM_A2 = '7000004';

    protected const WRITER_A = '7000005';

    protected const SUPERVISOR_A = '7000006';

    protected const HR_A = '7000007';

    /** A second unit in agency A, so "the agency" and "one unit" differ. */
    protected Unit $secondUnitA;

    protected User $pmInSecondUnitA;

    protected User $supervisorA;

    protected User $hrA;

    /** CODE_A's twin: the same task_code, in the other unit of the same agency. */
    protected Task $twinTaskA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);

        config()->set('services.n8n.key', 'test-n8n-key');

        $this->links = app(N8nTelegramLinkService::class);
        $this->orgA  = $this->populate($this->makeOrganization('read-a', 'Agency A'), 'A');
        $this->orgB  = $this->populate($this->makeOrganization('read-b', 'Agency B'), 'B');

        $this->subscribeOrganization($this->orgA['organization'], 'pro');
        $this->subscribeOrganization($this->orgB['organization'], 'pro');

        TenantContext::actingAsOrganization($this->orgA['organization']->id, function () {
            $this->secondUnitA = Unit::create(['name' => 'Second Unit A']);

            $this->pmInSecondUnitA = User::factory()->create([
                'name'    => 'PM In Second Unit A',
                'email'   => 'pm.second.a@example.test',
                'role'    => 'pm',
                'unit_id' => $this->secondUnitA->id,
            ]);

            $this->supervisorA = User::factory()->create([
                'name'  => 'Supervisor A',
                'email' => 'supervisor.a@example.test',
                'role'  => 'supervisor',
            ]);

            $this->hrA = User::factory()->create([
                'name'  => 'HR A',
                'email' => 'hr.a@example.test',
                'role'  => 'hr',
            ]);

            /*
             * The fixture the per-unit scoping tests turn on. task_code is
             * unique per unit, not per agency, so CODE_A is a legitimate code
             * in both of agency A's units — and an endpoint that filtered on
             * the code alone would hand back both.
             */
            $this->twinTaskA = Task::create([
                'title'         => 'Twin Task A',
                'task_code'     => 'CODE_A',
                'unit_id'       => $this->secondUnitA->id,
                'created_by'    => $this->pmInSecondUnitA->id,
                'pm_id'         => $this->pmInSecondUnitA->id,
                'priority'      => 'high',
                'status'        => 'completed',
                'deadline'      => now()->addDays(3),
                'credit_amount' => 4.00,
            ]);
        });

        $this->link($this->orgA['admin'], self::ADMIN_A);
        $this->link($this->orgB['admin'], self::ADMIN_B);
        $this->link($this->orgA['pm'], self::PM_A);
        $this->link($this->pmInSecondUnitA, self::PM_A2);
        $this->link($this->orgA['writer'], self::WRITER_A);
        $this->link($this->supervisorA, self::SUPERVISOR_A);
        $this->link($this->hrA, self::HR_A);
    }

    private function link(User $user, string $chatId): void
    {
        $this->links->verify($this->links->issueCode($user), $chatId);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     */
    private function tasks(
        string $chatId,
        array $query = [],
        array $headers = ['X-N8n-Key' => 'test-n8n-key']
    ) {
        return $this->getJson(
            '/api/v1/n8n/telegram/tasks?'.http_build_query($query + ['chat_id' => $chatId]),
            $headers
        );
    }

    /** The codes in a response body, for assertions that do not care about order. */
    private function codes(array $body): array
    {
        return array_column($body['tasks'], 'task_code');
    }

    // ── The envelope ─────────────────────────────────────────────────────────

    public function test_the_response_is_a_tasks_array_and_a_count(): void
    {
        $body = $this->tasks(self::ADMIN_A)->assertOk()->json();

        $this->assertArrayHasKey('tasks', $body);
        $this->assertArrayHasKey('count', $body);
        $this->assertIsArray($body['tasks']);
        $this->assertIsInt($body['count']);
    }

    /**
     * n8n addresses fields by path, so a 'data' wrapper appearing on either the
     * envelope or the collection inside it is a silent null in every downstream
     * node. ResourceCollection's own static $wrap is Laravel's default even
     * when the resource it collects sets null, so this is worth asserting.
     */
    public function test_the_payload_is_not_wrapped_in_data(): void
    {
        $body = $this->tasks(self::ADMIN_A)->assertOk()->json();

        $this->assertArrayNotHasKey('data', $body);
        $this->assertArrayNotHasKey('data', $body['tasks']);
    }

    public function test_a_task_carries_the_fields_the_bot_reads(): void
    {
        $body = $this->tasks(self::ADMIN_A, ['task_code' => 'CODE_A', 'unit_id' => $this->orgA['unit']->id])
            ->assertOk()->json();

        $task = $body['tasks'][0];

        foreach (['id', 'task_code', 'title', 'status', 'unit_id', 'pm_id', 'deadline', 'credit_amount'] as $field) {
            $this->assertArrayHasKey($field, $task);
        }

        $this->assertIsInt($task['id']);
        $this->assertSame('CODE_A', $task['task_code']);
        $this->assertSame('pending', $task['status']);
        $this->assertSame((int) $this->orgA['unit']->id, $task['unit_id']);
    }

    /**
     * Tenancy bookkeeping stays off the wire, matching N8nTaskResource on the
     * create endpoint. The caller was never told an organization id and has no
     * use for one.
     */
    public function test_the_organization_id_is_never_published(): void
    {
        $body = $this->tasks(self::ADMIN_A)->assertOk()->json();

        $this->assertArrayNotHasKey('organization_id', $body['tasks'][0]);
    }

    // ── No results is not an error ───────────────────────────────────────────

    /**
     * The distinction the bot cannot do without. A 404 here would be
     * indistinguishable from a routing mistake or a bad deploy, so "there is no
     * such task" and "this endpoint is broken" would read identically in an n8n
     * error branch.
     */
    public function test_no_matches_is_an_empty_200_rather_than_a_404(): void
    {
        $body = $this->tasks(self::ADMIN_A, ['task_code' => 'NO_SUCH_CODE'])
            ->assertOk()
            ->json();

        $this->assertSame([], $body['tasks']);
        $this->assertSame(0, $body['count']);
    }

    // ── task_code is unique per unit, not per agency ─────────────────────────

    public function test_a_code_and_its_own_unit_finds_the_task(): void
    {
        $body = $this->tasks(self::ADMIN_A, [
            'task_code' => 'CODE_A',
            'unit_id'   => $this->orgA['unit']->id,
        ])->assertOk()->json();

        $this->assertSame(1, $body['count']);
        $this->assertSame((int) $this->orgA['task']->id, $body['tasks'][0]['id']);
    }

    /**
     * The same code, asked for in the agency's *other* unit, is a different
     * task — and asked for in a unit that has no such code, nothing. This is
     * the check the bot makes before filing, so getting it wrong means either
     * refusing a legitimate code or allowing a duplicate.
     */
    public function test_the_same_code_in_another_unit_is_a_different_task(): void
    {
        $body = $this->tasks(self::ADMIN_A, [
            'task_code' => 'CODE_A',
            'unit_id'   => $this->secondUnitA->id,
        ])->assertOk()->json();

        $this->assertSame(1, $body['count']);
        $this->assertSame((int) $this->twinTaskA->id, $body['tasks'][0]['id']);
    }

    public function test_a_code_absent_from_the_named_unit_is_no_match(): void
    {
        TenantContext::actingAsOrganization($this->orgA['organization']->id, function () {
            Task::create([
                'title'         => 'Only In Unit One',
                'task_code'     => 'ONLY_ONE',
                'unit_id'       => $this->orgA['unit']->id,
                'created_by'    => $this->orgA['pm']->id,
                'pm_id'         => $this->orgA['pm']->id,
                'priority'      => 'low',
                'status'        => 'pending',
                'deadline'      => now()->addDay(),
                'credit_amount' => 1.00,
            ]);
        });

        $body = $this->tasks(self::ADMIN_A, [
            'task_code' => 'ONLY_ONE',
            'unit_id'   => $this->secondUnitA->id,
        ])->assertOk()->json();

        $this->assertSame(0, $body['count']);
    }

    /** Without a unit, a code is ambiguous across units and both twins answer. */
    public function test_a_code_without_a_unit_answers_every_unit_the_caller_reaches(): void
    {
        $body = $this->tasks(self::ADMIN_A, ['task_code' => 'CODE_A'])->assertOk()->json();

        $this->assertSame(2, $body['count']);
    }

    // ── Tenancy ──────────────────────────────────────────────────────────────

    public function test_an_admin_never_sees_another_agencys_tasks(): void
    {
        $body = $this->tasks(self::ADMIN_A)->assertOk()->json();

        $this->assertNotContains('CODE_B', $this->codes($body));
    }

    /**
     * Asked from the other side too. Nothing in the request names an
     * organization — it comes from the chat's owner — so a leak here would be
     * silent.
     */
    public function test_each_agency_sees_only_its_own(): void
    {
        $body = $this->tasks(self::ADMIN_B)->assertOk()->json();

        $this->assertSame(['CODE_B'], $this->codes($body));
    }

    public function test_another_agencys_task_code_is_simply_no_match(): void
    {
        $body = $this->tasks(self::ADMIN_A, ['task_code' => 'CODE_B'])->assertOk()->json();

        $this->assertSame(0, $body['count']);
    }

    // ── The role ceiling ─────────────────────────────────────────────────────

    public function test_an_admin_reaches_every_unit_of_their_agency(): void
    {
        $body = $this->tasks(self::ADMIN_A)->assertOk()->json();

        $this->assertEqualsCanonicalizing(['CODE_A', 'CODE_A'], $this->codes($body));
        $this->assertSame(2, $body['count']);
    }

    /**
     * A supervisor runs the agency's work across every unit without running the
     * agency, and TaskPolicy::owns() already says so. The bot has to agree with
     * the board, or the same person is told two different numbers.
     */
    public function test_a_supervisor_reaches_every_unit_of_their_agency(): void
    {
        $body = $this->tasks(self::SUPERVISOR_A)->assertOk()->json();

        $this->assertSame(2, $body['count']);
    }

    /**
     * The ceiling that matters most, and the one the spec originally proposed
     * setting to pm_id. It is the unit, because that is what TaskPolicy::owns()
     * gives a PM on the board: answering their own unit's count differently
     * here would make the bot and the screen disagree in front of one person.
     */
    public function test_a_pm_reaches_their_own_unit(): void
    {
        $body = $this->tasks(self::PM_A)->assertOk()->json();

        $this->assertSame(1, $body['count']);
        $this->assertSame((int) $this->orgA['unit']->id, $body['tasks'][0]['unit_id']);
    }

    public function test_a_pm_never_sees_another_units_tasks(): void
    {
        $body = $this->tasks(self::PM_A)->assertOk()->json();

        $ids = array_column($body['tasks'], 'id');

        $this->assertNotContains((int) $this->twinTaskA->id, $ids);
    }

    /**
     * A writer's reach is their assignments, not a unit — they carry no unit_id
     * at all, so a unit comparison would have reached nothing.
     */
    public function test_a_writer_reaches_only_the_tasks_assigned_to_them(): void
    {
        $body = $this->tasks(self::WRITER_A)->assertOk()->json();

        $this->assertSame(1, $body['count']);
        $this->assertSame((int) $this->orgA['task']->id, $body['tasks'][0]['id']);
    }

    public function test_a_writer_with_a_new_assignment_reaches_that_task_too(): void
    {
        TenantContext::actingAsOrganization($this->orgA['organization']->id, function () {
            TaskAssignment::create([
                'task_id'     => $this->twinTaskA->id,
                'writer_id'   => $this->orgA['writer']->id,
                'assigned_by' => $this->orgA['admin']->id,
                'status'      => 'pending',
            ]);
        });

        $body = $this->tasks(self::WRITER_A)->assertOk()->json();

        $this->assertSame(2, $body['count']);
    }

    /**
     * tasks.view is off for HR by default, and the endpoint asks the *person*
     * rather than the pipeline's key — so switching it off in the Authorization
     * panel stops the bot for that role without anyone touching n8n.
     */
    public function test_a_role_without_the_view_permission_is_refused(): void
    {
        $this->tasks(self::HR_A)->assertForbidden();
    }

    // ── unit_id may narrow, never widen ──────────────────────────────────────

    public function test_a_pm_may_name_their_own_unit(): void
    {
        $body = $this->tasks(self::PM_A, ['unit_id' => $this->orgA['unit']->id])
            ->assertOk()
            ->json();

        $this->assertSame(1, $body['count']);
    }

    /**
     * The core of the authorization story. The bot sends whatever it likes; the
     * backend already knows who the chat belongs to, so a unit outside that
     * person's reach is refused rather than served.
     *
     * A 403 rather than an empty list on purpose: an empty list is a truthful
     * answer to a legitimate question ("that unit has no tasks"), so using it
     * for a refusal would leave a workflow unable to tell the two apart.
     */
    public function test_a_pm_naming_another_unit_is_refused(): void
    {
        $this->tasks(self::PM_A, ['unit_id' => $this->secondUnitA->id])->assertForbidden();
    }

    public function test_an_admin_may_name_any_unit_of_their_own_agency(): void
    {
        $body = $this->tasks(self::ADMIN_A, ['unit_id' => $this->secondUnitA->id])
            ->assertOk()
            ->json();

        $this->assertSame(1, $body['count']);
        $this->assertSame((int) $this->twinTaskA->id, $body['tasks'][0]['id']);
    }

    public function test_an_admin_naming_another_agencys_unit_is_refused(): void
    {
        $this->tasks(self::ADMIN_A, ['unit_id' => $this->orgB['unit']->id])->assertForbidden();
    }

    /**
     * The same refusal for a unit that never existed. A different answer for
     * "another agency's unit" and "no such unit" would turn this endpoint into
     * a way to ask whether an id is in use anywhere on the platform.
     */
    public function test_a_unit_that_does_not_exist_is_refused_identically(): void
    {
        $forOther   = $this->tasks(self::ADMIN_A, ['unit_id' => $this->orgB['unit']->id]);
        $forMissing = $this->tasks(self::ADMIN_A, ['unit_id' => 999999]);

        // Pinned rather than merely compared. Two identical failures satisfy an
        // equivalence assertion perfectly well, so without this the test would
        // have passed against a route that did not yet exist.
        $forOther->assertForbidden();
        $forMissing->assertForbidden();

        $this->assertSame($forOther->json('message'), $forMissing->json('message'));
    }

    // ── pm_id narrows within the ceiling ─────────────────────────────────────

    public function test_a_pm_id_filter_narrows_to_that_persons_tasks(): void
    {
        $body = $this->tasks(self::ADMIN_A, ['pm_id' => $this->orgA['pm']->id])
            ->assertOk()
            ->json();

        $this->assertSame(1, $body['count']);
        $this->assertSame((int) $this->orgA['pm']->id, $body['tasks'][0]['pm_id']);
    }

    /**
     * pm_id is a filter, not a key: it can only ever remove rows from what the
     * caller already reaches. So a PM naming a colleague in another unit gets
     * nothing rather than a refusal — there is no widening to refuse.
     */
    public function test_a_pm_naming_a_colleague_in_another_unit_gets_nothing(): void
    {
        $body = $this->tasks(self::PM_A, ['pm_id' => $this->pmInSecondUnitA->id])
            ->assertOk()
            ->json();

        $this->assertSame(0, $body['count']);
    }

    public function test_a_pm_id_from_another_agency_gets_nothing(): void
    {
        $body = $this->tasks(self::ADMIN_A, ['pm_id' => $this->orgB['pm']->id])
            ->assertOk()
            ->json();

        $this->assertSame(0, $body['count']);
    }

    // ── status ───────────────────────────────────────────────────────────────

    public function test_the_status_filter_selects_one_status(): void
    {
        $body = $this->tasks(self::ADMIN_A, ['status' => 'completed'])->assertOk()->json();

        $this->assertSame(1, $body['count']);
        $this->assertSame('completed', $body['tasks'][0]['status']);
    }

    public function test_counting_pending_work_across_the_agency(): void
    {
        $body = $this->tasks(self::ADMIN_A, ['status' => 'pending'])->assertOk()->json();

        $this->assertSame(1, $body['count']);
    }

    /**
     * Validated against Task::STATUSES rather than left to the column. sqlite
     * accepts any string in an enum, so an unvalidated status would pass every
     * test here and fail only against MySQL in production.
     */
    public function test_an_unknown_status_is_a_validation_error(): void
    {
        $this->tasks(self::ADMIN_A, ['status' => 'nonsense'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    // ── Combining filters ────────────────────────────────────────────────────

    public function test_filters_combine_with_and(): void
    {
        $body = $this->tasks(self::ADMIN_A, [
            'unit_id' => $this->secondUnitA->id,
            'status'  => 'pending',
        ])->assertOk()->json();

        $this->assertSame(0, $body['count']);
    }

    // ── The cap ──────────────────────────────────────────────────────────────

    /**
     * A broad query must not hand a conversation the agency's whole task table.
     * count stays the true total, because "how many are pending" is one of the
     * three questions this endpoint exists to answer — capping the count as
     * well as the list would answer it wrongly.
     */
    public function test_a_broad_query_caps_the_list_but_not_the_count(): void
    {
        TenantContext::actingAsOrganization($this->orgA['organization']->id, function () {
            for ($i = 0; $i < 60; $i++) {
                Task::create([
                    'title'         => "Bulk {$i}",
                    'task_code'     => "BULK_{$i}",
                    'unit_id'       => $this->orgA['unit']->id,
                    'created_by'    => $this->orgA['pm']->id,
                    'pm_id'         => $this->orgA['pm']->id,
                    'priority'      => 'low',
                    'status'        => 'pending',
                    'deadline'      => now()->addDays(5),
                    'credit_amount' => 1.00,
                ]);
            }
        });

        $body = $this->tasks(self::ADMIN_A)->assertOk()->json();

        $this->assertCount(50, $body['tasks']);
        $this->assertSame(62, $body['count']);
        $this->assertTrue($body['truncated']);
    }

    public function test_a_small_result_is_not_truncated(): void
    {
        $body = $this->tasks(self::ADMIN_A)->assertOk()->json();

        $this->assertFalse($body['truncated']);
    }

    // ── Authentication ───────────────────────────────────────────────────────

    public function test_a_missing_key_is_a_401(): void
    {
        $this->tasks(self::ADMIN_A, [], [])->assertUnauthorized();
    }

    public function test_a_wrong_key_is_a_401(): void
    {
        $this->tasks(self::ADMIN_A, [], ['X-N8n-Key' => 'wrong'])->assertUnauthorized();
    }

    // ── JSON regardless of the Accept header ─────────────────────────────────

    /*
     * The bug this endpoint was asked not to repeat. Laravel decides the shape
     * of an error response from expectsJson(), so without an Accept header a
     * ValidationException redirects and an AuthenticationException sends the
     * caller to the login page — a 302 to the homepage, which n8n cannot parse
     * and which hides the real error completely.
     *
     * Every one of these calls the plain get() helper rather than getJson(), so
     * no Accept header is sent at all.
     */

    public function test_a_missing_key_is_json_without_an_accept_header(): void
    {
        $response = $this->get('/api/v1/n8n/telegram/tasks?chat_id='.self::ADMIN_A);

        $response->assertUnauthorized();
        $response->assertHeader('content-type', 'application/json');
    }

    public function test_a_validation_error_is_json_without_an_accept_header(): void
    {
        $response = $this->get(
            '/api/v1/n8n/telegram/tasks?chat_id='.self::ADMIN_A.'&status=nonsense',
            ['X-N8n-Key' => 'test-n8n-key']
        );

        $response->assertStatus(422);
        $this->assertIsArray($response->json('errors'));
    }

    public function test_a_refusal_is_json_without_an_accept_header(): void
    {
        $response = $this->get(
            '/api/v1/n8n/telegram/tasks?chat_id='.self::PM_A.'&unit_id='.$this->secondUnitA->id,
            ['X-N8n-Key' => 'test-n8n-key']
        );

        $response->assertForbidden();
        $this->assertIsString($response->json('message'));
    }

    public function test_a_success_is_json_without_an_accept_header(): void
    {
        $response = $this->get(
            '/api/v1/n8n/telegram/tasks?chat_id='.self::ADMIN_A,
            ['X-N8n-Key' => 'test-n8n-key']
        );

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');
    }

    // ── The actor ────────────────────────────────────────────────────────────

    /** Answered exactly as /resolve answers it, so n8n handles one shape. */
    public function test_an_unlinked_chat_is_a_404_with_linked_false(): void
    {
        $this->tasks('7999999')
            ->assertNotFound()
            ->assertJson(['linked' => false]);
    }

    public function test_a_missing_chat_id_is_a_validation_error(): void
    {
        $this->getJson('/api/v1/n8n/telegram/tasks', ['X-N8n-Key' => 'test-n8n-key'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('chat_id');
    }

    /**
     * The commercial gate, asked of the person behind the chat rather than of
     * the pipeline — EnsureSubscriptionActive reads $request->user(), which is
     * null on a key-authenticated route, so it would wave everything through.
     */
    public function test_an_agency_below_pro_is_refused(): void
    {
        $this->subscribeOrganization($this->orgA['organization'], 'base');

        $this->tasks(self::ADMIN_A)->assertStatus(402);
    }
}
