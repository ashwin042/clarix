<?php

namespace App\Livewire\Attendance;

use App\Livewire\Traits\RequiresPlan;
use App\Models\Attendance;
use App\Services\AttendanceClock;
use Livewire\Component;

/**
 * The clock in / clock out control, for the signed-in user and nobody else.
 *
 * There is no user parameter by design. Every action reads auth()->user()
 * directly, so there is no property a crafted Livewire request could set to
 * aim this at a colleague. Marking somebody else's day is a different screen
 * with a different permission behind it.
 *
 * Rendered both on the dashboards and at the top of the attendance page.
 */
class ClockWidget extends Component
{
    use RequiresPlan;

    public ?Attendance $today = null;

    /**
     * Set on the dashboard, where the card links through to the full page.
     */
    public bool $compact = false;

    public function mount(bool $compact = false): void
    {
        // Ahead of refreshToday(), so an agency without ERP never runs an
        // attendance query at all.
        $this->assertPlanIncludes('erp');

        $this->compact = $compact;
        $this->refreshToday();
    }

    public function clockIn(): void
    {
        // Structural, not permission-gated: recording your own attendance is
        // like editing your own profile. There is still an authenticated user
        // to record it for, which is what this asserts.
        abort_unless(auth()->check(), 403);

        app(AttendanceClock::class)->clockIn(auth()->user());

        $this->refreshToday();
        $this->dispatch('notify', message: 'Clocked in.', type: 'success');
        $this->dispatch('attendance-updated');
    }

    public function clockOut(): void
    {
        abort_unless(auth()->check(), 403);

        app(AttendanceClock::class)->clockOut(auth()->user());

        $this->refreshToday();
        $this->dispatch('notify', message: 'Clocked out.', type: 'success');
        $this->dispatch('attendance-updated');
    }

    protected function refreshToday(): void
    {
        $this->today = app(AttendanceClock::class)->today(auth()->user());
    }

    public function render()
    {
        return view('livewire.attendance.clock-widget', [
            'canClockIn'  => $this->today === null || $this->today->clock_in === null,
            'canClockOut' => $this->today !== null && $this->today->isOpen(),
        ]);
    }
}
