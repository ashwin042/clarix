<?php

namespace App\Livewire\Profile;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\PayrollRecord;
use App\Services\LeaveBalance;
use App\Services\PersonalTaskStats;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * One person's own view of themselves, gathered from across the app.
 *
 * There is no user parameter, no route segment and no public property that
 * could name somebody — every figure on this page is derived from
 * auth()->user() and nothing else. That is the whole security model, and it is
 * deliberately structural rather than a policy check: a page that cannot
 * express "somebody else" cannot be talked into showing them.
 *
 * This is not an admin lookup tool. An admin opening it sees their own
 * attendance, their own leave and their own pay, exactly as a writer does,
 * despite holding every permission in the agency. Looking up a colleague is a
 * different feature and would need a different page.
 *
 * The page itself is never refused. Each section consults the same gate its
 * own full screen consults, and a section the viewer may not read is replaced
 * with a short note rather than a 403 — following LeavePage, where refusing
 * the whole screen over a view toggle was already rejected. Someone holding
 * none of the toggles still gets their name, their unit and their joining
 * date, because none of those were ever behind a permission.
 */
class ProfileOverview extends Component
{
    public function render()
    {
        $user = auth()->user();

        /*
         * Two independent layers, both required for the ERP sections.
         *
         * Which one refused decides what the section says. "Not in your plan"
         * and "your role cannot see this" are different facts that send the
         * reader to different people — an administrator can fix the second in
         * the Authorization panel and cannot fix the first at all.
         */
        $hasErp = $user->planAllows('erp');

        $canSeeTasks      = $user->hasPermission('tasks.view');
        $canSeeAttendance = $hasErp && Gate::allows('viewOwn', Attendance::class);
        $canSeeLeave      = $hasErp && Gate::allows('viewOwn', LeaveRequest::class);
        $canSeePayroll    = $hasErp && Gate::allows('viewOwn', PayrollRecord::class);

        return view('livewire.profile.profile-overview', [
            'user' => $user,

            // Which refusal the three ERP sections should show when withheld.
            'erpReason' => $hasErp ? 'permission' : 'plan',

            'canSeeTasks'   => $canSeeTasks,
            'taskStats'     => $canSeeTasks ? app(PersonalTaskStats::class)->for($user) : null,
            'taskStatuses'  => PersonalTaskStats::STATUSES,

            'canSeeAttendance'   => $canSeeAttendance,
            'attendanceSummary'  => $canSeeAttendance ? $this->attendanceThisMonth() : null,
            'attendanceStatuses' => Attendance::STATUSES,

            'canSeeLeave'   => $canSeeLeave,
            // Reused wholesale rather than reimplemented: how many days a
            // person has left is one derivation, and a second copy of it here
            // would be free to drift away from the figure the Leave page
            // shows for the same person on the same day.
            'leaveBalances' => $canSeeLeave ? app(LeaveBalance::class)->summaryFor($user) : null,
            'pendingLeave'  => $canSeeLeave
                ? LeaveRequest::where('user_id', $user->id)->pending()->count()
                : 0,

            'canSeePayroll'   => $canSeePayroll,
            'payrollRecords'  => $canSeePayroll ? $this->recentPayroll() : null,
            'payrollPaidTotal' => $canSeePayroll ? $this->paidTotal() : 0,
        ])->layout('layouts.app', ['pageTitle' => 'Profile']);
    }

    /**
     * This calendar month's own attendance, one count per status.
     *
     * @return array<string, int>
     */
    protected function attendanceThisMonth(): array
    {
        /*
         * Bounded with whereDate rather than a whereBetween on two Carbons.
         *
         * The column holds a plain "2026-08-01" while a bound Carbon renders
         * as "2026-08-01 00:00:00", and the string comparison then sorts the
         * stored value *before* the lower bound — so a month's worth of
         * attendance silently counted as zero. whereDate normalises both
         * sides, which also covers the rows written as full timestamps that
         * the Attendance model warns about.
         */
        $counts = Attendance::query()
            ->where('user_id', auth()->id())
            ->whereDate('date', '>=', now()->startOfMonth()->toDateString())
            ->whereDate('date', '<=', now()->endOfMonth()->toDateString())
            ->select('status')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $summary = [];

        foreach (array_keys(Attendance::STATUSES) as $status) {
            $summary[$status] = (int) $counts->get($status, 0);
        }

        return $summary;
    }

    /**
     * The last handful of the viewer's own payroll records.
     *
     * Shaped the same way MyPayroll shapes it, and keyed on auth()->id() for
     * the same reason: there is no parameter to tamper with, and the tenant
     * scope is a second lock rather than the first.
     */
    protected function recentPayroll()
    {
        return PayrollRecord::where('user_id', auth()->id())
            ->orderByDesc('month')
            ->limit(6)
            ->get();
    }

    /**
     * Only money that has actually been paid, matching MyPayroll. A draft is a
     * working figure and a finalised record is a promise; neither has been
     * received. Totalled over the whole history rather than the six rows
     * shown, so the figure does not shrink as older records scroll off.
     */
    protected function paidTotal(): float
    {
        return (float) PayrollRecord::where('user_id', auth()->id())
            ->where('status', 'paid')
            ->sum('net_amount');
    }
}
