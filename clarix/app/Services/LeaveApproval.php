<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deciding a leave request, and the mark it leaves on the attendance record.
 *
 * The two modules meet here and nowhere else. Approving leave writes an
 * `on_leave` attendance row for every day in the range, so the attendance
 * screen tells the truth about why somebody was not at work without anyone
 * having to mark the days by hand.
 */
class LeaveApproval
{
    /**
     * Approve a request and record the days as leave.
     *
     * The whole thing runs in a transaction: a request marked approved with
     * only half its days written to attendance would be a worse state than
     * either outcome on its own.
     *
     * @throws ValidationException
     */
    public function approve(LeaveRequest $request, User $reviewer): LeaveRequest
    {
        $this->assertDecidable($request, $reviewer);

        return DB::transaction(function () use ($request, $reviewer) {
            $request->status      = 'approved';
            $request->reviewed_by = $reviewer->id;
            $request->reviewed_at = now();
            $request->save();

            $this->markAttendance($request);

            return $request;
        });
    }

    /**
     * @throws ValidationException
     */
    public function reject(LeaveRequest $request, User $reviewer): LeaveRequest
    {
        $this->assertDecidable($request, $reviewer);

        $request->status      = 'rejected';
        $request->reviewed_by = $reviewer->id;
        $request->reviewed_at = now();
        $request->save();

        return $request;
    }

    /**
     * Withdraw your own request, while it is still undecided.
     *
     * Deliberately limited to a pending request. Cancelling something already
     * approved would mean unwinding the attendance days it wrote, and deciding
     * what to do about a day the person has since worked — real questions that
     * belong with a manager rejecting the request, not with a self-service
     * button.
     *
     * @throws ValidationException
     */
    public function cancel(LeaveRequest $request, User $actor): LeaveRequest
    {
        if ($request->user_id !== $actor->id) {
            throw ValidationException::withMessages([
                'leave' => 'You can only withdraw your own request.',
            ]);
        }

        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'leave' => 'Only a pending request can be withdrawn. Ask a manager to reject it instead.',
            ]);
        }

        $request->status = 'cancelled';
        $request->save();

        return $request;
    }

    /**
     * The two conditions that hold for any decision, whoever is making it.
     *
     * The self-approval bar is structural and sits here rather than in the
     * policy, so it holds for an admin as much as for anyone granted
     * leave.manage. Being the most senior person in an agency is not a reason
     * to sign off your own time away.
     *
     * @throws ValidationException
     */
    protected function assertDecidable(LeaveRequest $request, User $reviewer): void
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'leave' => 'This request has already been decided.',
            ]);
        }

        if ($request->user_id === $reviewer->id) {
            throw ValidationException::withMessages([
                'leave' => 'You cannot decide your own leave request.',
            ]);
        }
    }

    /**
     * Write an on_leave attendance row for every day of an approved request.
     *
     * Existing rows are overwritten, clock times and all. An approval is a
     * deliberate statement that the person is away, so it takes precedence
     * over whatever the day previously said — and a row claiming both seven
     * hours worked and a day of leave would be self-contradictory. Attendance
     * already clears the clock when a day is marked on_leave by hand; this is
     * the same rule reached from the other side.
     */
    protected function markAttendance(LeaveRequest $request): void
    {
        foreach ($request->period() as $day) {
            $date = $day->toDateString();

            $attendance = Attendance::where('user_id', $request->user_id)
                ->whereDate('date', $date)
                ->first() ?? new Attendance;

            // user_id is stamped server-side, never mass-assigned.
            $attendance->user_id   = $request->user_id;
            $attendance->date      = $date;
            $attendance->status    = 'on_leave';
            $attendance->clock_in  = null;
            $attendance->clock_out = null;
            $attendance->notes     = trim(($request->leaveType?->name ?? 'Leave').': approved leave');

            /*
             * The attendance row has to land in the requester's organization,
             * not the reviewer's. They are the same agency in every real flow
             * — a manager only ever decides their own people's requests — but
             * the record belongs to the person it describes, so it is stamped
             * from the request rather than from whoever is signed in.
             */
            $attendance->organization_id = $request->organization_id;

            $attendance->save();
        }
    }
}
