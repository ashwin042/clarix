<?php

namespace Tests\Feature\Storage;

use App\Livewire\Admin\StorageUsage;
use App\Models\Unit;
use App\Models\User;
use App\Services\StorageUsageService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StorageUsageAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function makeUnit(string $name, ?int $capGb = null): Unit
    {
        return Unit::create(['name' => $name, 'storage_cap_gb' => $capGb]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('admin.storage'))->assertRedirect(route('login'));
    }

    public function test_a_pm_cannot_reach_the_storage_page(): void
    {
        $pm = User::factory()->create(['role' => 'pm', 'unit_id' => $this->makeUnit('U')->id]);

        $this->actingAs($pm)->get(route('admin.storage'))->assertForbidden();
    }

    public function test_a_writer_cannot_reach_the_storage_page(): void
    {
        $writer = User::factory()->create(['role' => 'writer']);

        $this->actingAs($writer)->get(route('admin.storage'))->assertForbidden();
    }

    public function test_an_admin_can_reach_the_storage_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.storage'))->assertOk();
    }

    /**
     * The route group is the primary gate, but the component guards itself as
     * well so it cannot be rendered from anywhere tenant-facing.
     */
    public function test_the_component_refuses_to_render_for_a_non_admin(): void
    {
        $writer = User::factory()->create(['role' => 'writer']);

        Livewire::actingAs($writer)
            ->test(StorageUsage::class)
            ->assertForbidden();
    }

    public function test_the_storage_link_is_hidden_from_tenant_users(): void
    {
        $pm = User::factory()->create(['role' => 'pm', 'unit_id' => $this->makeUnit('U')->id]);

        $this->actingAs($pm)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.storage'));
    }

    /*
     * The four tests that stood here described the screen as it was: a table
     * of units, each measured against its own cap, with a search box. Storage
     * is now reported for the organization as a whole against the allowance
     * its plan carries, so those behaviours no longer exist to test. What they
     * were really protecting — that the figures shown are right — is covered
     * below and, in more depth, in OrganizationStorageTest.
     */

    public function test_the_screen_shows_the_organizations_combined_total(): void
    {
        $usage = app(StorageUsageService::class);
        $gb    = 1073741824;

        // Three units in the same organization; the screen reports one figure.
        $usage->set($this->makeUnit('Unit One')->id, 2 * $gb);
        $usage->set($this->makeUnit('Unit Two')->id, 3 * $gb);
        $usage->set($this->makeUnit('Unit Three')->id, 1 * $gb);

        $admin = User::factory()->create(['role' => 'admin']);

        $summary = Livewire::actingAs($admin)
            ->test(StorageUsage::class)
            ->viewData('summary');

        $this->assertSame(6 * $gb, $summary['bytes']);
    }

    public function test_the_allowance_shown_comes_from_the_plan_not_the_unit(): void
    {
        $gb = 1073741824;

        // A per-unit cap is set and must be ignored: the plan decides now.
        $unit = $this->makeUnit('Capped Unit', 40);
        app(StorageUsageService::class)->set($unit->id, 5 * $gb);

        $admin = User::factory()->create(['role' => 'admin']);

        $summary = Livewire::actingAs($admin)
            ->test(StorageUsage::class)
            ->viewData('summary');

        // The founding organization is seeded onto the pro plan by an earlier
        // migration, so that is the allowance — and emphatically not the 40 GB
        // set on the unit, which is no longer read at all.
        $this->assertNotSame(40, $summary['cap_gb'], "the unit's own cap is ignored");
        $this->assertSame(
            (int) config('storage.plan_caps_gb.pro'),
            $summary['cap_gb'],
            'the allowance comes from the plan'
        );
    }
}
