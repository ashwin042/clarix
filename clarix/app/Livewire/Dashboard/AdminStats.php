<?php

namespace App\Livewire\Dashboard;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskFile;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class AdminStats extends Component
{
    public function render()
    {
        $totalTasks      = Task::count();
        $completedTasks  = Task::where('status', 'completed')->count();
        $pendingTasks    = Task::whereIn('status', ['pending', 'in_progress'])->count();
        $totalUnits      = Unit::count();
        $totalUsers      = User::count();
        $totalCredits    = Task::where('status', 'completed')->sum('credit_amount');

        // Completion rate
        $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

        $stats = compact('totalUsers', 'totalTasks', 'completedTasks', 'pendingTasks', 'totalUnits', 'totalCredits', 'completionRate');

        // Donut chart: task status breakdown
        $statusCounts = Task::select('status', DB::raw('count(*) as count'))
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

        // Line chart: tasks created per day last 30 days
        $trendRaw = Task::select(DB::raw('DATE(created_at) as day'), DB::raw('count(*) as count'))
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

        // Bar chart: tasks per unit
        $unitTasks = Unit::withCount('tasks')->orderByDesc('tasks_count')->take(8)->get();
        $barData = [
            'labels' => $unitTasks->pluck('name')->toArray(),
            'data'   => $unitTasks->pluck('tasks_count')->toArray(),
        ];

        // Activity feed: recent meaningful events
        $recentTasks       = Task::with(['unit', 'pm'])->latest()->take(4)->get();
        $recentAssignments = TaskAssignment::with(['task', 'writer'])->latest()->take(3)->get();
        $recentFiles       = TaskFile::with(['task', 'uploader'])->latest()->take(3)->get();

        return view('livewire.dashboard.admin-stats', compact(
            'stats', 'donutData', 'trendLabels', 'trendValues', 'barData',
            'recentTasks', 'recentAssignments', 'recentFiles'
        ));
    }
}
