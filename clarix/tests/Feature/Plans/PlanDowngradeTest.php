<?php

namespace Tests\Feature\Plans;

use App\Models\Attendance;
use App\Models\OrganizationSubscription;
use App\Models\PayrollRecord;
use App\Services\PermissionService;
use App\Services\PlanFeatures;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * Downgrading hides, it does not delete.
 *
 * The consequence of the opposite would be severe and silent: an agency that
 * dropped a tier for a month would come back to find its payroll history gone.
 * Gating lives entirely on the read path so that cannot happen.
 */
class PlanDowngradeTest extends TestCase
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

        $this->org = $this->populate($this->makeOrganization('down-a', 'Agency A'), 'A');
    }

    /**
     * A plan change, dated so that each successive call is the newest row —
     * which is how a real upgrade or downgrade is recorded.
     */
    protected function setPlan(string $plan, string $startedAt): void
    {
        TenantContext::actingAsOrganization($this->org['organization']->id, function () use ($plan, $startedAt) {
            $subscription = new OrganizationSubscription([
                'plan'          => $plan,
                'price'         => 1000,
                'billing_cycle' => 'monthly',
                'started_at'    => $startedAt,
                'status'        => 'active',
            ]);
            $subscription->next_renewal_at = $subscription->renewalDateFrom($startedAt);
            $subscription->save();
        });

        // Mirrors what a real HTTP request does — each begins with an empty
        // memo. The test process is one long-lived PHP process, so it has to
        // be cleared by hand.
        PlanFeatures::flush();
    }

    public function test_a_downgrade_hides_erp_without_destroying_it(): void
    {
        $this->setPlan('pro', now()->subYear()->toDateString());

        $record = TenantContext::actingAsOrganization($this->org['organization']->id, function () {
            $payroll = new PayrollRecord([
                'month'       => now()->startOfMonth()->toDateString(),
                'base_amount' => 5000,
                'deductions'  => 0,
            ]);
            $payroll->user_id    = $this->org['pm']->id;
            $payroll->created_by = $this->org['admin']->id;
            $payroll->save();

            $attendance = new Attendance(['date' => now()->toDateString(), 'status' => 'present']);
            $attendance->user_id = $this->org['pm']->id;
            $attendance->save();

            return $payroll;
        });

        $this->actingAs($this->org['pm'])->get('/payroll')->assertOk();

        $this->setPlan('base', now()->toDateString());

        // Access is gone...
        $this->actingAs($this->org['pm'])->get('/payroll')->assertStatus(402);
        $this->actingAs($this->org['pm'])->get('/attendance')->assertStatus(402);

        // ...the records are not.
        $this->assertDatabaseHas('payroll_records', ['id' => $record->id, 'base_amount' => 5000]);
        $this->assertSame(1, DB::table('attendances')->where('user_id', $this->org['pm']->id)->count());
    }

    public function test_upgrading_again_restores_access_to_the_same_records(): void
    {
        $this->setPlan('base', now()->subYear()->toDateString());

        TenantContext::actingAsOrganization($this->org['organization']->id, function () {
            $payroll = new PayrollRecord([
                'month'       => now()->startOfMonth()->toDateString(),
                'base_amount' => 7777,
                'deductions'  => 0,
            ]);
            $payroll->user_id    = $this->org['pm']->id;
            $payroll->created_by = $this->org['admin']->id;
            $payroll->save();
        });

        $this->actingAs($this->org['pm'])->get('/payroll')->assertStatus(402);

        $this->setPlan('standard', now()->toDateString());

        $this->actingAs($this->org['pm'])->get('/payroll')
            ->assertOk()
            ->assertSee('7,777.00');
    }

    public function test_a_plan_change_takes_effect_on_the_next_request(): void
    {
        $this->setPlan('base', now()->subYear()->toDateString());
        $this->actingAs($this->org['admin'])->get('/attendance')->assertStatus(402);

        // No flush step and no cache to wait out: the very next request sees
        // it. This is why PlanFeatures memoizes per request and nothing more.
        $this->setPlan('standard', now()->toDateString());
        $this->actingAs($this->org['admin'])->get('/attendance')->assertOk();
    }

    public function test_a_downgrade_also_takes_the_links_out_of_the_sidebar(): void
    {
        $this->setPlan('pro', now()->subYear()->toDateString());

        $this->actingAs($this->org['admin'])->get('/dashboard')
            ->assertOk()
            ->assertSee(route('attendance.index'), escape: false);

        $this->setPlan('base', now()->toDateString());

        $this->actingAs($this->org['admin'])->get('/dashboard')
            ->assertOk()
            ->assertDontSee(route('attendance.index'), escape: false);
    }
}
