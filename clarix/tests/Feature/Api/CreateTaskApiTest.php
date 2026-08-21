<?php

namespace Tests\Feature\Api;

use App\Models\Task;
use App\Models\User;
use App\Notifications\NewTaskCreatedNotification;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * The endpoint an external service posts a task to.
 *
 * Users are built through BuildsOrganizations rather than a bare
 * User::factory()->create(): the factory sets neither role nor
 * organization_id, which is the root cause of several long-standing failures
 * elsewhere in the suite and would make these tests fail for reasons that have
 * nothing to do with the API.
 */
class CreateTaskApiTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    protected array $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);

        $organization = $this->makeOrganization('acme', 'Acme Agency');
        $this->org    = $this->populate($organization, 'acme');
    }

    /** A valid body for the endpoint. */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title'         => 'Filed by robot',
            'task_code'     => 'API_001',
            'priority'      => 'medium',
            'deadline'      => now()->addDays(7)->format('Y-m-d'),
            'credit_amount' => '3.50',
        ], $overrides);
    }

    private function serviceAccount(): User
    {
        return $this->org['pm'];
    }

    public function test_service_account_can_create_a_task(): void
    {
        Sanctum::actingAs($this->serviceAccount(), ['tasks:create']);

        $response = $this->postJson('/api/v1/tasks', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.task_code', 'API_001')
            ->assertJsonPath('data.title', 'Filed by robot')
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_created_task_belongs_to_the_token_owner_and_their_unit(): void
    {
        $account = $this->serviceAccount();

        Sanctum::actingAs($account, ['tasks:create']);

        $this->postJson('/api/v1/tasks', $this->payload())->assertCreated();

        $task = TenantContext::runWithoutScope(fn () => Task::where('task_code', 'API_001')->first());

        $this->assertNotNull($task);
        $this->assertSame((int) $account->id, (int) $task->pm_id);
        $this->assertSame((int) $account->id, (int) $task->created_by);
        $this->assertSame((int) $account->unit_id, (int) $task->unit_id);
        $this->assertSame((int) $account->organization_id, (int) $task->organization_id);
        $this->assertSame('pending', $task->status);
    }

    /**
     * The payload must not be able to say whose the task is. These are the
     * three columns the service takes from the actor instead, so a caller that
     * sends them should be ignored rather than obeyed.
     */
    public function test_payload_cannot_override_ownership_fields(): void
    {
        $account = $this->serviceAccount();
        $other   = $this->org['admin'];

        Sanctum::actingAs($account, ['tasks:create']);

        $this->postJson('/api/v1/tasks', $this->payload([
            'pm_id'      => $other->id,
            'created_by' => $other->id,
            'unit_id'    => 99999,
            'status'     => 'completed',
        ]))->assertCreated();

        $task = TenantContext::runWithoutScope(fn () => Task::where('task_code', 'API_001')->first());

        $this->assertSame((int) $account->id, (int) $task->pm_id);
        $this->assertSame((int) $account->id, (int) $task->created_by);
        $this->assertSame((int) $account->unit_id, (int) $task->unit_id);
        $this->assertSame('pending', $task->status);
    }

    public function test_admins_are_notified_exactly_as_they_are_from_the_screen(): void
    {
        Notification::fake();

        Sanctum::actingAs($this->serviceAccount(), ['tasks:create']);

        $this->postJson('/api/v1/tasks', $this->payload())->assertCreated();

        Notification::assertSentTo([$this->org['admin']], NewTaskCreatedNotification::class);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->postJson('/api/v1/tasks', $this->payload())->assertUnauthorized();
    }

    public function test_token_without_the_ability_is_rejected(): void
    {
        Sanctum::actingAs($this->serviceAccount(), ['something:else']);

        $this->postJson('/api/v1/tasks', $this->payload())->assertForbidden();
    }

    public function test_account_without_a_unit_is_rejected(): void
    {
        $account = $this->org['writer'];   // role writer, and no unit
        $account->forceFill(['role' => 'pm', 'unit_id' => null])->save();

        Sanctum::actingAs($account, ['tasks:create']);

        $this->postJson('/api/v1/tasks', $this->payload())->assertForbidden();
    }

    public function test_validation_errors_come_back_as_json(): void
    {
        Sanctum::actingAs($this->serviceAccount(), ['tasks:create']);

        $this->postJson('/api/v1/tasks', $this->payload([
            'title'         => '',
            'task_code'     => '',
            'priority'      => 'urgent',
            'credit_amount' => '-5',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'task_code', 'priority', 'credit_amount']);
    }

    /**
     * The per-unit uniqueness rule reaches the endpoint through the same
     * TaskCreationService::rules() the screen uses.
     */
    public function test_task_code_must_be_unique_within_the_unit(): void
    {
        Sanctum::actingAs($this->serviceAccount(), ['tasks:create']);

        $this->postJson('/api/v1/tasks', $this->payload())->assertCreated();

        $this->postJson('/api/v1/tasks', $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['task_code']);
    }

    public function test_assigned_admin_must_be_an_admin(): void
    {
        Sanctum::actingAs($this->serviceAccount(), ['tasks:create']);

        $this->postJson('/api/v1/tasks', $this->payload([
            'assigned_admin_id' => $this->org['writer']->id,
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['assigned_admin_id']);
    }
}
