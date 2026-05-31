<?php

namespace App\Livewire\Admin;

use App\Models\AccountDeletionRequest;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class DeletionRequests extends Component
{
    use WithPagination;

    public string $filterStatus = '';

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function approve(int $requestId): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $request = AccountDeletionRequest::findOrFail($requestId);

        if ($request->status !== 'pending') {
            $this->dispatch('notify', message: 'This request has already been processed.', type: 'error');
            return;
        }

        $user = $request->user;

        $request->update([
            'status' => 'approved',
            'processed_by' => auth()->id(),
            'processed_at' => now(),
        ]);

        if ($user) {
            $user->delete();
        }

        $this->dispatch('notify', message: 'Request approved. User has been deleted.', type: 'success');
    }

    public function reject(int $requestId): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $request = AccountDeletionRequest::findOrFail($requestId);

        if ($request->status !== 'pending') {
            $this->dispatch('notify', message: 'This request has already been processed.', type: 'error');
            return;
        }

        $request->update([
            'status' => 'rejected',
            'processed_by' => auth()->id(),
            'processed_at' => now(),
        ]);

        $this->dispatch('notify', message: 'Request rejected.', type: 'success');
    }

    public function render()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $requests = AccountDeletionRequest::with(['user', 'processor'])
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->latest()
            ->paginate(15);

        return view('livewire.admin.deletion-requests', compact('requests'))
            ->layout('layouts.app', ['pageTitle' => 'Deletion Requests']);
    }
}
