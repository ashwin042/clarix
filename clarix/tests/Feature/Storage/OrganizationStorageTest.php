<?php

namespace Tests\Feature\Storage;

use App\Livewire\Admin\StorageUsage;
use App\Livewire\Superadmin\PlatformStorage;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Unit;
use App\Models\UnitStorageUsage;
use App\Models\User;
use App\Services\OrganizationStorage;
use App\Services\StorageUsageService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * Storage measured the way an agency buys it: one total for the organization,
 * against the allowance its plan carries.
 *
 * The per-unit rollups are still how the write path keeps count — nothing
 * about file paths or ownership changed — but the number anyone sees is the
 * organization's.
 */
class OrganizationStorageTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    protected const GB = 1073741824;

    /** @var array<string, mixed> */
    protected array $a;

    /** @var array<string, mixed> */
    protected array $b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        $this->a = $this->populate($this->makeOrganization('sto-a', 'Agency A'), 'A');
        $this->b = $this->populate($this->makeOrganization('sto-b', 'Agency B'), 'B');
    }

    protected function storage(): OrganizationStorage
    {
        return app(OrganizationStorage::class);
    }

    /**
     * A second unit inside an organization, holding some bytes.
     */
    protected function unitHolding(array $org, string $name, int $bytes): Unit
    {
        $unit = TenantContext::actingAsOrganization(
            $org['organization']->id,
            fn () => Unit::create(['name' => $name])
        );

        app(StorageUsageService::class)->set($unit->id, $bytes);

        return $unit;
    }

    protected function givePlan(array $org, string $plan): void
    {
        TenantContext::actingAsOrganization($org['organization']->id, function () use ($plan) {
            OrganizationSubscription::create([
                'plan'            => $plan,
                'price'           => 99.00,
                'billing_cycle'   => 'monthly',
                'started_at'      => now()->subMonth(),
                'next_renewal_at' => now()->addMonth(),
                'status'          => 'active',
            ]);
        });
    }

    // ── Aggregation across units ─────────────────────────────────────────────

    /**
     * The fixture agency already holds one 100-byte task file, tracked through
     * the ordinary upload path by TaskFileObserver. It is counted here rather
     * than subtracted out — a real file flowing into the organization total is
     * exactly what this is measuring.
     */
    public function test_usage_aggregates_every_unit_in_the_organization(): void
    {
        $baseline = $this->storage()->bytesFor($this->a['organization']->id);

        $this->assertSame(100, $baseline, 'the fixture task file is already counted');

        $this->unitHolding($this->a, 'Unit One', 500_000_000);
        $this->unitHolding($this->a, 'Unit Two', 300_000_000);
        $this->unitHolding($this->a, 'Unit Three', 200_000_000);

        $this->assertSame(
            $baseline + 1_000_000_000,
            $this->storage()->bytesFor($this->a['organization']->id),
            'one total across the fixture unit and all three new ones'
        );
    }

    public function test_an_organization_holding_nothing_reads_zero(): void
    {
        $empty = $this->makeOrganization('sto-empty', 'Empty Co');

        $this->assertSame(0, $this->storage()->bytesFor($empty->id));
    }

    public function test_one_organizations_usage_never_counts_anothers(): void
    {
        $baselineA = $this->storage()->bytesFor($this->a['organization']->id);
        $baselineB = $this->storage()->bytesFor($this->b['organization']->id);

        $this->unitHolding($this->a, 'A Unit', 900_000_000);
        $this->unitHolding($this->b, 'B Unit', 100_000_000);

        $this->assertSame($baselineA + 900_000_000, $this->storage()->bytesFor($this->a['organization']->id));
        $this->assertSame($baselineB + 100_000_000, $this->storage()->bytesFor($this->b['organization']->id));
    }

    // ── The cap comes from the plan ──────────────────────────────────────────

    public function test_the_cap_follows_the_subscription_plan(): void
    {
        $caps = config('storage.plan_caps_gb');

        foreach (['base', 'standard', 'pro'] as $plan) {
            $org = $this->makeOrganization("cap-{$plan}", ucfirst($plan).' Co');
            TenantContext::actingAsOrganization($org->id, function () use ($plan) {
                OrganizationSubscription::create([
                    'plan' => $plan, 'price' => 1, 'billing_cycle' => 'monthly',
                    'started_at' => now()->subMonth(), 'next_renewal_at' => now()->addMonth(),
                    'status' => 'active',
                ]);
            });

            $this->assertSame(
                (int) $caps[$plan],
                $this->storage()->capGbFor($org->id),
                "{$plan} plan cap"
            );
        }
    }

    public function test_a_plan_change_changes_the_cap(): void
    {
        $organizationId = $this->a['organization']->id;

        $this->givePlan($this->a, 'base');
        $this->assertSame((int) config('storage.plan_caps_gb.base'), $this->storage()->capGbFor($organizationId));

        // A newer subscription row supersedes the old one.
        $this->givePlan($this->a, 'pro');
        $this->assertSame((int) config('storage.plan_caps_gb.pro'), $this->storage()->capGbFor($organizationId));
    }

    /**
     * An agency whose plan cannot be determined gets the least generous
     * allowance, not the most.
     */
    public function test_no_subscription_falls_back_to_the_default_cap(): void
    {
        $organization = $this->makeOrganization('sto-plainless', 'No Plan Co');

        $this->assertSame(
            (int) config('storage.default_cap_gb'),
            $this->storage()->capGbFor($organization->id)
        );

        $this->assertSame(
            (int) config('storage.default_cap_gb'),
            $this->storage()->capGbForPlan('some-unknown-tier')
        );
    }

    public function test_the_summary_reports_percentage_against_the_plan_cap(): void
    {
        $this->givePlan($this->a, 'standard');
        $capGb = (int) config('storage.plan_caps_gb.standard');

        // Exactly half the allowance.
        $this->unitHolding($this->a, 'Half Unit', (int) ($capGb * self::GB / 2));

        $summary = $this->storage()->summaryFor($this->a['organization']->id);

        $this->assertSame($capGb, $summary['cap_gb']);
        $this->assertSame('standard', $summary['plan']);
        $this->assertEqualsWithDelta(50.0, $summary['percent'], 0.01);
    }

    /**
     * The per-unit cap column is no longer consulted; the plan decides.
     */
    public function test_a_unit_level_cap_no_longer_affects_the_allowance(): void
    {
        $this->givePlan($this->a, 'pro');

        $unit = $this->unitHolding($this->a, 'Capped Unit', 1_000);
        $unit->update(['storage_cap_gb' => 1]);

        $this->assertSame(
            (int) config('storage.plan_caps_gb.pro'),
            $this->storage()->summaryFor($this->a['organization']->id)['cap_gb']
        );
    }

    // ── The org admin's own view ─────────────────────────────────────────────

    public function test_an_org_admin_sees_their_own_organizations_total(): void
    {
        $this->givePlan($this->a, 'pro');

        $baseline = $this->storage()->bytesFor($this->a['organization']->id);

        $this->unitHolding($this->a, 'Busy Unit', 2_000_000_000);
        $this->unitHolding($this->b, 'Other Agency Unit', 9_000_000_000);

        $summary = Livewire::actingAs($this->a['admin'])
            ->test(StorageUsage::class)
            ->viewData('summary');

        $this->assertSame($baseline + 2_000_000_000, $summary['bytes'], "only agency A's bytes");
        $this->assertSame((int) config('storage.plan_caps_gb.pro'), $summary['cap_gb']);
    }

    // ── The per-unit breakdown, for the org's own admin ──────────────────────

    public function test_the_breakdown_lists_only_the_admins_own_units(): void
    {
        $mine  = $this->unitHolding($this->a, 'My Unit', 400_000_000);
        $mine2 = $this->unitHolding($this->a, 'My Other Unit', 100_000_000);
        $theirs = $this->unitHolding($this->b, 'Their Unit', 900_000_000);

        $names = Livewire::actingAs($this->a['admin'])
            ->test(StorageUsage::class)
            ->viewData('units')
            ->pluck('name');

        $this->assertTrue($names->contains('My Unit'));
        $this->assertTrue($names->contains('My Other Unit'));
        $this->assertFalse($names->contains('Their Unit'), "another agency's unit must never appear");

        // Nor may its bytes leak in through the totals.
        $this->assertFalse(
            Livewire::actingAs($this->a['admin'])
                ->test(StorageUsage::class)
                ->viewData('units')
                ->contains(fn (array $row) => $row['bytes'] === 900_000_000)
        );
    }

    public function test_the_breakdown_is_sorted_by_usage_descending(): void
    {
        $this->unitHolding($this->a, 'Small', 1_000_000);
        $this->unitHolding($this->a, 'Largest', 900_000_000);
        $this->unitHolding($this->a, 'Middling', 50_000_000);

        $bytes = Livewire::actingAs($this->a['admin'])
            ->test(StorageUsage::class)
            ->viewData('units')
            ->pluck('bytes')
            ->all();

        $sorted = $bytes;
        rsort($sorted);

        $this->assertSame($sorted, $bytes, 'largest first');
    }

    /**
     * The share is of the organization's own total, not of the plan cap. A
     * unit holding half the agency's data reads 50% however much headroom the
     * plan leaves.
     */
    public function test_the_share_is_of_the_org_total_not_the_cap(): void
    {
        $this->givePlan($this->a, 'pro');

        // The fixture's 100-byte file is negligible against these, but the
        // shares are computed from the real total either way.
        $this->unitHolding($this->a, 'Half A', 500_000_000);
        $this->unitHolding($this->a, 'Half B', 500_000_000);

        $component = Livewire::actingAs($this->a['admin'])->test(StorageUsage::class);

        $units   = $component->viewData('units');
        $summary = $component->viewData('summary');

        $this->assertEqualsWithDelta(50.0, $units->firstWhere('name', 'Half A')['share'], 0.01);
        $this->assertEqualsWithDelta(50.0, $units->firstWhere('name', 'Half B')['share'], 0.01);

        // Against a 100 GB cap the same unit is a fraction of a percent, which
        // is exactly why the two numbers must not be confused.
        $this->assertLessThan(2.0, $summary['percent']);
    }

    public function test_a_unit_holding_nothing_still_appears_at_zero(): void
    {
        $this->unitHolding($this->a, 'Busy', 100_000_000);
        $empty = TenantContext::actingAsOrganization(
            $this->a['organization']->id,
            fn () => Unit::create(['name' => 'Idle'])
        );

        $units = Livewire::actingAs($this->a['admin'])
            ->test(StorageUsage::class)
            ->viewData('units');

        $idle = $units->firstWhere('name', 'Idle');

        $this->assertNotNull($idle, 'a unit with no rollup row is still listed');
        $this->assertSame(0, $idle['bytes']);
        $this->assertSame(0.0, $idle['share']);
    }

    public function test_an_organization_holding_nothing_reports_no_shares(): void
    {
        $empty = $this->makeOrganization('sto-shares', 'Shares Co');

        $admin = TenantContext::actingAsOrganization(
            $empty->id,
            fn () => User::factory()->create(['role' => 'admin', 'email' => 'shares.admin@example.test'])
        );

        TenantContext::actingAsOrganization($empty->id, fn () => Unit::create(['name' => 'Nothing Here']));

        $units = Livewire::actingAs($admin)->test(StorageUsage::class)->viewData('units');

        // No division by zero, and no misleading 100%.
        $this->assertSame(0.0, $units->firstWhere('name', 'Nothing Here')['share']);
    }

    public function test_a_non_admin_cannot_open_the_storage_screen(): void
    {
        Livewire::actingAs($this->a['pm'])->test(StorageUsage::class)->assertForbidden();
        Livewire::actingAs($this->a['writer'])->test(StorageUsage::class)->assertForbidden();
    }

    // ── The platform view ────────────────────────────────────────────────────

    public function test_a_superadmin_sees_a_row_per_organization_fullest_first(): void
    {
        $this->givePlan($this->a, 'pro');
        $this->givePlan($this->b, 'base');

        $baseCap = (int) config('storage.plan_caps_gb.base');

        // Agency B holds less in absolute terms but far more of its allowance.
        $this->unitHolding($this->a, 'A Unit', 1_000_000_000);
        $this->unitHolding($this->b, 'B Unit', (int) ($baseCap * self::GB * 0.8));

        $rows = Livewire::actingAs(User::withoutGlobalScopes()->where('role', 'superadmin')->firstOrFail())
            ->test(PlatformStorage::class)
            ->viewData('rows');

        $names = array_map(fn (array $row) => $row['organization']->name, $rows);

        $this->assertSame(
            'Agency B',
            $names[0],
            'closest to its cap first, not the largest in absolute bytes'
        );

        $this->assertContains('Agency A', $names);
    }

    /**
     * The exception stops at the total. The per-unit rollups behind it stay
     * closed to the platform, as every other operational model does.
     */
    public function test_a_superadmin_still_reads_no_per_unit_storage_rows(): void
    {
        $this->unitHolding($this->a, 'A Unit', 5_000_000);

        $this->actingAs(User::withoutGlobalScopes()->where('role', 'superadmin')->firstOrFail());

        $this->assertSame(0, UnitStorageUsage::count());
        $this->assertTrue(UnitStorageUsage::all()->isEmpty());
        $this->assertSame(0, (int) UnitStorageUsage::sum('bytes_used'));
        $this->assertSame(0, Unit::count(), 'and no unit names either');
    }

    /**
     * Adding the per-unit table to the org admin's screen must not have opened
     * anything on the platform's. Asserted against the view data itself rather
     * than the rendered page, so a row appearing under any key would be
     * caught.
     */
    public function test_the_platform_view_still_carries_no_per_unit_data(): void
    {
        $this->unitHolding($this->a, 'Distinctive Unit Name', 5_000_000);

        $component = Livewire::actingAs(User::withoutGlobalScopes()->where('role', 'superadmin')->firstOrFail())
            ->test(PlatformStorage::class);

        // Not merely empty — the key does not exist, so reading it errors.
        $this->assertThrows(
            fn () => $component->viewData('units'),
            \ErrorException::class
        );

        foreach ($component->viewData('rows') as $row) {
            $this->assertSame(
                ['organization', 'bytes', 'cap_gb', 'cap_bytes', 'percent', 'plan'],
                array_keys($row),
                'a platform row carries totals and nothing else'
            );
        }

        $component->assertDontSee('Distinctive Unit Name');
    }

    public function test_an_org_admin_cannot_open_the_platform_storage_view(): void
    {
        Livewire::actingAs($this->a['admin'])->test(PlatformStorage::class)->assertForbidden();

        $this->actingAs($this->a['admin'])->get(route('superadmin.storage'))->assertForbidden();
    }

    // ── Ownership still runs through the unit, not the path ─────────────────

    /**
     * The redesign changed how usage is totalled, not how files are stored.
     * A rollup is attributed to an organization through its unit, exactly as
     * before, and the organization appears nowhere in the object key.
     */
    public function test_a_rollup_is_owned_through_its_unit(): void
    {
        $unit = $this->unitHolding($this->a, 'Owned Unit', 1_234);

        $row = UnitStorageUsage::withoutGlobalScopes()->where('unit_id', $unit->id)->firstOrFail();

        $this->assertSame($this->a['organization']->id, (int) $row->organization_id);
        $this->assertSame($this->a['organization']->id, (int) $unit->refresh()->organization_id);
    }
}
