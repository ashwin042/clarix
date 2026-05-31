<?php

use App\Models\AccountDeletionRequest;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public bool $showDeleteModal = false;
    public string $reason = '';
    public string $confirmInput = '';

    public function requestDeletion(): void
    {
        if (strtolower(trim($this->confirmInput)) !== 'delete') {
            $this->addError('confirmInput', 'You must type DELETE to confirm.');
            return;
        }

        $user = Auth::user();

        if (AccountDeletionRequest::where('user_id', $user->id)->where('status', 'pending')->exists()) {
            $this->addError('confirmInput', 'You already have a pending deletion request.');
            $this->showDeleteModal = false;
            return;
        }

        AccountDeletionRequest::create([
            'user_id' => $user->id,
            'reason'  => $this->reason ?: null,
            'status'  => 'pending',
        ]);

        $this->showDeleteModal = false;
        $this->reset(['reason', 'confirmInput']);
        $this->dispatch('notify', message: 'Account deletion request submitted. An admin will process it.', type: 'success');
    }

    public function hasPendingRequest(): bool
    {
        return AccountDeletionRequest::where('user_id', Auth::id())->where('status', 'pending')->exists();
    }
}; ?>

<div>
    @if($this->hasPendingRequest())
        <div class="flex items-center gap-3 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
            <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>
                <p class="text-sm font-medium text-amber-800 dark:text-amber-200">Deletion request pending</p>
                <p class="text-xs text-amber-600 dark:text-amber-400 mt-0.5">Your account deletion request is being reviewed by an administrator.</p>
            </div>
        </div>
    @else
        <div class="space-y-4">
            <div>
                <p class="text-sm text-gray-600 dark:text-slate-400">Once your account is deleted, all data will be permanently removed. This action requires admin approval.</p>
            </div>
            <button wire:click="$set('showDeleteModal', true)"
                class="px-4 py-2.5 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-colors">
                Request Account Deletion
            </button>
        </div>
    @endif

    {{-- Delete Request Modal --}}
    @if($showDeleteModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data x-on:keydown.escape.window="$wire.set('showDeleteModal', false)">
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" wire:click="$set('showDeleteModal', false)"></div>
        <div class="relative bg-white dark:bg-slate-900 rounded-2xl shadow-2xl w-full max-w-md z-10 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-800/60">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-red-100 dark:bg-red-900/30 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    </div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-slate-100">Request Account Deletion</h3>
                </div>
            </div>
            <form wire:submit="requestDeletion" class="p-6 space-y-4">
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3">
                    <p class="text-sm text-red-700 dark:text-red-300 font-medium">This action is irreversible</p>
                    <ul class="mt-1.5 text-xs text-red-600 dark:text-red-400 space-y-0.5 list-disc list-inside">
                        <li>All your data will be permanently deleted</li>
                        <li>Tasks and files associated with you will be affected</li>
                        <li>An admin will review and process your request</li>
                    </ul>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Reason <span class="text-gray-400 font-normal">(optional)</span></label>
                    <textarea wire:model="reason" rows="2" placeholder="Why do you want to delete your account?"
                        class="w-full border border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 resize-none"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Type <span class="font-semibold text-red-600">DELETE</span> to confirm</label>
                    <input wire:model="confirmInput" type="text" placeholder="Type DELETE to confirm"
                        class="w-full border border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 @error('confirmInput') border-red-400 @enderror">
                    @error('confirmInput') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex gap-3 pt-1">
                    <button type="submit"
                        class="flex-1 py-2.5 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-colors disabled:opacity-50"
                        :disabled="!$wire.confirmInput || $wire.confirmInput.toLowerCase() !== 'delete'">
                        Submit Request
                    </button>
                    <button type="button" wire:click="$set('showDeleteModal', false)"
                        class="flex-1 py-2.5 border border-gray-300 dark:border-slate-700 text-gray-700 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
