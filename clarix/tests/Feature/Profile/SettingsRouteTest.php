<?php

namespace Tests\Feature\Profile;

use App\Services\PermissionService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * The account forms moved off /profile to make room for the profile overview.
 *
 * Only the move is pinned here — what the forms themselves do is
 * Tests\Feature\SettingsTest's business.
 */
class SettingsRouteTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        PermissionService::flushAll();

        $this->org = $this->populate($this->makeOrganization('set-a', 'Agency A'), 'A');
    }

    public function test_the_account_forms_live_at_settings(): void
    {
        $this->actingAs($this->org['pm'])
            ->get('/settings')
            ->assertOk()
            ->assertSee('Danger Zone');
    }

    public function test_the_profile_page_is_not_the_account_forms(): void
    {
        $this->actingAs($this->org['pm'])
            ->get('/profile')
            ->assertOk()
            ->assertDontSee('Danger Zone');
    }

    public function test_the_profile_page_points_at_settings_for_editing(): void
    {
        $this->actingAs($this->org['pm'])
            ->get('/profile')
            ->assertOk()
            ->assertSee('Edit account settings');
    }
}
