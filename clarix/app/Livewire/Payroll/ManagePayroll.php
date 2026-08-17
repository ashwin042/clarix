<?php

namespace App\Livewire\Payroll;

use App\Livewire\Traits\RequiresPlan;
use App\Livewire\Traits\WithDeleteConfirmation;
use App\Models\PayrollRecord;
use App\Models\User;
use App\Services\PayrollLifecycle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * The agency's payroll for a chosen month.
 *
 * One row per member, whether or not a record exists yet, so the screen shows
 * who has been done and who has not. Everything here needs payroll.manage, and
 * the tenant scope keeps every query inside the acting admin's agency.
 */
class ManagePayroll extends Component
{
    use RequiresPlan;
    use WithDeleteConfirmation;

    /** The month being worked on, as the first of that month. */
    public string $month = '';

    public bool $showModal = false;

    public ?int $subjectId = null;

    public ?int $editingId = null;

    public string $base_amount = '';

    public string $deductions = '0';

    public string $notes = '';

    public function mount(): void
    {
        // The plan layer first, the policy below it.
        $this->assertPlanIncludes('erp');

        $this->authorizeManage();
        $this->month = now()->startOfMonth()->toDateString();
    }

    protected function authorizeManage(): void
    {
        abort_unless(Gate::allows('manage', PayrollRecord::class), 403);
    }

    /**
     * The people this agency pays. Confined by the tenant scope on User;
     * payroll has no unit-level tier, so an admin sees the whole agency.
     */
    protected function members()
    {
        return User::query()->orderBy('name');
    }

    public function updatedMonth($value): void
    {
        $this->month = Carbon::parse($value)->startOfMonth()->toDateString();
    }

    // ── Entering and correcting ──────────────────────────────────────────────

    public function openRecord(int $userId): void
    {
        $this->authorizeManage();

        // Re-resolved through the scoped member list, so a tampered id cannot
        // name somebody outside this agency — the lookup simply fails.
        $subject = $this->members()->findOrFail($userId);

        $existing = PayrollRecord::where('user_id', $subject->id)
            ->forMonth($this->month)
            ->first();

        $this->subjectId   = $subject->id;
        $this->editingId   = $existing?->id;
        $this->base_amount = (string) ($existing->base_amount ?? '');
        $this->deductions  = (string) ($existing->deductions ?? '0');
        $this->notes       = (string) ($existing->notes ?? '');

        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorizeManage();

        $subject = $this->members()->findOrFail($this->subjectId);

        $data = $this->validate([
            'base_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'deductions'  => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'notes'       => ['nullable', 'string', 'max:1000'],
        ]);

        $deductions = (float) ($data['deductions'] ?? 0);

        if ($deductions > (float) $data['base_amount']) {
            $this->addError('deductions', 'Deductions cannot exceed the base amount.');

            return;
        }

        $record = PayrollRecord::where('user_id', $subject->id)
            ->forMonth($this->month)
            ->first();

        if ($record !== null) {
            // A finalised or paid record is closed. Reverting it to draft is a
            // deliberate act with its own button.
            abort_unless(Gate::allows('update', $record), 403);
        } else {
            abort_unless(Gate::allows('create', PayrollRecord::class), 403);

            $record = new PayrollRecord;
            // Both stamped server-side — neither is fillable.
            $record->user_id    = $subject->id;
            $record->created_by = auth()->id();
        }

        $record->month       = $this->month;
        $record->base_amount = $data['base_amount'];
        $record->deductions  = $deductions;
        $record->notes       = $this->notes ?: null;

        // net_amount is recomputed by the model on save; nothing sets it here.
        $record->save();

        $this->showModal = false;
        $this->reset(['subjectId', 'editingId', 'base_amount', 'notes']);
        $this->deductions = '0';

        $this->dispatch('notify', message: 'Payroll record saved.', type: 'success');
    }

    // ── Moving through the states ────────────────────────────────────────────

    public function finalize(int $id): void
    {
        $this->transition($id, 'finalized', 'Payroll finalized.');
    }

    public function markPaid(int $id): void
    {
        $this->transition($id, 'paid', 'Marked as paid.');
    }

    public function revertToDraft(int $id): void
    {
        $this->transition($id, 'draft', 'Reopened as draft.');
    }

    protected function transition(int $id, string $to, string $message): void
    {
        $this->authorizeManage();

        $record = PayrollRecord::findOrFail($id);

        abort_unless(Gate::allows('transition', $record), 403);

        app(PayrollLifecycle::class)->transition($record, $to, auth()->user());

        $this->dispatch('notify', message: $message, type: 'success');
    }

    public function confirmDelete(): void
    {
        $record = PayrollRecord::findOrFail($this->deletingId);

        // Admin of the owning agency, structurally — and never a paid record,
        // which describes money that actually moved.
        abort_unless(Gate::allows('delete', $record), 403);

        $record->delete();
        $this->cancelDelete();
        $this->dispatch('notify', message: 'Payroll record removed.', type: 'success');
    }

    public function render()
    {
        $this->authorizeManage();

        $members = $this->members()->get();

        $records = PayrollRecord::forMonth($this->month)
            ->whereIn('user_id', $members->pluck('id'))
            ->get()
            ->keyBy('user_id');

        return view('livewire.payroll.manage-payroll', [
            'members'  => $members,
            'records'  => $records,
            'statuses' => PayrollRecord::STATUSES,
            'totals'   => [
                'net'  => $records->sum('net_amount'),
                'paid' => $records->where('status', 'paid')->sum('net_amount'),
            ],
        ])->layout('layouts.app', ['pageTitle' => 'Payroll']);
    }
}
