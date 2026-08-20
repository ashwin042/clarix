<?php

namespace App\Livewire\Finance;

use App\Models\OrganizationSubscription;
use App\Models\OrganizationSubscriptionPayment;
use Livewire\Component;

/**
 * This organization's own billing with Clarix: what it is on, when it renews,
 * and everything it has paid.
 *
 * Nothing here filters by organization explicitly, and it does not need to.
 * Both models are tenant-scoped, so an admin's queries are already confined to
 * their own agency by the global scope — another organization's subscription
 * is not something this screen could show even if it were asked to.
 */
class Subscription extends Component
{
    public function render()
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $subscription = OrganizationSubscription::query()->latest('started_at')->first();

        // The full history, not just recent lines: an organization is entitled
        // to the complete record of what it has paid.
        $payments = OrganizationSubscriptionPayment::query()->latest('paid_at')->get();

        return view('livewire.finance.subscription', [
            'organization' => auth()->user()->organization,
            'subscription' => $subscription,
            'payments'     => $payments,
            'totalPaid'    => $payments->sum('amount'),
        ])->layout('layouts.app', ['pageTitle' => 'Subscription']);
    }
}
