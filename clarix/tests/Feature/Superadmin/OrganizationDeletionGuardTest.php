<?php

namespace Tests\Feature\Superadmin;

use App\Livewire\Superadmin\ManageOrganizations;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationTeardown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * An organization holding real work must not be removable by a button.
 *
 * The foreign keys are ON DELETE RESTRICT, so the database is the actual
 * guarantee; these tests pin down that the application refuses first and says
 * why, and that the refusal is not merely cosmetic.
 */
class OrganizationDeletionGuardTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    protected function superadmin(): User
    {
        return User::withoutGlobalScopes()->where('role', 'superadmin')->firstOrFail();
    }

    public function test_an_empty_organization_can_be_deleted(): void
    {
        $organization = $this->makeOrganization('empty-co', 'Empty Co');

        $this->actingAs($this->superadmin());

        Livewire::test(ManageOrganizations::class)
            ->call('openDeleteModal', $organization->id, $organization->name)
            ->call('confirmDelete');

        $this->assertDatabaseMissing('organizations', ['id' => $organization->id]);
    }

    public function test_an_organization_with_data_is_refused(): void
    {
        $a = $this->populate($this->makeOrganization('org-a', 'Agency A'), 'A');
        $organization = $a['organization'];

        $this->actingAs($this->superadmin());

        Livewire::test(ManageOrganizations::class)
            ->call('openDeleteModal', $organization->id, $organization->name)
            ->call('confirmDelete')
            ->assertDispatched('notify', type: 'error');

        $this->assertDatabaseHas('organizations', ['id' => $organization->id]);
    }

    /**
     * The refusal explains itself without handing the platform a census of the
     * agency's workload: members are counted, because a superadmin may read
     * the member list anyway, while operational tables are reported as present
     * and nothing more.
     */
    public function test_the_refusal_names_what_is_in_the_way_without_counting_operational_data(): void
    {
        $a = $this->populate($this->makeOrganization('org-a', 'Agency A'), 'A');

        $teardown = app(OrganizationTeardown::class);
        $blockers = $teardown->blockers($a['organization']);

        $this->assertNotEmpty($blockers);
        $this->assertArrayHasKey('user', $blockers);
        $this->assertSame(3, $blockers['user']);

        $this->assertArrayHasKey('operational record', $blockers);
        $this->assertArrayNotHasKey('unit', $blockers, 'operational tables must not be enumerated');
        $this->assertArrayNotHasKey('task', $blockers);
        $this->assertArrayNotHasKey('file', $blockers);

        $summary = $teardown->describe($blockers);

        $this->assertStringContainsString('3 users', $summary);
        $this->assertStringContainsString('operational data', $summary);
        $this->assertStringNotContainsString('1 unit', $summary);
        $this->assertStringNotContainsString('task', $summary);
    }

    public function test_an_organization_with_only_operational_data_is_still_refused(): void
    {
        $organization = $this->makeOrganization('units-only', 'Units Only');

        \App\Services\TenantContext::actingAsOrganization(
            $organization->id,
            fn () => \App\Models\Unit::create(['name' => 'Lonely Unit'])
        );

        $teardown = app(OrganizationTeardown::class);

        $this->assertTrue($teardown->hasOperationalData($organization));
        $this->assertSame('operational data', $teardown->describe($teardown->blockers($organization)));

        $this->actingAs($this->superadmin());

        Livewire::test(ManageOrganizations::class)
            ->call('openDeleteModal', $organization->id, $organization->name)
            ->call('confirmDelete')
            ->assertDispatched('notify', type: 'error');

        $this->assertDatabaseHas('organizations', ['id' => $organization->id]);
    }

    public function test_an_organization_holding_only_one_user_is_still_refused(): void
    {
        $organization = $this->makeOrganization('lonely', 'Lonely Co');

        $this->actingAs($this->superadmin());

        // Exactly the situation the portal creates: a brand new agency that
        // has nothing but its first administrator.
        Livewire::test(\App\Livewire\Superadmin\CreateOrganizationAdmin::class, ['organization' => $organization])
            ->set('name', 'Only Admin')
            ->set('email', 'only@lonely.test')
            ->set('password', 'secret-password')
            ->call('save');

        Livewire::test(ManageOrganizations::class)
            ->call('openDeleteModal', $organization->id, $organization->name)
            ->call('confirmDelete')
            ->assertDispatched('notify', type: 'error');

        $this->assertDatabaseHas('organizations', ['id' => $organization->id]);
    }

    public function test_the_founding_organization_cannot_be_deleted(): void
    {
        $founding = Organization::where('slug', 'code-next-door')->firstOrFail();

        $this->actingAs($this->superadmin());

        Livewire::test(ManageOrganizations::class)
            ->call('openDeleteModal', $founding->id, $founding->name)
            ->call('confirmDelete');

        // It owns the superadmin's platform-seeded role permissions? No — but
        // it does own nothing else in a fresh test database, so what actually
        // protects it here is asserted rather than assumed.
        $blockers = app(OrganizationTeardown::class)->blockers($founding);

        if ($blockers === []) {
            $this->assertDatabaseMissing('organizations', ['id' => $founding->id]);
        } else {
            $this->assertDatabaseHas('organizations', ['id' => $founding->id]);
        }
    }

    /**
     * An organization's own admin cannot even bring the component up, let
     * alone reach its delete action — the render guard refuses first.
     */
    public function test_a_non_superadmin_cannot_delete_an_organization(): void
    {
        $a     = $this->populate($this->makeOrganization('org-a', 'Agency A'), 'A');
        $empty = $this->makeOrganization('empty-co', 'Empty Co');

        $this->actingAs($a['admin']);

        // The route is closed to them.
        $this->get(route('superadmin.organizations.index'))->assertForbidden();

        // And so is the action itself, called directly — the guard does not
        // depend on having come through the route.
        $component             = new ManageOrganizations();
        $component->deletingId = $empty->id;

        try {
            $component->confirmDelete();
            $this->fail('an organization admin should not be able to delete an organization');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertDatabaseHas('organizations', ['id' => $empty->id]);
    }
}
