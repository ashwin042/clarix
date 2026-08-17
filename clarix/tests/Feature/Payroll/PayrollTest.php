<?php

namespace Tests\Feature\Payroll;

use App\Livewire\Payroll\ManagePayroll;
use App\Livewire\Payroll\MyPayroll;
use App\Models\Organization;
use App\Models\PayrollRecord;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\PayrollLifecycle;
use App\Services\PermissionService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * Payroll, phase 3 of the ERP work.
 *
 * Salary is the most sensitive thing an agency keeps in Clarix, so the tests
 * lean hardest on who cannot see it: a colleague, another agency, and the
 * platform. The rest pins the state machine — draft, finalized, paid — and the
 * rule that a paid record is closed.
 */
class PayrollTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $a;

    /** @var array<string, mixed> */
    protected array $b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        PermissionService::flushAll();

        $this->a = $this->populate($this->makeOrganization('pay-a', 'Agency A'), 'A');
        $this->b = $this->populate($this->makeOrganization('pay-b', 'Agency B'), 'B');

        // Payroll is ERP, which the plan layer sells from Standard up. This
        // suite is about the policy layer, so both agencies go on a plan that
        // includes ERP.
        $this->subscribeOrganization($this->a['organization']);
        $this->subscribeOrganization($this->b['organization']);
    }

    protected function setPermission(array $org, string $role, string $name, bool $allowed): void
    {
        TenantContext::actingAsOrganization($org['organization']->id, function () use ($role, $name, $allowed) {
            RolePermission::updateOrCreate(
                ['role' => $role, 'permission_id' => Permission::where('name', $name)->firstOrFail()->id],
                ['allowed' => $allowed]
            );
        });

        PermissionService::flushAll();
    }

    protected function recordFor(array $org, User $user, string $month = null, float $base = 1000, float $deductions = 100): PayrollRecord
    {
        $month ??= now()->startOfMonth()->toDateString();

        return TenantContext::actingAsOrganization($org['organization']->id, function () use ($org, $user, $month, $base, $deductions) {
            $record = new PayrollRecord([
                'month'       => $month,
                'base_amount' => $base,
                'deductions'  => $deductions,
            ]);

            $record->user_id    = $user->id;
            $record->created_by = $org['admin']->id;
            $record->save();

            return $record;
        });
    }

    // ── The record itself ────────────────────────────────────────────────────

    public function test_net_amount_is_derived_and_cannot_be_set_by_hand(): void
    {
        $record = $this->recordFor($this->a, $this->a['writer'], base: 5000, deductions: 750);

        $this->assertEquals(4250, (float) $record->net_amount);

        // Even assigned directly, the saving hook recomputes it.
        $record->net_amount = 999999;
        $record->save();

        $this->assertEquals(4250, (float) $record->refresh()->net_amount);
    }

    public function test_the_month_is_normalised_to_the_first(): void
    {
        $record = $this->recordFor($this->a, $this->a['writer'], month: '2026-07-19');

        $this->assertSame('2026-07-01', $record->refresh()->month->toDateString());
    }

    public function test_one_record_per_person_per_month_is_enforced(): void
    {
        $this->recordFor($this->a, $this->a['writer'], month: '2026-07-01');

        $this->expectException(UniqueConstraintViolationException::class);

        // A different day in the same month normalises to the same first, so
        // the constraint sees it as the duplicate it is.
        $this->recordFor($this->a, $this->a['writer'], month: '2026-07-22');
    }

    public function test_a_record_is_stamped_with_its_organization_and_author(): void
    {
        $record = $this->recordFor($this->a, $this->a['writer']);

        $this->assertSame($this->a['organization']->id, $record->organization_id);
        $this->assertSame($this->a['admin']->id, $record->created_by);
    }

    // ── The state machine ────────────────────────────────────────────────────

    public function test_a_record_moves_from_draft_to_finalized_to_paid(): void
    {
        $record    = $this->recordFor($this->a, $this->a['writer']);
        $lifecycle = app(PayrollLifecycle::class);
        $admin     = $this->a['admin'];

        $this->assertSame('draft', $record->status);

        $lifecycle->transition($record, 'finalized', $admin);
        $this->assertSame('finalized', $record->refresh()->status);
        $this->assertNull($record->paid_at);

        $lifecycle->transition($record, 'paid', $admin);
        $record->refresh();
        $this->assertSame('paid', $record->status);
        $this->assertNotNull($record->paid_at);
    }

    public function test_draft_cannot_jump_straight_to_paid(): void
    {
        $record = $this->recordFor($this->a, $this->a['writer']);

        $this->expectException(ValidationException::class);
        app(PayrollLifecycle::class)->transition($record, 'paid', $this->a['admin']);
    }

    public function test_a_paid_record_is_terminal(): void
    {
        $record    = $this->recordFor($this->a, $this->a['writer']);
        $lifecycle = app(PayrollLifecycle::class);

        $lifecycle->transition($record, 'finalized', $this->a['admin']);
        $lifecycle->transition($record, 'paid', $this->a['admin']);

        $this->expectException(ValidationException::class);
        $lifecycle->transition($record->refresh(), 'draft', $this->a['admin']);
    }

    public function test_a_finalized_record_can_be_reopened_for_correction(): void
    {
        $record    = $this->recordFor($this->a, $this->a['writer']);
        $lifecycle = app(PayrollLifecycle::class);

        $lifecycle->transition($record, 'finalized', $this->a['admin']);
        $lifecycle->transition($record->refresh(), 'draft', $this->a['admin']);

        $this->assertSame('draft', $record->refresh()->status);
        $this->assertTrue($record->amountsAreEditable());
    }

    public function test_amounts_are_locked_once_finalized(): void
    {
        $record = $this->recordFor($this->a, $this->a['writer']);
        app(PayrollLifecycle::class)->transition($record, 'finalized', $this->a['admin']);

        $this->actingAs($this->a['admin']);

        $this->assertFalse($record->refresh()->amountsAreEditable());
        $this->assertFalse($this->a['admin']->can('update', $record));
    }

    // ── The management screen ────────────────────────────────────────────────

    public function test_an_admin_enters_a_payroll_record(): void
    {
        Livewire::actingAs($this->a['admin'])
            ->test(ManagePayroll::class)
            ->call('openRecord', $this->a['writer']->id)
            ->set('base_amount', '4200')
            ->set('deductions', '200')
            ->set('notes', 'August')
            ->call('save')
            ->assertHasNoErrors();

        $record = PayrollRecord::withoutGlobalScopes()->where('user_id', $this->a['writer']->id)->firstOrFail();

        $this->assertEquals(4200, (float) $record->base_amount);
        $this->assertEquals(4000, (float) $record->net_amount);
        $this->assertSame('draft', $record->status);
        $this->assertSame($this->a['admin']->id, $record->created_by);
    }

    public function test_deductions_cannot_exceed_the_base_amount(): void
    {
        Livewire::actingAs($this->a['admin'])
            ->test(ManagePayroll::class)
            ->call('openRecord', $this->a['writer']->id)
            ->set('base_amount', '1000')
            ->set('deductions', '1500')
            ->call('save')
            ->assertHasErrors('deductions');

        $this->assertSame(0, DB::table('payroll_records')->count());
    }

    public function test_the_screen_walks_a_record_through_its_states(): void
    {
        $record = $this->recordFor($this->a, $this->a['writer']);

        Livewire::actingAs($this->a['admin'])
            ->test(ManagePayroll::class)
            ->call('finalize', $record->id)
            ->call('markPaid', $record->id);

        $record->refresh();
        $this->assertSame('paid', $record->status);
        $this->assertNotNull($record->paid_at);
    }

    public function test_a_paid_record_cannot_be_removed(): void
    {
        $record    = $this->recordFor($this->a, $this->a['writer']);
        $lifecycle = app(PayrollLifecycle::class);
        $lifecycle->transition($record, 'finalized', $this->a['admin']);
        $lifecycle->transition($record, 'paid', $this->a['admin']);

        Livewire::actingAs($this->a['admin'])
            ->test(ManagePayroll::class)
            ->call('openDeleteModal', $record->id, 'Writer A')
            ->call('confirmDelete')
            ->assertForbidden();

        $this->assertDatabaseHas('payroll_records', ['id' => $record->id]);
    }

    // ── A regular user sees their own, and only reads ────────────────────────

    public function test_a_user_sees_their_own_payroll_history(): void
    {
        $this->recordFor($this->a, $this->a['writer'], base: 3000, deductions: 0);

        $records = Livewire::actingAs($this->a['writer'])
            ->test(MyPayroll::class)
            ->viewData('records');

        $this->assertCount(1, $records);
        $this->assertEquals(3000, (float) $records->first()->net_amount);
    }

    public function test_a_user_never_sees_a_colleagues_payroll(): void
    {
        $this->recordFor($this->a, $this->a['pm'], base: 9000);
        $this->recordFor($this->a, $this->a['writer'], base: 3000);

        $records = Livewire::actingAs($this->a['writer'])
            ->test(MyPayroll::class)
            ->viewData('records');

        $this->assertCount(1, $records, 'only their own row');
        $this->assertSame($this->a['writer']->id, $records->first()->user_id);
    }

    public function test_a_user_cannot_open_the_management_screen(): void
    {
        Livewire::actingAs($this->a['writer'])->test(ManagePayroll::class)->assertForbidden();
        Livewire::actingAs($this->a['pm'])->test(ManagePayroll::class)->assertForbidden();

        $this->actingAs($this->a['writer'])->get(route('payroll.manage'))->assertForbidden();
    }

    public function test_a_user_cannot_edit_a_record_through_the_policy(): void
    {
        $record = $this->recordFor($this->a, $this->a['writer']);

        $this->actingAs($this->a['writer']);

        $this->assertTrue($this->a['writer']->can('view', $record), 'they may read their own');
        $this->assertFalse($this->a['writer']->can('update', $record));
        $this->assertFalse($this->a['writer']->can('transition', $record));
        $this->assertFalse($this->a['writer']->can('delete', $record));
    }

    public function test_revoking_view_own_closes_the_payroll_page(): void
    {
        $this->setPermission($this->a, 'writer', 'payroll.view_own', false);

        $this->actingAs($this->a['writer'])->get(route('payroll.index'))->assertForbidden();
    }

    public function test_the_manage_toggle_opens_the_screen_for_a_pm(): void
    {
        Livewire::actingAs($this->a['pm'])->test(ManagePayroll::class)->assertForbidden();

        $this->setPermission($this->a, 'pm', 'payroll.manage', true);

        Livewire::actingAs($this->a['pm']->fresh())->test(ManagePayroll::class)->assertOk();
    }

    // ── Cross-organization isolation ─────────────────────────────────────────

    public function test_an_admin_cannot_see_another_organizations_payroll(): void
    {
        $foreign = $this->recordFor($this->b, $this->b['writer'], base: 8888);

        $this->actingAs($this->a['admin']);

        $this->assertSame(0, PayrollRecord::count());
        $this->assertNull(PayrollRecord::find($foreign->id));
        $this->assertSame(0, (int) PayrollRecord::sum('net_amount'), 'not even a total leaks');

        $members = Livewire::actingAs($this->a['admin'])
            ->test(ManagePayroll::class)
            ->viewData('members')
            ->pluck('id');

        $this->assertFalse($members->contains($this->b['writer']->id));
    }

    public function test_an_admin_cannot_enter_payroll_for_another_organizations_member(): void
    {
        $this->assertThrows(
            fn () => Livewire::actingAs($this->a['admin'])
                ->test(ManagePayroll::class)
                ->call('openRecord', $this->b['writer']->id),
            ModelNotFoundException::class
        );

        $this->assertSame(0, DB::table('payroll_records')->where('user_id', $this->b['writer']->id)->count());
    }

    public function test_an_admin_cannot_transition_another_organizations_record(): void
    {
        $foreign = $this->recordFor($this->b, $this->b['writer']);

        $this->assertThrows(
            fn () => Livewire::actingAs($this->a['admin'])
                ->test(ManagePayroll::class)
                ->call('finalize', $foreign->id),
            ModelNotFoundException::class
        );

        $this->assertSame('draft', $foreign->refresh()->status);
    }

    // ── The platform sees nothing at all ─────────────────────────────────────

    public function test_a_superadmin_reads_no_payroll_whatsoever(): void
    {
        $record = $this->recordFor($this->a, $this->a['writer'], base: 7777);

        $this->actingAs(User::withoutGlobalScopes()->where('role', 'superadmin')->firstOrFail());

        $this->assertSame(0, PayrollRecord::count());
        $this->assertNull(PayrollRecord::find($record->id));
        $this->assertTrue(PayrollRecord::all()->isEmpty());
        $this->assertFalse(PayrollRecord::query()->exists());
        $this->assertNull(PayrollRecord::max('base_amount'), 'aggregates leak nothing');
        $this->assertSame(0, (int) PayrollRecord::sum('net_amount'));
    }

    public function test_a_superadmin_cannot_write_to_payroll(): void
    {
        $record = $this->recordFor($this->a, $this->a['writer'], base: 1000, deductions: 0);
        $total  = DB::table('payroll_records')->count();

        $this->actingAs(User::withoutGlobalScopes()->where('role', 'superadmin')->firstOrFail());

        PayrollRecord::query()->update(['status' => 'paid']);
        PayrollRecord::query()->delete();

        $this->assertSame($total, DB::table('payroll_records')->count());
        $this->assertSame('draft', $record->refresh()->status);
    }

    public function test_a_superadmin_is_refused_both_payroll_screens(): void
    {
        $this->actingAs(User::withoutGlobalScopes()->where('role', 'superadmin')->firstOrFail());

        $this->get(route('payroll.index'))->assertForbidden();
        $this->get(route('payroll.manage'))->assertForbidden();
    }

    // ── Defaults and the panel ───────────────────────────────────────────────

    public function test_a_new_organization_receives_the_payroll_defaults(): void
    {
        $fresh = Organization::create([
            'name' => 'Fresh', 'contact_number' => '0', 'email' => 'fresh@example.test',
            'address' => 'x', 'subscription_type' => 'base', 'slug' => 'pay-fresh',
        ]);

        $writer = TenantContext::actingAsOrganization(
            $fresh->id,
            fn () => User::factory()->create(['role' => 'writer', 'email' => 'fresh.w@example.test'])
        );

        PermissionService::flushAll();

        $this->assertTrue($writer->hasPermission('payroll.view_own'));
        $this->assertFalse($writer->hasPermission('payroll.manage'), 'payroll stays with an admin by default');
    }

    public function test_the_authorization_panel_offers_the_payroll_toggles(): void
    {
        $matrix = Livewire::actingAs($this->a['admin'])
            ->test(\App\Livewire\Admin\AuthorizationPanel::class)
            ->get('matrix');

        foreach (['payroll.view_own', 'payroll.manage'] as $name) {
            $this->assertArrayHasKey($name, $matrix['pm'], "{$name} must be toggleable");
            $this->assertArrayHasKey($name, $matrix['writer'], "{$name} must be toggleable");
        }
    }

    /**
     * The lesson from the last two phases: a module that adds a tenant table
     * has to tell OrganizationTeardown about it, or an agency holding real
     * records becomes deletable — or, for provisioned defaults, undeletable.
     */
    public function test_payroll_records_block_an_organization_from_being_deleted(): void
    {
        $this->recordFor($this->a, $this->a['writer']);

        $teardown = app(\App\Services\OrganizationTeardown::class);

        $this->assertTrue($teardown->hasOperationalData($this->a['organization']));
        $this->assertNotEmpty($teardown->blockers($this->a['organization']));
    }
}
