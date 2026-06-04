<?php

namespace App\Livewire\Dashboard;

use App\Models\Task;
use App\Models\TaskAssignment;
use Livewire\Component;

class WriterStats extends Component
{
    public function render()
    {
        $userId = auth()->id();

        $total     = TaskAssignment::where('writer_id', $userId)->count();
        $pending   = TaskAssignment::where('writer_id', $userId)->where('status', 'pending')->count();
        $review    = TaskAssignment::where('writer_id', $userId)
            ->where('status', 'ready_for_review')
            ->whereHas('task', fn($q) => $q->whereNotIn('status', ['completed']))
            ->count();
        $completed = TaskAssignment::where('writer_id', $userId)
            ->whereHas('task', fn($q) => $q->where('status', 'completed'))
            ->count();

        $stats = compact('total', 'pending', 'review', 'completed');

        $assignments = TaskAssignment::with(['task.assignedAdmin'])
            ->where('writer_id', $userId)
            ->whereHas('task', fn($q) => $q->whereNotIn('status', ['completed']))
            ->latest()
            ->get();

        $completedAssignments = TaskAssignment::with(['task.assignedAdmin'])
            ->where('writer_id', $userId)
            ->whereHas('task', fn($q) => $q->where('status', 'completed'))
            ->latest()
            ->take(10)
            ->get();

        return view('livewire.dashboard.writer-stats', compact('stats', 'assignments', 'completedAssignments'));
    }
}

