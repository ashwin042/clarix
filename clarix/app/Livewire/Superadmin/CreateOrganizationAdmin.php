<?php

namespace App\Livewire\Superadmin;

use App\Models\Organization;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

/**
 * Creates an administrator inside an agency, on that agency's behalf.
 *
 * This is the one place in the application where a record is deliberately
 * created for an organization other than the actor's own, and it is the case
 * the auto-fill cannot infer. A superadmin belongs to no organization, so
 * TenantContext would resolve null and User::create() would quietly produce an
 * admin owned by nobody — users.organization_id is nullable, so nothing would
 * even complain until that account logged in and found itself scoped to
 * nothing.
 *
 * Naming the organization through actingAsOrganization() is what closes that.
 * organization_id still never comes from the form; it comes from the route's
 * organization, resolved server-side, which is why a hand-crafted request
 * cannot aim this at a different agency.
 */
class CreateOrganizationAdmin extends Component
{
    public Organization $organization;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public function mount(Organization $organization): void
    {
        abort_unless(auth()->user()?->isSuperadmin(), 403);

        $this->organization = $organization;
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->isSuperadmin(), 403);

        $data = $this->validate([
            'name'     => ['required', 'string', 'max:255'],
            // Sign-in is by email across the whole platform, so uniqueness is
            // checked platform-wide rather than within the organization.
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $organizationId = $this->organization->id;

        TenantContext::actingAsOrganization($organizationId, function () use ($data) {
            User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => Hash::make($data['password']),
                'role'     => 'admin',
                'unit_id'  => null,
            ]);
        });

        $this->dispatch('notify', message: 'Administrator created.', type: 'success');

        $this->redirectRoute(
            'superadmin.organizations.show',
            ['organization' => $this->organization->slug],
            navigate: false
        );
    }

    public function render()
    {
        abort_unless(auth()->user()?->isSuperadmin(), 403);

        return view('livewire.superadmin.create-organization-admin', [
            // Counted for the copy on the page: the first admin reads
            // differently from the fourth.
            'existingAdmins' => User::withoutGlobalScopes()
                ->where('organization_id', $this->organization->id)
                ->where('role', 'admin')
                ->count(),
        ])->layout('layouts.superadmin', [
            'pageTitle' => 'Add administrator - '.$this->organization->name,
        ]);
    }
}
