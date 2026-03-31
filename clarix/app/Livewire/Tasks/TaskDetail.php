<?php

namespace App\Livewire\Tasks;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskNote;
use App\Models\User;
use App\Notifications\TaskStatusUpdatedNotification;
use Livewire\Component;
use Livewire\WithFileUploads;

class TaskDetail extends Component
{
    use WithFileUploads;

    public Task $task;

    // Assignment modal
    public bool $showAssignModal = false;
    public array $selectedWriters = [];

    // File upload
    public bool $showUploadModal = false;
    public array $pendingFiles = [];
    public string $uploadNote = '';

    // Note form
    public string $note = '';

    public function mount(Task $task): void
    {
        $this->task = $task->load(['unit', 'creator', 'pm', 'assignments.writer', 'files.uploader', 'notes.author']);
    }

    // ── Assignments ──────────────────────────────────────────────────────────

    public function openAssignModal(): void
    {
        $this->selectedWriters = [];
        $this->resetErrorBag();
        $this->showAssignModal = true;
    }

    public function assign(): void
    {
        $this->validate(['selectedWriters' => 'required|array|min:1']);

        foreach ($this->selectedWriters as $writerId) {
            TaskAssignment::firstOrCreate(
                ['task_id' => $this->task->id, 'writer_id' => $writerId],
                ['assigned_by' => auth()->id(), 'status' => 'pending']
            );
        }

        $this->task->refresh();
        $this->showAssignModal = false;
        $this->selectedWriters = [];
        $this->dispatch('notify', message: 'Writers assigned.', type: 'success');
    }

    public function removeAssignment(TaskAssignment $assignment): void
    {
        $assignment->delete();
        $this->task->refresh();
        $this->dispatch('notify', message: 'Assignment removed.', type: 'success');
    }

    public function updateAssignmentStatus(int $assignmentId, string $status): void
    {
        $assignment = TaskAssignment::findOrFail($assignmentId);

        if ($assignment->writer_id !== auth()->id()) {
            abort(403);
        }

        $assignment->update(['status' => $status]);
        $this->task->refresh();
        $this->dispatch('notify', message: 'Status updated.', type: 'success');
    }

    // ── File Upload ───────────────────────────────────────────────────────────

    public function openUploadModal(): void
    {
        $this->pendingFiles = [];
        $this->uploadNote = '';
        $this->resetErrorBag();
        $this->showUploadModal = true;
    }

    public function uploadFiles(): void
    {
        $this->validate([
            'pendingFiles'   => 'required|array|min:1',
            'pendingFiles.*' => 'file|max:10240',
        ]);

        foreach ($this->pendingFiles as $file) {
            $path = $file->store('tasks/' . $this->task->id, 'local');

            $this->task->files()->create([
                'file_path'     => $path,
                'original_name' => $file->getClientOriginalName(),
                'file_size'     => $file->getSize(),
                'mime_type'     => $file->getMimeType(),
                'uploaded_by'   => auth()->id(),
            ]);
        }

        if ($this->uploadNote) {
            $this->task->notes()->create([
                'note'       => $this->uploadNote,
                'created_by' => auth()->id(),
            ]);
        }

        $this->task->refresh();
        $this->showUploadModal = false;
        $this->pendingFiles = [];
        $this->uploadNote = '';
        $this->dispatch('notify', message: 'Files uploaded.', type: 'success');
    }

    public function deleteFile(int $fileId): void
    {
        $file = $this->task->files()->findOrFail($fileId);
        \Illuminate\Support\Facades\Storage::disk('local')->delete($file->file_path);
        $file->delete();
        $this->task->refresh();
        $this->dispatch('notify', message: 'File deleted.', type: 'success');
    }

    // ── Notes ─────────────────────────────────────────────────────────────────

    public function addNote(): void
    {
        $this->validate(['note' => 'required|string|max:5000']);

        $this->task->notes()->create([
            'note'       => $this->note,
            'created_by' => auth()->id(),
        ]);

        $this->note = '';
        $this->task->refresh();
        $this->dispatch('notify', message: 'Note added.', type: 'success');
    }

    // ── Task Status ───────────────────────────────────────────────────────────

    public function updateTaskStatus(string $status): void
    {
        if (!auth()->user()->isAdmin() && !auth()->user()->isPm()) {
            abort(403);
        }
        $this->task->update(['status' => $status]);
        $this->task->refresh();

        // Notify the PM
        if ($this->task->pm) {
            $this->task->pm->notify(new TaskStatusUpdatedNotification($this->task));
        }

        $this->dispatch('notification-updated');
        $this->dispatch('notify', message: 'Status updated.', type: 'success');
    }

    public function render()
    {
        $user = auth()->user();

        $assignedIds = $this->task->assignments->pluck('writer_id')->toArray();

        $availableWriters = $user->isAdmin()
            ? User::where('role', 'writer')->whereNotIn('id', $assignedIds)->orderBy('name')
                ->withCount(['taskAssignments as active_tasks' => fn ($q) => $q->whereNotIn('status', ['ready_for_review'])])
                ->get()
            : collect();

        $this->task->load(['unit', 'creator', 'assignments.writer', 'files.uploader', 'notes' => fn ($q) => $q->with('author')->latest()]);

        return view('livewire.tasks.task-detail', compact('availableWriters'))
            ->layout('layouts.app', ['pageTitle' => $this->task->task_code]);
    }
}
