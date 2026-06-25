<div>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-slate-100">Financial Dashboard</h1>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">Revenue, credits, and profit analytics</p>
        </div>
        <div class="flex items-center gap-3">
            <select wire:model.live="filterUnit" class="border border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All units</option>
                @foreach($units as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                @endforeach
            </select>
            <input wire:model.live="dateFrom" type="date" class="border border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <input wire:model.live="dateTo" type="date" class="border border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
        {{-- Total Revenue: from recorded payments only --}}
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-500/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Total Revenue</p>
                    <p class="text-xl font-bold text-gray-900 dark:text-slate-100">Rs {{ number_format($totalRevenue, 2) }}</p>
                    <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-0.5">From recorded payments</p>
                </div>
            </div>
        </div>
        {{-- Total Credits: credits earned from completed tasks --}}
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-500/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Total Credits</p>
                    <p class="text-xl font-bold text-gray-900 dark:text-slate-100">{{ number_format($totalCredits, 2) }}</p>
                    <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-0.5">Earned from completed tasks</p>
                </div>
            </div>
        </div>
        {{-- Net Profit: equals recorded revenue --}}
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-emerald-100 dark:bg-emerald-500/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Net Profit</p>
                    <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400">Rs {{ number_format($netProfit, 2) }}</p>
                </div>
            </div>
        </div>
        {{-- Top Paying Unit: hidden when a single unit is already filtered (redundant) --}}
        @if(!$filterUnit)
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-amber-100 dark:bg-amber-500/10 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0"/></svg>
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Top Paying Unit</p>
                    @if($topPayingUnit)
                        <p class="text-xl font-bold text-gray-900 dark:text-slate-100 truncate">{{ $topPayingUnit['name'] }}</p>
                        <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-0.5">Rs {{ number_format($topPayingUnit['total'], 2) }} paid</p>
                    @else
                        <p class="text-xl font-bold text-gray-900 dark:text-slate-100">No payments yet</p>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- Charts Row --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        {{-- Revenue vs Credits --}}
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 p-5">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100 mb-4">Revenue vs Credits</h3>
            <div class="h-64" wire:ignore>
                <canvas id="financeRevenueChart"></canvas>
            </div>
        </div>
        {{-- Unit Profitability --}}
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 p-5">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100 mb-4">Unit Profitability</h3>
            <div class="h-64" wire:ignore>
                <canvas id="financeUnitChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Payment History --}}
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-800/60 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100">Payment History</h3>
                <p class="text-xs text-gray-400 dark:text-slate-500 mt-0.5">Recorded payments for the selected filters</p>
            </div>
            <a href="{{ route('admin.payments') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline whitespace-nowrap">View all</a>
        </div>
        @if($payments->count())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-slate-800/60">
                    <thead class="bg-gray-50 dark:bg-slate-950/60">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase">Date</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase">Payer Name</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase">Amount</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase">Credit Covered</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase">Unit</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase">Method</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-slate-800/60">
                        @foreach($payments as $p)
                            <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-slate-400 whitespace-nowrap">{{ $p->created_at->format('M d, Y') }}</td>
                                <td class="px-5 py-3 text-sm font-medium text-gray-900 dark:text-slate-100">{{ $p->payer_name }}</td>
                                <td class="px-5 py-3 text-sm font-semibold text-green-600 dark:text-green-400 whitespace-nowrap">Rs {{ number_format($p->amount, 2) }}</td>
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-slate-300 whitespace-nowrap">{{ number_format($p->total_credit, 2) }}</td>
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-slate-300">{{ $p->unit?->name ?? '-' }}</td>
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-slate-300 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-400">
                                        {{ str_replace('_', ' ', ucfirst($p->payment_method)) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-slate-400 max-w-xs truncate" title="{{ $p->notes }}">{{ $p->notes ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($payments->hasPages())
                <div class="px-5 py-4 border-t border-gray-100 dark:border-slate-800/60">{{ $payments->links() }}</div>
            @endif
        @else
            <div class="py-12 text-center">
                <p class="text-sm text-gray-500 dark:text-slate-400">No payments recorded for the selected filters.</p>
            </div>
        @endif
    </div>
</div>

@script
<script>
    let revenueChart = null;
    let unitChart = null;

    const palette = () => {
        const isDark = document.documentElement.classList.contains('dark');
        return {
            grid:   isDark ? 'rgba(148,163,184,0.07)' : 'rgba(0,0,0,0.06)',
            tick:   isDark ? '#94a3b8' : '#6B7280',
            legend: isDark ? '#cbd5e1' : '#374151',
        };
    };

    const drawRevenue = (data) => {
        const ctx = document.getElementById('financeRevenueChart');
        if (!ctx) return;
        if (revenueChart) revenueChart.destroy();
        const c = palette();

        revenueChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.monthLabels,
                datasets: [
                    {
                        label: 'Revenue',
                        data: data.revenueData,
                        borderColor: '#10B981',
                        backgroundColor: 'rgba(16,185,129,0.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 4,
                        pointBackgroundColor: '#10B981',
                    },
                    {
                        label: 'Credits',
                        data: data.creditData,
                        borderColor: '#6366F1',
                        backgroundColor: 'rgba(99,102,241,0.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 4,
                        pointBackgroundColor: '#6366F1',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { labels: { color: c.legend, usePointStyle: true, pointStyle: 'circle' } } },
                scales: {
                    x: { ticks: { color: c.tick }, grid: { color: c.grid } },
                    y: { ticks: { color: c.tick, callback: v => 'Rs ' + v.toLocaleString() }, grid: { color: c.grid } }
                }
            }
        });
    };

    const drawUnit = (data) => {
        const ctx = document.getElementById('financeUnitChart');
        if (!ctx) return;
        if (unitChart) unitChart.destroy();
        const c = palette();

        unitChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.unitLabels,
                datasets: [
                    {
                        label: 'Revenue',
                        data: data.unitRevenueData,
                        backgroundColor: 'rgba(16,185,129,0.7)',
                        borderRadius: 4,
                    },
                    {
                        label: 'Credits',
                        data: data.unitCreditData,
                        backgroundColor: 'rgba(99,102,241,0.7)',
                        borderRadius: 4,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { labels: { color: c.legend, usePointStyle: true, pointStyle: 'circle' } } },
                scales: {
                    x: { ticks: { color: c.tick }, grid: { display: false } },
                    y: { ticks: { color: c.tick, callback: v => 'Rs ' + v.toLocaleString() }, grid: { color: c.grid } }
                }
            }
        });
    };

    const drawAll = (data) => {
        drawRevenue(data);
        drawUnit(data);
    };

    // Initial paint from server-rendered data.
    drawAll(@js($chartData));

    // Redraw with fresh figures whenever filters change (scoped, auto-cleaned listener).
    $wire.on('finance-charts-updated', (payload) => {
        drawAll((payload && payload.data) ? payload.data : payload);
    });
</script>
@endscript
