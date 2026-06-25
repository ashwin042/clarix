<?php

namespace App\Livewire\Admin;

use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class UnitAnalytics extends Component
{
    public Unit $unit;

    public function mount(Unit $unit): void
    {
        $this->unit = $unit;
    }

    public function render()
    {
        $unit = $this->unit;

        $totalTasks     = $unit->tasks()->count();
        $completedTasks = $unit->tasks()->where('status', 'completed')->count();
        $pendingTasks   = $unit->tasks()->whereIn('status', ['pending', 'in_progress'])->count();
        $totalCredits   = $unit->tasks()->where('status', 'completed')->sum('credit_amount');

        $pms = $unit->users()
            ->where('role', 'pm')
            ->withCount([
                'ownedTasks as total_tasks_count' => fn ($q) => $q->where('unit_id', $unit->id),
                'ownedTasks as completed_tasks_count' => fn ($q) => $q->where('unit_id', $unit->id)->where('status', 'completed'),
            ])
            ->orderBy('name')
            ->get();

        $tasks = $unit->tasks()
            ->with(['assignedAdmin', 'writers'])
            ->latest()
            ->get();

        $taskTypeCounts = $unit->tasks()
            ->whereNotNull('task_type')
            ->select('task_type', DB::raw('count(*) as count'))
            ->groupBy('task_type')
            ->orderByDesc('count')
            ->pluck('count', 'task_type');

        return view('livewire.admin.unit-analytics', [
            'unit'            => $unit,
            'totalTasks'      => $totalTasks,
            'completedTasks'  => $completedTasks,
            'pendingTasks'    => $pendingTasks,
            'totalCredits'    => $totalCredits,
            'pms'             => $pms,
            'tasks'           => $tasks,
            'taskTypeCounts'  => $taskTypeCounts,
        ])->layout('layouts.app', ['pageTitle' => $unit->name]);
    }
}
