<?php

namespace App\Livewire\Finance;

use App\Models\Payment;
use App\Models\Task;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class FinancialDashboard extends Component
{
    use WithPagination;

    public string $filterUnit = '';
    public string $dateFrom = '';
    public string $dateTo = '';

    public function mount(): void
    {
        $this->dateFrom = now()->startOfYear()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function updatingFilterUnit(): void { $this->resetPage(); }
    public function updatingDateFrom(): void { $this->resetPage(); }
    public function updatingDateTo(): void { $this->resetPage(); }

    /**
     * Recorded payments matching the active unit + date-range filters.
     * A payment is included only when its covered period falls within the range.
     */
    private function paymentQuery(): Builder
    {
        return Payment::query()
            ->when($this->filterUnit, fn ($q) => $q->where('unit_id', $this->filterUnit))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('from_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('to_date', '<=', $this->dateTo));
    }

    /**
     * Completed tasks (credits earned) matching the active unit + date-range filters.
     */
    private function creditQuery(): Builder
    {
        return Task::query()
            ->where('status', 'completed')
            ->when($this->filterUnit, fn ($q) => $q->where('unit_id', $this->filterUnit))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('completed_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('completed_at', '<=', $this->dateTo));
    }

    public function render()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        // ── Stat cards ──────────────────────────────────────────────────────────
        // Revenue comes only from recorded payments; credits from completed tasks.
        $totalRevenue = (float) $this->paymentQuery()->sum('amount');
        $totalCredits = (float) $this->creditQuery()->sum('credit_amount');
        // Net profit tracks recorded revenue.
        $netProfit    = $totalRevenue;

        // ── Month buckets spanning the selected range (capped to last 24) ────────
        $start = $this->dateFrom
            ? \Carbon\Carbon::parse($this->dateFrom)->startOfMonth()
            : now()->subMonths(5)->startOfMonth();
        $end = $this->dateTo
            ? \Carbon\Carbon::parse($this->dateTo)->startOfMonth()
            : now()->startOfMonth();

        $months = collect();
        $cursor = $start->copy();
        while ($cursor->lessThanOrEqualTo($end)) {
            $months->push($cursor->format('Y-m'));
            $cursor->addMonth();
        }
        if ($months->count() > 24) {
            $months = $months->slice(-24)->values();
        }

        // ── Revenue vs Credits over time ─────────────────────────────────────────
        $monthlyRevenue = $this->paymentQuery()
            ->select(DB::raw("DATE_FORMAT(from_date, '%Y-%m') as month"), DB::raw('SUM(amount) as total'))
            ->groupBy('month')
            ->pluck('total', 'month');

        $monthlyCredits = $this->creditQuery()
            ->select(DB::raw("DATE_FORMAT(completed_at, '%Y-%m') as month"), DB::raw('SUM(credit_amount) as total'))
            ->groupBy('month')
            ->pluck('total', 'month');

        $monthLabels = $months->map(fn ($m) => \Carbon\Carbon::parse($m . '-01')->format('M Y'))->values()->all();
        $revenueData = $months->map(fn ($m) => (float) ($monthlyRevenue[$m] ?? 0))->values()->all();
        $creditData  = $months->map(fn ($m) => (float) ($monthlyCredits[$m] ?? 0))->values()->all();

        // ── Unit profitability: revenue received vs credits owed per unit ────────
        $units = Unit::orderBy('name')->get();
        $unitsForChart = $this->filterUnit
            ? $units->where('id', (int) $this->filterUnit)->values()
            : $units;

        $unitRevenue = $this->paymentQuery()
            ->whereNotNull('unit_id')
            ->select('unit_id', DB::raw('SUM(amount) as total'))
            ->groupBy('unit_id')
            ->pluck('total', 'unit_id');

        $unitCredits = $this->creditQuery()
            ->select('unit_id', DB::raw('SUM(credit_amount) as total'))
            ->groupBy('unit_id')
            ->pluck('total', 'unit_id');

        $unitLabels      = $unitsForChart->pluck('name')->values()->all();
        $unitRevenueData = $unitsForChart->map(fn ($u) => (float) ($unitRevenue[$u->id] ?? 0))->values()->all();
        $unitCreditData  = $unitsForChart->map(fn ($u) => (float) ($unitCredits[$u->id] ?? 0))->values()->all();

        // ── Top paying unit (hidden when a single unit is already filtered) ──────
        $topPayingUnit = null;
        if (! $this->filterUnit && $unitRevenue->isNotEmpty()) {
            $topUnitId = $unitRevenue->sortDesc()->keys()->first();
            $topPayingUnit = [
                'name'  => $units->firstWhere('id', $topUnitId)?->name ?? 'Unknown',
                'total' => (float) $unitRevenue->get($topUnitId),
            ];
        }

        $chartData = [
            'monthLabels'     => $monthLabels,
            'revenueData'     => $revenueData,
            'creditData'      => $creditData,
            'unitLabels'      => $unitLabels,
            'unitRevenueData' => $unitRevenueData,
            'unitCreditData'  => $unitCreditData,
        ];

        // Push fresh chart data to the browser so Chart.js redraws on every filter change.
        $this->dispatch('finance-charts-updated', data: $chartData);

        // ── Payment history (respects filters, most recent first, paginated) ─────
        $payments = $this->paymentQuery()
            ->with('unit')
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('livewire.finance.financial-dashboard', [
            'totalRevenue'  => $totalRevenue,
            'totalCredits'  => $totalCredits,
            'netProfit'     => $netProfit,
            'topPayingUnit' => $topPayingUnit,
            'chartData'     => $chartData,
            'payments'      => $payments,
            'units'         => $units,
        ])->layout('layouts.app', ['pageTitle' => 'Financial Dashboard']);
    }
}
