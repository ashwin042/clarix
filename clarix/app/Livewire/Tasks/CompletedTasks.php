<?php

namespace App\Livewire\Tasks;

use App\Models\Task;
use App\Models\Unit;
use Livewire\Component;
use Livewire\WithPagination;

class CompletedTasks extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterPriority = '';
    public string $filterUnit = '';
    public string $filterTaskType = '';
    public string $sortBy = 'completed_at';
    public string $sortDir = 'desc';

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingFilterPriority(): void { $this->resetPage(); }
    public function updatingFilterUnit(): void { $this->resetPage(); }
    public function updatingFilterTaskType(): void { $this->resetPage(); }

    public function sortBy(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'asc';
        }
    }

    public function render()
    {
        $user = auth()->user();
        abort_unless($user->hasPermission('tasks.view'), 403);

        $query = Task::with(['unit', 'creator', 'pm', 'assignments.writer', 'assignedAdmin', 'files'])
            ->where('status', 'completed')
            ->when($user->isPm(), fn ($q) => $q->where('unit_id', $user->unit_id))
            ->when($user->isWriter(), fn ($q) => $q->whereHas('assignments', fn ($a) => $a->where('writer_id', $user->id)))
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('title', 'like', "%{$this->search}%")
                  ->orWhere('task_code', 'like', "%{$this->search}%");
            }))
            ->when($this->filterPriority, fn ($q) => $q->where('priority', $this->filterPriority))
            ->when($this->filterUnit && $user->isAdmin(), fn ($q) => $q->where('unit_id', $this->filterUnit))
            ->when($this->filterTaskType, fn ($q) => $q->where('task_type', $this->filterTaskType))
            ->orderBy($this->sortBy, $this->sortDir);

        $tasks = $query->paginate(15);

        $units = $user->isAdmin()
            ? Unit::orderBy('name')->get()
            : Unit::where('id', $user->unit_id)->get();

        return view('livewire.tasks.completed-tasks', compact('tasks', 'units'))
            ->layout('layouts.app', ['pageTitle' => 'Completed Tasks']);
    }
}
