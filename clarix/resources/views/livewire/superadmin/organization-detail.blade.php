<div>
    <div class="mb-6">
        <a href="{{ route('superadmin.organizations.index') }}"
            class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-amber-400 transition-colors mb-3">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            All organizations
        </a>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-white">{{ $organization->name }}</h1>
                <p class="text-sm text-slate-500 font-mono mt-0.5">{{ $organization->slug }}</p>
            </div>
            <a href="{{ route('superadmin.organizations.admin', $organization) }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-amber-500 text-amber-950 text-sm font-semibold rounded-lg hover:bg-amber-400 transition-colors">
                Add administrator
            </a>
        </div>
    </div>

    {{-- Headline figures. Members and billing only: this portal has no
         visibility of what the agency is working on. --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        @foreach([
            'Tier'    => ucfirst($organization->subscription_type),
            'Members' => $userCount,
            'Plan'    => $subscription ? ucfirst($subscription->plan) : '—',
            'Status'  => $subscription ? str_replace('_', ' ', $subscription->status) : 'No subscription',
        ] as $label => $value)
            <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-4">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $label }}</p>
                <p class="text-2xl font-bold text-white mt-1 capitalize">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Record --}}
        <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-400 mb-4">Organization record</h2>
            <dl class="space-y-3 text-sm">
                @foreach([
                    'Contact number' => $organization->contact_number,
                    'Email'          => $organization->email,
                    'Address'        => $organization->address,
                    'Created'        => $organization->created_at?->format('d M Y, H:i'),
                ] as $label => $value)
                    <div class="flex justify-between gap-6">
                        <dt class="text-slate-500 shrink-0">{{ $label }}</dt>
                        <dd class="text-slate-200 text-right">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        {{-- Subscription --}}
        <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-5">
            <div class="flex items-center justify-between gap-4 mb-4">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-400">Subscription</h2>
                <button wire:click="openSubscription"
                    class="px-2.5 py-1 rounded-md text-xs font-semibold text-amber-400 hover:bg-amber-500/10 transition-colors">
                    {{ $subscription ? 'Edit plan' : 'Set up plan' }}
                </button>
            </div>

            @if($subscription)
                <div class="flex items-center gap-2 mb-4">
                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold uppercase tracking-wide
                        {{ $subscription->status === 'active' ? 'bg-emerald-500/15 text-emerald-400'
                           : ($subscription->status === 'past_due' ? 'bg-red-500/15 text-red-400' : 'bg-slate-700/50 text-slate-300') }}">
                        {{ str_replace('_', ' ', $subscription->status) }}
                    </span>
                    <span class="text-sm text-slate-300">{{ $subscription->renewalSummary() }}</span>
                </div>

                <dl class="space-y-3 text-sm">
                    @foreach([
                        'Plan'          => ucfirst($subscription->plan),
                        'Price'         => number_format((float) $subscription->price, 2).' / '.$subscription->billing_cycle,
                        'Started'       => $subscription->started_at?->format('d M Y'),
                        'Next renewal'  => $subscription->next_renewal_at?->format('d M Y') ?: '—',
                        'Grace ends'    => $subscription->isPastDue() ? $subscription->graceEndsAt()?->format('d M Y') : null,
                    ] as $label => $value)
                        @if($value !== null)
                            <div class="flex justify-between gap-6">
                                <dt class="text-slate-500 shrink-0">{{ $label }}</dt>
                                <dd class="text-slate-200 text-right">{{ $value }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>

                {{-- Lifecycle actions. Money arrives outside the system, so
                     these are the superadmin recording what happened. --}}
                <div class="mt-5 pt-4 border-t border-slate-800 flex flex-wrap gap-2">
                    <button wire:click="renewSubscription"
                        class="px-3 py-1.5 rounded-lg bg-emerald-500/15 text-emerald-400 text-xs font-semibold hover:bg-emerald-500/25 transition-colors">
                        Renew a cycle
                    </button>

                    @if($subscription->isSuspended())
                        <button wire:click="reactivateSubscription"
                            class="px-3 py-1.5 rounded-lg bg-amber-500 text-amber-950 text-xs font-semibold hover:bg-amber-400 transition-colors">
                            Reactivate
                        </button>
                    @else
                        <button wire:click="suspendSubscription"
                            class="px-3 py-1.5 rounded-lg bg-red-500/15 text-red-400 text-xs font-semibold hover:bg-red-500/25 transition-colors">
                            Suspend access
                        </button>
                    @endif
                </div>

                @if($subscription->isSuspended())
                    <p class="mt-3 text-xs text-red-400">
                        This organization&rsquo;s users are blocked from the application until it is reactivated.
                    </p>
                @endif
            @else
                <div class="rounded-lg border border-dashed border-slate-700 p-4">
                    <p class="text-sm text-slate-400">No subscription on record for this organization.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Members --}}
    <div class="mt-6 rounded-xl border border-slate-800 bg-slate-900/40 p-5">
        <div class="flex items-center justify-between gap-4 mb-4">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-400">Members</h2>
            @if(count($roleCounts))
                <div class="flex flex-wrap gap-2">
                    @foreach($roleCounts as $role => $total)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-800 text-xs text-slate-300">
                            {{ $role }} <span class="font-semibold text-slate-100 tabular-nums">{{ $total }}</span>
                        </span>
                    @endforeach
                </div>
            @endif
        </div>

        @forelse($users as $user)
            <div class="flex items-center gap-3 py-2 {{ ! $loop->last ? 'border-b border-slate-800' : '' }}">
                <div class="w-8 h-8 rounded-full bg-amber-500/15 flex items-center justify-center shrink-0">
                    <span class="text-xs font-semibold text-amber-400">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-slate-100 truncate">{{ $user->name }}</p>
                    <p class="text-xs text-slate-500 truncate">{{ $user->email }}</p>
                </div>
                <span class="text-xs text-slate-400 shrink-0">{{ $user->role }}</span>
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-amber-500/40 bg-amber-500/5 p-4">
                <p class="text-sm text-amber-300 font-medium">No members yet</p>
                <a href="{{ route('superadmin.organizations.admin', $organization) }}"
                    class="inline-block mt-2 text-xs font-semibold text-amber-400 hover:text-amber-300">Create the first administrator &rarr;</a>
            </div>
        @endforelse
    </div>

    {{-- Payment history --}}
    <div class="mt-6 rounded-xl border border-slate-800 bg-slate-900/40 p-5">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-400">Subscription payments</h2>
            <div class="flex items-center gap-4">
                @if($payments->count())
                    <span class="text-sm text-slate-300">Total paid <span class="font-semibold text-white tabular-nums">{{ number_format((float) $totalPaid, 2) }}</span></span>
                @endif
                @if($subscription)
                    <button wire:click="openPayment"
                        class="px-3 py-1.5 rounded-lg bg-amber-500 text-amber-950 text-xs font-semibold hover:bg-amber-400 transition-colors">
                        Record payment
                    </button>
                @endif
            </div>
        </div>

        @if($payments->count())
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500 border-b border-slate-800">
                            <th class="py-2 pr-4">Paid at</th>
                            <th class="py-2 pr-4">Method</th>
                            <th class="py-2 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach($payments as $payment)
                            <tr>
                                <td class="py-2 pr-4 text-slate-300 whitespace-nowrap">{{ $payment->paid_at?->format('d M Y, H:i') }}</td>
                                <td class="py-2 pr-4 text-slate-400">{{ $payment->method ? str_replace('_', ' ', $payment->method) : '—' }}</td>
                                <td class="py-2 text-right text-slate-100 font-medium tabular-nums">{{ number_format((float) $payment->amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-slate-400">No payments recorded yet.</p>
        @endif
    </div>

    {{-- Removal status --}}
    <div class="mt-6 rounded-xl border border-slate-800 bg-slate-900/40 p-5">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-400 mb-3">Removal</h2>
        @if($blockerSummary)
            <p class="text-sm text-slate-300">
                This organization still holds <span class="font-semibold text-white">{{ $blockerSummary }}</span>
                and cannot be deleted.
            </p>
            <p class="text-xs text-slate-500 mt-2">
                Retiring an agency that has real work is a deliberate process — export or move
                the data first. There is no cascade delete, by design.
            </p>
        @else
            <p class="text-sm text-slate-300">This organization is empty and can be deleted from the list.</p>
        @endif
    </div>

    {{-- Subscription form --}}
    @if($showSubscriptionModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70">
            <div class="w-full max-w-lg bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="px-6 py-4 border-b border-slate-800">
                    <h2 class="text-lg font-semibold text-white">
                        {{ $subscription ? 'Edit subscription' : 'Set up subscription' }}
                    </h2>
                    <p class="text-xs text-slate-400 mt-1">For <span class="text-amber-400 font-semibold">{{ $organization->name }}</span></p>
                </div>

                <form wire:submit="saveSubscription" class="px-6 py-5 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1.5">Plan</label>
                            <select wire:model="plan"
                                class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                @foreach($plans as $option)
                                    <option value="{{ $option }}">{{ ucfirst($option) }}</option>
                                @endforeach
                            </select>
                            @error('plan') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                            <p class="mt-1 text-xs text-slate-500">
                                Decides both feature access and the storage cap. Takes effect immediately.
                            </p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1.5">Price</label>
                            <input wire:model="price" type="number" step="0.01" min="0"
                                class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-sm text-slate-100 tabular-nums focus:outline-none focus:ring-2 focus:ring-amber-500">
                            @error('price') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1.5">Billing cycle</label>
                            <select wire:model="billing_cycle"
                                class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                @foreach($cycles as $option)
                                    <option value="{{ $option }}">{{ ucfirst($option) }}</option>
                                @endforeach
                            </select>
                            @error('billing_cycle') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1.5">Status</label>
                            <select wire:model="status"
                                class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                @foreach($statuses as $option)
                                    <option value="{{ $option }}">{{ ucfirst(str_replace('_', ' ', $option)) }}</option>
                                @endforeach
                            </select>
                            @error('status') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- The Pro extra-storage arrangement, applied by hand. The
                         money is agreed in conversation, as it is for the plan
                         itself, so there is nothing to automate here. --}}
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1.5">
                            Storage override (GB)
                        </label>
                        <input wire:model="storage_cap_override_gb" type="number" min="1"
                            placeholder="Leave blank to use the plan's own cap"
                            class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-sm text-slate-100 tabular-nums focus:outline-none focus:ring-2 focus:ring-amber-500">
                        <p class="mt-1 text-xs text-slate-500">
                            Beats the plan default in both directions. Blank restores the plan's cap
                            ({{ config('storage.plan_caps_gb.base') }}/{{ config('storage.plan_caps_gb.standard') }}/{{ config('storage.plan_caps_gb.pro') }} GB).
                        </p>
                        @error('storage_cap_override_gb') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1.5">Started</label>
                        <input wire:model="started_at" type="date"
                            class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500">
                        <p class="mt-1 text-xs text-slate-500">
                            The renewal date is worked out from this and the billing cycle &mdash;
                            one {{ $billing_cycle === 'yearly' ? 'year' : 'month' }} later. Use
                            <span class="text-slate-300">Renew</span> afterwards to move it on a cycle.
                        </p>
                        @error('started_at') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('showSubscriptionModal', false)"
                            class="px-4 py-2 rounded-lg text-sm font-medium text-slate-300 hover:bg-slate-800 transition-colors">Cancel</button>
                        <button type="submit"
                            class="px-4 py-2 rounded-lg bg-amber-500 text-amber-950 text-sm font-semibold hover:bg-amber-400 transition-colors">
                            {{ $subscription ? 'Save changes' : 'Create subscription' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Payment form --}}
    @if($showPaymentModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70">
            <div class="w-full max-w-md bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl">
                <div class="px-6 py-4 border-b border-slate-800">
                    <h2 class="text-lg font-semibold text-white">Record payment</h2>
                    <p class="text-xs text-slate-400 mt-1">Against <span class="text-amber-400 font-semibold">{{ $organization->name }}</span>&rsquo;s subscription</p>
                </div>

                <form wire:submit="savePayment" class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1.5">Amount</label>
                        <input wire:model="amount" type="number" step="0.01" min="0.01"
                            class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-sm text-slate-100 tabular-nums focus:outline-none focus:ring-2 focus:ring-amber-500">
                        @error('amount') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1.5">Paid at</label>
                        <input wire:model="paid_at" type="datetime-local"
                            class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500">
                        @error('paid_at') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1.5">Method</label>
                        <input wire:model="method" type="text" placeholder="bank_transfer"
                            class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500">
                        @error('method') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('showPaymentModal', false)"
                            class="px-4 py-2 rounded-lg text-sm font-medium text-slate-300 hover:bg-slate-800 transition-colors">Cancel</button>
                        <button type="submit"
                            class="px-4 py-2 rounded-lg bg-amber-500 text-amber-950 text-sm font-semibold hover:bg-amber-400 transition-colors">
                            Record payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
