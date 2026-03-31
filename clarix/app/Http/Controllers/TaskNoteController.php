<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;

class TaskNoteController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $this->authorize('view', $task);

        $request->validate(['note' => 'required|string|max:5000']);

        $task->notes()->create([
            'note'       => $request->note,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('tasks.show', $task)->with('success', 'Note added.');
    }
}
