<?php

namespace App\Livewire\Tasks;

use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use App\Models\TaskFile;
use App\Notifications\NewTaskCreatedNotification;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class ManageTasks extends Component
{
    use WithPagination, WithFileUploads;

    public string $search = '';
    public string $filterStatus = '';
    public string $filterPriority = '';
    public string $filterUnit = '';
    public string $sortBy = 'created_at';
    public string $sortDir = 'desc';
    public bool $showModal = false;
    public ?int $editingId = null;

    // Form fields
    public string $title = '';
    public string $task_code = '';
    public string $important_notes = '';
    public string $unit_id = '';
    public string $pm_id = '';
    public string $priority = 'medium';
    public string $status = 'pending';
    public string $deadline = '';
    public string $credit_amount = '0';
    public array $newFiles = [];

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingFilterStatus(): void { $this->resetPage(); }
    public function updatingFilterPriority(): void { $this->resetPage(); }
    public function updatingFilterUnit(): void { $this->resetPage(); }

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
        $this->reset(['title', 'task_code', 'important_notes', 'unit_id', 'pm_id', 'priority', 'status', 'deadline', 'credit_amount', 'editingId']);
        $this->priority = 'medium';
        $this->status = 'pending';
        $this->credit_amount = '0';
        $this->newFiles = [];

        if (auth()->user()->isPm()) {
            $this->unit_id = (string) auth()->user()->unit_id;
            $this->pm_id   = (string) auth()->id();
        }

        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function openEdit(Task $task): void
    {
        $this->editingId     = $task->id;
        $this->title         = $task->title;
        $this->task_code     = $task->task_code;
        $this->important_notes = $task->important_notes ?? '';
        $this->unit_id       = (string) $task->unit_id;
        $this->pm_id         = (string) ($task->pm_id ?? '');
        $this->priority      = $task->priority;
        $this->status        = $task->status;
        $this->deadline      = $task->deadline->format('Y-m-d');
        $this->credit_amount = (string) $task->credit_amount;
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save(): void
    {
        $authUser = auth()->user();

        // Backend enforcement: PM cannot choose another PM or unit
        if ($authUser->isPm()) {
            $this->unit_id = (string) $authUser->unit_id;
            $this->pm_id   = (string) $authUser->id;
            $this->status  = 'pending';
        }

        $unitId = $this->unit_id;
        $uniqueRule = $this->editingId
            ? "unique:tasks,task_code,{$this->editingId},id,unit_id,{$unitId}"
            : "unique:tasks,task_code,NULL,id,unit_id,{$unitId}";

        $this->validate([
            'title'         => 'required|string|max:255',
            'task_code'     => ['required', 'string', 'max:50', $uniqueRule],
            'unit_id'       => 'required|exists:units,id',
            'pm_id'         => [
                'required',
                'exists:users,id',
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
            'status'        => 'required|in:pending,in_progress,submitted,verified,completed',
            'deadline'      => 'required|date',
            'credit_amount' => 'required|numeric|min:0',
            'newFiles'      => 'nullable|array',
            'newFiles.*'    => 'file|max:10240',
            'important_notes'   => 'nullable|string|max:5000',
        ]);

        $data = [
            'title'         => $this->title,
            'task_code'     => $this->task_code,
            'important_notes'   => $this->important_notes ?: null,
            'unit_id'       => $this->unit_id,
            'pm_id'         => $this->pm_id,
            'priority'      => $this->priority,
            'status'        => $this->status,
            'deadline'      => $this->deadline,
            'credit_amount' => $this->credit_amount,
        ];

        if ($this->editingId) {
            Task::findOrFail($this->editingId)->update($data);
            $this->dispatch('notify', message: 'Task updated.', type: 'success');
        } else {
            $task = Task::create($data + ['created_by' => auth()->id()]);
            foreach ($this->newFiles as $file) {
                $path = $file->store('tasks/' . $task->id, 'local');
                $task->files()->create([
                    'file_path'     => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'file_size'     => $file->getSize(),
                    'mime_type'     => $file->getMimeType(),
                    'uploaded_by'   => auth()->id(),
                ]);
            }
            $this->dispatch('notify', message: 'Task created.', type: 'success');

            // Notify all admins
            $admins = User::where('role', 'admin')->get();
            foreach ($admins as $admin) {
                $admin->notify(new NewTaskCreatedNotification($task));
            }
            $this->dispatch('notification-updated');
        }

        $this->showModal = false;
        $this->reset(['title', 'task_code', 'important_notes', 'unit_id', 'pm_id', 'priority', 'status', 'deadline', 'credit_amount', 'newFiles', 'editingId']);
    }

    public function delete(Task $task): void
    {
        $task->delete();
        $this->dispatch('notify', message: 'Task deleted.', type: 'success');
    }

    public function render()
    {
        $user = auth()->user();
        abort_unless($user->hasPermission('tasks.view'), 403);

        $query = Task::with(['unit', 'creator', 'pm', 'assignments'])
            ->when($user->isPm(), fn ($q) => $q->where('unit_id', $user->unit_id))
            ->when($user->isWriter(), fn ($q) => $q->whereHas('assignments', fn ($a) => $a->where('writer_id', $user->id)))
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('title', 'like', "%{$this->search}%")
                  ->orWhere('task_code', 'like', "%{$this->search}%");
            }))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterPriority, fn ($q) => $q->where('priority', $this->filterPriority))
            ->when($this->filterUnit && $user->isAdmin(), fn ($q) => $q->where('unit_id', $this->filterUnit))
            ->orderBy($this->sortBy, $this->sortDir);

        $tasks = $query->paginate(15);

        $units = $user->isAdmin()
            ? Unit::orderBy('name')->get()
            : Unit::where('id', $user->unit_id)->get();

        // PMs for selected unit (admin sees dropdown; PM gets own record injected)
        $pmsForUnit = $this->unit_id
            ? User::where('role', 'pm')->where('unit_id', $this->unit_id)->orderBy('name')->get()
            : collect();

        return view('livewire.tasks.manage-tasks', compact('tasks', 'units', 'pmsForUnit'))
            ->layout('layouts.app', ['pageTitle' => 'Tasks']);
    }
}
