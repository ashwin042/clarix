<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-slate-100">Leave types</h1>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">The categories your agency offers.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('leave.index') }}" class="text-sm font-medium text-gray-500 dark:text-slate-400 hover:underline">Back to leave</a>
            <button type="button" wire:click="openCreate"
                class="px-3.5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium">New type</button>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 dark:bg-slate-800/50 text-left">
                    <tr class="text-xs uppercase tracking-wider text-gray-500 dark:text-slate-400">
                        <th class="px-5 py-2.5 font-medium">Name</th>
                        <th class="px-5 py-2.5 font-medium">Annual allowance</th>
                        <th class="px-5 py-2.5 font-medium">Requests</th>
                        <th class="px-5 py-2.5 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                    @forelse($types as $type)
                        <tr>
                            <td class="px-5 py-2.5 text-gray-900 dark:text-slate-100">{{ $type->name }}</td>
                            <td class="px-5 py-2.5 text-gray-600 dark:text-slate-300">
                                @if($type->default_annual_allowance !== null)
                                    {{ $type->default_annual_allowance }} days
                                @else
                                    <span class="text-gray-400 dark:text-slate-500">Not tracked</span>
                                @endif
                            </td>
                            <td class="px-5 py-2.5 text-gray-600 dark:text-slate-300">{{ $type->requests_count }}</td>
                            <td class="px-5 py-2.5 text-right whitespace-nowrap">
                                <button type="button" wire:click="openEdit({{ $type->id }})"
                                    class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">Edit</button>
                                <button type="button" wire:click="openDeleteModal({{ $type->id }}, '{{ addslashes($type->name) }}')"
                                    class="ml-3 text-xs font-medium text-rose-600 dark:text-rose-400 hover:underline">Remove</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-8 text-center text-gray-500 dark:text-slate-400">No leave types defined.</td></tr>
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
                        {{ $editingId ? 'Edit leave type' : 'New leave type' }}
                    </h3>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">Name</label>
                        <input type="text" wire:model="name"
                            class="w-full rounded-lg border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">
                            Annual allowance <span class="text-gray-400">(days, leave blank if not tracked)</span>
                        </label>
                        <input type="number" min="0" max="365" wire:model="default_annual_allowance"
                            class="w-full rounded-lg border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        @error('default_annual_allowance') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        <p class="mt-1.5 text-xs text-gray-500 dark:text-slate-400">
                            Shown alongside days taken. Requests are not blocked for exceeding it.
                        </p>
                    </div>
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
                <h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100">Remove leave type</h3>
                <p class="mt-1.5 text-sm text-gray-500 dark:text-slate-400">Remove "{{ $deletingName }}"?</p>
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
