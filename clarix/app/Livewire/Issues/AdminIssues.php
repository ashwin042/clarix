<?php

namespace App\Livewire\Issues;

use App\Models\Issue;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class AdminIssues extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterStatus = '';
    public string $filterPriority = '';
    public string $filterUser = '';
    public string $filterRole = '';

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingFilterStatus(): void { $this->resetPage(); }
    public function updatingFilterPriority(): void { $this->resetPage(); }
    public function updatingFilterUser(): void { $this->resetPage(); }
    public function updatingFilterRole(): void { $this->resetPage(); }

    public function render()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $issues = Issue::with('creator')
            ->when($this->search, fn ($q) => $q->where('title', 'like', "%{$this->search}%"))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterPriority, fn ($q) => $q->where('priority', $this->filterPriority))
            ->when($this->filterUser, fn ($q) => $q->where('created_by', $this->filterUser))
            ->when($this->filterRole, fn ($q) => $q->whereHas('creator', fn ($u) => $u->where('role', $this->filterRole)))
            ->orderByDesc('created_at')
            ->paginate(20);

        $users = User::whereIn('role', ['pm', 'writer'])->orderBy('name')->get();

        return view('livewire.issues.admin-issues', [
            'issues' => $issues,
            'users'  => $users,
        ])->layout('layouts.app', ['pageTitle' => 'Issue Management']);
    }
}
