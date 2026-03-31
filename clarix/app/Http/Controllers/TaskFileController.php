<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadTaskFilesRequest;
use App\Models\Task;
use App\Models\TaskFile;
use Illuminate\Support\Facades\Storage;

class TaskFileController extends Controller
{
    public function store(UploadTaskFilesRequest $request, Task $task)
    {
        foreach ($request->file('files') as $file) {
            $path = $file->store('task-files/' . $task->id, 'private');

            $task->files()->create([
                'file_path'     => $path,
                'original_name' => $file->getClientOriginalName(),
                'file_size'     => $file->getSize(),
                'mime_type'     => $file->getMimeType(),
                'uploaded_by'   => auth()->id(),
            ]);
        }

        return redirect()->route('tasks.show', $task)->with('success', 'Files uploaded.');
    }

    public function download(Task $task, TaskFile $file)
    {
        $user = auth()->user();

        if ($user->role === 'admin') {
            // allow
        } elseif ($user->role === 'pm' && $task->unit_id === $user->unit_id) {
            // allow
        } elseif ($user->role === 'writer' && $task->assignments()->where('writer_id', $user->id)->exists()) {
            // allow
        } else {
            abort(403);
        }

        return Storage::disk('local')->download($file->file_path, $file->original_name);
    }

    public function destroy(Task $task, TaskFile $file)
    {
        $this->authorize('uploadFiles', $task);

        Storage::disk('local')->delete($file->file_path);
        $file->delete();

        return redirect()->route('tasks.show', $task)->with('success', 'File deleted.');
    }
}
