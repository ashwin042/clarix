<?php

namespace App\Livewire\Admin;

use App\Rules\TenantExists;
use App\Livewire\Traits\WithDeleteConfirmation;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class ManageUsers extends Component
{
    use WithPagination;

    /*
     * The trait's openDeleteModal() is a plain state setter shared by ten
     * screens, so the check cannot go in it. Aliased here so this screen can
     * ask the policy first and then hand off.
     */
    use WithDeleteConfirmation {
        openDeleteModal as protected armDeleteModal;
    }

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

    /**
     * The roles this actor may file somebody under.
     *
     * Creating an admin is the one act on this screen that hands over the
     * agency itself, so it stays with the admin. The authorization panel is
     * admin-only, which means a granted role cannot widen its own permissions
     * — but minting an admin account and signing into it arrives at the same
     * place, with a password the creator chose. So the ceiling is enforced
     * here and re-checked in save(), not drawn only in the markup.
     *
     * @return array<string, string> role => label
     */
    public function assignableRoles(): array
    {
        $roles = [
            'supervisor' => 'Supervisor',
            'pm'         => 'Project Manager',
            'hr'         => 'HR',
            'writer'     => 'Writer',
        ];

        return auth()->user()->isAdmin()
            ? ['admin' => 'Admin'] + $roles
            : $roles;
    }

    public function openCreate(): void
    {
        // The affordance is drawn behind this same permission, but a Livewire
        // action arrives on its own endpoint — the route middleware never sees
        // it, so a hidden button is not a closed door.
        abort_unless(auth()->user()->hasPermission('users.create'), 403);

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
        // Same reasoning as openCreate(): the button is drawn behind this
        // policy, but a Livewire action arrives on its own endpoint.
        abort_unless(Gate::allows('update', $user), 403);

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

        // The permission the panel actually names for what is about to happen.
        // Checked here rather than only in render(), because a Livewire action
        // arrives on its own endpoint: the route middleware does not see it,
        // and render()'s guard runs after the write has already landed.
        //
        // An edit goes through the policy rather than the bare permission,
        // because who is being edited matters: editingId is a public property
        // and can be set without ever calling openEdit().
        if ($this->editingId) {
            abort_unless(Gate::allows('update', User::findOrFail($this->editingId)), 403);
        } else {
            abort_unless($authUser->hasPermission('users.create'), 403);
        }

        // Backend enforcement: PM can only create PMs in their own unit
        if ($authUser->isPm()) {
            $this->role    = 'pm';
            $this->unit_id = (string) $authUser->unit_id;
        }

        $email = $this->email_username . '@clarix.com';

        $rules = [
            'name'           => 'required|string|max:255',
            'email_username' => ['required', 'regex:/^[a-zA-Z0-9._%+\-]+$/i', 'max:100'],
            // Not a flat list of every role: a non-admin creator must not be
            // able to post 'admin' past a dropdown that never offered it.
            'role'           => ['required', Rule::in(array_keys($this->assignableRoles()))],
        ];

        if (!$this->editingId) {
            $rules['password'] = 'required|min:8';
        }

        if ($this->role === 'pm') {
            $rules['unit_id'] = ['required', TenantExists::in('units')];
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

    /**
     * Deletion is admin-only structurally — there is no users.delete to grant,
     * and administers() answers false for everyone else. Asked here so the
     * modal never opens for somebody confirmDelete() would refuse.
     */
    public function openDeleteModal(int $id, string $name = ''): void
    {
        $user = User::findOrFail($id);

        // Self-deletion is let through to confirmDelete(), which answers it
        // with a sentence rather than a 403. Refusing it here would turn a
        // deliberate piece of wording into an error page. Mirrors the order
        // confirmDelete() itself uses.
        if ($user->id !== auth()->id()) {
            abort_unless(Gate::allows('delete', $user), 403);
        }

        $this->armDeleteModal($id, $name);
    }

    public function confirmDelete(): void
    {
        $user = User::findOrFail($this->deletingId);

        // Deleting yourself is a mistake rather than an intrusion, so it keeps
        // its sentence instead of becoming a 403. Checked before the policy,
        // which refuses the same case without being able to explain itself.
        if ($user->id === auth()->id()) {
            $this->dispatch('notify', message: 'You cannot delete your own account.', type: 'error');
            $this->cancelDelete();
            return;
        }

        // Admin of this agency, structurally. No permission grant reaches it.
        abort_unless(Gate::allows('delete', $user), 403);

        $user->delete();
        $this->cancelDelete();
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

        return view('livewire.admin.manage-users', [
            'users'           => $users,
            'units'           => $units,
            'pmUnit'          => $pmUnit,
            'assignableRoles' => $this->assignableRoles(),
        ])
            ->layout('layouts.app', ['pageTitle' => 'Manage Users']);
    }
}
