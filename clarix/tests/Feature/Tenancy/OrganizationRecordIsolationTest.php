<?php

namespace Tests\Feature\Tenancy;

use App\Models\Organization;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The organizations table used to be the one tenant table with no scope on it.
 * No screen exposed it, but any authenticated user could have read every
 * agency's name, contact number, email and address with a single direct query.
 *
 * These tests go straight at the model rather than through a page, because a
 * direct query is exactly what was unguarded.
 */
class OrganizationRecordIsolationTest extends TestCase
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

        $this->a = $this->populate($this->makeOrganization('org-a', 'Agency A'), 'A');
        $this->b = $this->populate($this->makeOrganization('org-b', 'Agency B'), 'B');
    }

    protected function superadmin(): User
    {
        return User::withoutGlobalScopes()->where('role', 'superadmin')->firstOrFail();
    }

    public function test_all_three_organizations_exist(): void
    {
        // Both agencies plus the founding organization the migrations create.
        $this->assertSame(3, DB::table('organizations')->count());
    }

    /**
     * @return array<string, string>
     */
    public static function roleProvider(): array
    {
        return ['admin' => ['admin'], 'pm' => ['pm'], 'writer' => ['writer']];
    }

    /**
     * @dataProvider roleProvider
     */
    public function test_a_member_reads_only_their_own_organization(string $role): void
    {
        $this->actingAs($this->a[$role]);

        $this->assertSame(1, Organization::count(), "{$role}: only their own agency");

        $visible = Organization::all();
        $this->assertCount(1, $visible);
        $this->assertSame($this->a['organization']->id, $visible->first()->id);

        $this->assertNotNull(Organization::find($this->a['organization']->id));
        $this->assertNull(Organization::find($this->b['organization']->id), "{$role}: another agency must be invisible");
    }

    public function test_an_admin_cannot_reach_another_organization_by_any_direct_query(): void
    {
        $this->actingAs($this->a['admin']);

        $other = $this->b['organization'];

        $this->assertNull(Organization::find($other->id));
        $this->assertSame(0, Organization::where('id', $other->id)->count());
        $this->assertSame(0, Organization::where('slug', 'org-b')->count());
        $this->assertNull(Organization::where('name', 'Agency B')->first());
        $this->assertFalse(Organization::where('id', $other->id)->exists());
        $this->assertNotContains($other->id, Organization::pluck('id')->all());

        // Route model binding resolves by slug, so it goes through the scope
        // too — the other agency's handle finds nothing.
        $this->assertNull(Organization::where('slug', $other->slug)->first());
    }

    /**
     * The leak that mattered: contact details, not just the existence of a row.
     */
    public function test_another_organizations_contact_details_are_not_readable(): void
    {
        $this->actingAs($this->a['admin']);

        $this->assertSame(0, Organization::where('email', 'org-b@example.test')->count());
        $this->assertNull(Organization::pluck('email')->first(fn ($e) => $e === 'org-b@example.test'));
        $this->assertNull(Organization::pluck('address')->first(fn ($a) => $a === null));
    }

    public function test_a_member_cannot_update_or_delete_another_organization(): void
    {
        $this->actingAs($this->a['admin']);

        $other = $this->b['organization'];

        Organization::where('id', $other->id)->update(['name' => 'Hijacked']);
        Organization::query()->update(['contact_number' => '999']);
        Organization::where('id', $other->id)->delete();

        $row = DB::table('organizations')->where('id', $other->id)->first();

        $this->assertNotNull($row, 'agency B must still exist');
        $this->assertSame('Agency B', $row->name);
        $this->assertNotSame('999', $row->contact_number);

        // The unqualified update did land on their own record, proving the
        // statement ran and was narrowed rather than silently discarded.
        $this->assertSame(
            '999',
            DB::table('organizations')->where('id', $this->a['organization']->id)->value('contact_number')
        );
    }

    public function test_the_users_own_organization_relation_still_resolves(): void
    {
        $this->actingAs($this->a['admin']);

        $organization = auth()->user()->organization;

        $this->assertNotNull($organization, 'a member must still be able to load their own agency');
        $this->assertSame($this->a['organization']->id, $organization->id);
        $this->assertSame('Agency A', $organization->name);
    }

    public function test_a_superadmin_still_sees_every_organization(): void
    {
        $this->actingAs($this->superadmin());

        $this->assertSame(3, Organization::count());
        $this->assertNotNull(Organization::find($this->a['organization']->id));
        $this->assertNotNull(Organization::find($this->b['organization']->id));
        $this->assertNotNull(Organization::where('slug', 'code-next-door')->first());
    }

    public function test_a_superadmin_acting_into_an_organization_sees_only_that_one(): void
    {
        $this->actingAs($this->superadmin());

        TenantContext::actingAsOrganization($this->a['organization']->id, function () {
            $this->assertSame(1, Organization::count());
            $this->assertNull(Organization::find($this->b['organization']->id));
        });

        $this->assertSame(3, Organization::count(), 'and is unconfined again afterwards');
    }

    public function test_unauthenticated_contexts_are_unaffected(): void
    {
        // Seeders and console commands still need to reach every organization.
        TenantContext::useOrganization(null);

        $this->assertSame(3, Organization::count());
    }

    /**
     * Slug uniqueness is enforced by the validator, which queries the builder
     * directly and never sees the scope. If it did, an agency could claim a
     * slug already taken by one it cannot see.
     */
    public function test_slug_uniqueness_is_still_checked_platform_wide(): void
    {
        $this->actingAs($this->a['admin']);

        $validator = validator(
            ['slug' => 'org-b'],
            ['slug' => ['required', \Illuminate\Validation\Rule::unique('organizations', 'slug')]]
        );

        $this->assertTrue($validator->fails(), 'a slug taken by an unseen agency must still be refused');
    }
}
