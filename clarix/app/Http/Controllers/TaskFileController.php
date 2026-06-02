<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadCompletedFilesRequest;
use App\Http\Requests\UploadTaskFilesRequest;
use App\Models\Task;
use App\Models\TaskFile;
use App\Notifications\CompletedFileUploadedNotification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class TaskFileController extends Controller
{
    public function store(UploadTaskFilesRequest $request, Task $task)
    {
        foreach ($request->file('files') as $file) {
            $path = $file->store('task-files/' . $task->task_code, 'r2');

            $task->files()->create([
                'file_path'     => $path,
                'original_name' => $file->getClientOriginalName(),
                'file_size'     => $file->getSize(),
                'mime_type'     => $file->getMimeType(),
                'uploaded_by'   => auth()->id(),
            ]);
        }

        if ($note = $request->input('upload_note')) {
            $task->notes()->create([
                'note'       => $note,
                'created_by' => auth()->id(),
            ]);
        }

        return redirect()->back(fallback: route('tasks.index'))->with('success', 'Files uploaded.');
    }

    public function storeCompleted(UploadCompletedFilesRequest $request, Task $task)
    {
        Gate::authorize('uploadCompletedFile', $task);

        foreach ($request->file('files') as $file) {
            $path = $file->store('task-files/' . $task->task_code . '/completed', 'r2');

            $task->files()->create([
                'file_path'         => $path,
                'original_name'     => $file->getClientOriginalName(),
                'file_size'         => $file->getSize(),
                'mime_type'         => $file->getMimeType(),
                'uploaded_by'       => auth()->id(),
                'is_completed_file' => true,
            ]);
        }

        $task->assignments()->update(['status' => 'ready_for_review']);

        if ($task->pm) {
            $task->pm->notify(new CompletedFileUploadedNotification($task));
        }

        if ($task->assignedAdmin) {
            $task->assignedAdmin->notify(new CompletedFileUploadedNotification($task));
        }

        return redirect()->back(fallback: route('tasks.index'))->with('success', 'Completed file uploaded.');
    }

    public function download(Task $task, TaskFile $file)
    {
        abort_if($file->task_id !== $task->id, 404);

        $user = auth()->user();

        if ($file->is_completed_file) {
            if ($user->role === 'admin') {
                // always allowed
            } elseif ($user->role === 'writer' && $task->assignments()->where('writer_id', $user->id)->exists()) {
                // allowed
            } elseif ($user->role === 'pm' && $task->unit_id === $user->unit_id && $task->status === 'completed') {
                // allowed only when task is completed
            } else {
                abort(403);
            }
        } else {
            if ($user->role === 'admin') {
                // allow
            } elseif ($user->role === 'pm' && $task->unit_id === $user->unit_id) {
                // allow
            } elseif ($user->role === 'writer' && $task->assignments()->where('writer_id', $user->id)->exists()) {
                // allow
            } else {
                abort(403);
            }
        }

        return Storage::disk('r2')->download($file->file_path, $file->original_name);
    }

    public function destroy(Task $task, TaskFile $file)
    {
        Gate::authorize('uploadFiles', $task);

        Storage::disk('r2')->delete($file->file_path);
        $file->delete();

        return redirect()->route('tasks.show', $task)->with('success', 'File deleted.');
    }
}
