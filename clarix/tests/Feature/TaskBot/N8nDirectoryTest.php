<?php

namespace Tests\Feature\TaskBot;

use App\Models\Unit;
use App\Models\User;
use App\Services\N8nTelegramLinkService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * GET /api/v1/n8n/telegram/units and .../units/{unit}/pms — the two lookups an
 * admin's conversation needs before it can file anything.
 *
 * A PM carries their unit on their user row, so the workflow never has to ask
 * where their work goes. An admin belongs to no unit, so the bot has to offer
 * them the agency's units and then that unit's PMs. These endpoints exist for
 * that one branch of the conversation and nothing else, which is why both are
 * shut to everybody but an admin.
 *
 * Most of what follows is really one question asked from several directions:
 * can an admin see, or name, anything belonging to another agency.
 */
class N8nDirectoryTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    protected N8nTelegramLinkService $links;

    /** @var array<string, mixed> */
    protected array $orgA;

    /** @var array<string, mixed> */
    protected array $orgB;

    protected const ADMIN_A = '6000001';

    protected const ADMIN_B = '6000002';

    protected const PM_A = '6000003';

    protected const WRITER_A = '6000004';

    protected Unit $secondUnitA;

    protected User $writerInUnitA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);

        config()->set('services.n8n.key', 'test-n8n-key');

        $this->links = app(N8nTelegramLinkService::class);
        $this->orgA  = $this->populate($this->makeOrganization('dir-a', 'Agency A'), 'A');
        $this->orgB  = $this->populate($this->makeOrganization('dir-b', 'Agency B'), 'B');

        $this->subscribeOrganization($this->orgA['organization'], 'pro');
        $this->subscribeOrganization($this->orgB['organization'], 'pro');

        // A second unit and a unit-bound writer, so "the agency's units" and
        // "this unit's people" are both lists of more than one thing and a
        // query that ignored its filter would be visible.
        TenantContext::actingAsOrganization($this->orgA['organization']->id, function () {
            $this->secondUnitA = Unit::create(['name' => 'Second Unit A']);

            $this->writerInUnitA = User::factory()->create([
                'name'    => 'Writer In Unit A',
                'email'   => 'writer.in.unit.a@example.test',
                'role'    => 'writer',
                'unit_id' => $this->orgA['unit']->id,
            ]);
        });

        $this->link($this->orgA['admin'], self::ADMIN_A);
        $this->link($this->orgB['admin'], self::ADMIN_B);
        $this->link($this->orgA['pm'], self::PM_A);
        $this->link($this->writerInUnitA, self::WRITER_A);
    }

    private function link(User $user, string $chatId): void
    {
        $this->links->verify($this->links->issueCode($user), $chatId);
    }

    private function units(string $chatId, array $headers = ['X-N8n-Key' => 'test-n8n-key'])
    {
        return $this->getJson('/api/v1/n8n/telegram/units?chat_id='.$chatId, $headers);
    }

    private function pms(int $unitId, string $chatId, array $headers = ['X-N8n-Key' => 'test-n8n-key'])
    {
        return $this->getJson("/api/v1/n8n/telegram/units/{$unitId}/pms?chat_id={$chatId}", $headers);
    }

    // ── Listing units ────────────────────────────────────────────────────────

    public function test_an_admin_sees_every_unit_in_their_own_agency(): void
    {
        $body = $this->units(self::ADMIN_A)->assertOk()->json();

        $this->assertEqualsCanonicalizing(
            [(int) $this->orgA['unit']->id, (int) $this->secondUnitA->id],
            array_column($body, 'id')
        );
    }

    public function test_the_unit_list_is_a_bare_array_of_id_and_name(): void
    {
        $body = $this->units(self::ADMIN_A)->assertOk()->json();

        $this->assertArrayNotHasKey('data', $body);
        $this->assertSame(['id', 'name'], array_keys($body[0]));
        $this->assertIsInt($body[0]['id']);
        $this->assertIsString($body[0]['name']);
    }

    /**
     * The tenant scope doing its job. Nothing in the request names an
     * organization — it is taken from the chat's owner — so a unit list that
     * leaked would leak silently.
     */
    public function test_an_admin_never_sees_another_agencys_units(): void
    {
        $ids = array_column($this->units(self::ADMIN_A)->assertOk()->json(), 'id');

        $this->assertNotContains((int) $this->orgB['unit']->id, $ids);

        $idsB = array_column($this->units(self::ADMIN_B)->assertOk()->json(), 'id');

        $this->assertSame([(int) $this->orgB['unit']->id], $idsB);
    }

    /**
     * A PM's unit is already on their user row, so there is nothing here for
     * them to choose. Refused rather than answered with an empty list: an empty
     * list reads as "your agency has no units", which is a different and
     * misleading thing for a workflow to branch on.
     */
    public function test_a_pm_may_not_list_units(): void
    {
        $this->units(self::PM_A)->assertForbidden();
    }

    public function test_a_writer_may_not_list_units(): void
    {
        $this->units(self::WRITER_A)->assertForbidden();
    }

    // ── Listing a unit's PMs ─────────────────────────────────────────────────

    public function test_an_admin_sees_the_people_assigned_to_a_unit(): void
    {
        $body = $this->pms((int) $this->orgA['unit']->id, self::ADMIN_A)->assertOk()->json();

        $this->assertEqualsCanonicalizing(
            [(int) $this->orgA['pm']->id, (int) $this->writerInUnitA->id],
            array_column($body, 'id')
        );
        $this->assertSame(['id', 'name'], array_keys($body[0]));
    }

    /**
     * Scoped to the unit in the path, not to the agency. Naming the other unit
     * must not hand back the first one's people.
     */
    public function test_the_pm_list_is_confined_to_the_unit_named_in_the_path(): void
    {
        $this->pms((int) $this->secondUnitA->id, self::ADMIN_A)
            ->assertOk()
            ->assertExactJson([]);
    }

    /** Admins belong to no unit, and would be a nonsense answer to "who files this". */
    public function test_the_pm_list_excludes_admins(): void
    {
        $ids = array_column($this->pms((int) $this->orgA['unit']->id, self::ADMIN_A)->assertOk()->json(), 'id');

        $this->assertNotContains((int) $this->orgA['admin']->id, $ids);
    }

    /**
     * The cross-agency case, and the reason the unit is resolved under the
     * acting scope rather than by route-model binding. A 404 rather than a 403:
     * a unit in another agency and a unit that never existed must be
     * indistinguishable from outside.
     */
    public function test_an_admin_cannot_read_another_agencys_unit(): void
    {
        $this->pms((int) $this->orgB['unit']->id, self::ADMIN_A)->assertNotFound();
    }

    public function test_a_unit_that_does_not_exist_is_a_404(): void
    {
        $this->pms(999999, self::ADMIN_A)->assertNotFound();
    }

    public function test_a_pm_may_not_list_a_units_people(): void
    {
        $this->pms((int) $this->orgA['unit']->id, self::PM_A)->assertForbidden();
    }

    /**
     * Refused before the unit is looked at, so a PM cannot use the 403/404
     * difference to learn which unit ids their agency owns.
     */
    public function test_a_pm_is_refused_for_a_unit_that_does_not_exist_too(): void
    {
        $this->pms(999999, self::PM_A)->assertForbidden();
    }

    // ── The shared guards ────────────────────────────────────────────────────

    public function test_both_endpoints_require_the_pipeline_key(): void
    {
        $this->units(self::ADMIN_A, [])->assertUnauthorized();
        $this->pms((int) $this->orgA['unit']->id, self::ADMIN_A, [])->assertUnauthorized();
    }

    public function test_an_unlinked_chat_is_answered_the_way_resolve_answers_it(): void
    {
        $this->units('6999999')
            ->assertNotFound()
            ->assertJsonPath('linked', false);
    }

    public function test_a_missing_chat_id_is_a_validation_error(): void
    {
        $this->getJson('/api/v1/n8n/telegram/units', ['X-N8n-Key' => 'test-n8n-key'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('chat_id');
    }

    /** The same commercial gate the link and intake endpoints apply. */
    public function test_an_agency_below_pro_is_refused(): void
    {
        $this->subscribeOrganization($this->orgA['organization'], 'base');

        $this->units(self::ADMIN_A)->assertStatus(402);
    }
}
