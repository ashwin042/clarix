<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Task;
use App\Models\Unit;

class TaskController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $tasks = match ($user->role) {
            'admin'  => Task::with(['unit', 'creator'])->latest()->paginate(20),
            'pm'     => Task::forPm($user->unit_id)->latest()->paginate(20),
            'writer' => Task::forWriter($user->id)->latest()->paginate(20),
            default  => collect(),
        };

        return view('tasks.index', compact('tasks'));
    }

    public function create()
    {
        $this->authorize('create', Task::class);

        $units = auth()->user()->isAdmin()
            ? Unit::orderBy('name')->get()
            : Unit::where('id', auth()->user()->unit_id)->get();

        return view('tasks.create', compact('units'));
    }

    public function store(StoreTaskRequest $request)
    {
        $task = Task::create($request->validated() + ['created_by' => auth()->id()]);

        return redirect()->route('tasks.show', $task)->with('success', 'Task created.');
    }

    public function show(Task $task)
    {
        $this->authorize('view', $task);

        $task->load(['unit', 'creator', 'assignments.writer', 'files.uploader', 'notes.author']);

        return view('tasks.show', compact('task'));
    }

    public function edit(Task $task)
    {
        $this->authorize('update', $task);

        $units = auth()->user()->isAdmin()
            ? Unit::orderBy('name')->get()
            : Unit::where('id', auth()->user()->unit_id)->get();

        return view('tasks.edit', compact('task', 'units'));
    }

    public function update(UpdateTaskRequest $request, Task $task)
    {
        $task->update($request->validated());

        return redirect()->route('tasks.show', $task)->with('success', 'Task updated.');
    }

    public function destroy(Task $task)
    {
        $this->authorize('delete', $task);

        $task->delete();

        return redirect()->route('tasks.index')->with('success', 'Task deleted.');
    }
}
