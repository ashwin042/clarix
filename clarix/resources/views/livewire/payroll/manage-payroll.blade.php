<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-slate-100">Payroll</h1>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">
                Record-keeping only. Payments are made outside Clarix and noted here.
            </p>
        </div>
        <div class="flex items-center gap-3">
            <label class="text-xs font-medium text-gray-600 dark:text-slate-400">Month</label>
            <input type="month" wire:model.live="month" value="{{ \Illuminate\Support\Carbon::parse($month)->format('Y-m') }}"
                class="rounded-lg border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-lg">
        <div class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-slate-500">Recorded this month</p>
            <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-slate-100">{{ number_format((float) $totals['net'], 2) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-slate-500">Marked paid</p>
            <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-slate-100">{{ number_format((float) $totals['paid'], 2) }}</p>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-200 dark:border-slate-800">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-slate-100">
                {{ \Illuminate\Support\Carbon::parse($month)->format('F Y') }}
            </h2>
            <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">{{ $members->count() }} {{ Str::plural('person', $members->count()) }}</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 dark:bg-slate-800/50 text-left">
                    <tr class="text-xs uppercase tracking-wider text-gray-500 dark:text-slate-400">
                        <th class="px-5 py-2.5 font-medium">Name</th>
                        <th class="px-5 py-2.5 font-medium">Role</th>
                        <th class="px-5 py-2.5 font-medium text-right">Base</th>
                        <th class="px-5 py-2.5 font-medium text-right">Deductions</th>
                        <th class="px-5 py-2.5 font-medium text-right">Net</th>
                        <th class="px-5 py-2.5 font-medium">Status</th>
                        <th class="px-5 py-2.5 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                    @forelse($members as $person)
                        @php $record = $records->get($person->id); @endphp
                        <tr>
                            <td class="px-5 py-2.5 text-gray-900 dark:text-slate-100">{{ $person->name }}</td>
                            <td class="px-5 py-2.5 text-gray-500 dark:text-slate-400 capitalize">{{ $person->role }}</td>
                            <td class="px-5 py-2.5 text-right text-gray-600 dark:text-slate-300">
                                {{ $record ? number_format((float) $record->base_amount, 2) : '—' }}
                            </td>
                            <td class="px-5 py-2.5 text-right text-gray-600 dark:text-slate-300">
                                {{ $record ? number_format((float) $record->deductions, 2) : '—' }}
                            </td>
                            <td class="px-5 py-2.5 text-right font-medium text-gray-900 dark:text-slate-100">
                                {{ $record ? number_format((float) $record->net_amount, 2) : '—' }}
                            </td>
                            <td class="px-5 py-2.5">
                                @if($record)
                                    <x-payroll-status :status="$record->status" />
                                @else
                                    <span class="text-xs text-gray-400 dark:text-slate-500">Not entered</span>
                                @endif
                            </td>
                            <td class="px-5 py-2.5 text-right whitespace-nowrap">
                                @if(! $record)
                                    <button type="button" wire:click="openRecord({{ $person->id }})"
                                        class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">Enter</button>
                                @else
                                    @if($record->isDraft())
                                        <button type="button" wire:click="openRecord({{ $person->id }})"
                                            class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">Edit</button>
                                        <button type="button" wire:click="finalize({{ $record->id }})"
                                            class="ml-3 text-xs font-medium text-sky-600 dark:text-sky-400 hover:underline">Finalize</button>
                                    @elseif($record->status === 'finalized')
                                        <button type="button" wire:click="markPaid({{ $record->id }})"
                                            class="text-xs font-medium text-emerald-600 dark:text-emerald-400 hover:underline">Mark paid</button>
                                        <button type="button" wire:click="revertToDraft({{ $record->id }})"
                                            class="ml-3 text-xs font-medium text-gray-500 dark:text-slate-400 hover:underline">Reopen</button>
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-slate-500">
                                            Paid {{ $record->paid_at?->format('j M Y') }}
                                        </span>
                                    @endif

                                    @if(! $record->isPaid() && auth()->user()->isAdmin())
                                        <button type="button" wire:click="openDeleteModal({{ $record->id }}, '{{ addslashes($person->name) }}')"
                                            class="ml-3 text-xs font-medium text-rose-600 dark:text-rose-400 hover:underline">Remove</button>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-8 text-center text-gray-500 dark:text-slate-400">Nobody to show.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
            <div class="w-full max-w-md rounded-xl bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 shadow-xl">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-slate-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100">
                        Payroll for {{ \Illuminate\Support\Carbon::parse($month)->format('F Y') }}
                    </h3>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">Base amount</label>
                        <input type="number" step="0.01" min="0" wire:model="base_amount"
                            class="w-full rounded-lg border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        @error('base_amount') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">Deductions</label>
                        <input type="number" step="0.01" min="0" wire:model="deductions"
                            class="w-full rounded-lg border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        @error('deductions') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        <p class="mt-1.5 text-xs text-gray-500 dark:text-slate-400">A single figure. Net is calculated as base minus deductions.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">Notes <span class="text-gray-400">(optional)</span></label>
                        <textarea wire:model="notes" rows="3"
                            class="w-full rounded-lg border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        @error('notes') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    @error('payroll') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div class="px-5 py-4 border-t border-gray-200 dark:border-slate-800 flex justify-end gap-2">
                    <button type="button" wire:click="$set('showModal', false)"
                        class="px-3.5 py-2 rounded-lg text-sm font-medium text-gray-600 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800">Cancel</button>
                    <button type="button" wire:click="save"
                        class="px-3.5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium">Save</button>
                </div>
            </div>
        </div>
    @endif

    @if($showDeleteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
            <div class="w-full max-w-sm rounded-xl bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 shadow-xl p-5">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100">Remove payroll record</h3>
                <p class="mt-1.5 text-sm text-gray-500 dark:text-slate-400">
                    Remove {{ $deletingName ?: 'this' }}'s record for {{ \Illuminate\Support\Carbon::parse($month)->format('F Y') }}?
                </p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="cancelDelete"
                        class="px-3.5 py-2 rounded-lg text-sm font-medium text-gray-600 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800">Cancel</button>
                    <button type="button" wire:click="confirmDelete"
                        class="px-3.5 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-sm font-medium">Remove</button>
                </div>
            </div>
        </div>
    @endif
</div>
