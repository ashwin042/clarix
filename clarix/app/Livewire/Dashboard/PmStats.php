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

        /*
         * A PM's figures are their unit's; a supervisor's are the agency's.
         *
         * Both read this component, so the scope is a closure rather than a
         * unit id: a supervisor carries no unit_id, and filtering on it would
         * have shown them a dashboard of zeroes. The tenant scope on Task
         * keeps "the agency" honest either way.
         */
        $scope = fn () => $user->isSupervisor()
            ? Task::query()
            : Task::where('unit_id', $unitId);

        $total     = $scope()->count();
        $completed = $scope()->where('status', 'completed')->count();
        $inProgress= $scope()->where('status', 'in_progress')->count();
        $credits   = $scope()->where('status', 'completed')->sum('credit_amount');
        $completionRate = $total > 0 ? round(($completed / $total) * 100) : 0;

        $stats = compact('total', 'completed', 'inProgress', 'credits', 'completionRate');

        // Donut: status breakdown for this unit
        $statusCounts = $scope()
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $donutData = [
            'labels' => ['Pending', 'In Progress', 'Completed', 'Cancelled'],
            'data'   => [
                $statusCounts->get('pending', 0),
                $statusCounts->get('in_progress', 0),
                $statusCounts->get('completed', 0),
                $statusCounts->get('cancelled', 0),
            ],
        ];

        // Line: tasks created last 30 days for this unit
        $trendRaw = $scope()
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

        /*
         * The task ids in scope, as a subquery rather than a materialised
         * list. For a PM that was one unit's worth; for a supervisor it is
         * every task the agency owns, and pulling all of them back only to
         * send them straight out again in an IN clause scales badly.
         */
        $taskIds = $scope()->select('id');

        // Writer progress: how many writers are ready_for_review vs total assigned
        $totalWriters  = TaskAssignment::whereIn('task_id', $taskIds)->count();
        $readyWriters  = TaskAssignment::whereIn('task_id', $taskIds)->where('status', 'ready_for_review')->count();

        // Recent tasks (unit only)
        $recentTasks = $scope()->latest()->take(5)->get();

        // Recent file uploads, from the same tasks
        $recentFiles = TaskFile::whereIn('task_id', $taskIds)
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

