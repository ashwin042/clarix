<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <x-auth.heading :title="__('Verify your email')" :subtitle="__('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.')" />

    @if (session('status') == 'verification-link-sent')
        <x-auth.notice class="auth-rise mb-6" style="--i:2" :status="__('A new verification link has been sent to the email address you provided during registration.')" />
    @endif

    <div class="auth-rise space-y-5" style="--i:3">
        <x-auth.button type="button" wire:click="sendVerification" target="sendVerification" loading="Sending">{{ __('Resend Verification Email') }}</x-auth.button>

        <p class="text-center">
            <button wire:click="logout" type="submit" class="auth-link !text-sm">
                {{ __('Log Out') }}
            </button>
        </p>
    </div>
</div>
