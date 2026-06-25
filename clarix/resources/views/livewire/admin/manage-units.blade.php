<div>
    {{-- Header: title left, Add Unit button right (desktop only) --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-slate-100">Units</h1>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">Manage organisational units</p>
        </div>
        <button wire:click="openCreate"
            class="hidden md:inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Unit
        </button>
    </div>

    {{-- Search row: search + button side-by-side on mobile; search only on desktop --}}
    <div class="mb-4 flex items-center gap-2">
        <div class="relative flex-1 md:max-w-xs">
            <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search units..."
                class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100 dark:placeholder-slate-500">
        </div>
        <button wire:click="openCreate"
            class="inline-flex md:hidden items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors shadow-sm shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Unit
        </button>
    </div>

    {{-- Content --}}
    @if($units->count())
        {{-- Mobile: Card layout (hidden on md+) --}}
        <div class="md:hidden space-y-3">
            @foreach($units as $unit)
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 p-4">
                    {{-- Card title: avatar + name --}}
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-9 h-9 rounded-lg bg-indigo-50 dark:bg-indigo-950/50 flex items-center justify-center shrink-0">
                            <span class="text-xs font-bold text-indigo-600">{{ strtoupper(substr($unit->name, 0, 2)) }}</span>
                        </div>
                        <span class="text-sm font-semibold text-gray-900 dark:text-slate-100 leading-snug">{{ $unit->name }}</span>
                    </div>
                    {{-- Key-value rows --}}
                    <dl class="space-y-1.5 text-sm mb-3">
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-slate-400">Members</dt>
                            <dd class="font-medium text-gray-900 dark:text-slate-100">{{ $unit->users_count }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-slate-400">Tasks</dt>
                            <dd class="font-medium text-gray-900 dark:text-slate-100">{{ $unit->tasks_count }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-slate-400">Created</dt>
                            <dd class="text-gray-500 dark:text-slate-400">{{ $unit->created_at->format('M d, Y') }}</dd>
                        </div>
                    </dl>
                    {{-- Action buttons --}}
                    <div class="flex gap-2 pt-3 border-t border-gray-100 dark:border-slate-800/60">
                        @if(auth()->user()->isAdmin())
                            <a href="{{ route('units.show', $unit) }}"
                                class="flex-1 text-center py-2 text-sm font-medium text-gray-600 dark:text-slate-400 bg-gray-50 hover:bg-gray-100 dark:bg-slate-800/60 dark:hover:bg-slate-800 rounded-lg transition-colors">
                                View
                            </a>
                        @endif
                        <button wire:click="openEdit({{ $unit->id }})"
                            class="flex-1 py-2 text-sm font-medium text-indigo-600 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/40 dark:hover:bg-indigo-950/70 rounded-lg transition-colors">
                            Edit
                        </button>
                        <button wire:click="openDeleteModal({{ $unit->id }}, '{{ $unit->name }}')"
                            class="flex-1 py-2 text-sm font-medium text-red-500 bg-red-50 hover:bg-red-100 dark:bg-red-950/40 dark:hover:bg-red-950/70 rounded-lg transition-colors">
                            Delete
                        </button>
                    </div>
                </div>
            @endforeach
            @if($units->hasPages())
                <div class="py-2">{{ $units->links() }}</div>
            @endif
        </div>

        {{-- Desktop: Table layout (hidden below md) --}}
        <div class="hidden md:block bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100 dark:divide-slate-800/60">
                <thead class="bg-gray-50 dark:bg-slate-950/60">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Name</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Members</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Tasks</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Created</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-800/60">
                    @foreach($units as $unit)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center">
                                        <span class="text-xs font-bold text-indigo-600">{{ strtoupper(substr($unit->name, 0, 2)) }}</span>
                                    </div>
                                    <span class="text-sm font-medium text-gray-900 dark:text-slate-100">{{ $unit->name }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-sm text-gray-600 dark:text-slate-400">{{ $unit->users_count }}</td>
                            <td class="px-5 py-3 text-sm text-gray-600 dark:text-slate-400">{{ $unit->tasks_count }}</td>
                            <td class="px-5 py-3 text-sm text-gray-500 dark:text-slate-400">{{ $unit->created_at->format('M d, Y') }}</td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if(auth()->user()->isAdmin())
                                        <a href="{{ route('units.show', $unit) }}"
                                            class="px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800/50 rounded-lg transition-colors">View</a>
                                    @endif
                                    <button wire:click="openEdit({{ $unit->id }})"
                                        class="px-3 py-1.5 text-xs font-medium text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">Edit</button>
                                    <button wire:click="openDeleteModal({{ $unit->id }}, '{{ $unit->name }}')"
                                        class="px-3 py-1.5 text-xs font-medium text-red-500 hover:bg-red-50 rounded-lg transition-colors">Delete</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if($units->hasPages())
                <div class="px-5 py-4 border-t border-gray-100 dark:border-slate-800/60">{{ $units->links() }}</div>
            @endif
        </div>
    @else
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 overflow-hidden">
            <div class="py-16 text-center">
                <div class="w-12 h-12 mx-auto bg-gray-100 dark:bg-slate-800 rounded-full flex items-center justify-center mb-3">
                    <svg class="w-6 h-6 text-gray-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16"/></svg>
                </div>
                <p class="text-sm text-gray-500 dark:text-slate-400">No units found.</p>
            </div>
        </div>
    @endif

    {{-- Create / Edit Modal --}}
    <x-livewire-modal :title="$editingId ? 'Edit Unit' : 'Add Unit'" maxWidth="sm">
        <form wire:submit="save" class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Unit Name</label>
                <input wire:model="name" type="text"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('name') border-red-400 @enderror"
                    placeholder="e.g. Content Unit A" autofocus>
                @error('name') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex gap-3 pt-1">
                <button type="submit"
                    class="flex-1 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors">
                    {{ $editingId ? 'Save Changes' : 'Create Unit' }}
                </button>
                <button type="button" wire:click="$set('showModal', false)"
                    class="flex-1 py-2.5 border border-gray-300 text-gray-700 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </x-livewire-modal>

    <x-delete-confirm-modal
        title="Delete Unit"
        :description="'You are about to delete: ' . $deletingName"
        :consequences="['Remove all users assigned to this unit', 'Affect all related tasks and data']"
    />
</div>
