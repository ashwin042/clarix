<?php

namespace App\Livewire\Superadmin;

use App\Livewire\Traits\WithDeleteConfirmation;
use App\Models\Organization;
use App\Services\OrganizationTeardown;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The platform's list of agencies, with create and edit.
 *
 * Route middleware already restricts this to superadmins; the render guard is
 * a second lock on the same door, because a Livewire component can be reached
 * by its own update endpoint as well as by the route that first rendered it.
 */
class ManageOrganizations extends Component
{
    use WithPagination, WithDeleteConfirmation;

    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $slug = '';

    public string $contact_number = '';

    public string $email = '';

    public string $address = '';

    /**
     * Set once the slug has been typed into directly, after which it stops
     * following the name. Without this, editing the name of an established
     * organization would silently rewrite a slug that its people may already
     * be using in URLs.
     */
    public bool $slugTouched = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedName(string $value): void
    {
        if (! $this->slugTouched && ! $this->editingId) {
            $this->slug = Str::slug($value);
        }
    }

    public function updatedSlug(string $value): void
    {
        $this->slugTouched = true;
        $this->slug = Str::slug($value);
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $organization = Organization::findOrFail($id);

        $this->editingId         = $organization->id;
        $this->name              = $organization->name;
        $this->slug              = $organization->slug;
        $this->contact_number    = (string) $organization->contact_number;
        $this->email             = (string) $organization->email;
        $this->address           = (string) $organization->address;
        $this->slugTouched       = true;

        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorizeSuperadmin();

        $data = $this->validate([
            'name'              => ['required', 'string', 'max:255'],
            'slug'              => [
                'required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('organizations', 'slug')->ignore($this->editingId),
            ],
            'contact_number'    => ['nullable', 'string', 'max:50'],
            'email'             => ['nullable', 'email', 'max:255'],
            'address'           => ['nullable', 'string', 'max:500'],
        ], [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and single hyphens.',
        ]);

        if ($this->editingId) {
            Organization::findOrFail($this->editingId)->update($data);
            $this->showModal = false;
            $this->dispatch('notify', message: 'Organization updated.', type: 'success');

            return;
        }

        $organization = Organization::create($data);

        $this->showModal = false;
        $this->resetForm();

        // A new agency with nobody in it cannot be used, so the flow goes
        // straight on to creating its first admin rather than dropping the
        // superadmin back on a list.
        $this->redirectRoute('superadmin.organizations.admin', ['organization' => $organization->slug], navigate: false);
    }

    /**
     * Delete an organization, but only while it still holds nothing.
     *
     * The foreign keys are ON DELETE RESTRICT, so the database would refuse
     * this anyway; checking first turns what would surface as a driver error
     * into a sentence naming what is in the way. Removing an agency that has
     * real data is deliberately not offered here.
     */
    public function confirmDelete(): void
    {
        $this->authorizeSuperadmin();

        $teardown     = app(OrganizationTeardown::class);
        $organization = Organization::findOrFail($this->deletingId);

        if ($blockers = $teardown->blockers($organization)) {
            $this->cancelDelete();
            $this->dispatch(
                'notify',
                message: "{$organization->name} still holds ".$teardown->describe($blockers).'. Empty it first, or retire it as a separate deliberate process.',
                type: 'error'
            );

            return;
        }

        // Nothing is in the way, so the defaults the platform provisioned for
        // this agency come off before the row itself does.
        $teardown->purgeProvisioned($organization);

        $organization->delete();
        $this->cancelDelete();
        $this->dispatch('notify', message: 'Organization deleted.', type: 'success');
    }

    protected function resetForm(): void
    {
        $this->reset([
            'name', 'slug', 'contact_number', 'email', 'address',
            'editingId', 'slugTouched',
        ]);

        $this->resetErrorBag();
    }

    protected function authorizeSuperadmin(): void
    {
        abort_unless(auth()->user()?->isSuperadmin(), 403);
    }

    public function render()
    {
        $this->authorizeSuperadmin();

        // Members and billing only. Unit and task counts used to appear here
        // and have been removed: the platform is not entitled to a measure of
        // what each agency is working on, and with those models now refusing
        // to answer a superadmin the counts would read zero regardless.
        $organizations = Organization::query()
            ->withCount('users')
            ->with('subscription')
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('slug', 'like', "%{$this->search}%");
            }))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.superadmin.manage-organizations', [
            'organizations'      => $organizations,
            'subscriptionTypes'  => Organization::SUBSCRIPTION_TYPES,
        ])->layout('layouts.superadmin', ['pageTitle' => 'Organizations']);
    }
}
