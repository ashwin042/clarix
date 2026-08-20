<?php

namespace App\Console\Commands;

use App\Models\OrganizationSubscription;
use App\Services\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Walks every organization's subscription forward through the lifecycle.
 *
 *   active    -> past_due    the renewal date has passed
 *   past_due  -> suspended   the grace period has run out
 *
 * Both steps are driven by the calendar rather than by when this last ran, so
 * the command is idempotent and a missed night costs nobody anything: run it
 * twice in a day and the second run finds nothing, skip it for a week and it
 * still lands on exactly the rows that should have moved.
 *
 * Nothing here reverses a transition. Going back to active is a decision a
 * superadmin makes, by renewing or reactivating, and an automated job that
 * could undo that would make the manual override unreliable.
 */
class EnforceSubscriptionStatuses extends Command
{
    protected $signature = 'subscriptions:enforce
                            {--dry-run : Report the transitions without writing them}';

    protected $description = 'Move lapsed subscriptions to past_due, and out-of-grace ones to suspended';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->comment('Dry run — no statuses will be changed.');
        }

        $graceDays = (int) config('subscription.grace_days');

        // Runs from the console with nobody authenticated, so no scope is in
        // play — but stated explicitly, because this job is meant to see every
        // organization and that should not rest on an accident of context.
        [$suspended, $pastDue] = TenantContext::runWithoutScope(function () use ($dryRun) {
            // Out of grace first. Doing it in this order means a subscription
            // that lapsed long ago is not moved to past_due and then suspended
            // by the same run, which would log two transitions for one event.
            $suspended = $this->transition(
                OrganizationSubscription::query()->outOfGrace()->get(),
                'suspended',
                $dryRun
            );

            $pastDue = $this->transition(
                OrganizationSubscription::query()->lapsed()->get(),
                'past_due',
                $dryRun
            );

            return [$suspended, $pastDue];
        });

        $this->report($pastDue, $suspended, $graceDays, $dryRun);

        return self::SUCCESS;
    }

    /**
     * Move a set of subscriptions to the given status, recording each change.
     *
     * @param  \Illuminate\Support\Collection<int, OrganizationSubscription>  $subscriptions
     * @return list<array{organization: string, from: string, to: string, renewal: ?string}>
     */
    protected function transition($subscriptions, string $to, bool $dryRun): array
    {
        $changes = [];

        foreach ($subscriptions as $subscription) {
            $from = $subscription->status;

            // The organization is read without a scope for the same reason the
            // rest of this runs unscoped: the job spans every tenant.
            $organization = $subscription->organization()->withoutGlobalScopes()->first();

            if (! $dryRun) {
                $subscription->forceFill(['status' => $to])->save();
            }

            Log::info('Subscription status changed by enforcement.', [
                'organization_id'   => $subscription->organization_id,
                'organization_name' => $organization?->name,
                'subscription_id'   => $subscription->id,
                'from'              => $from,
                'to'                => $to,
                'next_renewal_at'   => $subscription->next_renewal_at?->toDateString(),
                'dry_run'           => $dryRun,
                'timestamp'         => now()->toIso8601String(),
            ]);

            $changes[] = [
                'organization' => $organization?->name ?? "#{$subscription->organization_id}",
                'from'         => $from,
                'to'           => $to,
                'renewal'      => $subscription->next_renewal_at?->toDateString(),
            ];
        }

        return $changes;
    }

    /**
     * Print a summary for whoever is watching the scheduler output.
     *
     * @param  list<array{organization: string, from: string, to: string, renewal: ?string}>  $pastDue
     * @param  list<array{organization: string, from: string, to: string, renewal: ?string}>  $suspended
     */
    protected function report(array $pastDue, array $suspended, int $graceDays, bool $dryRun): void
    {
        $all = [...$pastDue, ...$suspended];

        if ($all === []) {
            $this->info('Every subscription is where it should be; nothing to change.');

            return;
        }

        $this->table(
            ['Organization', 'From', 'To', 'Renewal was due'],
            array_map(fn (array $row) => [
                $row['organization'],
                $row['from'],
                $row['to'],
                $row['renewal'] ?? '—',
            ], $all)
        );

        $verb = $dryRun ? 'would move' : 'moved';

        $this->info(sprintf(
            '%s %d subscription(s) to past_due and %d to suspended (grace period %d days).',
            ucfirst($verb),
            count($pastDue),
            count($suspended),
            $graceDays
        ));

        if ($suspended !== []) {
            $this->warn(count($suspended).' organization(s) now have access blocked until a superadmin reactivates them.');
        }
    }
}
