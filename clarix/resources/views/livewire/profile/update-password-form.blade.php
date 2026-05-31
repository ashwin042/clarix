<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component
{
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');
            throw $e;
        }

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->dispatch('notify', message: 'Password updated.', type: 'success');
    }
}; ?>

<form wire:submit="updatePassword" class="space-y-4">
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Current Password</label>
        <input wire:model="current_password" type="password"
            class="w-full border border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('current_password') border-red-400 @enderror">
        @error('current_password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">New Password</label>
            <input wire:model="password" type="password"
                class="w-full border border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('password') border-red-400 @enderror">
            @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Confirm Password</label>
            <input wire:model="password_confirmation" type="password"
                class="w-full border border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
    </div>

    <div class="flex items-center gap-3 pt-2">
        <button type="submit"
            class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors">
            Update Password
        </button>
        <span wire:loading wire:target="updatePassword" class="text-xs text-gray-500">Updating...</span>
    </div>
</form>
