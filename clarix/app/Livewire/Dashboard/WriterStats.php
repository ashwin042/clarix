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

        $total   = TaskAssignment::where('writer_id', $userId)->count();
        $pending = TaskAssignment::where('writer_id', $userId)->where('status', 'pending')->count();
        $review  = TaskAssignment::where('writer_id', $userId)->where('status', 'ready_for_review')->count();

        $stats = compact('total', 'pending', 'review');

        $assignments = TaskAssignment::with('task')
            ->where('writer_id', $userId)
            ->whereNotIn('status', ['completed'])
            ->latest()
            ->get();

        return view('livewire.dashboard.writer-stats', compact('stats', 'assignments'));
    }
}

