<?php

namespace Tests\Feature\Tenancy;

use App\Models\AccountDeletionRequest;
use App\Models\DailyChatRequest;
use App\Models\Issue;
use App\Models\IssueReply;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\OrganizationSubscriptionPayment;
use App\Models\Payment;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskFile;
use App\Models\TaskNote;
use App\Models\Unit;
use App\Models\UnitStorageUsage;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Running the platform does not come with a right to read what the agencies
 * are working on.
 *
 * A superadmin used to be simply unscoped, which meant they saw everything.
 * Operational models now refuse them outright: not filtered to nothing by
 * accident, but returning no rows by design, for reads, aggregates, updates
 * and deletes alike.
 */
class SuperadminOperationalBlindnessTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $a;

    /** @var array<string, mixed> */
    protected array $b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = $this->populate($this->makeOrganization('org-a', 'Agency A'), 'A');
        $this->b = $this->populate($this->makeOrganization('org-b', 'Agency B'), 'B');
    }

    protected function superadmin(): User
    {
        return User::withoutGlobalScopes()->where('role', 'superadmin')->firstOrFail();
    }

    /**
     * Every model a superadmin must not be able to read, paired with a real
     * row of each so the assertions cannot pass by there being nothing there.
     *
     * @return array<string, array{0: class-string, 1: int}>
     */
    protected function forbiddenModels(): array
    {
        return [
            'tasks'               => [Task::class, $this->a['task']->id],
            'task files'          => [TaskFile::class, $this->a['file']->id],
            'task notes'          => [TaskNote::class, $this->a['note']->id],
            'task assignments'    => [TaskAssignment::class, $this->a['assignment']->id],
            'issues'              => [Issue::class, $this->a['issue']->id],
            'issue replies'       => [IssueReply::class, $this->a['reply']->id],
            'client payments'     => [Payment::class, $this->a['payment']->id],
            'chat requests'       => [DailyChatRequest::class, $this->a['chat']->id],
            'units'               => [Unit::class, $this->a['unit']->id],
        ];
    }

    public function test_the_rows_exist_for_someone(): void
    {
        // Guards everything below: these assertions are only meaningful
        // because the data is really in the database.
        foreach ([
            'tasks' => 2, 'task_files' => 2, 'task_notes' => 2, 'task_assignments' => 2,
            'issues' => 2, 'issue_replies' => 2, 'payments' => 2, 'daily_chat_requests' => 2,
            'units' => 2,
        ] as $table => $expected) {
            $this->assertSame($expected, DB::table($table)->count(), $table);
        }
    }

    public function test_a_superadmin_reads_no_operational_rows_at_all(): void
    {
        $this->actingAs($this->superadmin());

        foreach ($this->forbiddenModels() as $label => [$model, $realId]) {
            $this->assertSame(0, $model::count(), "{$label}: count must be zero");
            $this->assertNull($model::find($realId), "{$label}: find must return nothing");
            $this->assertTrue($model::all()->isEmpty(), "{$label}: all must be empty");
            $this->assertSame(0, $model::where('id', $realId)->count(), "{$label}: an explicit id must not help");
            $this->assertFalse($model::query()->exists(), "{$label}: exists must be false");
            $this->assertNull($model::first(), "{$label}: first must return nothing");
        }
    }

    public function test_a_superadmin_cannot_aggregate_operational_data(): void
    {
        $this->actingAs($this->superadmin());

        // Aggregates are the sneakiest leak: a sum tells you plenty without
        // returning a single row.
        $this->assertNull(Task::max('credit_amount'));
        $this->assertSame(0, (int) Task::sum('credit_amount'));
        $this->assertSame(0, (int) TaskFile::sum('file_size'));
        $this->assertSame(0, (int) Payment::sum('amount'));
        $this->assertSame(0, UnitStorageUsage::count());
        $this->assertSame(0, Notification::count());
        $this->assertSame(0, AccountDeletionRequest::count());
    }

    public function test_a_superadmin_cannot_write_to_operational_data(): void
    {
        $this->actingAs($this->superadmin());

        Task::query()->update(['status' => 'cancelled']);
        Task::query()->delete();

        // Both agencies' tasks are untouched and still present.
        $this->assertSame(2, DB::table('tasks')->count());
        $this->assertSame(0, DB::table('tasks')->where('status', 'cancelled')->count());
    }

    public function test_a_superadmin_cannot_reach_operational_data_through_relations(): void
    {
        $this->actingAs($this->superadmin());

        $organization = Organization::find($this->a['organization']->id);

        $this->assertSame(0, $organization->tasks()->count());
        $this->assertSame(0, $organization->units()->count());
    }

    public function test_a_superadmin_still_sees_organizations_users_and_billing(): void
    {
        $subscription = $this->giveSubscription($this->a['organization']);

        $this->actingAs($this->superadmin());

        // Organizations: the tenant root, never scoped.
        $this->assertSame(3, Organization::count(), 'both agencies plus the founding organization');

        // Users: platform visible, across every agency.
        $this->assertSame(7, User::count());
        $this->assertNotNull(User::find($this->a['admin']->id));
        $this->assertNotNull(User::find($this->b['admin']->id));

        // Billing: platform visible. Two — agency A's, plus the one the
        // enforcement migration seeds for the founding organization.
        $this->assertSame(2, OrganizationSubscription::count());
        $this->assertNotNull(OrganizationSubscription::find($subscription->id));
        $this->assertSame(2, OrganizationSubscriptionPayment::count());
    }

    /**
     * The blindness is a property of being an unconfined superadmin, not a
     * property of the models. Console commands and queued jobs still have to
     * be able to read everything, or the nightly storage reconcile would stop
     * working.
     */
    public function test_console_context_is_unaffected(): void
    {
        // Nobody authenticated, and no ambient organization either — the test
        // harness sets one so that fixtures have an owner, but a real console
        // command runs without it.
        TenantContext::useOrganization(null);

        $this->assertSame(2, Task::count());
        $this->assertSame(2, Unit::count());
    }

    public function test_run_without_scope_still_lifts_the_refusal(): void
    {
        $this->actingAs($this->superadmin());

        $this->assertSame(0, Task::count());

        TenantContext::runWithoutScope(function () {
            $this->assertSame(2, Task::count(), 'the explicit escape hatch still works');
        });

        $this->assertSame(0, Task::count(), 'and does not leak past the callback');
    }

    /**
     * A superadmin acting inside one organization is bound by it, exactly as
     * a member would be — the portal uses this to create an agency's first
     * admin.
     */
    public function test_a_superadmin_acting_into_an_organization_is_confined_to_it(): void
    {
        $this->actingAs($this->superadmin());

        TenantContext::actingAsOrganization($this->a['organization']->id, function () {
            $this->assertSame(1, Unit::count(), 'only agency A\'s unit');
            $this->assertNull(Unit::find($this->b['unit']->id));
            $this->assertSame(3, User::count(), 'only agency A\'s members');
        });
    }

    protected function giveSubscription(Organization $organization): OrganizationSubscription
    {
        return TenantContext::actingAsOrganization($organization->id, function () {
            $subscription = OrganizationSubscription::create([
                'plan'            => 'standard',
                'price'           => 99.00,
                'billing_cycle'   => 'monthly',
                'started_at'      => now()->subMonths(2),
                'next_renewal_at' => now()->addDays(12),
                'status'          => 'active',
            ]);

            foreach ([2, 1] as $monthsAgo) {
                OrganizationSubscriptionPayment::create([
                    'subscription_id' => $subscription->id,
                    'amount'          => 99.00,
                    'paid_at'         => now()->subMonths($monthsAgo),
                    'method'          => 'bank_transfer',
                ]);
            }

            return $subscription;
        });
    }
}
