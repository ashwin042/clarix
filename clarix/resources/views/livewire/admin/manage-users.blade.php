<div>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-slate-100">Users</h1>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">Manage project managers and writers</p>
        </div>
        {{-- Add User button: desktop only --}}
        <button wire:click="openCreate"
            class="hidden md:inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add User
        </button>
    </div>

    {{-- Filters --}}
    {{-- Mobile: search + Add User button on row 1, role select on row 2
         Desktop: all on one row, Add User in header --}}
    <div class="mb-5 space-y-2 md:space-y-0 md:flex md:items-center md:gap-3">
        <div class="flex items-center gap-2">
            <div class="relative flex-1 md:max-w-xs">
                <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search by name or email..."
                    class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100 dark:placeholder-slate-500">
            </div>
            {{-- Add User button: mobile only, beside search --}}
            <button wire:click="openCreate"
                class="inline-flex md:hidden items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors shadow-sm shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add User
            </button>
        </div>
        <select wire:model.live="filterRole"
            class="w-full md:w-auto border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100">
            <option value="">All roles</option>
            <option value="admin">Admin</option>
            <option value="pm">Project Manager</option>
            <option value="writer">Writer</option>
        </select>
    </div>

    {{-- Content --}}
    @if($users->count())
        {{-- Mobile: Card layout (hidden on md+) --}}
        <div class="block md:hidden space-y-3">
            @foreach($users as $user)
                @php
                    $roleClass = match($user->role) {
                        'admin'  => 'bg-purple-100 text-purple-700',
                        'pm'     => 'bg-blue-100 text-blue-700',
                        'writer' => 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-400',
                    };
                @endphp
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 p-4">
                    {{-- Card header: avatar + name + email --}}
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-full bg-indigo-100 dark:bg-indigo-950/50 flex items-center justify-center shrink-0">
                            <span class="text-sm font-semibold text-indigo-600">{{ strtoupper(substr($user->name, 0, 2)) }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-slate-100 truncate">{{ $user->name }}</p>
                            <p class="text-xs text-gray-400 dark:text-slate-500 truncate">{{ $user->email }}</p>
                        </div>
                    </div>
                    {{-- Key-value rows --}}
                    <dl class="space-y-1.5 text-sm mb-3">
                        <div class="flex justify-between items-center">
                            <dt class="text-gray-500 dark:text-slate-400">Role</dt>
                            <dd>
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium {{ $roleClass }}">{{ ucfirst($user->role) }}</span>
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-slate-400">Unit</dt>
                            <dd class="font-medium text-gray-900 dark:text-slate-100">{{ $user->unit?->name ?? '—' }}</dd>
                        </div>
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
            @if($users->hasPages())
                <div class="py-2">{{ $users->links() }}</div>
            @endif
        </div>

        {{-- Desktop: Table layout (hidden below md) --}}
        <div class="hidden md:block bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100 dark:divide-slate-800/60">
                <thead class="bg-gray-50 dark:bg-slate-950/60">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">User</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Role</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Unit</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Joined</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-800/60">
                    @foreach($users as $user)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                                        <span class="text-xs font-semibold text-indigo-600">{{ strtoupper(substr($user->name, 0, 2)) }}</span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-slate-100">{{ $user->name }}</p>
                                        <p class="text-xs text-gray-400 dark:text-slate-500">{{ $user->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                @php
                                    $roleClass = match($user->role) {
                                        'admin'  => 'bg-purple-100 text-purple-700',
                                        'pm'     => 'bg-blue-100 text-blue-700',
                                        'writer' => 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-400',
                                    };
                                @endphp
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium {{ $roleClass }}">{{ ucfirst($user->role) }}</span>
                            </td>
                            <td class="px-5 py-3 text-sm text-gray-600 dark:text-slate-400">{{ $user->unit?->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-sm text-gray-500 dark:text-slate-400">{{ $user->created_at->format('M d, Y') }}</td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button wire:click="openEdit({{ $user->id }})"
                                        class="px-3 py-1.5 text-xs font-medium text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">Edit</button>
                                    <button wire:click="openDeleteModal({{ $user->id }}, '{{ $user->name }}')"
                                        class="px-3 py-1.5 text-xs font-medium text-red-500 hover:bg-red-50 rounded-lg transition-colors">Delete</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if($users->hasPages())
                <div class="px-5 py-4 border-t border-gray-100 dark:border-slate-800/60">{{ $users->links() }}</div>
            @endif
        </div>
    @else
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 overflow-hidden">
            <div class="py-16 text-center">
                <p class="text-sm text-gray-500 dark:text-slate-400">No users found.</p>
            </div>
        </div>
    @endif

    {{-- Create / Edit Modal --}}
    <x-livewire-modal :title="$editingId ? 'Edit User' : 'Add User'">
        <form wire:submit="save" class="p-6 space-y-4">
            {{-- Name --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Full Name</label>
                <input wire:model="name" type="text" placeholder="Jane Smith"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('name') border-red-400 @enderror">
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Email --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Email</label>
                <div class="flex rounded-lg border border-gray-300 overflow-hidden focus-within:ring-2 focus-within:ring-indigo-500 focus-within:border-indigo-500 @error('email_username') border-red-400 @enderror">
                    <input wire:model="email_username" type="text" placeholder="username"
                        class="flex-1 px-3 py-2.5 text-sm focus:outline-none min-w-0">
                    <span class="flex items-center px-3 bg-gray-50 dark:bg-slate-950/50 border-l border-gray-200 dark:border-slate-800/60 text-sm text-gray-400 dark:text-slate-500 font-medium select-none whitespace-nowrap">@clarix.com</span>
                </div>
                @error('email_username') <p class="mt-0.5 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Role + Unit --}}
            <div class="grid grid-cols-2 gap-4">
                @if(auth()->user()->isAdmin())
                    {{-- Admin: full role dropdown --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Role</label>
                        <select wire:model.live="role"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="admin">Admin</option>
                            <option value="pm">Project Manager</option>
                            <option value="writer">Writer</option>
                        </select>
                    </div>
                    {{-- Admin: unit dropdown (only for pm) --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Unit</label>
                        @if($role === 'pm')
                            <select wire:model="unit_id"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('unit_id') border-red-400 @enderror">
                                <option value="">Select unit</option>
                                @foreach($units as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                                @endforeach
                            </select>
                            @error('unit_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        @else
                            <input type="text" value="—" disabled
                                class="w-full border border-gray-200 dark:border-slate-800/60 bg-gray-50 dark:bg-slate-950/50 rounded-lg px-3 py-2.5 text-sm text-gray-400 dark:text-slate-500 cursor-not-allowed">
                        @endif
                    </div>
                @else
                    {{-- PM: locked role field --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Role</label>
                        <div class="relative">
                            <input type="text" value="Project Manager" disabled
                                class="w-full border border-gray-200 dark:border-slate-800/60 bg-gray-50 dark:bg-slate-950/50 rounded-lg px-3 py-2.5 text-sm text-gray-500 dark:text-slate-400 cursor-not-allowed">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2">
                                <svg class="w-4 h-4 text-gray-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            </span>
                        </div>
                        <p class="mt-1 text-[11px] text-gray-400 dark:text-slate-500">Only PMs can be added by a PM</p>
                    </div>
                    {{-- PM: locked unit field --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Unit</label>
                        <div class="relative">
                            <input type="text" value="{{ $pmUnit?->name ?? '—' }}" disabled
                                class="w-full border border-gray-200 dark:border-slate-800/60 bg-gray-50 dark:bg-slate-950/50 rounded-lg px-3 py-2.5 text-sm text-gray-500 dark:text-slate-400 cursor-not-allowed">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2">
                                <svg class="w-4 h-4 text-gray-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            </span>
                        </div>
                        <p class="mt-1 text-[11px] text-gray-400 dark:text-slate-500">Assigned to your unit automatically</p>
                    </div>
                @endif
            </div>

            {{-- Password --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">
                    Password {{ $editingId ? '(leave blank to keep current)' : '' }}
                </label>
                <input wire:model="password" type="password" placeholder="{{ $editingId ? '••••••••' : 'Min 8 characters' }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('password') border-red-400 @enderror">
                @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Actions --}}
            <div class="flex gap-3 pt-1">
                <button type="submit"
                    class="flex-1 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors">
                    {{ $editingId ? 'Save Changes' : 'Create User' }}
                </button>
                <button type="button" wire:click="$set('showModal', false)"
                    class="flex-1 py-2.5 border border-gray-300 text-gray-700 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </x-livewire-modal>

    <x-delete-confirm-modal
        title="Delete User"
        :description="'You are about to delete: ' . $deletingName"
        :consequences="['Remove user access permanently', 'May affect assigned tasks', 'This action cannot be undone']"
    />
</div>
