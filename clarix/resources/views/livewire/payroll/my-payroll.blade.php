<div class="space-y-6">
    <div>
        <h1 class="text-xl font-semibold text-gray-900 dark:text-slate-100">My payroll</h1>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">Your own record. Amounts are entered by your administrator.</p>
    </div>

    @if($paidTotal > 0)
        <div class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 max-w-xs">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-slate-500">Paid to date</p>
            <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-slate-100">{{ number_format((float) $paidTotal, 2) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Across the records shown below.</p>
        </div>
    @endif

    <div class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 dark:bg-slate-800/50 text-left">
                    <tr class="text-xs uppercase tracking-wider text-gray-500 dark:text-slate-400">
                        <th class="px-5 py-2.5 font-medium">Month</th>
                        <th class="px-5 py-2.5 font-medium text-right">Base</th>
                        <th class="px-5 py-2.5 font-medium text-right">Deductions</th>
                        <th class="px-5 py-2.5 font-medium text-right">Net</th>
                        <th class="px-5 py-2.5 font-medium">Status</th>
                        <th class="px-5 py-2.5 font-medium">Paid on</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                    @forelse($records as $record)
                        <tr>
                            <td class="px-5 py-2.5 text-gray-900 dark:text-slate-100">{{ $record->month->format('F Y') }}</td>
                            <td class="px-5 py-2.5 text-right text-gray-600 dark:text-slate-300">{{ number_format((float) $record->base_amount, 2) }}</td>
                            <td class="px-5 py-2.5 text-right text-gray-600 dark:text-slate-300">{{ number_format((float) $record->deductions, 2) }}</td>
                            <td class="px-5 py-2.5 text-right font-medium text-gray-900 dark:text-slate-100">{{ number_format((float) $record->net_amount, 2) }}</td>
                            <td class="px-5 py-2.5"><x-payroll-status :status="$record->status" /></td>
                            <td class="px-5 py-2.5 text-gray-500 dark:text-slate-400">{{ $record->paid_at?->format('j M Y') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-gray-500 dark:text-slate-400">
                                No payroll records yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
