<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return;
        }

        $this->reset('email');

        session()->flash('status', __($status));
    }
}; ?>

<div>
    <x-auth.heading :title="__('Reset your password')" :subtitle="__('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.')" />

    <!-- Session Status -->
    <x-auth.notice class="auth-rise mb-6" style="--i:2" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink" class="space-y-5">
        <!-- Email Address -->
        <div class="auth-rise" style="--i:2">
            <x-auth.input id="email" :label="__('Email')" error-key="email" wire:model="email" type="email" name="email" required autofocus />
        </div>

        <div class="auth-rise space-y-5" style="--i:3">
            <x-auth.button target="sendPasswordResetLink" loading="Sending link">{{ __('Email Password Reset Link') }}</x-auth.button>

            <p class="text-center">
                <a class="auth-link !text-sm" href="{{ route('login') }}" wire:navigate>Back to sign in</a>
            </p>
        </div>
    </form>
</div>
