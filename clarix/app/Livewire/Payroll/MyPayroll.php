<?php

namespace App\Livewire\Payroll;

use App\Livewire\Traits\RequiresPlan;
use App\Models\PayrollRecord;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * A person's own payroll history. Read only, and no other person's.
 *
 * There is no user parameter and no filter that could name somebody else —
 * every query is keyed on auth()->id(), so there is nothing to tamper with.
 * The tenant scope is a second lock rather than the first.
 */
class MyPayroll extends Component
{
    use RequiresPlan;

    public function render()
    {
        // The plan layer first, the policy below it.
        $this->assertPlanIncludes('erp');

        abort_unless(Gate::allows('viewOwn', PayrollRecord::class), 403);

        $records = PayrollRecord::where('user_id', auth()->id())
            ->orderByDesc('month')
            ->limit(36)
            ->get();

        return view('livewire.payroll.my-payroll', [
            'records' => $records,
            // Only what has actually been paid is totalled. A draft is a
            // working figure and a finalised record is a promise; neither is
            // money the person has received.
            'paidTotal' => $records->where('status', 'paid')->sum('net_amount'),
        ])->layout('layouts.app', ['pageTitle' => 'My payroll']);
    }
}
