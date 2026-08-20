<div class="max-w-lg mx-auto">
    <a href="{{ route('superadmin.organizations.show', $organization) }}"
        class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-amber-400 transition-colors mb-4">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        {{ $organization->name }}
    </a>

    <div class="rounded-2xl border border-slate-800 bg-slate-900/40 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-800">
            <h1 class="text-lg font-semibold text-white">
                {{ $existingAdmins === 0 ? 'Create the first administrator' : 'Add an administrator' }}
            </h1>
            <p class="text-sm text-slate-400 mt-1">
                This account will be created inside
                <span class="font-semibold text-amber-400">{{ $organization->name }}</span>
                with the admin role, and will only ever see that organization's data.
            </p>
        </div>

        <form wire:submit="save" class="px-6 py-5 space-y-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1.5">Full name</label>
                <input wire:model="name" type="text"
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500">
                @error('name') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1.5">Email</label>
                <input wire:model="email" type="email"
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500">
                <p class="mt-1 text-xs text-slate-500">Sign-in is by email across the platform, so this must be unique everywhere.</p>
                @error('email') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1.5">Temporary password</label>
                <input wire:model="password" type="text"
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-sm text-slate-100 font-mono focus:outline-none focus:ring-2 focus:ring-amber-500">
                <p class="mt-1 text-xs text-slate-500">Shown in plain text so you can pass it on. Have them change it after first sign-in.</p>
                @error('password') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <a href="{{ route('superadmin.organizations.show', $organization) }}"
                    class="px-4 py-2 rounded-lg text-sm font-medium text-slate-300 hover:bg-slate-800 transition-colors">
                    {{ $existingAdmins === 0 ? 'Skip for now' : 'Cancel' }}
                </a>
                <button type="submit"
                    class="px-4 py-2 rounded-lg bg-amber-500 text-amber-950 text-sm font-semibold hover:bg-amber-400 transition-colors">
                    Create administrator
                </button>
            </div>
        </form>
    </div>
</div>
