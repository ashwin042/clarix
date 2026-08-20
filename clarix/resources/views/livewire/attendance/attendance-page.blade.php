<div class="space-y-6">
    <div>
        <h1 class="text-xl font-semibold text-gray-900 dark:text-slate-100">Attendance</h1>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">Clock in and out, and review the record.</p>
    </div>

    {{-- Your own clock. Always available: recording your own attendance is
         structural, not something an agency switches on. --}}
    <div class="max-w-md">
        @livewire('attendance.clock-widget')
    </div>

    {{-- Your own recent history --}}
    <div class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-200 dark:border-slate-800">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-slate-100">Your last 14 days</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 dark:bg-slate-800/50 text-left">
                    <tr class="text-xs uppercase tracking-wider text-gray-500 dark:text-slate-400">
                        <th class="px-5 py-2.5 font-medium">Date</th>
                        <th class="px-5 py-2.5 font-medium">Status</th>
                        <th class="px-5 py-2.5 font-medium">In</th>
                        <th class="px-5 py-2.5 font-medium">Out</th>
                        <th class="px-5 py-2.5 font-medium">Worked</th>
                        <th class="px-5 py-2.5 font-medium">Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                    @forelse($mine as $record)
                        <tr>
                            <td class="px-5 py-2.5 text-gray-900 dark:text-slate-100">{{ $record->date->format('D, j M') }}</td>
                            <td class="px-5 py-2.5">
                                <x-attendance-status :status="$record->status" />
                            </td>
                            <td class="px-5 py-2.5 text-gray-600 dark:text-slate-300">{{ $record->clock_in?->format('H:i') ?? '—' }}</td>
                            <td class="px-5 py-2.5 text-gray-600 dark:text-slate-300">{{ $record->clock_out?->format('H:i') ?? '—' }}</td>
                            <td class="px-5 py-2.5 text-gray-600 dark:text-slate-300">{{ $record->workedForHumans() }}</td>
                            <td class="px-5 py-2.5 text-gray-500 dark:text-slate-400">{{ $record->notes ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-gray-500 dark:text-slate-400">
                                Nothing recorded yet. Clock in to start.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- The team table, only for whoever may look beyond their own record. --}}
    @if($this->canViewTeam)
        <div class="rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-slate-100">
                        {{ auth()->user()->isPm() ? 'Your unit' : 'Everyone' }}
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">{{ $roster->count() }} {{ Str::plural('person', $roster->count()) }}</p>
                </div>

                <input type="date" wire:model.live="date"
                    class="rounded-lg border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-slate-800/50 text-left">
                        <tr class="text-xs uppercase tracking-wider text-gray-500 dark:text-slate-400">
                            <th class="px-5 py-2.5 font-medium">Name</th>
                            <th class="px-5 py-2.5 font-medium">Role</th>
                            <th class="px-5 py-2.5 font-medium">Status</th>
                            <th class="px-5 py-2.5 font-medium">In</th>
                            <th class="px-5 py-2.5 font-medium">Out</th>
                            <th class="px-5 py-2.5 font-medium">Worked</th>
                            @if($this->canManage)
                                <th class="px-5 py-2.5 font-medium text-right">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                        @forelse($roster as $person)
                            @php $record = $team->get($person->id); @endphp
                            <tr>
                                <td class="px-5 py-2.5 text-gray-900 dark:text-slate-100">{{ $person->name }}</td>
                                <td class="px-5 py-2.5 text-gray-500 dark:text-slate-400 capitalize">{{ $person->role }}</td>
                                <td class="px-5 py-2.5">
                                    @if($record)
                                        <x-attendance-status :status="$record->status" />
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-slate-500">Not recorded</span>
                                    @endif
                                </td>
                                <td class="px-5 py-2.5 text-gray-600 dark:text-slate-300">{{ $record?->clock_in?->format('H:i') ?? '—' }}</td>
                                <td class="px-5 py-2.5 text-gray-600 dark:text-slate-300">{{ $record?->clock_out?->format('H:i') ?? '—' }}</td>
                                <td class="px-5 py-2.5 text-gray-600 dark:text-slate-300">{{ $record?->workedForHumans() ?? '—' }}</td>
                                @if($this->canManage)
                                    <td class="px-5 py-2.5 text-right whitespace-nowrap">
                                        <button type="button" wire:click="openMark({{ $person->id }})"
                                            class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                                            {{ $record ? 'Edit' : 'Mark' }}
                                        </button>
                                        @if($record && auth()->user()->isAdmin())
                                            <button type="button" wire:click="openDeleteModal({{ $record->id }}, '{{ addslashes($person->name) }}')"
                                                class="ml-3 text-xs font-medium text-rose-600 dark:text-rose-400 hover:underline">
                                                Remove
                                            </button>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $this->canManage ? 7 : 6 }}" class="px-5 py-8 text-center text-gray-500 dark:text-slate-400">
                                    Nobody to show.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Mark / correct --}}
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" wire:key="mark-modal">
            <div class="w-full max-w-md rounded-xl bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 shadow-xl">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-slate-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100">
                        Attendance for {{ \Illuminate\Support\Carbon::parse($date)->format('j M Y') }}
                    </h3>
                </div>

                <div class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">Status</label>
                        <select wire:model="status"
                            class="w-full rounded-lg border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach($statuses as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-slate-400 mb-1">Notes <span class="text-gray-400">(optional)</span></label>
                        <textarea wire:model="notes" rows="3"
                            class="w-full rounded-lg border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        @error('notes') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <p class="text-xs text-gray-500 dark:text-slate-400">
                        Marking someone absent or on leave clears any clock times recorded for that day.
                    </p>
                </div>

                <div class="px-5 py-4 border-t border-gray-200 dark:border-slate-800 flex justify-end gap-2">
                    <button type="button" wire:click="$set('showModal', false)"
                        class="px-3.5 py-2 rounded-lg text-sm font-medium text-gray-600 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800">
                        Cancel
                    </button>
                    <button type="button" wire:click="save"
                        class="px-3.5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium">
                        Save
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($showDeleteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" wire:key="delete-modal">
            <div class="w-full max-w-sm rounded-xl bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 shadow-xl p-5">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100">Remove attendance record</h3>
                <p class="mt-1.5 text-sm text-gray-500 dark:text-slate-400">
                    This removes {{ $deletingName ?: 'this person' }}'s record for the selected day.
                </p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="cancelDelete"
                        class="px-3.5 py-2 rounded-lg text-sm font-medium text-gray-600 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800">
                        Cancel
                    </button>
                    <button type="button" wire:click="confirmDelete"
                        class="px-3.5 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-sm font-medium">
                        Remove
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
