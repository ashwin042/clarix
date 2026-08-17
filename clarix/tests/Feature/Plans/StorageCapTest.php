<?php

namespace Tests\Feature\Plans;

use App\Models\OrganizationSubscription;
use App\Services\OrganizationStorage;
use App\Services\PlanFeatures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

class StorageCapTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    protected function storage(): OrganizationStorage
    {
        PlanFeatures::flush();

        return app(OrganizationStorage::class);
    }

    public function test_the_caps_are_five_fifty_and_one_hundred(): void
    {
        foreach (['base' => 5, 'standard' => 50, 'pro' => 100] as $plan => $expected) {
            $org = $this->makeOrganization('cap-'.$plan, ucfirst($plan));
            $this->subscribeOrganization($org, $plan);

            $this->assertSame($expected, $this->storage()->capGbFor($org->id), $plan.' cap');
        }
    }

    public function test_an_organization_with_no_subscription_gets_the_smallest_cap(): void
    {
        $org = $this->makeOrganization('cap-none', 'No Plan');

        // An agency the platform has not set up billing for yet is given the
        // least, never the most.
        $this->assertSame(5, $this->storage()->capGbFor($org->id));
    }

    public function test_an_unrecognised_plan_gets_the_smallest_cap(): void
    {
        $org = $this->makeOrganization('cap-junk', 'Junk Plan');
        $this->subscribeOrganization($org, 'enterprise-deluxe');

        $this->assertSame(5, $this->storage()->capGbFor($org->id));
    }

    public function test_a_per_org_override_beats_the_plan_default(): void
    {
        $org = $this->makeOrganization('cap-override', 'Extra Storage');
        $this->subscribeOrganization($org, 'pro');

        $this->assertSame(100, $this->storage()->capGbFor($org->id));

        // The Pro extra-storage arrangement, applied by hand: +100 GB.
        $org->forceFill(['storage_cap_override_gb' => 200])->save();

        $this->assertSame(200, $this->storage()->capGbFor($org->id));
    }

    public function test_an_override_below_the_plan_default_still_wins(): void
    {
        $org = $this->makeOrganization('cap-low', 'Reduced');
        $this->subscribeOrganization($org, 'pro');

        // The override is an instruction, not a ceiling — a superadmin setting
        // it low means it.
        $org->forceFill(['storage_cap_override_gb' => 1])->save();

        $this->assertSame(1, $this->storage()->capGbFor($org->id));
    }

    public function test_clearing_the_override_returns_to_the_plan_default(): void
    {
        $org = $this->makeOrganization('cap-clear', 'Back To Plan');
        $this->subscribeOrganization($org, 'standard');

        $org->forceFill(['storage_cap_override_gb' => 500])->save();
        $this->assertSame(500, $this->storage()->capGbFor($org->id));

        // Null means "use the plan"; zero would mean "no allowance at all",
        // which is why the check is for null rather than for falsiness.
        $org->forceFill(['storage_cap_override_gb' => null])->save();
        $this->assertSame(50, $this->storage()->capGbFor($org->id));
    }

    public function test_the_summary_reports_the_overridden_cap(): void
    {
        $org = $this->makeOrganization('cap-summary', 'Summary');
        $this->subscribeOrganization($org, 'base');
        $org->forceFill(['storage_cap_override_gb' => 250])->save();

        $summary = $this->storage()->summaryFor($org->id);

        $this->assertSame(250, $summary['cap_gb']);
        $this->assertSame(250 * OrganizationStorage::BYTES_PER_GB, $summary['cap_bytes']);

        // The plan itself is still reported honestly alongside the override.
        $this->assertSame('base', $summary['plan']);
    }

    public function test_a_plan_change_moves_the_cap_with_it(): void
    {
        $org = $this->makeOrganization('cap-upgrade', 'Upgrader');
        $this->subscribeOrganization($org, 'base');

        $this->assertSame(5, $this->storage()->capGbFor($org->id));

        // Both subscriptions start on the same date, so this also exercises
        // the documented tiebreak: the later-recorded row wins.
        $this->subscribeOrganization($org, 'pro');

        $this->assertSame(100, $this->storage()->capGbFor($org->id));
    }
}
