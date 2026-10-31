<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $password = '';

    /**
     * Confirm the current user's password.
     */
    public function confirmPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <x-auth.heading :title="__('Confirm your password')" :subtitle="__('This is a secure area of the application. Please confirm your password before continuing.')" />

    <form wire:submit="confirmPassword" class="space-y-5">
        <!-- Password -->
        <div class="auth-rise" style="--i:2">
            <x-auth.input id="password" :label="__('Password')" error-key="password" wire:model="password" type="password" name="password" :toggle="true" required autocomplete="current-password" />
        </div>

        <div class="auth-rise" style="--i:3">
            <x-auth.button target="confirmPassword" loading="Confirming">{{ __('Confirm') }}</x-auth.button>
        </div>
    </form>
</div>
