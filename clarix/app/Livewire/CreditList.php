<?php

namespace App\Livewire;

use App\Models\Unit;
use App\Models\User;
use App\Services\CreditQueryBuilder;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

class CreditList extends Component
{
    use WithPagination;

    public string $dateFrom        = '';
    public string $dateTo          = '';
    public string $filterUnit      = '';
    public string $filterPm        = '';
    public string $filterTaskType  = '';
    public string $viewMode        = 'grouped'; // grouped | unified

    protected string $paginationTheme = 'tailwind';

    public function updatedDateFrom(): void       { $this->resetPage(); }
    public function updatedDateTo(): void         { $this->resetPage(); }
    public function updatedFilterUnit(): void     { $this->resetPage(); $this->filterPm = ''; }
    public function updatedFilterPm(): void       { $this->resetPage(); }
    public function updatedFilterTaskType(): void { $this->resetPage(); }
    public function updatedViewMode(): void       { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->reset(['dateFrom', 'dateTo', 'filterUnit', 'filterPm', 'filterTaskType']);
        $this->resetPage();
    }

    private function baseQuery()
    {
        return (new CreditQueryBuilder())->build(
            auth()->user(),
            $this->dateFrom,
            $this->dateTo,
            $this->filterUnit,
            $this->filterPm,
            $this->filterTaskType,
        );
    }

    public function render()
    {
        $user = auth()->user();
        abort_unless($user->hasPermission('credits.view'), 403);

        // Totals (unpaginated)
        $totals = $this->baseQuery()
            ->selectRaw('COUNT(*) as task_count, COALESCE(SUM(credit_amount), 0) as total_credits')
            ->first();

        // Unit list for filter dropdowns (admin only)
        $units = $user->isAdmin() ? Unit::orderBy('name')->get() : collect();

        // PM list filtered by chosen unit (admin only)
        $pms = collect();
        if ($user->isAdmin()) {
            $pmQuery = User::where('role', 'pm')->orderBy('name');
            if ($this->filterUnit) {
                $pmQuery->where('unit_id', $this->filterUnit);
            }
            $pms = $pmQuery->get();
        }

        // Writers always see a flat list — no unit grouping
        if ($user->isWriter()) {
            $tasks = $this->baseQuery()
                ->orderBy('updated_at', 'desc')
                ->paginate(25);

            return view('livewire.credit-list', compact('totals', 'units', 'pms', 'tasks'))
                ->layout('layouts.app', ['pageTitle' => 'Credit List']);
        }

        if ($this->viewMode === 'grouped') {
            // Get all tasks for grouped view (we group manually)
            $tasks = $this->baseQuery()
                ->orderBy('unit_id')
                ->orderBy('updated_at', 'desc')
                ->get();

            $grouped = $tasks->groupBy(fn ($t) => $t->unit_id)->map(function ($unitTasks) {
                return [
                    'unit'    => $unitTasks->first()->unit,
                    'tasks'   => $unitTasks,
                    'credits' => $unitTasks->sum('credit_amount'),
                    'count'   => $unitTasks->count(),
                ];
            })->sortBy(fn ($g) => $g['unit']?->name ?? '')->values();

            return view('livewire.credit-list', compact('totals', 'units', 'pms', 'grouped'))
                ->layout('layouts.app', ['pageTitle' => 'Credit List']);
        }

        // Unified / flat paginated view
        $tasks = $this->baseQuery()
            ->orderBy('updated_at', 'desc')
            ->paginate(25);

        return view('livewire.credit-list', compact('totals', 'units', 'pms', 'tasks'))
            ->layout('layouts.app', ['pageTitle' => 'Credit List']);
    }
}
