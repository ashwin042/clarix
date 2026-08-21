<?php

namespace Tests\Feature\Api;

use App\Models\OrganizationSubscription;
use App\Models\Task;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * The isolation properties the endpoint rests on, with two real agencies.
 *
 * This is the suite that matters most for the API. The whole design — a token
 * that resolves to a real user rather than a bare key checked in middleware —
 * exists so that TenantContext has an organization to scope by. If that
 * premise ever breaks, these are the tests that say so.
 */
class ApiTenantIsolationTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    protected array $acme;
    protected array $globex;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);

        $this->acme   = $this->populate($this->makeOrganization('acme', 'Acme Agency'), 'acme');
        $this->globex = $this->populate($this->makeOrganization('globex', 'Globex Agency'), 'globex');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title'         => 'Cross tenant probe',
            'task_code'     => 'ISO_001',
            'priority'      => 'medium',
            'deadline'      => now()->addDays(7)->format('Y-m-d'),
            'credit_amount' => '1.00',
        ], $overrides);
    }

    public function test_task_is_stamped_with_the_tokens_organization(): void
    {
        Sanctum::actingAs($this->acme['pm'], ['tasks:create']);

        $this->postJson('/api/v1/tasks', $this->payload())->assertCreated();

        $task = TenantContext::runWithoutScope(fn () => Task::where('task_code', 'ISO_001')->first());

        $this->assertSame(
            (int) $this->acme['organization']->id,
            (int) $task->organization_id,
            'A task filed with an Acme token must belong to Acme.'
        );
    }

    /**
     * The reason TenantExists exists.
     *
     * assigned_admin_id is the one id in the payload that names another row,
     * so it is the one place a caller could try to reach across agencies. A
     * plain `exists:users,id` would accept Globex's admin here.
     */
    public function test_cannot_assign_an_admin_from_another_organization(): void
    {
        Sanctum::actingAs($this->acme['pm'], ['tasks:create']);

        $this->postJson('/api/v1/tasks', $this->payload([
            'assigned_admin_id' => $this->globex['admin']->id,
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['assigned_admin_id']);

        $this->assertNull(
            TenantContext::runWithoutScope(fn () => Task::where('task_code', 'ISO_001')->first())
        );
    }

    /**
     * Same task_code, two agencies, both accepted — uniqueness is per unit,
     * not global, and one agency's codes must not constrain another's.
     */
    public function test_same_task_code_is_accepted_in_two_organizations(): void
    {
        Sanctum::actingAs($this->acme['pm'], ['tasks:create']);
        $this->postJson('/api/v1/tasks', $this->payload())->assertCreated();

        Sanctum::actingAs($this->globex['pm'], ['tasks:create']);
        $this->postJson('/api/v1/tasks', $this->payload())->assertCreated();

        $codes = TenantContext::runWithoutScope(
            fn () => Task::where('task_code', 'ISO_001')->pluck('organization_id')->map(fn ($id) => (int) $id)->sort()->values()->all()
        );

        $this->assertSame([
            (int) $this->acme['organization']->id,
            (int) $this->globex['organization']->id,
        ], $codes);
    }

    /**
     * A suspended agency's integration must be refused, and must be refused in
     * a form it can read — the web path answers with the suspension page,
     * which an HTTP client cannot do anything with.
     */
    public function test_suspended_organization_gets_a_json_402(): void
    {
        TenantContext::actingAsOrganization($this->acme['organization']->id, function () {
            $subscription = new OrganizationSubscription([
                'plan'          => 'pro',
                'price'         => 1000,
                'billing_cycle' => 'monthly',
                'started_at'    => now()->subMonth()->toDateString(),
                'status'        => 'suspended',
            ]);
            $subscription->next_renewal_at = $subscription->renewalDateFrom(now()->subMonth());
            $subscription->save();
        });

        Sanctum::actingAs($this->acme['pm'], ['tasks:create']);

        $response = $this->postJson('/api/v1/tasks', $this->payload());

        $response->assertStatus(402)
            ->assertHeader('content-type', 'application/json')
            ->assertJsonPath('status', 'suspended');

        $this->assertNull(
            TenantContext::runWithoutScope(fn () => Task::where('task_code', 'ISO_001')->first())
        );
    }

    /**
     * The web path keeps its page. Pinned alongside the JSON case so the
     * content negotiation cannot quietly become "JSON for everyone".
     */
    public function test_browser_still_gets_the_suspension_page(): void
    {
        TenantContext::actingAsOrganization($this->acme['organization']->id, function () {
            $subscription = new OrganizationSubscription([
                'plan'          => 'pro',
                'price'         => 1000,
                'billing_cycle' => 'monthly',
                'started_at'    => now()->subMonth()->toDateString(),
                'status'        => 'suspended',
            ]);
            $subscription->next_renewal_at = $subscription->renewalDateFrom(now()->subMonth());
            $subscription->save();
        });

        $response = $this->actingAs($this->acme['pm'])->get(route('tasks.index'));

        $response->assertStatus(402);
        $this->assertStringContainsString('text/html', $response->headers->get('content-type'));
    }
}
