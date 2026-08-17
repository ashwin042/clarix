<?php

namespace Tests\Feature\Plans;

use App\Livewire\AI\Chatbot;
use App\Livewire\AI\McpPlugins;
use App\Livewire\Attendance\AttendancePage;
use App\Livewire\Attendance\ClockWidget;
use App\Livewire\Leave\LeavePage;
use App\Livewire\Payroll\MyPayroll;
use App\Models\OrganizationSubscription;
use App\Services\PermissionService;
use App\Services\PlanFeatures;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * Route middleware is not the only lock.
 *
 * Livewire actions POST to /livewire/update, which never passes through the
 * route's middleware stack — so a crafted request could mount a gated
 * component directly and skip plan: entirely. These tests mount the components
 * the way such a request would, and expect the component itself to refuse.
 */
class PlanLivewireGuardTest extends TestCase
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

        $this->org = $this->populate($this->makeOrganization('lw-base', 'Base Co'), 'B');
        $this->subscribeOrganization($this->org['organization'], 'base');
    }

    /**
     * @return list<array{0: class-string}>
     */
    public static function gatedComponents(): array
    {
        return [
            'attendance page' => [AttendancePage::class],
            'clock widget'    => [ClockWidget::class],
            'leave page'      => [LeavePage::class],
            'my payroll'      => [MyPayroll::class],
            'chatbot'         => [Chatbot::class],
            'mcp plugins'     => [McpPlugins::class],
        ];
    }

    /**
     * @dataProvider gatedComponents
     *
     * @param  class-string  $component
     */
    public function test_a_base_org_cannot_mount_a_gated_component(string $component): void
    {
        // The admin holds every permission, so a refusal can only be the plan.
        //
        // Asserted on the rendered refusal rather than on a thrown exception:
        // Livewire's test harness lists HttpException among the exceptions it
        // still lets Laravel handle, so the abort comes back as the 402 page
        // instead of propagating out of test().
        Livewire::actingAs($this->org['admin'])->test($component)
            ->assertSee('Not included in your plan')
            ->assertDontSee('wire:id');
    }

    public function test_the_same_components_mount_once_the_plan_includes_them(): void
    {
        // Newer subscription, so this is the one that counts.
        TenantContext::actingAsOrganization($this->org['organization']->id, function () {
            $subscription = new OrganizationSubscription([
                'plan'          => 'pro',
                'price'         => 1000,
                'billing_cycle' => 'monthly',
                'started_at'    => now()->addDay()->toDateString(),
                'status'        => 'active',
            ]);
            $subscription->next_renewal_at = $subscription->renewalDateFrom(now()->addDay());
            $subscription->save();
        });

        PlanFeatures::flush();

        foreach (array_column(self::gatedComponents(), 0) as $component) {
            Livewire::actingAs($this->org['admin'])->test($component)->assertOk();
        }
    }
}
