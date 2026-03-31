<?php

namespace App\Livewire\Finance;

use App\Livewire\Traits\WithDeleteConfirmation;
use App\Models\Payment;
use App\Models\Unit;
use Livewire\Component;
use Livewire\WithPagination;

class ManagePayments extends Component
{
    use WithPagination, WithDeleteConfirmation;

    public string $search = '';
    public string $filterUnit = '';
    public string $filterMethod = '';
    public string $sortBy = 'created_at';
    public string $sortDir = 'desc';
    public bool $showModal = false;
    public ?int $editingId = null;

    // Form
    public string $payer_name = '';
    public string $amount = '';
    public string $total_credit = '';
    public string $unit_id = '';
    public string $from_date = '';
    public string $to_date = '';
    public string $payment_method = 'bank_transfer';
    public string $notes = '';

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingFilterUnit(): void { $this->resetPage(); }
    public function updatingFilterMethod(): void { $this->resetPage(); }

    public function sortBy(string $col): void
    {
        if ($this->sortBy === $col) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $col;
            $this->sortDir = 'asc';
        }
    }

    public function openCreate(): void
    {
        $this->reset(['payer_name', 'amount', 'total_credit', 'unit_id', 'from_date', 'to_date', 'payment_method', 'notes', 'editingId']);
        $this->payment_method = 'bank_transfer';
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function openEdit(Payment $payment): void
    {
        $this->editingId = $payment->id;
        $this->payer_name = $payment->payer_name;
        $this->amount = (string) $payment->amount;
        $this->total_credit = (string) $payment->total_credit;
        $this->unit_id = (string) ($payment->unit_id ?? '');
        $this->from_date = $payment->from_date->format('Y-m-d');
        $this->to_date = $payment->to_date->format('Y-m-d');
        $this->payment_method = $payment->payment_method;
        $this->notes = $payment->notes ?? '';
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'payer_name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'total_credit' => 'required|numeric|min:0',
            'unit_id' => 'nullable|exists:units,id',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'payment_method' => 'required|string|max:50',
            'notes' => 'nullable|string|max:2000',
        ]);

        $data = [
            'payer_name' => $this->payer_name,
            'amount' => $this->amount,
            'total_credit' => $this->total_credit,
            'unit_id' => $this->unit_id ?: null,
            'from_date' => $this->from_date,
            'to_date' => $this->to_date,
            'payment_method' => $this->payment_method,
            'notes' => $this->notes ?: null,
        ];

        if ($this->editingId) {
            Payment::findOrFail($this->editingId)->update($data);
            $this->dispatch('notify', message: 'Payment updated.', type: 'success');
        } else {
            Payment::create($data + ['created_by' => auth()->id()]);
            $this->dispatch('notify', message: 'Payment recorded.', type: 'success');
        }

        $this->showModal = false;
        $this->reset(['payer_name', 'amount', 'total_credit', 'unit_id', 'from_date', 'to_date', 'payment_method', 'notes', 'editingId']);
    }

    public function confirmDelete(): void
    {
        $payment = Payment::findOrFail($this->deletingId);
        $payment->delete();
        $this->cancelDelete();
        $this->dispatch('notify', message: 'Payment deleted.', type: 'success');
    }

    public function render()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $payments = Payment::with(['unit', 'creator'])
            ->when($this->search, fn ($q) => $q->where('payer_name', 'like', "%{$this->search}%"))
            ->when($this->filterUnit, fn ($q) => $q->where('unit_id', $this->filterUnit))
            ->when($this->filterMethod, fn ($q) => $q->where('payment_method', $this->filterMethod))
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate(15);

        return view('livewire.finance.manage-payments', [
            'payments' => $payments,
            'units' => Unit::orderBy('name')->get(),
        ])->layout('layouts.app', ['pageTitle' => 'Payments']);
    }
}
