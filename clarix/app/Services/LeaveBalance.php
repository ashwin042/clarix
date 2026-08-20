<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;

/**
 * How much leave someone has taken, and how much they were allowed.
 *
 * Derived rather than stored. A leave_balances table would carry a used_days
 * column that has to be corrected on approve, reject, cancel and every future
 * edit path; the first path that forgets leaves a number that is quietly wrong
 * and that nobody thinks to doubt. Counting the approved requests is one query
 * and cannot drift from the records it describes.
 *
 * The figures are informational. Nothing here refuses a request that would
 * exceed an allowance, because enforcing that means answering questions Clarix
 * has no notion of — whether unused days roll over, how a mid-year joiner is
 * prorated, which days are working days. Those are policy, and inventing them
 * silently would be worse than leaving the decision with the person approving.
 */
class LeaveBalance
{
    /**
     * Days already approved for a person, per leave type, within one year.
     *
     * @return array<int, int> leave_type_id => days used
     */
    public function usedByType(User $user, ?int $year = null): array
    {
        $year ??= (int) now()->year;

        $requests = LeaveRequest::query()
            ->approved()
            ->where('user_id', $user->id)
            ->whereYear('start_date', $year)
            ->get(['leave_type_id', 'start_date', 'end_date']);

        $used = [];

        foreach ($requests as $request) {
            $used[$request->leave_type_id] = ($used[$request->leave_type_id] ?? 0) + $request->dayCount();
        }

        return $used;
    }

    /**
     * A row per leave type: what it allows, what has been used, what is left.
     *
     * `allowance` and `remaining` are null for a category the agency does not
     * track, which is different from zero — see LeaveType::isTracked().
     *
     * @return list<array{type: LeaveType, allowance: int|null, used: int, remaining: int|null}>
     */
    public function summaryFor(User $user, ?int $year = null): array
    {
        $used = $this->usedByType($user, $year);

        return LeaveType::orderBy('name')->get()->map(function (LeaveType $type) use ($used) {
            $taken = $used[$type->id] ?? 0;

            return [
                'type'      => $type,
                'allowance' => $type->default_annual_allowance,
                'used'      => $taken,
                'remaining' => $type->isTracked()
                    ? $type->default_annual_allowance - $taken
                    : null,
            ];
        })->all();
    }
}
