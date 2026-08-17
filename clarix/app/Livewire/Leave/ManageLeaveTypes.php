<?php

namespace App\Livewire\Leave;

use App\Livewire\Traits\RequiresPlan;
use App\Livewire\Traits\WithDeleteConfirmation;
use App\Models\LeaveType;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * An agency's own leave categories.
 *
 * Admin only. Changing the categories shapes the agency's policy rather than
 * administering one person's time off, so it deliberately sits outside
 * leave.manage — a PM granted approval rights does not thereby get to invent
 * new kinds of leave.
 */
class ManageLeaveTypes extends Component
{
    use RequiresPlan;
    use WithDeleteConfirmation;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    /** Blank means "not tracked", which is different from zero. */
    public string $default_annual_allowance = '';

    public function mount(): void
    {
        // The plan layer first: an agency without ERP has no leave types to
        // manage, whoever is asking.
        $this->assertPlanIncludes('erp');

        $this->authorizeAdmin();
    }

    protected function authorizeAdmin(): void
    {
        $user = auth()->user();

        abort_unless($user?->isAdmin() && $user->organization_id !== null, 403);
    }

    public function openCreate(): void
    {
        $this->authorizeAdmin();
        $this->reset(['name', 'default_annual_allowance', 'editingId']);
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $this->authorizeAdmin();

        // Scoped, so another agency's type is not findable here.
        $type = LeaveType::findOrFail($id);

        abort_unless(Gate::allows('update', $type), 403);

        $this->editingId               = $type->id;
        $this->name                    = $type->name;
        $this->default_annual_allowance = (string) ($type->default_annual_allowance ?? '');

        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorizeAdmin();

        $organizationId = auth()->user()->organization_id;

        $data = $this->validate([
            'name' => [
                'required', 'string', 'max:100',
                // Unique within the agency, not across the platform: two
                // agencies may both have a "Sick Leave".
                Rule::unique('leave_types', 'name')
                    ->where(fn ($q) => $q->where('organization_id', $organizationId))
                    ->ignore($this->editingId),
            ],
            'default_annual_allowance' => ['nullable', 'numeric', 'integer', 'min:0', 'max:365'],
        ]);

        $allowance = $this->default_annual_allowance === ''
            ? null
            : (int) $this->default_annual_allowance;

        if ($this->editingId) {
            $type = LeaveType::findOrFail($this->editingId);
            abort_unless(Gate::allows('update', $type), 403);

            $type->update(['name' => $data['name'], 'default_annual_allowance' => $allowance]);
        } else {
            abort_unless(Gate::allows('create', LeaveType::class), 403);

            LeaveType::create(['name' => $data['name'], 'default_annual_allowance' => $allowance]);
        }

        $this->showModal = false;
        $this->reset(['name', 'default_annual_allowance', 'editingId']);
        $this->dispatch('notify', message: 'Leave type saved.', type: 'success');
    }

    public function confirmDelete(): void
    {
        $type = LeaveType::findOrFail($this->deletingId);

        abort_unless(Gate::allows('delete', $type), 403);

        /*
         * The foreign key on leave_requests restricts, so the database would
         * refuse this anyway. Checking first turns an integrity error naming a
         * constraint into a sentence naming the reason.
         */
        if ($type->requests()->exists()) {
            $this->cancelDelete();
            $this->dispatch(
                'notify',
                message: 'People have already booked leave against this type, so it cannot be removed.',
                type: 'error'
            );

            return;
        }

        $type->delete();
        $this->cancelDelete();
        $this->dispatch('notify', message: 'Leave type removed.', type: 'success');
    }

    public function render()
    {
        $this->authorizeAdmin();

        return view('livewire.leave.manage-leave-types', [
            'types' => LeaveType::withCount('requests')->orderBy('name')->get(),
        ])->layout('layouts.app', ['pageTitle' => 'Leave types']);
    }
}
