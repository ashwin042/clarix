<div>
    <div class="flex items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white">Organizations</h1>
            <p class="text-sm text-slate-400 mt-0.5">Every agency on the platform</p>
        </div>
        <button wire:click="openCreate"
            class="inline-flex items-center gap-2 px-4 py-2 bg-amber-500 text-amber-950 text-sm font-semibold rounded-lg hover:bg-amber-400 transition-colors shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New organization
        </button>
    </div>

    <div class="mb-4 relative max-w-xs">
        <svg class="absolute left-3 top-2.5 w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search organizations..."
            class="w-full pl-9 pr-4 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500">
    </div>

    @if($organizations->count())
        <div class="overflow-x-auto rounded-xl border border-slate-800">
            <table class="min-w-full divide-y divide-slate-800">
                <thead class="bg-slate-900">
                    <tr class="text-left text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                        <th class="px-4 py-3">Organization</th>
                        <th class="px-4 py-3">Tier</th>
                        <th class="px-4 py-3 text-right">Users</th>
                        <th class="px-4 py-3">Subscription</th>
                        <th class="px-4 py-3">Created</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 bg-slate-900/40">
                    @foreach($organizations as $organization)
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="px-4 py-3">
                                <a href="{{ route('superadmin.organizations.show', $organization) }}"
                                    class="font-semibold text-slate-100 hover:text-amber-400 transition-colors">{{ $organization->name }}</a>
                                <div class="text-xs text-slate-500 font-mono">{{ $organization->slug }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold uppercase tracking-wide
                                    {{ $organization->subscription_type === 'pro' ? 'bg-amber-500/15 text-amber-400'
                                       : ($organization->subscription_type === 'standard' ? 'bg-sky-500/15 text-sky-400' : 'bg-slate-700/50 text-slate-300') }}">
                                    {{ $organization->subscription_type }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right text-slate-300 tabular-nums">{{ $organization->users_count }}</td>
                            <td class="px-4 py-3">
                                @if($organization->subscription)
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold uppercase tracking-wide
                                        {{ $organization->subscription->status === 'active' ? 'bg-emerald-500/15 text-emerald-400'
                                           : ($organization->subscription->status === 'past_due' ? 'bg-red-500/15 text-red-400' : 'bg-slate-700/50 text-slate-300') }}">
                                        {{ str_replace('_', ' ', $organization->subscription->status) }}
                                    </span>
                                    <div class="text-xs text-slate-500 mt-0.5">{{ $organization->subscription->renewalSummary() }}</div>
                                @else
                                    <span class="text-xs text-slate-500">None</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-400 text-sm whitespace-nowrap">{{ $organization->created_at?->format('d M Y') }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('superadmin.organizations.show', $organization) }}"
                                        class="px-2.5 py-1 rounded-md text-xs font-medium text-slate-300 hover:bg-slate-800 transition-colors">View</a>
                                    <button wire:click="openEdit({{ $organization->id }})"
                                        class="px-2.5 py-1 rounded-md text-xs font-medium text-slate-300 hover:bg-slate-800 transition-colors">Edit</button>
                                    <button wire:click="openDeleteModal({{ $organization->id }}, '{{ addslashes($organization->name) }}')"
                                        class="px-2.5 py-1 rounded-md text-xs font-medium text-red-400 hover:bg-red-900/30 transition-colors">Delete</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $organizations->links() }}</div>
    @else
        <div class="rounded-xl border border-dashed border-slate-700 p-12 text-center">
            <p class="text-slate-400">No organizations match that search.</p>
        </div>
    @endif

    {{-- Create / edit --}}
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70">
            <div class="w-full max-w-lg bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="px-6 py-4 border-b border-slate-800">
                    <h2 class="text-lg font-semibold text-white">
                        {{ $editingId ? 'Edit organization' : 'New organization' }}
                    </h2>
                    @unless($editingId)
                        <p class="text-xs text-slate-400 mt-1">You will be asked to create its first administrator next.</p>
                    @endunless
                </div>

                <form wire:submit="save" class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1.5">Name</label>
                        <input wire:model.live.debounce.400ms="name" type="text"
                            class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500">
                        @error('name') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1.5">Slug</label>
                        <input wire:model.blur="slug" type="text"
                            class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-sm text-slate-100 font-mono focus:outline-none focus:ring-2 focus:ring-amber-500">
                        <p class="mt-1 text-xs text-slate-500">Used in URLs and, later, organization-scoped sign-in. Must be unique.</p>
                        @error('slug') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>

                    {{-- Read-only on purpose.

                         This used to be an editable select, and it was the
                         second screen writing organizations.subscription_type
                         while Organization Detail wrote the subscription — so
                         the two drifted apart, and one agency ended up
                         labelled Base while paying for Standard. The
                         subscription is the only input now; the label here
                         follows it. --}}
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1.5">Plan</label>
                        <p class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-lg text-sm text-slate-300">
                            {{ $editingId
                                ? ucfirst(app(\App\Services\PlanFeatures::class)->planFor((int) $editingId))
                                : ucfirst(config('plans.default')) }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ $editingId
                                ? 'Set by the subscription. Change it in Organization Detail.'
                                : 'A new organization starts here until a subscription is set up in Organization Detail.' }}
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1.5">Contact number</label>
                            <input wire:model="contact_number" type="text"
                                class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500">
                            @error('contact_number') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1.5">Email</label>
                            <input wire:model="email" type="email"
                                class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500">
                            @error('email') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1.5">Address</label>
                        <textarea wire:model="address" rows="2"
                            class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500"></textarea>
                        @error('address') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('showModal', false)"
                            class="px-4 py-2 rounded-lg text-sm font-medium text-slate-300 hover:bg-slate-800 transition-colors">Cancel</button>
                        <button type="submit"
                            class="px-4 py-2 rounded-lg bg-amber-500 text-amber-950 text-sm font-semibold hover:bg-amber-400 transition-colors">
                            {{ $editingId ? 'Save changes' : 'Create organization' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Delete confirmation --}}
    @if($showDeleteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70">
            <div class="w-full max-w-md bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl">
                <div class="px-6 py-5">
                    <h2 class="text-lg font-semibold text-white">Delete {{ $deletingName }}?</h2>
                    <p class="text-sm text-slate-400 mt-2">
                        An organization can only be removed while it is empty. If it still holds
                        units, users or tasks, this will be refused and nothing will change.
                    </p>
                </div>
                <div class="px-6 py-4 border-t border-slate-800 flex items-center justify-end gap-2">
                    <button wire:click="cancelDelete"
                        class="px-4 py-2 rounded-lg text-sm font-medium text-slate-300 hover:bg-slate-800 transition-colors">Cancel</button>
                    <button wire:click="confirmDelete"
                        class="px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-semibold hover:bg-red-500 transition-colors">Delete</button>
                </div>
            </div>
        </div>
    @endif
</div>
