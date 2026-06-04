<?php

namespace App\Livewire\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Notifications\NewTaskCreatedNotification;
use Livewire\Component;
use Livewire\WithFileUploads;

class CreateTask extends Component
{
    use WithFileUploads;

    public string $title = '';
    public string $task_code = '';
    public string $task_type = '';
    public string $important_notes = '';
    public string $unit_id = '';
    public string $pm_id = '';
    public string $priority = 'medium';
    public string $deadline = '';
    public string $credit_amount = '0';
    public string $assigned_admin_id = '';
    public array  $uploads = [];

    public function mount(): void
    {
        if (!auth()->user()->isPm()) {
            abort(403);
        }
        $this->unit_id = (string) auth()->user()->unit_id;
        $this->pm_id   = (string) auth()->id();
    }

    public function removeFile(int $index): void
    {
        array_splice($this->uploads, $index, 1);
    }

    public function save(): void
    {
        $unitId     = (string) auth()->user()->unit_id;
        $uniqueRule = "unique:tasks,task_code,NULL,id,unit_id,{$unitId}";

        $this->validate([
            'title'             => 'required|string|max:255',
            'task_code'         => ['required', 'string', 'max:50', $uniqueRule],
            'priority'          => 'required|in:low,medium,high',
            'deadline'          => 'required|date',
            'credit_amount'     => 'required|numeric|min:0',
            'task_type'         => 'nullable|in:tech,content,accounts,maths,nursing,science,civil,others',
            'important_notes'   => 'nullable|string|max:5000',
            'assigned_admin_id' => [
                'nullable',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    if ($value && User::find($value)?->role !== 'admin') {
                        $fail('The selected user must be an admin.');
                    }
                },
            ],
            'uploads'   => ['nullable', 'array'],
            'uploads.*' => ['file', 'max:10240'],
        ]);

        $task = Task::create([
            'title'             => $this->title,
            'task_code'         => $this->task_code,
            'task_type'         => $this->task_type ?: null,
            'important_notes'   => $this->important_notes ?: null,
            'unit_id'           => auth()->user()->unit_id,
            'pm_id'             => auth()->id(),
            'assigned_admin_id' => $this->assigned_admin_id ?: null,
            'priority'          => $this->priority,
            'status'            => 'pending',
            'deadline'          => $this->deadline,
            'credit_amount'     => $this->credit_amount,
            'created_by'        => auth()->id(),
        ]);

        foreach ($this->uploads as $file) {
            $path = $file->store('task-files/' . $this->task_code, 'r2');
            $task->files()->create([
                'file_path'     => $path,
                'original_name' => $file->getClientOriginalName(),
                'file_size'     => $file->getSize(),
                'mime_type'     => $file->getMimeType(),
                'uploaded_by'   => auth()->id(),
            ]);
        }

        foreach (User::where('role', 'admin')->get() as $admin) {
            $admin->notify(new NewTaskCreatedNotification($task));
        }

        $this->redirectRoute('tasks.show', $task);
    }

    public function render()
    {
        $adminUsers = User::where('role', 'admin')->orderBy('name')->get();

        return view('livewire.tasks.create-task', compact('adminUsers'))
            ->layout('layouts.app', ['pageTitle' => 'New Task']);
    }
}
