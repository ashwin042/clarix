<?php

namespace App\Livewire\Admin;

use App\Livewire\Traits\WithDeleteConfirmation;
use App\Models\Unit;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class ManageUnits extends Component
{
    use WithPagination, WithDeleteConfirmation;

    public string $search = '';
    public bool $showModal = false;
    public ?int $editingId = null;

    #[Rule('required|string|max:255')]
    public string $name = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->reset(['name', 'editingId']);
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function openEdit(Unit $unit): void
    {
        $this->editingId = $unit->id;
        $this->name = $unit->name;
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255|unique:units,name' . ($this->editingId ? ",{$this->editingId}" : ''),
        ]);

        if ($this->editingId) {
            Unit::findOrFail($this->editingId)->update(['name' => $this->name]);
            $this->dispatch('notify', message: 'Unit updated.', type: 'success');
        } else {
            Unit::create(['name' => $this->name]);
            $this->dispatch('notify', message: 'Unit created.', type: 'success');
        }

        $this->showModal = false;
        $this->reset(['name', 'editingId']);
    }

    public function confirmDelete(): void
    {
        $unit = Unit::findOrFail($this->deletingId);
        $unit->delete();
        $this->cancelDelete();
        $this->dispatch('notify', message: 'Unit deleted.', type: 'success');
    }

    public function render()
    {
        abort_unless(auth()->user()->hasPermission('units.view'), 403);

        $units = Unit::withCount(['users', 'tasks'])
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(15);

        return view('livewire.admin.manage-units', compact('units'))
            ->layout('layouts.app', ['pageTitle' => 'Manage Units']);
    }
}
