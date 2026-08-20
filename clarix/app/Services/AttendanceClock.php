<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Clocking in and out, in one place.
 *
 * The widget appears on the dashboard as well as on the attendance page, and
 * both need the same answers about what today's record is and whether the
 * button should do anything. Two copies of that reasoning would drift, and the
 * half that drifted would be the one enforcing the rule.
 *
 * Everything here acts on one specific user and never reads the session, so
 * the callers stay responsible for deciding whose day is being recorded — and
 * they only ever pass the authenticated user.
 */
class AttendanceClock
{
    /**
     * Today's record for a user, or null if they have not started the day.
     */
    public function today(User $user): ?Attendance
    {
        return Attendance::where('user_id', $user->id)
            ->whereDate('date', today())
            ->first();
    }

    /**
     * Start the day.
     *
     * Creates today's record if there is none. Clocking in sets `present` and
     * nothing else does — the other statuses are an admin's judgement.
     *
     * @throws ValidationException
     */
    public function clockIn(User $user): Attendance
    {
        $existing = $this->today($user);

        if ($existing !== null && $existing->clock_in !== null) {
            throw ValidationException::withMessages([
                'attendance' => 'You have already clocked in today.',
            ]);
        }

        /*
         * user_id is set here rather than mass-assigned — it is deliberately
         * absent from $fillable so that no request can post attendance against
         * a colleague. organization_id is stamped by BelongsToOrganization.
         */
        if ($existing !== null) {
            // An admin marked the day before the person arrived. They are
            // here now, so the clock starts and the status follows.
            $existing->clock_in = now();
            $existing->status   = 'present';
            $existing->save();

            return $existing;
        }

        $attendance = new Attendance([
            'date'     => today()->toDateString(),
            'clock_in' => now(),
            'status'   => 'present',
        ]);

        $attendance->user_id = $user->id;
        $attendance->save();

        return $attendance;
    }

    /**
     * End the day.
     *
     * @throws ValidationException
     */
    public function clockOut(User $user): Attendance
    {
        $attendance = $this->today($user);

        if ($attendance === null || $attendance->clock_in === null) {
            throw ValidationException::withMessages([
                'attendance' => 'You have not clocked in today.',
            ]);
        }

        if ($attendance->clock_out !== null) {
            throw ValidationException::withMessages([
                'attendance' => 'You have already clocked out today.',
            ]);
        }

        $attendance->clock_out = now();
        $attendance->save();

        return $attendance;
    }
}
