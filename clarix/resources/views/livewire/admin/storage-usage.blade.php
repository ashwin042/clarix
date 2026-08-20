@php
    $percent = min(100, $summary['percent']);
    $bar = $summary['percent'] >= 90
        ? 'bg-rose-500'
        : ($summary['percent'] >= 70 ? 'bg-amber-500' : 'bg-indigo-500');
@endphp

<div class="space-y-6">
    <div>
        <h1 class="text-xl font-semibold text-gray-900 dark:text-slate-100">Storage Usage</h1>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">
            Your organization's total across all units, against the allowance your plan carries.
        </p>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 max-w-2xl">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-slate-500">Used</p>
                <p class="mt-1 text-3xl font-semibold text-gray-900 dark:text-slate-100">
                    {{ $storage->humanBytes($summary['bytes']) }}
                </p>
            </div>
            <div class="text-right">
                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-slate-500">Allowance</p>
                <p class="mt-1 text-lg font-medium text-gray-700 dark:text-slate-300">
                    {{ $summary['cap_gb'] }} GB
                    @if($summary['plan'])
                        <span class="text-xs font-normal text-gray-400 dark:text-slate-500">({{ ucfirst($summary['plan']) }} plan)</span>
                    @else
                        <span class="text-xs font-normal text-gray-400 dark:text-slate-500">(no plan on record)</span>
                    @endif
                </p>
            </div>
        </div>

        <div class="mt-5">
            <div class="h-2.5 w-full rounded-full bg-gray-100 dark:bg-slate-800 overflow-hidden">
                <div class="h-full rounded-full {{ $bar }} transition-all" style="width: {{ $percent }}%"></div>
            </div>
            <div class="mt-2 flex items-center justify-between text-xs">
                <span class="text-gray-500 dark:text-slate-400">{{ number_format($summary['percent'], 2) }}% used</span>
                <span class="text-gray-400 dark:text-slate-500">
                    {{ $storage->humanBytes(max(0, $summary['cap_bytes'] - $summary['bytes'])) }} remaining
                </span>
            </div>
        </div>

        @if($summary['percent'] >= 90)
            <p class="mt-4 text-xs text-rose-600 dark:text-rose-400">
                You are close to your allowance. Upgrading your plan raises it.
            </p>
        @endif
    </div>

    {{-- Supplementary: where the space has gone. Shares are of the
         organization's own total, not of the allowance above. --}}
    <div class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-200 dark:border-slate-800">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-slate-100">By unit</h2>
            <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">
                Each unit's share of your organization's {{ $storage->humanBytes($summary['bytes']) }} total.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 dark:bg-slate-800/50 text-left">
                    <tr class="text-xs uppercase tracking-wider text-gray-500 dark:text-slate-400">
                        <th class="px-5 py-2.5 font-medium">Unit</th>
                        <th class="px-5 py-2.5 font-medium text-right">Used</th>
                        <th class="px-5 py-2.5 font-medium w-64">Share of total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                    @forelse($units as $unit)
                        <tr>
                            <td class="px-5 py-3 text-gray-900 dark:text-slate-100">{{ $unit['name'] }}</td>
                            <td class="px-5 py-3 text-right text-gray-600 dark:text-slate-300 tabular-nums">
                                {{ $storage->humanBytes($unit['bytes']) }}
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex-1 h-2 rounded-full bg-gray-100 dark:bg-slate-800 overflow-hidden">
                                        <div class="h-full rounded-full bg-indigo-500" style="width: {{ min(100, $unit['share']) }}%"></div>
                                    </div>
                                    <span class="text-xs tabular-nums text-gray-500 dark:text-slate-400 w-14 text-right">
                                        {{ number_format($unit['share'], 2) }}%
                                    </span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-5 py-8 text-center text-gray-500 dark:text-slate-400">
                                No units yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-xs text-gray-400 dark:text-slate-500 max-w-2xl">
        Totals are kept up to date as files are uploaded and removed, and reconciled against
        actual storage nightly.
    </p>
</div>
