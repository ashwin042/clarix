<div>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Financial Dashboard</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Revenue, credits, and profit analytics</p>
        </div>
        <div class="flex items-center gap-3">
            <select wire:model.live="filterUnit" class="border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All units</option>
                @foreach($units as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                @endforeach
            </select>
            <input wire:model.live="dateFrom" type="date" class="border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <input wire:model.live="dateTo" type="date" class="border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/40 flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Revenue</p>
                    <p class="text-xl font-bold text-gray-900 dark:text-white">${{ number_format($totalRevenue, 2) }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Credits</p>
                    <p class="text-xl font-bold text-gray-900 dark:text-white">${{ number_format($totalCredits, 2) }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg {{ $netProfit >= 0 ? 'bg-emerald-100 dark:bg-emerald-900/40' : 'bg-red-100 dark:bg-red-900/40' }} flex items-center justify-center">
                    <svg class="w-5 h-5 {{ $netProfit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Net Profit</p>
                    <p class="text-xl font-bold {{ $netProfit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">${{ number_format(abs($netProfit), 2) }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Pending Credit</p>
                    <p class="text-xl font-bold text-amber-600 dark:text-amber-400">${{ number_format(max(0, $pendingCredit), 2) }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Charts Row --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        {{-- Revenue vs Credits --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">Revenue vs Credits</h3>
            <div class="h-64">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
        {{-- Unit Profitability --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">Unit Profitability</h3>
            <div class="h-64">
                <canvas id="unitChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Recent Payments --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Recent Payments</h3>
            <a href="{{ route('admin.payments') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">View all</a>
        </div>
        @if($recentPayments->count())
            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Payer</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Amount</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Unit</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Method</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($recentPayments as $p)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-5 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ $p->payer_name }}</td>
                            <td class="px-5 py-3 text-sm font-semibold text-green-600 dark:text-green-400">${{ number_format($p->amount, 2) }}</td>
                            <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $p->unit?->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ str_replace('_', ' ', ucfirst($p->payment_method)) }}</td>
                            <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $p->created_at->format('M d, Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="py-12 text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">No payments recorded yet.</p>
            </div>
        @endif
    </div>
</div>

@script
<script>
    const isDark = document.documentElement.classList.contains('dark');
    const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
    const tickColor = isDark ? '#9CA3AF' : '#6B7280';
    const legendColor = isDark ? '#D1D5DB' : '#374151';

    // Revenue vs Credits Line Chart
    new Chart(document.getElementById('revenueChart'), {
        type: 'line',
        data: {
            labels: @json($monthLabels),
            datasets: [
                {
                    label: 'Revenue',
                    data: @json($revenueData),
                    borderColor: '#10B981',
                    backgroundColor: 'rgba(16,185,129,0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4,
                    pointBackgroundColor: '#10B981',
                },
                {
                    label: 'Credits',
                    data: @json($creditData),
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
            plugins: { legend: { labels: { color: legendColor, usePointStyle: true, pointStyle: 'circle' } } },
            scales: {
                x: { ticks: { color: tickColor }, grid: { color: gridColor } },
                y: { ticks: { color: tickColor, callback: v => '$' + v.toLocaleString() }, grid: { color: gridColor } }
            }
        }
    });

    // Unit Profitability Bar Chart
    new Chart(document.getElementById('unitChart'), {
        type: 'bar',
        data: {
            labels: @json($unitLabels),
            datasets: [
                {
                    label: 'Revenue',
                    data: @json($unitRevenueData),
                    backgroundColor: 'rgba(16,185,129,0.7)',
                    borderRadius: 4,
                },
                {
                    label: 'Credits',
                    data: @json($unitCreditData),
                    backgroundColor: 'rgba(99,102,241,0.7)',
                    borderRadius: 4,
                },
                {
                    label: 'Profit',
                    data: @json($unitProfitData),
                    backgroundColor: 'rgba(245,158,11,0.7)',
                    borderRadius: 4,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { labels: { color: legendColor, usePointStyle: true, pointStyle: 'circle' } } },
            scales: {
                x: { ticks: { color: tickColor }, grid: { display: false } },
                y: { ticks: { color: tickColor, callback: v => '$' + v.toLocaleString() }, grid: { color: gridColor } }
            }
        }
    });
</script>
@endscript
