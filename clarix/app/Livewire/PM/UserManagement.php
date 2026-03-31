<?php

namespace App\Livewire\PM;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\WithPagination;

class UserManagement extends Component
{
    use WithPagination;

    public string $search    = '';
    public bool   $showModal = false;
    public ?int   $editingId = null;

    public string $name     = '';
    public string $email_username = '';
    public string $password = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->reset(['name', 'email_username', 'password', 'editingId']);
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function openEdit(User $user): void
    {
        // PM can only edit users in their own unit
        abort_unless($user->unit_id === auth()->user()->unit_id, 403);

        $this->editingId = $user->id;
        $this->name           = $user->name;
        $this->email_username = \Illuminate\Support\Str::before($user->email, '@');
        $this->password  = '';
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save(): void
    {
        $authUser = auth()->user();

        $this->validate([
            'name'           => 'required|string|max:255',
            'email_username' => ['required', 'regex:/^[a-zA-Z0-9._%+\-]+$/i', 'max:100'],
            'password'       => $this->editingId ? 'nullable|min:8' : 'required|min:8',
        ]);

        // Backend enforcement: always force PM's own unit and role
        $email = $this->email_username . '@clarix.com';

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
            'role'    => 'pm',
            'unit_id' => $authUser->unit_id,
        ];

        if ($this->password) {
            $data['password'] = Hash::make($this->password);
        }

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);
            abort_unless($user->unit_id === $authUser->unit_id, 403);
            $user->update($data);
            $this->dispatch('notify', message: 'User updated.', type: 'success');
        } else {
            User::create($data);
            $this->dispatch('notify', message: 'User created.', type: 'success');
        }

        $this->showModal = false;
        $this->reset(['name', 'email_username', 'password', 'editingId']);
    }

    public function delete(User $user): void
    {
        abort_unless($user->unit_id === auth()->user()->unit_id && $user->id !== auth()->id(), 403);

        $user->delete();
        $this->dispatch('notify', message: 'User removed.', type: 'success');
    }

    public function render()
    {
        $authUser = auth()->user();

        $users = User::with('unit')
            ->where('unit_id', $authUser->unit_id)
            ->where('role', 'pm')
            ->where('id', '!=', $authUser->id)
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%");
            }))
            ->latest()
            ->paginate(15);

        return view('livewire.pm.user-management', [
            'users'  => $users,
            'pmUnit' => $authUser->unit,
        ])->layout('layouts.app', ['pageTitle' => 'My Team']);
    }
}
