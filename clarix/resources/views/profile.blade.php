<x-app-layout pageTitle="Profile Settings">

    <div class="max-w-2xl space-y-6">

        <div>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Profile</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 dark:text-gray-500 mt-0.5">Manage your account information and password.</p>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            <livewire:profile.update-profile-information-form />
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            <livewire:profile.update-password-form />
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            <livewire:profile.delete-user-form />
        </div>

    </div>
</x-app-layout>
