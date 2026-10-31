<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <x-auth.heading :title="__('Sign in')" :subtitle="__('Welcome back. Enter your details to open your workspace.')" />

    <x-auth.notice class="auth-rise mb-6" style="--i:2" :status="session('status')" />

    <form wire:submit="login" class="space-y-5">
        <div class="auth-rise" style="--i:2">
            <x-auth.input
                id="email"
                :label="__('Email address')"
                error-key="form.email"
                wire:model="form.email"
                type="email"
                name="email"
                required
                autofocus
                autocomplete="username"
            />
        </div>

        <div class="auth-rise" style="--i:3">
            <x-auth.input
                id="password"
                :label="__('Password')"
                error-key="form.password"
                wire:model="form.password"
                type="password"
                name="password"
                :toggle="true"
                required
                autocomplete="current-password"
            >
                <x-slot:aside>
                    @if (Route::has('password.request'))
                        <a class="auth-link" href="{{ route('password.request') }}" wire:navigate>
                            Forgot password?
                        </a>
                    @endif
                </x-slot:aside>
            </x-auth.input>
        </div>

        <div class="auth-rise" style="--i:4">
            <label for="remember" class="auth-check">
                <input wire:model="form.remember" id="remember" type="checkbox">
                <span>{{ __('Keep me signed in') }}</span>
            </label>
        </div>

        <div class="auth-rise" style="--i:5">
            <x-auth.button target="login" loading="Signing in">Sign in</x-auth.button>
        </div>
    </form>
</div>
