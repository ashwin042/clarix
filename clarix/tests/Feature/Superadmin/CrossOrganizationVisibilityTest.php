<?php

namespace Tests\Feature\Superadmin;

use App\Livewire\Superadmin\ManageOrganizations;
use App\Livewire\Superadmin\OrganizationDetail;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * Phase 2 established that a superadmin's queries are unscoped. What is
 * checked here is that the portal built on top of it agrees: its lists count
 * every agency, and its per-agency figures are narrowed deliberately rather
 * than by accident of who is looking.
 */
class CrossOrganizationVisibilityTest extends TestCase
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

    public function test_the_organization_list_shows_every_agency(): void
    {
        $this->actingAs($this->superadmin());

        // Members are counted across both agencies, proving the list is not
        // quietly confined to one of them.
        $this->assertSame(7, User::count());

        Livewire::test(ManageOrganizations::class)
            ->assertSee('Agency A')
            ->assertSee('Agency B');
    }

    /**
     * The list used to carry unit and task counts. Both were removed when the
     * platform lost visibility of operational data — and would read zero now
     * in any case, since those models return nothing to a superadmin.
     */
    public function test_the_organization_list_carries_no_operational_counts(): void
    {
        $this->actingAs($this->superadmin());

        $this->assertSame(0, Unit::count());
        $this->assertSame(0, Task::count());

        Livewire::test(ManageOrganizations::class)
            ->assertViewHas('organizations', function ($organizations) {
                foreach ($organizations as $organization) {
                    $this->assertFalse(isset($organization->units_count));
                    $this->assertFalse(isset($organization->tasks_count));
                    $this->assertTrue(isset($organization->users_count));
                }

                return true;
            });
    }

    /**
     * The detail page narrows to one agency by filtering on organization_id
     * explicitly, because the ambient scope gives a superadmin everything.
     * If it leaned on the scope instead, every organization would show the
     * whole platform's numbers.
     */
    public function test_the_detail_page_reports_only_that_organizations_members(): void
    {
        $this->actingAs($this->superadmin());

        Livewire::test(OrganizationDetail::class, ['organization' => $this->a['organization']])
            ->assertViewHas('userCount', 3)
            ->assertSee('Agency A')
            ->assertSee('pm.A@example.test')
            ->assertDontSee('pm.B@example.test');

        Livewire::test(OrganizationDetail::class, ['organization' => $this->b['organization']])
            ->assertViewHas('userCount', 3)
            ->assertSee('Agency B')
            ->assertSee('pm.B@example.test');
    }

    /**
     * Nothing operational reaches the page — not the task's title, not the
     * unit's name, not a count of either.
     */
    public function test_the_detail_page_shows_nothing_operational(): void
    {
        $this->actingAs($this->superadmin());

        Livewire::test(OrganizationDetail::class, ['organization' => $this->a['organization']])
            ->assertDontSee('Task A')
            ->assertDontSee('Unit A')
            ->assertDontSee('Note A')
            ->assertDontSee('Issue A')
            ->assertDontSee('Payer A');
    }

    public function test_the_detail_page_lists_only_that_organizations_admins(): void
    {
        $this->actingAs($this->superadmin());

        Livewire::test(OrganizationDetail::class, ['organization' => $this->a['organization']])
            ->assertSee('admin.A@example.test')
            ->assertDontSee('admin.B@example.test');
    }

    /**
     * A superadmin who navigates into the ordinary application finds nothing
     * there. Route model binding goes through the same scope, so an agency's
     * task is not merely hidden from the listing — its URL 404s.
     *
     * This used to succeed for both agencies. That was the old rule.
     */
    public function test_a_superadmin_cannot_open_an_agencys_task_in_the_ordinary_app(): void
    {
        $this->actingAs($this->superadmin());

        $this->get(route('tasks.show', $this->a['task']->id))->assertNotFound();
        $this->get(route('tasks.show', $this->b['task']->id))->assertNotFound();

        // And the same URL still works for someone entitled to it, so the 404
        // is about who is asking rather than the row having gone.
        $this->actingAs($this->a['admin']);
        $this->get(route('tasks.show', $this->a['task']->id))->assertSuccessful();
    }

    public function test_admin_only_screens_remain_closed_to_a_superadmin(): void
    {
        $this->actingAs($this->superadmin());

        // Guarded by isAdmin(), which is deliberately false for a superadmin.
        // Reaching an agency's own admin tooling is a separate decision from
        // being able to read its rows, and is not part of this phase.
        $this->get(route('admin.storage'))->assertForbidden();
    }
}
