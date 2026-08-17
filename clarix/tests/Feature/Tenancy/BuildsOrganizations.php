<?php

namespace Tests\Feature\Tenancy;

use App\Models\DailyChatRequest;
use App\Models\Issue;
use App\Models\IssueReply;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Payment;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskFile;
use App\Models\TaskNote;
use App\Models\Unit;
use App\Models\User;
use App\Services\PlanFeatures;
use App\Services\TenantContext;

/**
 * Builds two fully populated, independent agencies for the isolation tests.
 *
 * Every record is created through the normal model path while the process is
 * acting as the owning organization, so the fixtures themselves exercise the
 * auto-fill rather than writing organization_id by hand. If auto-fill broke,
 * these helpers would produce unowned rows and the isolation assertions would
 * fail loudly instead of passing vacuously.
 */
trait BuildsOrganizations
{
    protected function makeOrganization(string $slug, string $name): Organization
    {
        return Organization::create([
            'name'              => $name,
            'contact_number'    => '0000000000',
            'email'             => "{$slug}@example.test",
            'address'           => 'Somewhere',
            'subscription_type' => 'base',
            'slug'              => $slug,
        ]);
    }

    /**
     * Put an organization on a plan.
     *
     * Deliberately *not* called from makeOrganization(). A subscription is
     * billing data, and several suites are about billing data — they count the
     * rows, assert an agency has none, or check that an empty organization can
     * be deleted. Creating one for every fixture broke all of those.
     *
     * So a test that needs a plan asks for one. Any suite touching ERP or the
     * AI pages needs 'pro', because without a subscription the plan layer
     * resolves the agency to 'base' and refuses.
     *
     * Backdated a month so that a subscription created later by a plan test,
     * starting today, is unambiguously the newer of the two — which is the row
     * PlanFeatures selects.
     */
    protected function subscribeOrganization(Organization $organization, string $plan = 'pro'): void
    {
        TenantContext::actingAsOrganization($organization->id, function () use ($plan) {
            $subscription = new OrganizationSubscription([
                'plan'          => $plan,
                'price'         => 1000,
                'billing_cycle' => 'monthly',
                'started_at'    => now()->subMonth()->toDateString(),
                'status'        => 'active',
            ]);
            $subscription->next_renewal_at = $subscription->renewalDateFrom(now()->subMonth());
            $subscription->save();
        });

        PlanFeatures::flush();
    }

    /**
     * One of everything, owned by the given organization.
     *
     * @return array<string, mixed>
     */
    protected function populate(Organization $organization, string $tag): array
    {
        return TenantContext::actingAsOrganization($organization->id, function () use ($organization, $tag) {
            $unit = Unit::create(['name' => "Unit {$tag}"]);

            $admin = User::factory()->create([
                'name'  => "Admin {$tag}",
                'email' => "admin.{$tag}@example.test",
                'role'  => 'admin',
            ]);

            $pm = User::factory()->create([
                'name'    => "PM {$tag}",
                'email'   => "pm.{$tag}@example.test",
                'role'    => 'pm',
                'unit_id' => $unit->id,
            ]);

            $writer = User::factory()->create([
                'name'  => "Writer {$tag}",
                'email' => "writer.{$tag}@example.test",
                'role'  => 'writer',
            ]);

            $task = Task::create([
                'title'         => "Task {$tag}",
                'task_code'     => "CODE_{$tag}",
                'unit_id'       => $unit->id,
                'created_by'    => $pm->id,
                'pm_id'         => $pm->id,
                'priority'      => 'medium',
                'status'        => 'pending',
                'deadline'      => now()->addDays(7),
                'credit_amount' => 10.00,
            ]);

            // Created through the ordinary path, events and all, so the
            // organization stamp is applied the same way a real upload would
            // apply it.
            $file = $task->files()->create([
                'file_path'     => "task-files/{$unit->id}/CODE_{$tag}/doc.pdf",
                'original_name' => 'doc.pdf',
                'file_size'     => 100,
                'mime_type'     => 'application/pdf',
                'uploaded_by'   => $pm->id,
            ]);

            $note = TaskNote::create([
                'task_id'    => $task->id,
                'note'       => "Note {$tag}",
                'created_by' => $pm->id,
            ]);

            $assignment = TaskAssignment::create([
                'task_id'     => $task->id,
                'writer_id'   => $writer->id,
                'assigned_by' => $admin->id,
                'status'      => 'pending',
            ]);

            $issue = Issue::create([
                'title'      => "Issue {$tag}",
                'message'    => 'Something is wrong.',
                'priority'   => 'low',
                'status'     => 'open',
                'created_by' => $pm->id,
            ]);

            $reply = IssueReply::create([
                'issue_id'   => $issue->id,
                'message'    => "Reply {$tag}",
                'created_by' => $admin->id,
            ]);

            $payment = Payment::create([
                'payer_name'     => "Payer {$tag}",
                'amount'         => 500.00,
                'total_credit'   => 500.00,
                'unit_id'        => $unit->id,
                'from_date'      => now()->subMonth(),
                'to_date'        => now(),
                'payment_method' => 'bank_transfer',
                'created_by'     => $admin->id,
            ]);

            $chat = DailyChatRequest::create([
                'user_id'       => $pm->id,
                'date'          => now()->toDateString(),
                'request_count' => 1,
            ]);

            return compact(
                'organization', 'unit', 'admin', 'pm', 'writer', 'task',
                'file', 'note', 'assignment', 'issue', 'reply', 'payment', 'chat'
            );
        });
    }
}
