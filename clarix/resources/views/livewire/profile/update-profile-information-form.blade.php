<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';
    public string $email_username = '';

    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email_username = str_replace('@clarix.com', '', Auth::user()->email);
    }

    public function updateProfileInformation(): void
    {
        $user = Auth::user();
        $email = strtolower(trim($this->email_username)) . '@clarix.com';

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email_username' => ['required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9._-]+$/'],
        ]);

        if (User::where('email', $email)->where('id', '!=', $user->id)->exists()) {
            $this->addError('email_username', 'This email is already taken.');
            return;
        }

        $user->update([
            'name' => $this->name,
            'email' => $email,
        ]);

        $this->dispatch('profile-updated', name: $user->name);
        $this->dispatch('notify', message: 'Profile updated.', type: 'success');
    }
}; ?>

<form wire:submit="updateProfileInformation" class="space-y-4">
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Name</label>
        <input wire:model="name" type="text" required
            class="w-full border border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('name') border-red-400 @enderror">
        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Email</label>
        <div class="flex">
            <input wire:model="email_username" type="text" required
                class="flex-1 border border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 rounded-l-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('email_username') border-red-400 @enderror"
                placeholder="username">
            <span class="inline-flex items-center px-3 py-2.5 bg-gray-100 dark:bg-slate-800 border border-l-0 border-gray-300 dark:border-slate-700 rounded-r-lg text-sm text-gray-500 dark:text-slate-300 font-mono">@clarix.com</span>
        </div>
        @error('email_username') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="flex items-center gap-3 pt-2">
        <button type="submit"
            class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors">
            Save Changes
        </button>
        <span wire:loading wire:target="updateProfileInformation" class="text-xs text-gray-500">Saving...</span>
    </div>
</form>
