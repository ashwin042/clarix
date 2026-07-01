<?php

namespace App\Livewire\Tasks;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskNote;
use App\Models\User;
use App\Notifications\TaskStatusUpdatedNotification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class TaskDetail extends Component
{
    public Task $task;

    // Assignment modal
    public bool $showAssignModal = false;
    public array $selectedWriters = [];

    // File upload
    public bool $showUploadModal = false;
    public bool $showCompletedUploadModal = false;

    // Note form
    public string $note = '';

    public function mount(Task $task): void
    {
        $this->task = $task->load([
            'unit', 'creator', 'pm', 'assignedAdmin',
            'assignments.writer',
            'regularFiles.uploader',
            'completedFiles.uploader',
            'notes.author',
        ]);

        if (session()->has('success')) {
            $this->dispatch('notify', message: session('success'), type: 'success');
        }
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

        if ($status === 'ready_for_review') {
            abort(403);
        }

        $assignment->update(['status' => $status]);
        $this->task->refresh();
        $this->dispatch('notify', message: 'Status updated.', type: 'success');
    }

    // ── File Upload ───────────────────────────────────────────────────────────

    public function openUploadModal(): void
    {
        $this->resetErrorBag();
        $this->showUploadModal = true;
    }

    public function openCompletedUploadModal(): void
    {
        $this->resetErrorBag();
        $this->showCompletedUploadModal = true;
    }

    public function deleteFile(int $fileId): void
    {
        $file = $this->task->regularFiles()->findOrFail($fileId);
        $filePath = $file->file_path;
        $file->delete();
        $this->deleteFromR2($filePath);
        $this->task->refresh();
        $this->dispatch('notify', message: 'File deleted.', type: 'success');
    }

    public function deleteCompletedFile(int $fileId): void
    {
        Gate::authorize('uploadCompletedFile', $this->task);

        $file = $this->task->completedFiles()->findOrFail($fileId);
        $filePath = $file->file_path;
        $file->delete();
        $this->deleteFromR2($filePath);
        $this->task->refresh();
        $this->dispatch('notify', message: 'File deleted.', type: 'success');
    }

    /**
     * Delete a stored object from R2. The database record has already been
     * removed by the caller, so a failure here is logged but not surfaced —
     * the UI stays clean even if the storage delete fails.
     */
    protected function deleteFromR2(string $filePath): void
    {
        try {
            // The r2 disk is configured with 'throw' => false, so a failed
            // delete returns false rather than raising an exception.
            if (! Storage::disk('r2')->delete($filePath)) {
                Log::error('R2 storage reported failure deleting file.', [
                    'file_path' => $filePath,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to delete file from R2 storage.', [
                'file_path' => $filePath,
                'exception' => $e->getMessage(),
            ]);
        }
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
        if (!auth()->user()->isAdmin()) {
            abort(403);
        }
        $updateData = ['status' => $status];
        if ($status === 'completed') {
            $updateData['completed_at'] = now();
        } elseif ($this->task->completed_at !== null) {
            $updateData['completed_at'] = null;
        }
        $this->task->update($updateData);

        if ($status === 'completed') {
            TaskAssignment::where('task_id', $this->task->id)
                ->where('status', 'ready_for_review')
                ->update(['status' => 'completed']);
        }

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
                ->withCount(['taskAssignments as active_tasks' => fn ($q) => $q->whereHas('task', fn ($t) => $t->whereIn('status', ['pending', 'in_progress']))])
                ->get()
            : collect();

        $this->task->load([
            'unit', 'creator', 'assignedAdmin',
            'assignments.writer',
            'regularFiles.uploader',
            'completedFiles.uploader',
            'notes' => fn ($q) => $q->with('author')->latest(),
        ]);

        return view('livewire.tasks.task-detail', compact('availableWriters'))
            ->layout('layouts.app', ['pageTitle' => $this->task->task_code]);
    }
}
