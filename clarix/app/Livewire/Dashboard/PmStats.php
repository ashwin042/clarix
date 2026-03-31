<?php

namespace App\Livewire\Dashboard;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskFile;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class PmStats extends Component
{
    public function render()
    {
        $user   = auth()->user();
        $unitId = $user->unit_id;

        $total     = Task::where('unit_id', $unitId)->count();
        $completed = Task::where('unit_id', $unitId)->where('status', 'completed')->count();
        $inProgress= Task::where('unit_id', $unitId)->where('status', 'in_progress')->count();
        $credits   = Task::where('unit_id', $unitId)->where('status', 'completed')->sum('credit_amount');
        $completionRate = $total > 0 ? round(($completed / $total) * 100) : 0;

        $stats = compact('total', 'completed', 'inProgress', 'credits', 'completionRate');

        // Donut: status breakdown for this unit
        $statusCounts = Task::where('unit_id', $unitId)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $donutData = [
            'labels' => ['Pending', 'In Progress', 'Submitted', 'Verified', 'Completed'],
            'data'   => [
                $statusCounts->get('pending', 0),
                $statusCounts->get('in_progress', 0),
                $statusCounts->get('submitted', 0),
                $statusCounts->get('verified', 0),
                $statusCounts->get('completed', 0),
            ],
        ];

        // Line: tasks created last 30 days for this unit
        $trendRaw = Task::where('unit_id', $unitId)
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('count(*) as count'))
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('count', 'day');

        $trendLabels = [];
        $trendValues = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i)->format('Y-m-d');
            $trendLabels[] = now()->subDays($i)->format('M d');
            $trendValues[] = $trendRaw->get($day, 0);
        }

        // Writer progress: how many writers are ready_for_review vs total assigned (unit tasks)
        $unitTaskIds   = Task::where('unit_id', $unitId)->pluck('id');
        $totalWriters  = TaskAssignment::whereIn('task_id', $unitTaskIds)->count();
        $readyWriters  = TaskAssignment::whereIn('task_id', $unitTaskIds)->where('status', 'ready_for_review')->count();

        // Recent tasks (unit only)
        $recentTasks = Task::where('unit_id', $unitId)->latest()->take(5)->get();

        // Recent file uploads (unit tasks)
        $recentFiles = TaskFile::whereIn('task_id', $unitTaskIds)
            ->with('task')
            ->latest()
            ->take(4)
            ->get();

        return view('livewire.dashboard.pm-stats', compact(
            'stats', 'donutData', 'trendLabels', 'trendValues',
            'recentTasks', 'recentFiles', 'totalWriters', 'readyWriters'
        ));
    }
}

