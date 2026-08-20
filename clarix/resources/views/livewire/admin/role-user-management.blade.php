<div>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-slate-100">{{ $roleLabel }}</h1>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">Manage {{ strtolower($roleLabel) }} accounts</p>
        </div>
        {{-- Add button: desktop only --}}
        <button wire:click="openCreate"
            class="hidden md:inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add {{ $roleSingular }}
        </button>
    </div>

    {{-- Search row: search + Add button (mobile); search only (desktop, button in header) --}}
    <div class="mb-5 flex items-center gap-2">
        <div class="relative flex-1 md:max-w-xs">
            <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search by name or email..."
                class="w-full pl-9 pr-4 py-2 border border-gray-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:placeholder-slate-500 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        {{-- Add button: mobile only --}}
        <button wire:click="openCreate"
            class="inline-flex md:hidden items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors shadow-sm shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add {{ $roleSingular }}
        </button>
    </div>

    {{-- Mobile: Card layout --}}
    <div class="block md:hidden">
        @if($users->count())
            <div class="space-y-3">
                @foreach($users as $user)
                    <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 p-4">
                        {{-- Card header: avatar + name + email --}}
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 rounded-full bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center shrink-0">
                                <span class="text-sm font-semibold text-indigo-600 dark:text-indigo-400">{{ strtoupper(substr($user->name, 0, 2)) }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-slate-100 truncate">{{ $user->name }}</p>
                                <p class="text-xs text-gray-400 dark:text-slate-500 truncate">{{ $user->email }}</p>
                            </div>
                        </div>
                        {{-- Key-value rows --}}
                        <dl class="space-y-1.5 text-sm mb-3">
                            @if($managedRole === 'pm')
                                <div class="flex justify-between">
                                    <dt class="text-gray-500 dark:text-slate-400">Unit</dt>
                                    <dd class="font-medium text-gray-900 dark:text-slate-100">{{ $user->unit?->name ?? '-' }}</dd>
                                </div>
                            @endif
                            <div class="flex justify-between">
                                <dt class="text-gray-500 dark:text-slate-400">Joined</dt>
                                <dd class="text-gray-500 dark:text-slate-400">{{ $user->created_at->format('M d, Y') }}</dd>
                            </div>
                        </dl>
                        {{-- Action buttons --}}
                        <div class="flex gap-2 pt-3 border-t border-gray-100 dark:border-slate-800/60">
                            <button wire:click="openEdit({{ $user->id }})"
                                class="flex-1 py-2 text-sm font-medium text-indigo-600 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/40 dark:hover:bg-indigo-950/70 rounded-lg transition-colors">
                                Edit
                            </button>
                            <button wire:click="openDeleteModal({{ $user->id }}, '{{ $user->name }}')"
                                class="flex-1 py-2 text-sm font-medium text-red-500 bg-red-50 hover:bg-red-100 dark:bg-red-950/40 dark:hover:bg-red-950/70 rounded-lg transition-colors">
                                Delete
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
            @if($users->hasPages())
                <div class="py-2">{{ $users->links() }}</div>
            @endif
        @else
            <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 py-16 text-center">
                <p class="text-sm text-gray-500 dark:text-slate-400">No {{ strtolower($roleLabel) }} found.</p>
            </div>
        @endif
    </div>

    {{-- Desktop: Table layout --}}
    <div class="hidden md:block bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 overflow-hidden">
        @if($users->count())
            <table class="min-w-full divide-y divide-gray-100 dark:divide-slate-800/60">
                <thead class="bg-gray-50 dark:bg-slate-950/60">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">User</th>
                        @if($managedRole === 'pm')
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Unit</th>
                        @endif
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Joined</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-800/60">
                    @foreach($users as $user)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center flex-shrink-0">
                                        <span class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">{{ strtoupper(substr($user->name, 0, 2)) }}</span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-slate-100">{{ $user->name }}</p>
                                        <p class="text-xs text-gray-400 dark:text-slate-500">{{ $user->email }}</p>
                                    </div>
                                </div>
                            </td>
                            @if($managedRole === 'pm')
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-slate-400">{{ $user->unit?->name ?? '-' }}</td>
                            @endif
                            <td class="px-5 py-3 text-sm text-gray-500 dark:text-slate-400">{{ $user->created_at->format('M d, Y') }}</td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button wire:click="openEdit({{ $user->id }})"
                                        class="px-3 py-1.5 text-xs font-medium text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 rounded-lg transition-colors">Edit</button>
                                    <button wire:click="openDeleteModal({{ $user->id }}, '{{ $user->name }}')"
                                        class="px-3 py-1.5 text-xs font-medium text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition-colors">Delete</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if($users->hasPages())
                <div class="px-5 py-4 border-t border-gray-100 dark:border-slate-800/60">{{ $users->links() }}</div>
            @endif
        @else
            <div class="py-16 text-center">
                <p class="text-sm text-gray-500 dark:text-slate-400">No {{ strtolower($roleLabel) }} found.</p>
            </div>
        @endif
    </div>

    {{-- Modal --}}
    <x-livewire-modal :title="$editingId ? 'Edit ' . $roleSingular : 'Add ' . $roleSingular">
        <form wire:submit="save" class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Full Name</label>
                <input wire:model="name" type="text" placeholder="Jane Smith"
                    class="w-full border border-gray-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('name') border-red-400 @enderror">
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Email</label>
                <div class="flex rounded-lg border border-gray-300 dark:border-slate-700 overflow-hidden focus-within:ring-2 focus-within:ring-indigo-500 @error('email_username') border-red-400 @enderror">
                    <input wire:model="email_username" type="text" placeholder="username"
                        class="flex-1 px-3 py-2.5 text-sm focus:outline-none dark:bg-slate-900 dark:text-slate-200 min-w-0">
                    <span class="flex items-center px-3 bg-gray-50 dark:bg-slate-950/50 border-l border-gray-200 dark:border-slate-800/60 text-sm text-gray-400 font-medium select-none whitespace-nowrap">@clarix.com</span>
                </div>
                @error('email_username') <p class="mt-0.5 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            @if($managedRole === 'pm')
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Unit</label>
                <select wire:model="unit_id"
                    class="w-full border border-gray-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('unit_id') border-red-400 @enderror">
                    <option value="">Select unit</option>
                    @foreach($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                    @endforeach
                </select>
                @error('unit_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">
                    Password {{ $editingId ? '(leave blank to keep current)' : '' }}
                </label>
                <input wire:model="password" type="password" placeholder="{{ $editingId ? '••••••••' : 'Min 8 characters' }}"
                    class="w-full border border-gray-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('password') border-red-400 @enderror">
                @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-3 pt-1">
                <button type="submit"
                    class="flex-1 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors">
                    {{ $editingId ? 'Save Changes' : 'Create ' . $roleSingular }}
                </button>
                <button type="button" wire:click="$set('showModal', false)"
                    class="flex-1 py-2.5 border border-gray-300 dark:border-slate-700 text-gray-700 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </x-livewire-modal>

    <x-delete-confirm-modal
        title="Delete {{ $roleSingular }}"
        :description="'You are about to delete: ' . $deletingName"
        :consequences="['Remove user access permanently', 'May affect assigned tasks', 'This action cannot be undone']"
    />
</div>
