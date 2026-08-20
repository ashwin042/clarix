<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-slate-100">Storage</h1>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">
                What each organization holds against the allowance its plan carries. Closest to the cap first.
            </p>
        </div>
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search organizations…"
            class="rounded-lg border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-lg">
        <div class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-slate-500">Organizations</p>
            <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-slate-100">{{ $totals['organizations'] }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-slate-500">Total stored</p>
            <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-slate-100">{{ $storage->humanBytes($totals['bytes']) }}</p>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 dark:bg-slate-800/50 text-left">
                    <tr class="text-xs uppercase tracking-wider text-gray-500 dark:text-slate-400">
                        <th class="px-5 py-2.5 font-medium">Organization</th>
                        <th class="px-5 py-2.5 font-medium">Plan</th>
                        <th class="px-5 py-2.5 font-medium text-right">Used</th>
                        <th class="px-5 py-2.5 font-medium text-right">Allowance</th>
                        <th class="px-5 py-2.5 font-medium w-64">Usage</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                    @forelse($rows as $row)
                        @php
                            $percent = min(100, $row['percent']);
                            $bar = $row['percent'] >= 90
                                ? 'bg-rose-500'
                                : ($row['percent'] >= 70 ? 'bg-amber-500' : 'bg-indigo-500');
                        @endphp
                        <tr>
                            <td class="px-5 py-3 text-gray-900 dark:text-slate-100">{{ $row['organization']->name }}</td>
                            <td class="px-5 py-3 text-gray-600 dark:text-slate-300">
                                {{ $row['plan'] ? ucfirst($row['plan']) : '—' }}
                            </td>
                            <td class="px-5 py-3 text-right text-gray-900 dark:text-slate-100">{{ $storage->humanBytes($row['bytes']) }}</td>
                            <td class="px-5 py-3 text-right text-gray-600 dark:text-slate-300">{{ $row['cap_gb'] }} GB</td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex-1 h-2 rounded-full bg-gray-100 dark:bg-slate-800 overflow-hidden">
                                        <div class="h-full rounded-full {{ $bar }}" style="width: {{ $percent }}%"></div>
                                    </div>
                                    <span class="text-xs tabular-nums text-gray-500 dark:text-slate-400 w-14 text-right">
                                        {{ number_format($row['percent'], 2) }}%
                                    </span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-gray-500 dark:text-slate-400">No organizations found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-xs text-gray-400 dark:text-slate-500 max-w-2xl">
        Organization totals only. The breakdown behind them describes how each agency is
        structured internally and stays with the agency.
    </p>
</div>
