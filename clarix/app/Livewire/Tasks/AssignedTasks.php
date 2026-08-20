<?php

namespace App\Livewire\Tasks;

use App\Rules\TenantExists;
use App\Livewire\Traits\WithDeleteConfirmation;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\NewTaskCreatedNotification;
use Livewire\Component;
use Livewire\WithPagination;

class AssignedTasks extends Component
{
    use WithPagination, WithDeleteConfirmation;

    public string $search = '';
    public string $filterStatus = '';
    public string $filterPriority = '';
    public string $filterUnit = '';
    public string $filterTaskType = '';
    public string $sortBy = 'created_at';
    public string $sortDir = 'desc';
    public bool $showModal = false;
    public ?int $editingId = null;

    // Form fields
    public string $title = '';
    public string $task_code = '';
    public string $task_type = '';
    public string $important_notes = '';
    public string $unit_id = '';
    public string $pm_id = '';
    public string $priority = 'medium';
    public string $status = 'pending';
    public string $deadline = '';
    public string $credit_amount = '0';
    public string $assigned_admin_id = '';

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingFilterStatus(): void { $this->resetPage(); }
    public function updatingFilterPriority(): void { $this->resetPage(); }
    public function updatingFilterUnit(): void { $this->resetPage(); }
    public function updatingFilterTaskType(): void { $this->resetPage(); }

    public function updatedUnitId(): void
    {
        $this->pm_id = '';
    }

    public function sortBy(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'asc';
        }
    }

    public function openCreate(): void
    {
        $this->reset(['title', 'task_code', 'task_type', 'important_notes', 'unit_id', 'pm_id', 'priority', 'status', 'deadline', 'credit_amount', 'editingId']);
        $this->priority = 'medium';
        $this->status = 'pending';
        $this->credit_amount = '0';
        $this->assigned_admin_id = (string) auth()->id();

        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function openEdit(Task $task): void
    {
        $this->editingId     = $task->id;
        $this->title         = $task->title;
        $this->task_code     = $task->task_code;
        $this->task_type     = $task->task_type ?? '';
        $this->important_notes = $task->important_notes ?? '';
        $this->unit_id       = (string) $task->unit_id;
        $this->pm_id         = (string) ($task->pm_id ?? '');
        $this->priority      = $task->priority;
        $this->status        = $task->status;
        $this->deadline      = $task->deadline->format('Y-m-d');
        $this->credit_amount = (string) $task->credit_amount;
        $this->assigned_admin_id = (string) ($task->assigned_admin_id ?? '');
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save(): void
    {
        // render() already refuses a non-admin, but only after the write. The
        // Livewire endpoint is reachable without ever passing the route's
        // role:admin middleware, so the guard is repeated where it bites.
        abort_unless(auth()->user()->isAdmin(), 403);

        $unitId = $this->unit_id;
        $uniqueRule = $this->editingId
            ? "unique:tasks,task_code,{$this->editingId},id,unit_id,{$unitId}"
            : "unique:tasks,task_code,NULL,id,unit_id,{$unitId}";

        $this->validate([
            'title'         => 'required|string|max:255',
            'task_code'     => ['required', 'string', 'max:50', $uniqueRule],
            'unit_id'       => ['required', TenantExists::in('units')],
            'pm_id'         => [
                'required',
                TenantExists::in('users'),
                function ($attribute, $value, $fail) use ($unitId) {
                    $pm = User::find($value);
                    if (!$pm || $pm->role !== 'pm') {
                        $fail('The selected user is not a project manager.');
                    } elseif ((int) $pm->unit_id !== (int) $unitId) {
                        $fail('The selected PM does not belong to the chosen unit.');
                    }
                },
            ],
            'priority'      => 'required|in:low,medium,high',
            'status'        => 'required|in:pending,in_progress,completed,cancelled',
            'deadline'      => 'required|date',
            'credit_amount' => 'required|numeric|min:0',
            'task_type'         => 'nullable|in:tech,content,accounts,maths,nursing,science,civil,others',
            'important_notes'   => 'nullable|string|max:5000',
            'assigned_admin_id' => [
                'nullable',
                TenantExists::in('users'),
                function ($attribute, $value, $fail) {
                    if ($value && User::find($value)?->role !== 'admin') {
                        $fail('The selected user must be an admin.');
                    }
                },
            ],
        ]);

        $data = [
            'title'             => $this->title,
            'task_code'         => $this->task_code,
            'task_type'         => $this->task_type ?: null,
            'important_notes'   => $this->important_notes ?: null,
            'unit_id'           => $this->unit_id,
            'pm_id'             => $this->pm_id,
            'assigned_admin_id' => $this->assigned_admin_id ?: null,
            'priority'          => $this->priority,
            'status'            => $this->status,
            'deadline'          => $this->deadline,
            'credit_amount'     => $this->credit_amount,
        ];

        if ($this->editingId) {
            $existing = Task::findOrFail($this->editingId);
            $existing->update($data + $existing->completedAtFor($this->status));
            $this->dispatch('notify', message: 'Task updated.', type: 'success');
        } else {
            $task = Task::create($data + (new Task)->completedAtFor($this->status) + ['created_by' => auth()->id()]);
            $this->dispatch('notify', message: 'Task created.', type: 'success');

            $admins = User::where('role', 'admin')->get();
            foreach ($admins as $admin) {
                $admin->notify(new NewTaskCreatedNotification($task));
            }
            $this->dispatch('notification-updated');
        }

        $this->showModal = false;
        $this->reset(['title', 'task_code', 'task_type', 'important_notes', 'unit_id', 'pm_id', 'assigned_admin_id', 'priority', 'status', 'deadline', 'credit_amount', 'editingId']);
    }

    public function confirmDelete(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $task = Task::with('files')->findOrFail($this->deletingId);
        $task->deleteWithFiles();
        $this->cancelDelete();
        $this->dispatch('notify', message: 'Task deleted.', type: 'success');
    }

    public function render()
    {
        $user = auth()->user();
        abort_unless($user->isAdmin(), 403);

        $query = Task::with(['unit', 'creator', 'pm', 'assignments.writer', 'assignedAdmin', 'files'])
            ->where('assigned_admin_id', $user->id)
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('title', 'like', "%{$this->search}%")
                  ->orWhere('task_code', 'like', "%{$this->search}%");
            }))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterPriority, fn ($q) => $q->where('priority', $this->filterPriority))
            ->when($this->filterUnit, fn ($q) => $q->where('unit_id', $this->filterUnit))
            ->when($this->filterTaskType, fn ($q) => $q->where('task_type', $this->filterTaskType))
            ->orderBy($this->sortBy, $this->sortDir);

        $tasks = $query->paginate(15);

        $adminUsers = User::where('role', 'admin')->orderBy('name')->get();
        $units = Unit::orderBy('name')->get();

        $pmsForUnit = $this->unit_id
            ? User::where('role', 'pm')->where('unit_id', $this->unit_id)->orderBy('name')->get()
            : collect();

        return view('livewire.tasks.assigned-tasks', compact('tasks', 'units', 'pmsForUnit', 'adminUsers'))
            ->layout('layouts.app', ['pageTitle' => 'Assigned Tasks']);
    }
}
