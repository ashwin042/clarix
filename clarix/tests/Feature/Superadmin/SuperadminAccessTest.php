<?php

namespace Tests\Feature\Superadmin;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * The portal is gated on the superadmin role specifically, not on seniority.
 * Being the top of an agency confers nothing at the platform level, so an
 * organization's own admin is refused exactly like any other role.
 */
class SuperadminAccessTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $a;

    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = $this->makeOrganization('org-a', 'Agency A');
        $this->a            = $this->populate($this->organization, 'A');
    }

    protected function superadmin(): User
    {
        return User::withoutGlobalScopes()->where('role', 'superadmin')->firstOrFail();
    }

    /**
     * @return list<string>
     */
    protected function portalRoutes(): array
    {
        return [
            route('superadmin.organizations.index'),
            route('superadmin.organizations.show', $this->organization),
            route('superadmin.organizations.admin', $this->organization),
        ];
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        foreach ($this->portalRoutes() as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_an_organization_admin_is_forbidden(): void
    {
        $this->actingAs($this->a['admin']);

        foreach ($this->portalRoutes() as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_a_pm_and_a_writer_are_forbidden(): void
    {
        foreach ([$this->a['pm'], $this->a['writer']] as $user) {
            $this->actingAs($user);

            foreach ($this->portalRoutes() as $url) {
                $this->get($url)->assertForbidden();
            }
        }
    }

    public function test_a_superadmin_can_reach_every_portal_route(): void
    {
        $this->actingAs($this->superadmin());

        foreach ($this->portalRoutes() as $url) {
            $this->get($url)->assertSuccessful();
        }
    }

    public function test_the_portal_root_redirects_to_the_organization_list(): void
    {
        $this->actingAs($this->superadmin());

        $this->get('/superadmin')->assertRedirect('/superadmin/organizations');
    }

    /**
     * The role did not exist when DashboardController was written, and its
     * match had no arm for it — a superadmin signing in used to hit an
     * unhandled match rather than a page.
     */
    public function test_a_superadmin_landing_on_the_dashboard_is_sent_to_the_portal(): void
    {
        $this->actingAs($this->superadmin());

        $this->get(route('dashboard'))
            ->assertRedirect(route('superadmin.organizations.index'));
    }

    public function test_an_ordinary_admin_still_gets_their_own_dashboard(): void
    {
        $this->actingAs($this->a['admin']);

        $this->get(route('dashboard'))->assertSuccessful();
    }
}
