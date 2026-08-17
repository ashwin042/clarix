<?php

namespace Tests\Feature\Superadmin;

use App\Livewire\Superadmin\CreateOrganizationAdmin;
use App\Livewire\Superadmin\ManageOrganizations;
use App\Models\Organization;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * Creating an agency and giving it its first administrator, which is the one
 * flow in the application that writes a record into an organization other than
 * the actor's own.
 */
class OrganizationManagementTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    protected function superadmin(): User
    {
        return User::withoutGlobalScopes()->where('role', 'superadmin')->firstOrFail();
    }

    public function test_a_superadmin_sees_every_organization_with_its_counts(): void
    {
        $a = $this->populate($this->makeOrganization('org-a', 'Agency A'), 'A');
        $b = $this->populate($this->makeOrganization('org-b', 'Agency B'), 'B');

        $this->actingAs($this->superadmin());

        Livewire::test(ManageOrganizations::class)
            ->assertSee('Agency A')
            ->assertSee('Agency B')
            // Plus the founding organization the phase 1 migration created.
            ->assertSee('Code Next Door');
    }

    public function test_creating_an_organization_stores_the_record_and_moves_on_to_its_first_admin(): void
    {
        $this->actingAs($this->superadmin());

        Livewire::test(ManageOrganizations::class)
            ->call('openCreate')
            ->set('name', 'Northwind Studio')
            ->set('contact_number', '5551234')
            ->set('email', 'hello@northwind.test')
            ->set('address', '1 Example Way')
            ->call('save')
            ->assertRedirect(route('superadmin.organizations.admin', ['organization' => 'northwind-studio']));

        // No plan is chosen here any more. A new organization starts on the
        // fallback tier until a superadmin sets up its subscription in
        // Organization Detail, which is the only thing that decides a plan.
        $this->assertDatabaseHas('organizations', [
            'name'              => 'Northwind Studio',
            'slug'              => 'northwind-studio',
            'subscription_type' => 'base',
            'contact_number'    => '5551234',
            'email'             => 'hello@northwind.test',
        ]);
    }

    public function test_the_slug_is_generated_from_the_name_but_can_be_overridden(): void
    {
        $this->actingAs($this->superadmin());

        Livewire::test(ManageOrganizations::class)
            ->call('openCreate')
            ->set('name', 'Acme & Sons Ltd')
            ->assertSet('slug', 'acme-sons-ltd')
            ->set('slug', 'acme')
            // Once touched, the slug stops following the name.
            ->set('name', 'Something Else Entirely')
            ->assertSet('slug', 'acme')
            ->call('save');

        $this->assertDatabaseHas('organizations', ['name' => 'Something Else Entirely', 'slug' => 'acme']);
    }

    public function test_a_duplicate_slug_is_rejected(): void
    {
        $this->makeOrganization('taken', 'Taken');

        $this->actingAs($this->superadmin());

        Livewire::test(ManageOrganizations::class)
            ->call('openCreate')
            ->set('name', 'Another')
            ->set('slug', 'taken')
            ->call('save')
            ->assertHasErrors(['slug']);

        $this->assertSame(1, Organization::where('slug', 'taken')->count());
    }

    /**
     * Typed input is normalised rather than refused, so the validation rule
     * behind it is a backstop that a person using the form never meets.
     */
    public function test_an_unsafe_slug_is_normalised_on_input(): void
    {
        $this->actingAs($this->superadmin());

        Livewire::test(ManageOrganizations::class)
            ->call('openCreate')
            ->set('name', 'Another')
            ->set('slug', 'Not A Slug!')
            ->assertSet('slug', 'not-a-slug')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('organizations', ['slug' => 'not-a-slug']);
    }

    public function test_the_slug_rule_still_refuses_an_unsafe_value_that_bypasses_the_input_hook(): void
    {
        $this->actingAs($this->superadmin());

        $validator = validator(
            ['slug' => 'Not A Slug!'],
            ['slug' => ['required', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/']]
        );

        $this->assertTrue($validator->fails());
    }

    public function test_an_organization_can_be_edited(): void
    {
        $organization = $this->makeOrganization('editable', 'Editable');

        $this->actingAs($this->superadmin());

        Livewire::test(ManageOrganizations::class)
            ->call('openEdit', $organization->id)
            ->assertSet('name', 'Editable')
            ->set('name', 'Renamed')
            ->call('save')
            ->assertHasNoErrors();

        // Editing an organization leaves its plan alone: this screen no
        // longer writes the label, so it stays whatever the subscription says.
        $this->assertDatabaseHas('organizations', [
            'id'                => $organization->id,
            'name'              => 'Renamed',
            'slug'              => 'editable',
        ]);
    }

    /**
     * The heart of phase 3: a superadmin has no organization of their own, so
     * an unqualified User::create() here would produce an admin owned by
     * nobody. The target organization has to be named explicitly.
     */
    public function test_the_first_admin_is_created_inside_the_target_organization(): void
    {
        $organization = $this->makeOrganization('northwind', 'Northwind');

        $this->actingAs($this->superadmin());

        Livewire::test(CreateOrganizationAdmin::class, ['organization' => $organization])
            ->set('name', 'Dana Admin')
            ->set('email', 'dana@northwind.test')
            ->set('password', 'secret-password')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('superadmin.organizations.show', ['organization' => 'northwind']));

        $created = DB::table('users')->where('email', 'dana@northwind.test')->first();

        $this->assertNotNull($created);
        $this->assertSame('admin', $created->role);
        $this->assertSame(
            $organization->id,
            (int) $created->organization_id,
            'the new admin must belong to the organization being administered'
        );
        $this->assertNotNull($created->organization_id, 'and must never be left unowned');
        $this->assertNull($created->unit_id);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('secret-password', $created->password));
    }

    public function test_the_superadmins_own_lack_of_an_organization_does_not_leak_into_the_new_admin(): void
    {
        $organization = $this->makeOrganization('northwind', 'Northwind');

        $this->actingAs($this->superadmin());
        $this->assertNull($this->superadmin()->organization_id);

        Livewire::test(CreateOrganizationAdmin::class, ['organization' => $organization])
            ->set('name', 'Dana Admin')
            ->set('email', 'dana@northwind.test')
            ->set('password', 'secret-password')
            ->call('save');

        $this->assertSame(
            0,
            DB::table('users')->whereNull('organization_id')->where('role', 'admin')->count(),
            'no admin should ever be created without an organization'
        );
    }

    public function test_creating_an_admin_does_not_disturb_the_superadmins_own_scope(): void
    {
        $organization = $this->makeOrganization('northwind', 'Northwind');
        $a            = $this->populate($this->makeOrganization('org-a', 'Agency A'), 'A');

        $this->actingAs($this->superadmin());

        Livewire::test(CreateOrganizationAdmin::class, ['organization' => $organization])
            ->set('name', 'Dana Admin')
            ->set('email', 'dana@northwind.test')
            ->set('password', 'secret-password')
            ->call('save');

        // The named organization must not have stuck: the superadmin should be
        // unconfined again, seeing every agency's members and none of their
        // work.
        $this->assertNull(TenantContext::organizationId(), 'the superadmin is unconfined again');
        $this->assertNotNull(User::find($a['admin']->id), 'agency A\'s members are visible again');
        $this->assertSame(0, Unit::count(), 'and its operational data still is not');
        $this->assertNull(Task::find($a['task']->id));
    }

    public function test_the_new_admin_only_sees_their_own_organization_end_to_end(): void
    {
        $organization = $this->makeOrganization('northwind', 'Northwind');
        $other        = $this->populate($this->makeOrganization('org-a', 'Agency A'), 'A');

        // Create the admin exactly as the portal does.
        $this->actingAs($this->superadmin());
        Livewire::test(CreateOrganizationAdmin::class, ['organization' => $organization])
            ->set('name', 'Dana Admin')
            ->set('email', 'dana@northwind.test')
            ->set('password', 'secret-password')
            ->call('save');

        // Give the new organization something of its own.
        $newAdmin = User::withoutGlobalScopes()->where('email', 'dana@northwind.test')->firstOrFail();
        $this->actingAs($newAdmin);

        $ownUnit = Unit::create(['name' => 'Northwind Unit']);
        $this->assertSame($organization->id, $ownUnit->organization_id);

        // ... and it sees that, and nothing else.
        $this->assertSame(1, Unit::count());
        $this->assertNull(Unit::find($other['unit']->id));
        $this->assertNull(Task::find($other['task']->id));
        $this->assertSame(0, Task::count());
        $this->assertSame(1, User::count(), 'only itself, so far');

        // And it is refused at the platform portal.
        $this->get(route('superadmin.organizations.index'))->assertForbidden();
    }

    public function test_the_new_admin_can_actually_sign_in(): void
    {
        $organization = $this->makeOrganization('northwind', 'Northwind');

        $this->actingAs($this->superadmin());
        Livewire::test(CreateOrganizationAdmin::class, ['organization' => $organization])
            ->set('name', 'Dana Admin')
            ->set('email', 'dana@northwind.test')
            ->set('password', 'secret-password')
            ->call('save');

        auth()->logout();

        // A real login request carries no ambient organization — only the test
        // harness sets one, so that fixtures built before anyone signs in have
        // an owner. Clearing it here reproduces the actual conditions of the
        // login screen, where the users table must be searchable across every
        // agency in order to find whoever is signing in.
        TenantContext::useOrganization(null);

        // Sign in through the real Volt login component, the same way the
        // existing authentication tests do.
        Volt::test('pages.auth.login')
            ->set('form.email', 'dana@northwind.test')
            ->set('form.password', 'secret-password')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticated();
        $this->assertSame($organization->id, auth()->user()->organization_id);
    }

    public function test_an_email_already_used_anywhere_on_the_platform_is_rejected(): void
    {
        $organization = $this->makeOrganization('northwind', 'Northwind');
        $a            = $this->populate($this->makeOrganization('org-a', 'Agency A'), 'A');

        $this->actingAs($this->superadmin());

        Livewire::test(CreateOrganizationAdmin::class, ['organization' => $organization])
            ->set('name', 'Clash')
            ->set('email', $a['admin']->email)
            ->set('password', 'secret-password')
            ->call('save')
            ->assertHasErrors(['email']);
    }
}
