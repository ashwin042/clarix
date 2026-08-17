<?php

namespace Tests\Feature\Plans;

use App\Livewire\Superadmin\OrganizationDetail;
use App\Models\User;
use App\Services\PlanFeatures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * organization_subscriptions.plan is the only input; the legacy column follows.
 *
 * The two disagreed in the production copy — one agency labelled 'base' while
 * paying for 'standard' — because two different superadmin screens wrote them.
 * Only one of them is allowed to be the answer now.
 */
class PlanSourceOfTruthTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    protected function superadmin(): User
    {
        return User::factory()->create([
            'name'            => 'Platform Super',
            'email'           => 'super@example.test',
            'role'            => 'superadmin',
            'organization_id' => null,
        ]);
    }

    public function test_saving_a_subscription_mirrors_the_plan_onto_the_organization(): void
    {
        $org = $this->makeOrganization('sot-a', 'Agency A');
        $org->forceFill(['subscription_type' => 'base'])->save();

        Livewire::actingAs($this->superadmin())
            ->test(OrganizationDetail::class, ['organization' => $org])
            ->call('openSubscription')
            ->set('plan', 'standard')
            ->set('price', '5000')
            ->set('billing_cycle', 'monthly')
            ->set('started_at', now()->toDateString())
            ->set('status', 'active')
            ->call('saveSubscription')
            ->assertHasNoErrors();

        // The legacy column can no longer drift away from the truth.
        $this->assertSame('standard', $org->fresh()->subscription_type);
    }

    public function test_a_plan_saved_by_a_superadmin_takes_effect_for_the_agency(): void
    {
        $org = $this->populate($this->makeOrganization('sot-live', 'Live Co'), 'L');
        $this->subscribeOrganization($org['organization'], 'base');

        $this->actingAs($org['admin'])->get('/attendance')->assertStatus(402);

        Livewire::actingAs($this->superadmin())
            ->test(OrganizationDetail::class, ['organization' => $org['organization']])
            ->call('openSubscription')
            ->set('plan', 'standard')
            ->set('price', '5000')
            ->set('billing_cycle', 'monthly')
            ->set('started_at', now()->toDateString())
            ->set('status', 'active')
            ->call('saveSubscription')
            ->assertHasNoErrors();

        // No separate step, no cache to wait out.
        $this->actingAs($org['admin'])->get('/attendance')->assertOk();
    }

    public function test_the_storage_override_can_be_set_and_cleared_by_a_superadmin(): void
    {
        $org = $this->makeOrganization('sot-b', 'Agency B');

        $component = Livewire::actingAs($this->superadmin())
            ->test(OrganizationDetail::class, ['organization' => $org])
            ->call('openSubscription')
            ->set('plan', 'pro')
            ->set('price', '9000')
            ->set('billing_cycle', 'monthly')
            ->set('started_at', now()->toDateString())
            ->set('status', 'active')
            ->set('storage_cap_override_gb', '200')
            ->call('saveSubscription')
            ->assertHasNoErrors();

        $this->assertSame(200, $org->fresh()->storage_cap_override_gb);

        $component->call('openSubscription')
            ->set('storage_cap_override_gb', '')
            ->call('saveSubscription')
            ->assertHasNoErrors();

        // Blank means "use the plan", which is not the same as zero.
        $this->assertNull($org->fresh()->storage_cap_override_gb);
    }

    public function test_the_backfill_corrects_a_stale_label(): void
    {
        // Model the production-copy state: the column said base, the
        // subscription said standard.
        $org = $this->makeOrganization('sot-stale', 'Stale Co');
        $this->subscribeOrganization($org, 'standard');

        $org->forceFill(['subscription_type' => 'base'])->save();

        // The backfill body lives on the service rather than inside the
        // migration class, because RefreshDatabase has already run every
        // migration against an empty table by the time a test starts and an
        // anonymous migration class is not addressable afterwards.
        app(PlanFeatures::class)->syncLegacyPlanColumn();

        $this->assertSame('standard', $org->fresh()->subscription_type);
    }

    public function test_the_backfill_leaves_an_already_correct_label_alone(): void
    {
        $org = $this->makeOrganization('sot-ok', 'Correct Co');
        $this->subscribeOrganization($org, 'pro');
        $org->forceFill(['subscription_type' => 'pro'])->save();

        app(PlanFeatures::class)->syncLegacyPlanColumn();

        $this->assertSame('pro', $org->fresh()->subscription_type);
    }

    public function test_an_organization_with_no_subscription_is_labelled_base(): void
    {
        $org = $this->makeOrganization('sot-none', 'No Plan Co');
        $org->forceFill(['subscription_type' => 'pro'])->save();

        app(PlanFeatures::class)->syncLegacyPlanColumn();

        // Nothing was bought, so the label must not claim otherwise.
        $this->assertSame('base', $org->fresh()->subscription_type);
    }
}
