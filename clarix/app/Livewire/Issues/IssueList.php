<?php

namespace App\Livewire\Issues;

use App\Models\Issue;
use App\Models\User;
use App\Notifications\IssueCreatedNotification;
use Livewire\Component;
use Livewire\WithPagination;

class IssueList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterStatus = '';
    public string $filterPriority = '';

    // Create modal
    public bool $showModal = false;
    public string $title = '';
    public string $message = '';
    public string $priority = 'medium';

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingFilterStatus(): void { $this->resetPage(); }
    public function updatingFilterPriority(): void { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->reset(['title', 'message', 'priority']);
        $this->priority = 'medium';
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function submit(): void
    {
        $this->validate([
            'title'    => 'required|string|max:255',
            'message'  => 'required|string|max:5000',
            'priority' => 'required|in:low,medium,high',
        ]);

        $issue = Issue::create([
            'title'      => $this->title,
            'message'    => $this->message,
            'priority'   => $this->priority,
            'status'     => 'open',
            'created_by' => auth()->id(),
        ]);

        // Notify all admins
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new IssueCreatedNotification($issue));
        }
        $this->dispatch('notification-updated');

        $this->showModal = false;
        $this->reset(['title', 'message', 'priority']);
        $this->dispatch('notify', message: 'Issue submitted.', type: 'success');
    }

    public function render()
    {
        $user = auth()->user();

        $query = Issue::with('creator')
            ->when(!$user->isAdmin(), fn ($q) => $q->where('created_by', $user->id))
            ->when($this->search, fn ($q) => $q->where('title', 'like', "%{$this->search}%"))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterPriority, fn ($q) => $q->where('priority', $this->filterPriority))
            ->orderByDesc('created_at');

        // Admin extra filter by user
        if ($user->isAdmin()) {
            $query->when(
                request('user'),
                fn ($q) => $q->where('created_by', request('user'))
            );
        }

        return view('livewire.issues.issue-list', [
            'issues' => $query->paginate(15),
        ])->layout('layouts.app', ['pageTitle' => 'Issues']);
    }
}
