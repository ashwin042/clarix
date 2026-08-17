<?php

namespace App\Livewire\Superadmin;

use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\OrganizationSubscriptionPayment;
use App\Models\User;
use App\Services\OrganizationTeardown;
use App\Services\TenantContext;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * An overview of one agency's record: who belongs to it, what it pays Clarix,
 * and whether it could be removed — plus the two forms that record its
 * billing.
 *
 * Everything here is either the organization's own record, its member list, or
 * its billing with the platform. It shows no tasks, files, notes, issues,
 * client payments or unit structure, and it cannot: those models refuse to
 * return rows to a superadmin at all, so an accidental reference would render
 * empty rather than leak.
 */
class OrganizationDetail extends Component
{
    public Organization $organization;

    public bool $showSubscriptionModal = false;

    public bool $showPaymentModal = false;

    // Subscription form.
    public string $plan = 'base';

    public string $price = '';

    public string $billing_cycle = 'monthly';

    public string $started_at = '';

    public string $status = 'active';

    /**
     * A storage allowance set by hand, in gigabytes.
     *
     * Blank means "use the plan's own cap" — see the migration that adds the
     * column for why blank and zero are different answers. Lives on the
     * subscription form because it is part of the same commercial
     * conversation: the Pro extra-storage arrangement is agreed alongside the
     * plan, not separately from it.
     */
    public string $storage_cap_override_gb = '';

    // Payment form.
    public string $amount = '';

    public string $paid_at = '';

    public string $method = 'bank_transfer';

    public function mount(Organization $organization): void
    {
        $this->authorizeSuperadmin();

        $this->organization = $organization;
    }

    protected function authorizeSuperadmin(): void
    {
        abort_unless(auth()->user()?->isSuperadmin(), 403);
    }

    /**
     * The plan currently in force for the organization being viewed.
     *
     * Filtered by organization explicitly. A superadmin's reads are not
     * confined to any one agency, so without the filter this would find
     * whichever subscription happened to be newest on the platform.
     */
    protected function currentSubscription(): ?OrganizationSubscription
    {
        return OrganizationSubscription::query()
            ->where('organization_id', $this->organization->id)
            ->latest('started_at')
            ->first();
    }

    public function openSubscription(): void
    {
        $this->authorizeSuperadmin();

        $subscription = $this->currentSubscription();

        if ($subscription) {
            $this->plan          = $subscription->plan;
            $this->price         = (string) $subscription->price;
            $this->billing_cycle = $subscription->billing_cycle;
            $this->started_at    = $subscription->started_at?->format('Y-m-d') ?? '';
            $this->status        = $subscription->status;
        } else {
            // Sensible starting point for a new plan: the organization's tier,
            // starting today.
            $this->plan          = $this->organization->subscription_type;
            $this->price         = '';
            $this->billing_cycle = 'monthly';
            $this->started_at    = now()->format('Y-m-d');
            $this->status        = 'active';
        }

        // Read from the organization either way: the override is a property of
        // the agency, not of any one subscription row.
        $this->storage_cap_override_gb = (string) ($this->organization->storage_cap_override_gb ?? '');

        $this->resetErrorBag();
        $this->showSubscriptionModal = true;
    }

    public function saveSubscription(): void
    {
        $this->authorizeSuperadmin();

        $data = $this->validate([
            'plan'          => ['required', Rule::in(Organization::SUBSCRIPTION_TYPES)],
            'price'         => ['required', 'numeric', 'min:0'],
            'billing_cycle' => ['required', Rule::in(OrganizationSubscription::BILLING_CYCLES)],
            'started_at'    => ['required', 'date'],
            'status'        => ['required', Rule::in(OrganizationSubscription::STATUSES)],

            // Nullable: an empty box means "use the plan's cap". min:1 because
            // zero would read as "no allowance at all", which is not something
            // this form should be able to say by accident.
            'storage_cap_override_gb' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        $existing = $this->currentSubscription();

        // The organization is named explicitly rather than inferred. A
        // superadmin belongs to no organization, so an unqualified create
        // would leave organization_id null and the NOT NULL column would
        // reject it — the same case CreateOrganizationAdmin handles.
        TenantContext::actingAsOrganization($this->organization->id, function () use ($data, $existing) {
            $subscription = $existing ?: new OrganizationSubscription();

            $subscription->fill($data);

            // Derived, never typed in: one cycle from the date it starts. The
            // model owns that sum so the form, the renew action and the
            // reactivate action cannot drift apart on what a cycle is.
            $subscription->next_renewal_at = $subscription->renewalDateFrom($data['started_at']);

            $subscription->save();
        });

        /*
         * The subscription is the only source of truth for the plan; this
         * keeps the legacy label on the organization from drifting away from
         * it again. One writer, so the two cannot disagree — they already had
         * once, when a second superadmin screen was also writing the column.
         *
         * The storage override rides along because it is agreed in the same
         * conversation as the plan.
         */
        $override = $data['storage_cap_override_gb'] ?? null;

        $this->organization->forceFill([
            'subscription_type'       => $data['plan'],
            'storage_cap_override_gb' => ($override === null || $override === '')
                ? null
                : (int) $override,
        ])->save();

        $this->showSubscriptionModal = false;

        $this->dispatch(
            'notify',
            message: $existing ? 'Subscription updated.' : 'Subscription created.',
            type: 'success'
        );
    }

    /**
     * Record that another cycle has been paid for.
     *
     * The money itself arrives outside the system, so this is the superadmin
     * confirming it: the renewal moves on by one cycle and the status returns
     * to active, whatever it had drifted to.
     */
    public function renewSubscription(): void
    {
        $this->authorizeSuperadmin();

        $subscription = $this->currentSubscription();

        if (! $subscription) {
            $this->dispatch('notify', message: 'Set up a subscription first.', type: 'error');

            return;
        }

        $subscription->renew();

        $this->dispatch(
            'notify',
            message: 'Renewed — next renewal '.$subscription->next_renewal_at->format('d M Y').'.',
            type: 'success'
        );
    }

    /**
     * Lift a suspension by hand, without recording a payment.
     */
    public function reactivateSubscription(): void
    {
        $this->authorizeSuperadmin();

        $subscription = $this->currentSubscription();

        if (! $subscription) {
            $this->dispatch('notify', message: 'Set up a subscription first.', type: 'error');

            return;
        }

        $subscription->reactivate();

        $this->dispatch(
            'notify',
            message: 'Reactivated — next renewal '.$subscription->next_renewal_at->format('d M Y').'.',
            type: 'success'
        );
    }

    /**
     * Block an organization by hand, independently of the nightly job — for a
     * non-payment settled through some other arrangement.
     */
    public function suspendSubscription(): void
    {
        $this->authorizeSuperadmin();

        $subscription = $this->currentSubscription();

        if (! $subscription) {
            $this->dispatch('notify', message: 'Set up a subscription first.', type: 'error');

            return;
        }

        $subscription->suspend();

        $this->dispatch('notify', message: 'Organization suspended — its users are now blocked.', type: 'success');
    }

    public function openPayment(): void
    {
        $this->authorizeSuperadmin();

        $subscription = $this->currentSubscription();

        $this->amount  = $subscription ? (string) $subscription->price : '';
        $this->paid_at = now()->format('Y-m-d\TH:i');
        $this->method  = 'bank_transfer';

        $this->resetErrorBag();
        $this->showPaymentModal = true;
    }

    public function savePayment(): void
    {
        $this->authorizeSuperadmin();

        $subscription = $this->currentSubscription();

        // A payment hangs off a subscription, so there has to be one. The form
        // is not offered without it, but the action is guarded too.
        if (! $subscription) {
            $this->showPaymentModal = false;
            $this->dispatch('notify', message: 'Record a subscription before logging a payment.', type: 'error');

            return;
        }

        $data = $this->validate([
            'amount'  => ['required', 'numeric', 'min:0.01'],
            'paid_at' => ['required', 'date'],
            'method'  => ['nullable', 'string', 'max:50'],
        ]);

        TenantContext::actingAsOrganization($this->organization->id, function () use ($data, $subscription) {
            OrganizationSubscriptionPayment::create([
                'subscription_id' => $subscription->id,
                'amount'          => $data['amount'],
                'paid_at'         => $data['paid_at'],
                'method'          => $data['method'] ?: null,
            ]);
        });

        $this->showPaymentModal = false;
        $this->reset(['amount', 'paid_at', 'method']);

        $this->dispatch('notify', message: 'Payment recorded.', type: 'success');
    }

    public function render()
    {
        $this->authorizeSuperadmin();

        $teardown       = app(OrganizationTeardown::class);
        $organizationId = $this->organization->id;

        $users = User::query()
            ->where('organization_id', $organizationId)
            ->orderBy('role')
            ->orderBy('name')
            ->get();

        $subscription = $this->currentSubscription();

        $payments = OrganizationSubscriptionPayment::query()
            ->where('organization_id', $organizationId)
            ->latest('paid_at')
            ->get();

        $blockers = $teardown->blockers($this->organization);

        return view('livewire.superadmin.organization-detail', [
            'users'          => $users,
            'userCount'      => $users->count(),
            'roleCounts'     => $users->groupBy('role')->map->count()->all(),
            'subscription'   => $subscription,
            'payments'       => $payments,
            'totalPaid'      => $payments->sum('amount'),
            'blockers'       => $blockers,
            'blockerSummary' => $blockers ? $teardown->describe($blockers) : null,
            'plans'          => Organization::SUBSCRIPTION_TYPES,
            'cycles'         => OrganizationSubscription::BILLING_CYCLES,
            'statuses'       => OrganizationSubscription::STATUSES,
        ])->layout('layouts.superadmin', ['pageTitle' => $this->organization->name]);
    }
}
