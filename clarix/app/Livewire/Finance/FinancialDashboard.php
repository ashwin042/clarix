<?php

namespace App\Livewire\Finance;

use App\Models\Payment;
use App\Models\Task;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class FinancialDashboard extends Component
{
    public string $filterUnit = '';
    public string $dateFrom = '';
    public string $dateTo = '';

    public function mount(): void
    {
        $this->dateFrom = now()->startOfYear()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function render()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $paymentQuery = Payment::query()
            ->when($this->filterUnit, fn ($q) => $q->where('unit_id', $this->filterUnit))
            ->when($this->dateFrom, fn ($q) => $q->where('from_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->where('to_date', '<=', $this->dateTo));

        $creditQuery = Task::query()
            ->where('status', 'completed')
            ->when($this->filterUnit, fn ($q) => $q->where('unit_id', $this->filterUnit))
            ->when($this->dateFrom, fn ($q) => $q->where('updated_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->where('updated_at', '<=', $this->dateTo));

        $totalRevenue = (clone $paymentQuery)->sum('amount');
        $totalCredits = (clone $creditQuery)->sum('credit_amount');
        $totalCreditCovered = (clone $paymentQuery)->sum('total_credit');
        $netProfit = $totalRevenue - $totalCredits;
        $pendingCredit = $totalCredits - $totalCreditCovered;

        // Monthly revenue vs credits (last 6 months)
        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $months->push(now()->subMonths($i)->format('Y-m'));
        }

        $monthlyRevenue = Payment::query()
            ->when($this->filterUnit, fn ($q) => $q->where('unit_id', $this->filterUnit))
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"), DB::raw('SUM(amount) as total'))
            ->whereIn(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"), $months)
            ->groupBy('month')
            ->pluck('total', 'month');

        $monthlyCredits = Task::where('status', 'completed')
            ->when($this->filterUnit, fn ($q) => $q->where('unit_id', $this->filterUnit))
            ->select(DB::raw("DATE_FORMAT(updated_at, '%Y-%m') as month"), DB::raw('SUM(credit_amount) as total'))
            ->whereIn(DB::raw("DATE_FORMAT(updated_at, '%Y-%m')"), $months)
            ->groupBy('month')
            ->pluck('total', 'month');

        $revenueData = $months->map(fn ($m) => (float) ($monthlyRevenue[$m] ?? 0))->values();
        $creditData = $months->map(fn ($m) => (float) ($monthlyCredits[$m] ?? 0))->values();
        $monthLabels = $months->map(fn ($m) => \Carbon\Carbon::parse($m . '-01')->format('M Y'))->values();

        // Unit profitability
        $units = Unit::orderBy('name')->get();
        $unitRevenue = Payment::whereNotNull('unit_id')
            ->select('unit_id', DB::raw('SUM(amount) as total'))
            ->groupBy('unit_id')
            ->pluck('total', 'unit_id');

        $unitCredits = Task::where('status', 'completed')
            ->select('unit_id', DB::raw('SUM(credit_amount) as total'))
            ->groupBy('unit_id')
            ->pluck('total', 'unit_id');

        $unitLabels = $units->pluck('name')->values();
        $unitRevenueData = $units->map(fn ($u) => (float) ($unitRevenue[$u->id] ?? 0))->values();
        $unitCreditData = $units->map(fn ($u) => (float) ($unitCredits[$u->id] ?? 0))->values();
        $unitProfitData = $units->map(fn ($u) => (float) (($unitRevenue[$u->id] ?? 0) - ($unitCredits[$u->id] ?? 0)))->values();

        // Recent payments
        $recentPayments = Payment::with(['unit', 'creator'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return view('livewire.finance.financial-dashboard', [
            'totalRevenue' => $totalRevenue,
            'totalCredits' => $totalCredits,
            'netProfit' => $netProfit,
            'pendingCredit' => $pendingCredit,
            'monthLabels' => $monthLabels,
            'revenueData' => $revenueData,
            'creditData' => $creditData,
            'unitLabels' => $unitLabels,
            'unitRevenueData' => $unitRevenueData,
            'unitCreditData' => $unitCreditData,
            'unitProfitData' => $unitProfitData,
            'recentPayments' => $recentPayments,
            'units' => $units,
        ])->layout('layouts.app', ['pageTitle' => 'Financial Dashboard']);
    }
}
