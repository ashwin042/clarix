<?php

namespace App\Livewire\Leave;

use App\Livewire\Traits\RequiresPlan;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveApproval;
use App\Services\LeaveBalance;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * The leave screen: ask for time off, see your own history, and — for whoever
 * may decide them — the queue of requests waiting.
 *
 * Submitting is open to everyone, like clocking in: the form carries no user
 * field and always records the signed-in person. The queue is drawn only for
 * a holder of leave.view_all, and the rows it draws are decided structurally.
 */
class LeavePage extends Component
{
    use RequiresPlan;

    // ── Request form ─────────────────────────────────────────────────────────

    public string $leave_type_id = '';

    public string $start_date = '';

    public string $end_date = '';

    public string $reason = '';

    /** Which queue tab is showing: pending or decided. */
    public string $tab = 'pending';

    public function mount(): void
    {
        $this->start_date = today()->toDateString();
        $this->end_date   = today()->toDateString();
    }

    public function getCanViewTeamProperty(): bool
    {
        return Gate::allows('viewAny', LeaveRequest::class);
    }

    public function getCanManageProperty(): bool
    {
        return auth()->user()->hasPermission('leave.manage');
    }

    /**
     * The people whose requests this viewer may reach — the organization for
     * an admin or HR, their own unit for a PM, themselves otherwise.
     */
    protected function subjectIds()
    {
        $user = auth()->user();

        // HR reaches the agency, as in AttendancePage — approving time off is
        // the role's job across every unit.
        $seesEveryone = $user->isAdmin() || $user->isHr();

        return User::query()
            ->when($user->isPm(), fn ($q) => $q->where('unit_id', $user->unit_id))
            ->when(! $seesEveryone && ! $user->isPm(), fn ($q) => $q->whereKey($user->id))
            ->pluck('id');
    }

    // ── Submitting ───────────────────────────────────────────────────────────

    public function submit(): void
    {
        $user = auth()->user();

        // Structural, not permission-gated: asking for time off must not
        // depend on a toggle. There is still an authenticated person to
        // record it for, which is what this asserts.
        abort_unless(auth()->check(), 403);

        $data = $this->validate([
            // The leave type is looked up through the scoped model, so a type
            // belonging to another agency simply does not exist here.
            'leave_type_id' => ['required', Rule::exists('leave_types', 'id')->where('organization_id', $user->organization_id)],
            'start_date'    => ['required', 'date'],
            'end_date'      => ['required', 'date', 'after_or_equal:start_date'],
            'reason'        => ['nullable', 'string', 'max:1000'],
        ]);

        // One live claim on a given day. Cancelled and rejected requests are
        // not in the way; a pending or approved one is.
        $clash = LeaveRequest::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->overlapping($data['start_date'], $data['end_date'])
            ->exists();

        if ($clash) {
            $this->addError('start_date', 'You already have a request covering those dates.');

            return;
        }

        $request = new LeaveRequest([
            'leave_type_id' => $data['leave_type_id'],
            'start_date'    => $data['start_date'],
            'end_date'      => $data['end_date'],
            'reason'        => $data['reason'] ?: null,
        ]);

        // user_id is stamped here, never mass-assigned — it is absent from
        // $fillable so no crafted request can book leave for a colleague.
        $request->user_id = $user->id;
        $request->status  = 'pending';
        $request->save();

        $this->reset(['reason']);
        $this->dispatch('notify', message: 'Leave request submitted.', type: 'success');
    }

    public function withdraw(int $id): void
    {
        $request = LeaveRequest::findOrFail($id);

        abort_unless(Gate::allows('cancel', $request), 403);

        app(LeaveApproval::class)->cancel($request, auth()->user());

        $this->dispatch('notify', message: 'Request withdrawn.', type: 'info');
    }

    // ── Deciding ─────────────────────────────────────────────────────────────

    public function approve(int $id): void
    {
        $this->decide($id, 'approve');
    }

    public function reject(int $id): void
    {
        $this->decide($id, 'reject');
    }

    protected function decide(int $id, string $action): void
    {
        // Re-resolved through the reachable set, so a tampered id cannot name
        // somebody outside this viewer's scope — the lookup fails to find it.
        $request = LeaveRequest::whereIn('user_id', $this->subjectIds())->findOrFail($id);

        abort_unless(Gate::allows('decide', $request), 403);

        // Deciding your own request is barred inside the service, so it holds
        // for an admin as well as for anyone granted leave.manage.
        app(LeaveApproval::class)->{$action}($request, auth()->user());

        $this->dispatch(
            'notify',
            message: $action === 'approve' ? 'Leave approved.' : 'Leave rejected.',
            type: $action === 'approve' ? 'success' : 'info'
        );
    }

    public function render()
    {
        // The plan layer, ahead of everything below. The page being open to
        // every role is a statement about permissions; it says nothing about
        // an agency that has not bought ERP.
        $this->assertPlanIncludes('erp');

        $user = auth()->user();

        /*
         * The page itself is never refused. Asking for time off is structural,
         * and the form lives here — so a 403 on the whole screen would mean
         * revoking a *view* toggle silently stopped people requesting leave,
         * which is exactly the failure the permission is not meant to cause.
         *
         * The gates apply to the parts that read records instead: your own
         * history and balances need leave.view_own, the queue needs
         * leave.view_all. Someone holding neither still gets the form.
         */
        $canViewOwn = Gate::allows('viewOwn', LeaveRequest::class);

        $mine = $canViewOwn
            ? LeaveRequest::where('user_id', $user->id)
                ->with(['leaveType', 'reviewer'])
                ->orderByDesc('start_date')
                ->limit(20)
                ->get()
            : collect();

        $queue = collect();

        if ($this->canViewTeam) {
            $queue = LeaveRequest::whereIn('user_id', $this->subjectIds())
                ->where('user_id', '!=', $user->id)
                ->when($this->tab === 'pending', fn ($q) => $q->pending())
                ->when($this->tab !== 'pending', fn ($q) => $q->whereIn('status', ['approved', 'rejected']))
                ->with(['user', 'leaveType', 'reviewer'])
                ->orderBy('start_date')
                ->limit(50)
                ->get();
        }

        return view('livewire.leave.leave-page', [
            'mine'       => $mine,
            'canViewOwn' => $canViewOwn,
            'queue'      => $queue,
            'types'      => LeaveType::orderBy('name')->get(),
            'balances'   => $canViewOwn ? app(LeaveBalance::class)->summaryFor($user) : [],
            'statuses'   => LeaveRequest::STATUSES,
        ])->layout('layouts.app', ['pageTitle' => 'Leave']);
    }
}
