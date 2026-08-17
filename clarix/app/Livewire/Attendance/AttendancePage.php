<?php

namespace App\Livewire\Attendance;

use App\Livewire\Traits\RequiresPlan;
use App\Livewire\Traits\WithDeleteConfirmation;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The attendance screen: your own record, and — for whoever may see it — the
 * team's for a chosen day.
 *
 * The team half is drawn only when the viewer holds attendance.view_all, and
 * the rows it draws are decided structurally: an admin sees the whole agency,
 * a PM their own unit. The tenant scope on Attendance keeps both inside their
 * organization without either query mentioning it.
 */
class AttendancePage extends Component
{
    use RequiresPlan;
    use WithPagination;
    use WithDeleteConfirmation;

    /** The day the team table is showing. */
    public string $date = '';

    // ── Manual marking / correction form ─────────────────────────────────────

    public bool $showModal = false;

    public ?int $editingId = null;

    public ?int $subjectId = null;

    public string $status = 'present';

    public string $notes = '';

    public function mount(): void
    {
        $this->date = today()->toDateString();
    }

    public function updatingDate(): void
    {
        $this->resetPage();
    }

    /**
     * Whether the viewer may see beyond their own record.
     */
    public function getCanViewTeamProperty(): bool
    {
        return Gate::allows('viewAny', Attendance::class);
    }

    public function getCanManageProperty(): bool
    {
        return auth()->user()->hasPermission('attendance.manage');
    }

    /**
     * The people whose attendance this viewer may reach.
     *
     * Confined to the organization by the tenant scope on User, and narrowed
     * again to a PM's own unit. A writer never gets here — the team table is
     * not drawn for them at all — but the query is written so that it would
     * return only themselves if they did.
     */
    protected function subjects()
    {
        $user = auth()->user();

        return User::query()
            ->when($user->isPm(), fn ($q) => $q->where('unit_id', $user->unit_id))
            ->when(! $user->isAdmin() && ! $user->isPm(), fn ($q) => $q->whereKey($user->id))
            ->orderBy('name');
    }

    // ── Marking and correcting ───────────────────────────────────────────────

    public function openMark(int $userId): void
    {
        $subject = $this->subjects()->findOrFail($userId);

        abort_unless(Gate::allows('manage', [Attendance::class, $subject]), 403);

        $existing = Attendance::where('user_id', $subject->id)
            ->whereDate('date', $this->date)
            ->first();

        $this->subjectId = $subject->id;
        $this->editingId = $existing?->id;
        $this->status    = $existing->status ?? 'present';
        $this->notes     = (string) ($existing->notes ?? '');

        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save(): void
    {
        // The subject is re-resolved through subjects(), so a tampered
        // subjectId cannot name somebody outside the viewer's reach: the
        // lookup simply fails to find them.
        $subject = $this->subjects()->findOrFail($this->subjectId);

        abort_unless(Gate::allows('manage', [Attendance::class, $subject]), 403);

        $this->validate([
            'status' => ['required', Rule::in(array_keys(Attendance::STATUSES))],
            'notes'  => ['nullable', 'string', 'max:1000'],
        ]);

        /*
         * Matched with whereDate rather than on an exact value. The column is
         * a date but the cast writes a full timestamp, so an equality match on
         * "2026-08-17" silently missed the row stored as "2026-08-17 00:00:00"
         * and inserted a second one — straight into the unique index.
         */
        $attendance = Attendance::where('user_id', $subject->id)
            ->whereDate('date', $this->date)
            ->first() ?? new Attendance;

        // user_id is stamped on the server, never mass-assigned — see the
        // Attendance model's $fillable.
        $attendance->user_id = $subject->id;
        $attendance->date    = $this->date;
        $attendance->status  = $this->status;
        $attendance->notes   = $this->notes ?: null;

        /*
         * Marking somebody absent or on leave clears any clock times that were
         * recorded, because the two would otherwise contradict each other on
         * the same row: a person cannot have worked 7 hours and been on leave.
         */
        if (in_array($this->status, ['absent', 'on_leave'], true)) {
            $attendance->clock_in  = null;
            $attendance->clock_out = null;
        }

        $attendance->save();

        $this->showModal = false;
        $this->reset(['editingId', 'subjectId', 'notes']);
        $this->dispatch('notify', message: 'Attendance updated.', type: 'success');
    }

    public function confirmDelete(): void
    {
        $attendance = Attendance::findOrFail($this->deletingId);

        // Deletion follows the session's rule: admin of the owning agency,
        // structurally, with no permission to grant.
        abort_unless(Gate::allows('delete', $attendance), 403);

        $attendance->delete();
        $this->cancelDelete();
        $this->dispatch('notify', message: 'Attendance record removed.', type: 'success');
    }

    public function render()
    {
        // The plan layer first, then the permission layer below it. Repeated
        // here as well as on the route because a Livewire action never passes
        // through route middleware.
        $this->assertPlanIncludes('erp');

        $user = auth()->user();

        abort_unless(
            Gate::allows('viewOwn', Attendance::class) || $this->canViewTeam,
            403
        );

        // Your own recent history, always.
        $mine = Attendance::where('user_id', $user->id)
            ->orderByDesc('date')
            ->limit(14)
            ->get();

        $team = collect();
        $roster = collect();

        if ($this->canViewTeam) {
            $roster = $this->subjects()->get();

            $team = Attendance::onDate($this->date)
                ->whereIn('user_id', $roster->pluck('id'))
                ->with('user')
                ->get()
                ->keyBy('user_id');
        }

        return view('livewire.attendance.attendance-page', [
            'mine'     => $mine,
            'roster'   => $roster,
            'team'     => $team,
            'statuses' => Attendance::STATUSES,
        ])->layout('layouts.app', ['pageTitle' => 'Attendance']);
    }
}
