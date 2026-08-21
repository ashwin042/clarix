<x-app-layout pageTitle="Settings">
    <div class="max-w-2xl space-y-8">

        {{-- Header --}}
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-slate-100">Settings</h1>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">Manage your account, security, and preferences.</p>
        </div>

        {{-- Profile Section --}}
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm dark:shadow-none">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-800/60">
                <h2 class="text-base font-semibold text-gray-900 dark:text-slate-100">Profile Information</h2>
                <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">Update your name and email address.</p>
            </div>
            <div class="p-6">
                <livewire:profile.update-profile-information-form />
            </div>
        </div>

        {{-- Security Section --}}
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm dark:shadow-none">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-800/60">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-slate-100">Security</h2>
                </div>
                <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">Update your password to keep your account secure.</p>
            </div>
            <div class="p-6">
                <livewire:profile.update-password-form />
            </div>
        </div>

        {{-- Danger Zone --}}
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-red-200 dark:border-red-500/20 shadow-sm dark:shadow-none">
            <div class="px-6 py-4 border-b border-red-100 dark:border-red-500/20">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    <h2 class="text-base font-semibold text-red-600 dark:text-red-400">Danger Zone</h2>
                </div>
                <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">Irreversible account actions. Proceed with caution.</p>
            </div>
            <div class="p-6">
                <livewire:profile.delete-user-form />
            </div>
        </div>

    </div>
</x-app-layout>
