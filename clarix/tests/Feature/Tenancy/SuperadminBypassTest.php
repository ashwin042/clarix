<?php

namespace Tests\Feature\Tenancy;

use App\Models\Issue;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * What the superadmin bypass now means.
 *
 * It began as "sees everything" and has since been narrowed to a short list:
 * the organizations themselves, their member lists, and their billing with
 * Clarix. Operational work is refused. These tests hold both halves of that
 * line in one place, and check that nothing else quietly acquires either half.
 *
 * The refusal itself is exercised in depth by
 * SuperadminOperationalBlindnessTest.
 */
class SuperadminBypassTest extends TestCase
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

    public function test_the_superadmin_has_no_organization(): void
    {
        $superadmin = $this->superadmin();

        $this->assertNull($superadmin->organization_id);
        $this->assertTrue($superadmin->isSuperadmin());
        $this->assertFalse($superadmin->isAdmin(), 'superadmin must be a distinct role, not an admin');
    }

    public function test_a_superadmin_sees_every_organization_and_every_member(): void
    {
        $this->actingAs($this->superadmin());

        $this->assertSame(3, Organization::count(), 'both agencies plus the founding organization');
        $this->assertNotNull(Organization::find($this->a['organization']->id));
        $this->assertNotNull(Organization::find($this->b['organization']->id));

        // Three members per agency, plus the superadmin itself.
        $this->assertSame(7, User::count());
        $this->assertNotNull(User::find($this->a['pm']->id));
        $this->assertNotNull(User::find($this->b['pm']->id));
    }

    public function test_a_superadmin_sees_no_operational_data_in_either_organization(): void
    {
        $this->actingAs($this->superadmin());

        foreach ([Unit::class, Task::class, Issue::class, Payment::class] as $model) {
            $this->assertSame(0, $model::count(), "{$model}: must be invisible to the platform");
        }

        $this->assertNull(Task::find($this->a['task']->id));
        $this->assertNull(Task::find($this->b['task']->id));
    }

    public function test_an_ordinary_admin_is_still_confined_to_their_own_organization(): void
    {
        $this->actingAs($this->a['admin']);

        // Not blinded — an agency's admin sees their agency's work in full.
        $this->assertSame(1, Task::count());
        $this->assertNotNull(Task::find($this->a['task']->id));

        // But no further.
        $this->assertNull(Task::find($this->b['task']->id));
        $this->assertSame(3, User::count());
    }

    public function test_no_ordinary_user_is_left_without_an_organization(): void
    {
        // The refusal keys off the superadmin role, so an ordinary account
        // with no organization would be neither scoped nor blinded. Nothing
        // should ever create one.
        $this->assertSame(
            0,
            DB::table('users')->whereNull('organization_id')->where('role', '!=', 'superadmin')->count()
        );

        $this->assertSame(
            1,
            DB::table('users')->whereNull('organization_id')->count(),
            'the superadmin is the only organization-less account'
        );
    }
}
