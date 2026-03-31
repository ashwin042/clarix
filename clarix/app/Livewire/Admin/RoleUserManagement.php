<?php

namespace App\Livewire\Admin;

use App\Livewire\Traits\WithDeleteConfirmation;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class RoleUserManagement extends Component
{
    use WithPagination, WithDeleteConfirmation;

    public string $managedRole; // 'admin', 'pm', or 'writer'
    public string $search = '';
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $email_username = '';
    public string $password = '';
    public string $unit_id = '';

    public function mount(string $role): void
    {
        abort_unless(in_array($role, ['admin', 'pm', 'writer']), 404);
        abort_unless(auth()->user()->isAdmin(), 403);
        $this->managedRole = $role;
    }

    public function updatingSearch(): void { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->reset(['name', 'email_username', 'password', 'unit_id', 'editingId']);
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function openEdit(User $user): void
    {
        if ($user->role !== $this->managedRole) {
            abort(403);
        }
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email_username = Str::before($user->email, '@');
        $this->unit_id = (string) ($user->unit_id ?? '');
        $this->password = '';
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $email = $this->email_username . '@clarix.com';

        $rules = [
            'name'           => 'required|string|max:255',
            'email_username' => ['required', 'regex:/^[a-zA-Z0-9._%+\-]+$/i', 'max:100'],
        ];

        if (!$this->editingId) {
            $rules['password'] = 'required|min:8';
        }

        if ($this->managedRole === 'pm') {
            $rules['unit_id'] = 'required|exists:units,id';
        }

        $this->validate($rules);

        $duplicate = User::where('email', $email)
            ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
            ->exists();

        if ($duplicate) {
            $this->addError('email_username', 'This email address is already taken.');
            return;
        }

        $data = [
            'name'    => $this->name,
            'email'   => $email,
            'role'    => $this->managedRole,
            'unit_id' => $this->managedRole === 'pm' ? $this->unit_id : null,
        ];

        if ($this->password) {
            $data['password'] = Hash::make($this->password);
        }

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);
            if ($user->role !== $this->managedRole) {
                abort(403);
            }
            $user->update($data);
            $this->dispatch('notify', message: ucfirst($this->managedRole) . ' updated.', type: 'success');
        } else {
            User::create($data);
            $this->dispatch('notify', message: ucfirst($this->managedRole) . ' created.', type: 'success');
        }

        $this->showModal = false;
        $this->reset(['name', 'email_username', 'password', 'unit_id', 'editingId']);
    }

    public function confirmDelete(): void
    {
        $user = User::findOrFail($this->deletingId);
        if ($user->id === auth()->id()) {
            $this->dispatch('notify', message: 'You cannot delete your own account.', type: 'error');
            $this->cancelDelete();
            return;
        }
        if ($user->role !== $this->managedRole) {
            abort(403);
        }
        $user->delete();
        $this->cancelDelete();
        $this->dispatch('notify', message: ucfirst($this->managedRole) . ' deleted.', type: 'success');
    }

    protected function roleLabel(): string
    {
        return match ($this->managedRole) {
            'admin'  => 'Admins',
            'pm'     => 'Project Managers',
            'writer' => 'Writers',
        };
    }

    protected function roleSingular(): string
    {
        return match ($this->managedRole) {
            'admin'  => 'Admin',
            'pm'     => 'Project Manager',
            'writer' => 'Writer',
        };
    }

    public function render()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $users = User::with('unit')
            ->where('role', $this->managedRole)
            ->where('id', '!=', auth()->id())
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%");
            }))
            ->latest()
            ->paginate(15);

        $units = $this->managedRole === 'pm' ? Unit::orderBy('name')->get() : collect();

        return view('livewire.admin.role-user-management', [
            'users'        => $users,
            'units'        => $units,
            'roleLabel'    => $this->roleLabel(),
            'roleSingular' => $this->roleSingular(),
        ])->layout('layouts.app', ['pageTitle' => 'Manage ' . $this->roleLabel()]);
    }
}
