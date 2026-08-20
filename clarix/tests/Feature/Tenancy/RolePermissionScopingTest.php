<?php

namespace Tests\Feature\Tenancy;

use App\Livewire\Admin\AuthorizationPanel;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\OrganizationPermissionDefaults;
use App\Services\PermissionService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A role's permission map belongs to one agency.
 *
 * The catalogue of what *can* be permitted is platform-wide; the decision
 * about what a PM may actually do is each agency's own. These tests cover the
 * seam: that a new agency starts with a working map, that two agencies with
 * different maps each resolve their own, that an admin editing one cannot
 * reach the other, and that the platform cannot read any of them.
 */
class RolePermissionScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        PermissionService::flushAll();
    }

    protected function makeOrganization(string $slug): Organization
    {
        return Organization::create([
            'name'              => strtoupper($slug),
            'contact_number'    => '0000000000',
            'email'             => "{$slug}@example.test",
            'address'           => 'Somewhere',
            'subscription_type' => 'base',
            'slug'              => $slug,
        ]);
    }

    protected function memberOf(Organization $organization, string $role, string $tag): User
    {
        return TenantContext::actingAsOrganization(
            $organization->id,
            fn () => User::factory()->create([
                'role'  => $role,
                'email' => "{$role}.{$tag}@example.test",
            ])
        );
    }

    /**
     * Set one permission for a role inside an agency, as its admin would.
     */
    protected function setPermission(Organization $organization, string $role, string $name, bool $allowed): void
    {
        TenantContext::actingAsOrganization($organization->id, function () use ($role, $name, $allowed) {
            RolePermission::updateOrCreate(
                ['role' => $role, 'permission_id' => Permission::where('name', $name)->firstOrFail()->id],
                ['allowed' => $allowed]
            );
        });

        PermissionService::flushAll();
    }

    // ── A new organization gets a working map ────────────────────────────────

    public function test_a_new_organization_is_provisioned_on_creation(): void
    {
        $organization = $this->makeOrganization('brand-new');

        $rows = RolePermission::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->count();

        $this->assertGreaterThan(0, $rows, 'a new agency must not start with an empty map');

        $pm = $this->memberOf($organization, 'pm', 'new');

        // The defaults are a working setup, not merely present.
        $this->assertTrue($pm->hasPermission('tasks.view'));
        $this->assertTrue($pm->hasPermission('tasks.create'));
        $this->assertTrue($pm->hasPermission('tasks.update'));
    }

    public function test_the_provisioned_map_contains_no_delete_permissions(): void
    {
        $organization = $this->makeOrganization('no-deletes');

        $names = RolePermission::withoutGlobalScopes()
            ->with('permission')
            ->where('organization_id', $organization->id)
            ->get()
            ->pluck('permission.name');

        foreach ($names as $name) {
            $this->assertStringNotContainsString('.delete', (string) $name);
        }
    }

    public function test_provisioning_is_idempotent_and_never_overwrites_a_choice(): void
    {
        $organization = $this->makeOrganization('idempotent');

        $this->setPermission($organization, 'pm', 'tasks.create', false);

        app(OrganizationPermissionDefaults::class)->applyTo($organization);

        $pm = $this->memberOf($organization, 'pm', 'idem');
        $this->assertFalse($pm->hasPermission('tasks.create'), 're-running must not restore the default');
    }

    /**
     * The template is the founding agency's live map, so a change there is
     * what a new agency inherits — the known trade-off of copying at runtime.
     */
    public function test_a_new_organization_inherits_the_template_agencys_map(): void
    {
        $founding = Organization::withoutGlobalScopes()->where('slug', 'code-next-door')->firstOrFail();

        $this->setPermission($founding, 'writer', 'credits.view', true);

        $organization = $this->makeOrganization('inheritor');
        $writer       = $this->memberOf($organization, 'writer', 'inherit');

        $this->assertTrue(
            $writer->hasPermission('credits.view'),
            'the new agency starts from the template, not from the hardcoded baseline'
        );
    }

    // ── Two agencies, two different answers ──────────────────────────────────

    /**
     * The test the per-organization cache key exists for.
     *
     * With a global key, whichever agency asked first wrote its answer under
     * "permissions_role_pm" and the other read it back — so B's PM would
     * inherit A's permissions for the life of the cache entry. Both are
     * resolved here, in both orders, through the real hasPermission() path.
     */
    public function test_two_organizations_resolve_their_own_permissions(): void
    {
        $a = $this->makeOrganization('org-a');
        $b = $this->makeOrganization('org-b');

        $this->setPermission($a, 'pm', 'units.view', true);
        $this->setPermission($b, 'pm', 'units.view', false);

        $pmA = $this->memberOf($a, 'pm', 'a');
        $pmB = $this->memberOf($b, 'pm', 'b');

        // A first, then B: B must not read A's cached answer.
        $this->assertTrue($pmA->hasPermission('units.view'), 'agency A allows it');
        $this->assertFalse($pmB->hasPermission('units.view'), 'agency B does not');

        // And the other way round, on freshly loaded users.
        PermissionService::flushAll();

        $this->assertFalse($pmB->fresh()->hasPermission('units.view'));
        $this->assertTrue($pmA->fresh()->hasPermission('units.view'));
    }

    public function test_a_change_in_one_organization_does_not_move_the_other(): void
    {
        $a = $this->makeOrganization('org-c');
        $b = $this->makeOrganization('org-d');

        $this->setPermission($a, 'writer', 'tasks.create', true);
        $this->setPermission($b, 'writer', 'tasks.create', false);

        $writerB = $this->memberOf($b, 'writer', 'd');
        $this->assertFalse($writerB->hasPermission('tasks.create'));

        // Agency A grants more. Agency B is unmoved.
        $this->setPermission($a, 'writer', 'tasks.assign', true);

        $this->assertFalse($writerB->fresh()->hasPermission('tasks.create'));
        $this->assertFalse($writerB->fresh()->hasPermission('tasks.assign'));
    }

    // ── An admin edits only their own agency ─────────────────────────────────

    public function test_an_org_admin_editing_permissions_only_affects_their_own_organization(): void
    {
        $a = $this->makeOrganization('org-e');
        $b = $this->makeOrganization('org-f');

        $this->setPermission($a, 'pm', 'units.view', false);
        $this->setPermission($b, 'pm', 'units.view', false);

        // Created up front, before anyone signs in — see the note in the
        // panel-visibility test below.
        $adminA = $this->memberOf($a, 'admin', 'e');
        $pmA    = $this->memberOf($a, 'pm', 'e2');

        $before = RolePermission::withoutGlobalScopes()
            ->where('organization_id', $b->id)
            ->get()
            ->map(fn ($r) => $r->role.':'.$r->permission_id.':'.(int) $r->allowed)
            ->sort()
            ->values()
            ->all();

        Livewire::actingAs($adminA)
            ->test(AuthorizationPanel::class)
            ->call('toggle', 'pm', 'units.view')
            ->call('grantAll', 'writer');

        $after = RolePermission::withoutGlobalScopes()
            ->where('organization_id', $b->id)
            ->get()
            ->map(fn ($r) => $r->role.':'.$r->permission_id.':'.(int) $r->allowed)
            ->sort()
            ->values()
            ->all();

        $this->assertSame($before, $after, "agency B's map must be byte-identical");

        // And agency A really did change, so the comparison above means something.
        PermissionService::flushAll();
        $this->assertTrue($pmA->fresh()->hasPermission('units.view'));
    }

    public function test_the_panel_only_shows_the_admins_own_organizations_map(): void
    {
        $a = $this->makeOrganization('org-g');
        $b = $this->makeOrganization('org-h');

        $this->setPermission($a, 'pm', 'units.view', true);
        $this->setPermission($b, 'pm', 'units.view', false);

        /*
         * Both admins are created before anyone signs in. TenantContext pins
         * an authenticated ordinary user to their own organization and ignores
         * actingAsOrganization() for them — correctly, since that is what stops
         * a signed-in member being lifted into another agency — so creating the
         * second admin mid-test would have placed them in the first one's.
         */
        $adminA = $this->memberOf($a, 'admin', 'g');
        $adminB = $this->memberOf($b, 'admin', 'h');

        $matrixA = Livewire::actingAs($adminA)->test(AuthorizationPanel::class)->get('matrix');
        $matrixB = Livewire::actingAs($adminB)->test(AuthorizationPanel::class)->get('matrix');

        $this->assertTrue($matrixA['pm']['units.view']);
        $this->assertFalse($matrixB['pm']['units.view']);
    }

    // ── The platform cannot read any of it ───────────────────────────────────

    public function test_a_superadmin_reads_no_role_permissions_at_all(): void
    {
        $organization = $this->makeOrganization('org-i');
        $this->setPermission($organization, 'pm', 'units.view', true);

        $realId = RolePermission::withoutGlobalScopes()->value('id');
        $this->assertNotNull($realId, 'rows really exist for someone');

        $this->actingAs(User::withoutGlobalScopes()->where('role', 'superadmin')->firstOrFail());

        $this->assertSame(0, RolePermission::count());
        $this->assertNull(RolePermission::find($realId));
        $this->assertTrue(RolePermission::all()->isEmpty());
        $this->assertFalse(RolePermission::query()->exists());
        $this->assertSame(0, RolePermission::where('id', $realId)->count());
    }

    public function test_a_superadmin_cannot_write_to_role_permissions(): void
    {
        $organization = $this->makeOrganization('org-j');
        $this->setPermission($organization, 'pm', 'units.view', true);

        $total = DB::table('role_permissions')->count();

        $this->actingAs(User::withoutGlobalScopes()->where('role', 'superadmin')->firstOrFail());

        RolePermission::query()->update(['allowed' => false]);
        RolePermission::query()->delete();

        $this->assertSame($total, DB::table('role_permissions')->count(), 'nothing deleted');
        $this->assertSame(
            1,
            DB::table('role_permissions')
                ->where('organization_id', $organization->id)
                ->where('role', 'pm')
                ->where('permission_id', Permission::where('name', 'units.view')->firstOrFail()->id)
                ->where('allowed', true)
                ->count(),
            'the row a superadmin tried to flip is untouched'
        );
    }

    public function test_a_superadmin_is_refused_the_authorization_panel(): void
    {
        $this->actingAs(User::withoutGlobalScopes()->where('role', 'superadmin')->firstOrFail())
            ->get(route('admin.authorization'))
            ->assertForbidden();
    }

    // ── Every row is owned ───────────────────────────────────────────────────

    public function test_every_role_permission_row_carries_an_organization(): void
    {
        $this->makeOrganization('org-k');

        $this->assertSame(
            0,
            DB::table('role_permissions')->whereNull('organization_id')->count(),
            'organization_id is NOT NULL — an unowned row should be impossible'
        );
    }
}
