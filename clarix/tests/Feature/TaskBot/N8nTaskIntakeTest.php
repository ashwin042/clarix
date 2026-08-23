<?php

namespace Tests\Feature\TaskBot;

use App\Models\OrganizationSubscription;
use App\Models\Task;
use App\Models\User;
use App\Notifications\NewTaskCreatedNotification;
use App\Services\N8nTelegramLinkService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * POST /api/v1/n8n/telegram/tasks — what a PM submits from Telegram.
 *
 * The chat id, not a token, is what names the actor here, so most of these
 * tests are really about one question: does the endpoint file the task as the
 * right person, in the right agency, and refuse everybody else.
 */
class N8nTaskIntakeTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    protected N8nTelegramLinkService $links;

    /** @var array<string, mixed> */
    protected array $orgA;

    /** @var array<string, mixed> */
    protected array $orgB;

    protected const CHAT_A = '5000001';

    protected const CHAT_B = '5000002';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);

        config()->set('services.n8n.key', 'test-n8n-key');

        $this->links = app(N8nTelegramLinkService::class);
        $this->orgA  = $this->populate($this->makeOrganization('ti-a', 'Agency A'), 'A');
        $this->orgB  = $this->populate($this->makeOrganization('ti-b', 'Agency B'), 'B');

        $this->subscribeOrganization($this->orgA['organization'], 'pro');
        $this->subscribeOrganization($this->orgB['organization'], 'pro');

        $this->link($this->orgA['pm'], self::CHAT_A);
        $this->link($this->orgB['pm'], self::CHAT_B);
    }

    private function link(User $user, string $chatId): void
    {
        $this->links->verify($this->links->issueCode($user), $chatId);
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'chat_id'       => self::CHAT_A,
            'title'         => 'Filed from Telegram',
            'task_code'     => 'BOT_001',
            'priority'      => 'medium',
            'deadline'      => now()->addDays(7)->format('Y-m-d'),
            'credit_amount' => '3.50',
        ], $overrides);
    }

    /** @param array<string, mixed> $body */
    private function file(array $body)
    {
        return $this->postJson('/api/v1/n8n/telegram/tasks', $body, ['X-N8n-Key' => 'test-n8n-key']);
    }

    // ── The happy path ───────────────────────────────────────────────────────

    public function test_a_linked_pm_can_file_a_task(): void
    {
        $this->file($this->payload())
            ->assertCreated()
            ->assertJsonPath('task_code', 'BOT_001')
            ->assertJsonPath('title', 'Filed from Telegram')
            ->assertJsonPath('status', 'pending');
    }

    /** Flat, like the identity envelope, and for the same reason. */
    public function test_the_response_is_flat_and_carries_the_id_the_attach_call_needs(): void
    {
        $body = $this->file($this->payload())->assertCreated()->json();

        $this->assertArrayNotHasKey('data', $body);
        $this->assertArrayHasKey('id', $body);
        $this->assertIsInt($body['id']);

        // Tenancy bookkeeping stays internal.
        $this->assertArrayNotHasKey('organization_id', $body);
    }

    /**
     * The whole point of ResolveN8nActor. No user is authenticated, so without
     * the acting-as context Task::create() would stamp organization_id null —
     * a row belonging to nobody that OrganizationScope never filters and every
     * agency's task list may show.
     */
    public function test_the_task_is_stamped_with_the_chat_owners_organization(): void
    {
        $id = $this->file($this->payload())->assertCreated()->json('id');

        $task = TenantContext::runWithoutScope(fn () => Task::find($id));

        $this->assertNotNull($task->organization_id);
        $this->assertSame((int) $this->orgA['pm']->organization_id, (int) $task->organization_id);
    }

    public function test_the_task_belongs_to_the_chat_owner_and_their_unit(): void
    {
        $id = $this->file($this->payload())->assertCreated()->json('id');

        $task = TenantContext::runWithoutScope(fn () => Task::find($id));
        $pm   = $this->orgA['pm'];

        $this->assertSame((int) $pm->id, (int) $task->pm_id);
        $this->assertSame((int) $pm->id, (int) $task->created_by);
        $this->assertSame((int) $pm->unit_id, (int) $task->unit_id);
        $this->assertSame('pending', $task->status);
    }

    /**
     * The security property the service exists to hold: the bot says what the
     * task is, the resolved person says whose it is.
     */
    public function test_the_payload_cannot_override_ownership_fields(): void
    {
        $intruder = $this->orgB['pm'];

        $id = $this->file($this->payload([
            'task_code'       => 'BOT_OVERRIDE',
            'pm_id'           => $intruder->id,
            'created_by'      => $intruder->id,
            'unit_id'         => $this->orgB['unit']->id,
            'organization_id' => $this->orgB['organization']->id,
            'status'          => 'completed',
        ]))->assertCreated()->json('id');

        $task = TenantContext::runWithoutScope(fn () => Task::find($id));
        $pm   = $this->orgA['pm'];

        $this->assertSame((int) $pm->id, (int) $task->pm_id);
        $this->assertSame((int) $pm->id, (int) $task->created_by);
        $this->assertSame((int) $pm->unit_id, (int) $task->unit_id);
        $this->assertSame((int) $pm->organization_id, (int) $task->organization_id);
        $this->assertSame('pending', $task->status);
    }

    /** assigned_admin_id is not part of what the bot submits. */
    public function test_the_payload_cannot_assign_an_admin(): void
    {
        $id = $this->file($this->payload([
            'assigned_admin_id' => $this->orgA['admin']->id,
        ]))->assertCreated()->json('id');

        $task = TenantContext::runWithoutScope(fn () => Task::find($id));

        $this->assertNull($task->assigned_admin_id);
    }

    /**
     * notifyAdmins() queries the tenant-scoped User model. Unscoped it would
     * tell every admin on the platform about one agency's task.
     */
    public function test_only_the_actors_own_admins_are_notified(): void
    {
        Notification::fake();

        $this->file($this->payload())->assertCreated();

        Notification::assertSentTo($this->orgA['admin'], NewTaskCreatedNotification::class);
        Notification::assertNotSentTo($this->orgB['admin'], NewTaskCreatedNotification::class);
    }

    public function test_optional_fields_are_accepted(): void
    {
        $id = $this->file($this->payload([
            'task_type'       => 'content',
            'important_notes' => 'Client wants British spelling.',
        ]))->assertCreated()->json('id');

        $task = TenantContext::runWithoutScope(fn () => Task::find($id));

        $this->assertSame('content', $task->task_type);
        $this->assertSame('Client wants British spelling.', $task->important_notes);
    }

    // ── Who may file ─────────────────────────────────────────────────────────

    public function test_an_unauthenticated_request_is_refused(): void
    {
        $this->postJson('/api/v1/n8n/telegram/tasks', $this->payload())
            ->assertUnauthorized();
    }

    public function test_an_unlinked_chat_cannot_file(): void
    {
        $this->file($this->payload(['chat_id' => '9999999']))
            ->assertNotFound()
            ->assertJsonPath('linked', false);

        $this->assertSame(0, TenantContext::runWithoutScope(fn () => Task::query()->where('task_code', 'BOT_001')->count()));
    }

    /** A disconnected link must stop filing work, not keep it flowing. */
    public function test_a_disconnected_chat_cannot_file(): void
    {
        $this->links->unlink($this->orgA['pm']);

        $this->file($this->payload())->assertNotFound();
    }

    public function test_a_missing_chat_id_is_refused(): void
    {
        $body = $this->payload();
        unset($body['chat_id']);

        $this->file($body)
            ->assertStatus(422)
            ->assertJsonValidationErrors('chat_id');
    }

    public function test_a_malformed_chat_id_is_refused(): void
    {
        $this->file($this->payload(['chat_id' => 'not-a-chat']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('chat_id');
    }

    /**
     * The permission is asked of the person behind the chat, not of the key. A
     * writer who links their Telegram does not thereby become able to file
     * tasks.
     */
    public function test_a_role_without_the_permission_cannot_file(): void
    {
        $writer = $this->orgA['writer'];
        $this->link($writer, '5000003');

        $this->file($this->payload(['chat_id' => '5000003', 'task_code' => 'BOT_WRITER']))
            ->assertForbidden();

        $this->assertSame(0, TenantContext::runWithoutScope(fn () => Task::query()->where('task_code', 'BOT_WRITER')->count()));
    }

    /** Revoking the permission has to take effect without touching the bot. */
    public function test_revoking_the_permission_stops_filing_immediately(): void
    {
        $this->file($this->payload())->assertCreated();

        TenantContext::actingAsOrganization($this->orgA['organization']->id, function () {
            // What the Authorization panel does: flips the flag rather than
            // deleting the row.
            \App\Models\RolePermission::query()
                ->where('role', 'pm')
                ->whereHas('permission', fn ($q) => $q->where('name', 'tasks.create'))
                ->update(['allowed' => false]);
        });

        \App\Services\PermissionService::flushFor('pm', (int) $this->orgA['organization']->id);

        $this->file($this->payload(['task_code' => 'BOT_002']))->assertForbidden();
    }

    /** Somebody with no unit has nowhere to file the work. */
    public function test_an_actor_without_a_unit_cannot_file(): void
    {
        $admin = $this->orgA['admin'];
        $this->link($admin, '5000004');

        $this->assertNull($admin->unit_id);

        $this->file($this->payload(['chat_id' => '5000004', 'task_code' => 'BOT_NOUNIT']))
            ->assertForbidden();
    }

    // ── Commercial gates ─────────────────────────────────────────────────────

    public function test_a_suspended_organization_cannot_file(): void
    {
        TenantContext::actingAsOrganization($this->orgA['organization']->id, function () {
            OrganizationSubscription::query()->update(['status' => 'suspended']);
        });

        $this->file($this->payload())->assertStatus(402);
    }

    public function test_an_organization_without_the_plan_cannot_file(): void
    {
        $this->subscribeOrganization($this->orgA['organization'], 'base');

        $this->file($this->payload())->assertStatus(402);
    }

    // ── Validation ───────────────────────────────────────────────────────────

    public function test_validation_errors_come_back_as_json(): void
    {
        $this->file(['chat_id' => self::CHAT_A])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'task_code', 'priority', 'deadline', 'credit_amount']);
    }

    public function test_the_task_code_must_be_unique_within_the_unit(): void
    {
        $this->file($this->payload())->assertCreated();

        $this->file($this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('task_code');
    }

    /**
     * The nearest thing this endpoint has to an idempotency key. A replayed
     * create is answered by the schema rather than by the transport, which is
     * worth knowing given that a static shared key leaves requests replayable —
     * see EnsureN8nRequest.
     */
    public function test_a_replayed_create_does_not_produce_a_second_task(): void
    {
        $payload = $this->payload();

        $this->file($payload)->assertCreated();
        $this->file($payload)->assertStatus(422);

        $this->assertSame(
            1,
            TenantContext::runWithoutScope(fn () => Task::query()->where('task_code', 'BOT_001')->count())
        );
    }

    /**
     * The same code in two agencies is two different tasks. task_code is unique
     * per unit, never per platform.
     */
    public function test_the_same_task_code_is_accepted_in_two_agencies(): void
    {
        $this->file($this->payload())->assertCreated();
        $this->file($this->payload(['chat_id' => self::CHAT_B]))->assertCreated();

        $this->assertSame(
            2,
            TenantContext::runWithoutScope(fn () => Task::query()->where('task_code', 'BOT_001')->count())
        );
    }

    public function test_an_invalid_priority_is_refused(): void
    {
        $this->file($this->payload(['priority' => 'urgent']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('priority');
    }

    public function test_a_negative_credit_is_refused(): void
    {
        $this->file($this->payload(['credit_amount' => '-1']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('credit_amount');
    }

    // ── Throttling ───────────────────────────────────────────────────────────

    /**
     * Keyed on the chat, not the IP: every intake call arrives from the same
     * n8n host, so an IP key would be one bucket shared by every agency and a
     * busy team would lock out the platform.
     */
    public function test_intake_is_throttled_per_chat_rather_than_per_host(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->file($this->payload(['task_code' => 'BOT_T'.$i]))->assertCreated();
        }

        $this->file($this->payload(['task_code' => 'BOT_OVER']))->assertStatus(429);

        // A different chat, same host, is unaffected.
        $this->file($this->payload(['chat_id' => self::CHAT_B, 'task_code' => 'BOT_B']))
            ->assertCreated();
    }

    /** Intake must not spend the link endpoints' allowance either. */
    public function test_intake_does_not_share_a_bucket_with_resolve(): void
    {
        for ($i = 0; $i < 31; $i++) {
            $this->file($this->payload(['task_code' => 'BOT_S'.$i]));
        }

        $this->file($this->payload(['task_code' => 'BOT_SOVER']))->assertStatus(429);

        $this->postJson('/api/v1/n8n/telegram/resolve', ['chat_id' => self::CHAT_A], [
            'X-N8n-Key' => 'test-n8n-key',
        ])->assertOk();
    }

    /**
     * The throttle sits in front of the actor middleware, not behind it.
     * Behind, a caller probing chat ids that are not linked would be answered
     * 404 without ever touching the limiter — and counting the refusals is the
     * point of having one.
     */
    public function test_probing_unlinked_chats_is_throttled_too(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->file($this->payload(['chat_id' => '7777777']))->assertNotFound();
        }

        $this->file($this->payload(['chat_id' => '7777777']))->assertStatus(429);
    }
}
