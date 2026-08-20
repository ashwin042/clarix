<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-slate-100">Subscription</h1>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">
            {{ $organization?->name ?? 'Your organization' }}&rsquo;s Clarix plan and billing history
        </p>
    </div>

    @if($subscription)
        {{-- Current plan --}}
        <div class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 mb-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2.5">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-slate-100 capitalize">{{ $subscription->plan }} plan</h2>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold uppercase tracking-wide
                            {{ $subscription->status === 'active' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400'
                               : ($subscription->status === 'past_due' ? 'bg-red-50 text-red-700 dark:bg-red-500/15 dark:text-red-400'
                               : 'bg-gray-100 text-gray-600 dark:bg-slate-800 dark:text-slate-300') }}">
                            {{ str_replace('_', ' ', $subscription->status) }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">{{ $subscription->renewalSummary() }}</p>
                </div>
                <div class="text-right">
                    <p class="text-2xl font-bold text-gray-900 dark:text-slate-100 tabular-nums">
                        {{ number_format((float) $subscription->price, 2) }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-slate-400">per {{ $subscription->billing_cycle === 'yearly' ? 'year' : 'month' }}</p>
                </div>
            </div>

            <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6 pt-6 border-t border-gray-100 dark:border-slate-800">
                @foreach([
                    'Billing cycle' => ucfirst($subscription->billing_cycle),
                    'Started'       => $subscription->started_at?->format('d M Y'),
                    'Next renewal'  => $subscription->next_renewal_at?->format('d M Y') ?: '—',
                    'Total paid'    => number_format((float) $totalPaid, 2),
                ] as $label => $value)
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-slate-500">{{ $label }}</dt>
                        <dd class="text-sm font-medium text-gray-900 dark:text-slate-100 mt-1">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @else
        <div class="rounded-xl border border-dashed border-gray-300 dark:border-slate-700 p-10 text-center mb-6">
            <p class="text-gray-600 dark:text-slate-300 font-medium">No subscription on record</p>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">
                Contact Clarix to set up billing for this organization.
            </p>
        </div>
    @endif

    {{-- Payment history --}}
    <div class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-800 flex items-center justify-between gap-4">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400">Payment history</h2>
            <span class="text-xs text-gray-500 dark:text-slate-400">{{ $payments->count() }} {{ Str::plural('payment', $payments->count()) }}</span>
        </div>

        @if($payments->count())
            {{-- Mobile --}}
            <div class="md:hidden divide-y divide-gray-100 dark:divide-slate-800">
                @foreach($payments as $payment)
                    <div class="px-6 py-4 flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-slate-100">{{ $payment->paid_at?->format('d M Y') }}</p>
                            <p class="text-xs text-gray-500 dark:text-slate-400">{{ $payment->method ? str_replace('_', ' ', $payment->method) : '—' }}</p>
                        </div>
                        <p class="text-sm font-semibold text-gray-900 dark:text-slate-100 tabular-nums shrink-0">
                            {{ number_format((float) $payment->amount, 2) }}
                        </p>
                    </div>
                @endforeach
            </div>

            {{-- Desktop --}}
            <div class="hidden md:block overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50 dark:bg-slate-950/40">
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400">
                            <th class="px-6 py-3">Paid at</th>
                            <th class="px-6 py-3">Method</th>
                            <th class="px-6 py-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                        @foreach($payments as $payment)
                            <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="px-6 py-3 text-sm text-gray-900 dark:text-slate-200 whitespace-nowrap">
                                    {{ $payment->paid_at?->format('d M Y, H:i') }}
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-500 dark:text-slate-400">
                                    {{ $payment->method ? str_replace('_', ' ', $payment->method) : '—' }}
                                </td>
                                <td class="px-6 py-3 text-sm font-medium text-gray-900 dark:text-slate-100 text-right tabular-nums">
                                    {{ number_format((float) $payment->amount, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 dark:bg-slate-950/40 border-t border-gray-200 dark:border-slate-800">
                        <tr>
                            <td colspan="2" class="px-6 py-3 text-sm font-semibold text-gray-700 dark:text-slate-300">Total</td>
                            <td class="px-6 py-3 text-sm font-bold text-gray-900 dark:text-slate-100 text-right tabular-nums">
                                {{ number_format((float) $totalPaid, 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <p class="px-6 py-10 text-center text-sm text-gray-500 dark:text-slate-400">No payments recorded yet.</p>
        @endif
    </div>
</div>
