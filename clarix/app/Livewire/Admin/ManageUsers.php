<?php

namespace App\Livewire\Admin;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\WithPagination;

class ManageUsers extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterRole = '';
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $email_username = '';
    public string $password = '';
    public string $role = 'writer';
    public string $unit_id = '';

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingFilterRole(): void { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->reset(['name', 'email_username', 'password', 'role', 'unit_id', 'editingId']);
        // PM can only create PMs in their own unit
        if (auth()->user()->isPm()) {
            $this->role    = 'pm';
            $this->unit_id = (string) auth()->user()->unit_id;
        } else {
            $this->role = 'writer';
        }
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function openEdit(User $user): void
    {
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email_username = \Illuminate\Support\Str::before($user->email, '@');
        $this->role = $user->role;
        $this->unit_id = (string) ($user->unit_id ?? '');
        $this->password = '';
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save(): void
    {
        $authUser = auth()->user();

        // Backend enforcement: PM can only create PMs in their own unit
        if ($authUser->isPm()) {
            abort_unless($authUser->hasPermission('users.create'), 403);
            $this->role    = 'pm';
            $this->unit_id = (string) $authUser->unit_id;
        }

        $email = $this->email_username . '@clarix.com';

        $rules = [
            'name'           => 'required|string|max:255',
            'email_username' => ['required', 'regex:/^[a-zA-Z0-9._%+\-]+$/i', 'max:100'],
            'role'           => 'required|in:admin,pm,writer',
        ];

        if (!$this->editingId) {
            $rules['password'] = 'required|min:8';
        }

        if ($this->role === 'pm') {
            $rules['unit_id'] = 'required|exists:units,id';
        }

        $this->validate($rules);

        // Check full-email uniqueness manually
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
            'role'    => $this->role,
            'unit_id' => $this->role === 'pm' ? $this->unit_id : null,
        ];

        if ($this->password) {
            $data['password'] = Hash::make($this->password);
        }

        if ($this->editingId) {
            User::findOrFail($this->editingId)->update($data);
            $this->dispatch('notify', message: 'User updated.', type: 'success');
        } else {
            User::create($data);
            $this->dispatch('notify', message: 'User created.', type: 'success');
        }

        $this->showModal = false;
        $this->reset(['name', 'email_username', 'password', 'role', 'unit_id', 'editingId']);
    }

    public function delete(User $user): void
    {
        if ($user->id === auth()->id()) {
            $this->dispatch('notify', message: 'You cannot delete your own account.', type: 'error');
            return;
        }
        $user->delete();
        $this->dispatch('notify', message: 'User deleted.', type: 'success');
    }

    public function render()
    {
        abort_unless(auth()->user()->hasPermission('users.view') || auth()->user()->hasPermission('users.create'), 403);

        $authUser = auth()->user();

        $users = User::with('unit')
            ->where('id', '!=', auth()->id())
            ->when($authUser->isPm(), fn ($q) => $q->where('unit_id', $authUser->unit_id))
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%");
            }))
            ->when($this->filterRole, fn ($q) => $q->where('role', $this->filterRole))
            ->latest()
            ->paginate(15);

        $units = Unit::orderBy('name')->get();
        $pmUnit = $authUser->isPm() ? $authUser->unit : null;

        return view('livewire.admin.manage-users', compact('users', 'units', 'pmUnit'))
            ->layout('layouts.app', ['pageTitle' => 'Manage Users']);
    }
}
