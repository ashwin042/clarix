<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskAssignmentRequest;
use App\Models\Task;
use App\Models\TaskAssignment;
use Illuminate\Http\Request;

class TaskAssignmentController extends Controller
{
    public function store(StoreTaskAssignmentRequest $request, Task $task)
    {
        foreach ($request->writer_ids as $writerId) {
            TaskAssignment::firstOrCreate(
                ['task_id' => $task->id, 'writer_id' => $writerId],
                ['assigned_by' => auth()->id(), 'status' => 'pending']
            );
        }

        return redirect()->route('tasks.show', $task)->with('success', 'Writers assigned.');
    }

    public function destroy(Task $task, TaskAssignment $assignment)
    {
        $this->authorize('assign', $task);

        $assignment->delete();

        return redirect()->route('tasks.show', $task)->with('success', 'Assignment removed.');
    }

    public function updateStatus(Request $request, Task $task, TaskAssignment $assignment)
    {
        if ($assignment->writer_id !== auth()->id()) {
            abort(403);
        }

        $request->validate(['status' => 'required|in:pending,in_progress,ready_for_review']);

        $assignment->update(['status' => $request->status]);

        return redirect()->route('tasks.show', $task)->with('success', 'Status updated.');
    }
}
