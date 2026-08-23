<?php

namespace Tests\Feature\TaskBot;

use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use App\Services\N8nTelegramLinkService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * POST /api/v1/n8n/telegram/tasks — the admin branch, where the task's unit and
 * PM come from the payload instead of from the actor's own row.
 *
 * This is the one place in the whole pipeline where the caller gets to say
 * something about *whose* a task is, so it is worth being explicit about what
 * has and has not changed. A PM is unaffected: their unit is still read off
 * their user row and the two new fields do not exist as far as their request is
 * concerned. An admin may name a unit, but only one their own agency owns, and
 * may name a PM, but only one already in that unit. created_by remains the
 * actor in both cases — the admin filed it, whoever it is for.
 *
 * N8nTaskIntakeTest covers the PM path end to end; this covers the difference.
 */
class N8nAdminTargetingTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    protected N8nTelegramLinkService $links;

    /** @var array<string, mixed> */
    protected array $orgA;

    /** @var array<string, mixed> */
    protected array $orgB;

    protected const ADMIN_A = '7000001';

    protected const ADMIN_B = '7000002';

    protected const PM_A = '7000003';

    protected Unit $secondUnitA;

    protected User $secondPmA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);

        config()->set('services.n8n.key', 'test-n8n-key');

        $this->links = app(N8nTelegramLinkService::class);
        $this->orgA  = $this->populate($this->makeOrganization('tgt-a', 'Agency A'), 'A');
        $this->orgB  = $this->populate($this->makeOrganization('tgt-b', 'Agency B'), 'B');

        $this->subscribeOrganization($this->orgA['organization'], 'pro');
        $this->subscribeOrganization($this->orgB['organization'], 'pro');

        TenantContext::actingAsOrganization($this->orgA['organization']->id, function () {
            $this->secondUnitA = Unit::create(['name' => 'Second Unit A']);

            $this->secondPmA = User::factory()->create([
                'name'    => 'Second PM A',
                'email'   => 'second.pm.a@example.test',
                'role'    => 'pm',
                'unit_id' => $this->secondUnitA->id,
            ]);
        });

        $this->link($this->orgA['admin'], self::ADMIN_A);
        $this->link($this->orgB['admin'], self::ADMIN_B);
        $this->link($this->orgA['pm'], self::PM_A);
    }

    private function link(User $user, string $chatId): void
    {
        $this->links->verify($this->links->issueCode($user), $chatId);
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'chat_id'       => self::ADMIN_A,
            'title'         => 'Filed by an admin',
            'task_code'     => 'ADM_001',
            'priority'      => 'medium',
            'deadline'      => now()->addDays(7)->format('Y-m-d'),
            'credit_amount' => '3.50',
        ], $overrides);
    }

    /** @param array<string, mixed> $body */
    private function file(array $body)
    {
        return $this->postJson('/api/v1/n8n/telegram/tasks', $body, ['X-N8n-Key' => 'test-n8n-key']);
    }

    private function task(int $id): Task
    {
        return TenantContext::runWithoutScope(fn () => Task::findOrFail($id));
    }

    // ── The admin branch ─────────────────────────────────────────────────────

    /**
     * The point of the whole change. An admin carries no unit_id, so before
     * this the endpoint refused them outright.
     */
    public function test_an_admin_can_file_a_task_into_a_named_unit(): void
    {
        $id = $this->file($this->payload([
            'target_unit_id' => $this->secondUnitA->id,
        ]))->assertCreated()->json('id');

        $task = $this->task($id);

        $this->assertSame((int) $this->secondUnitA->id, (int) $task->unit_id);
        $this->assertSame((int) $this->orgA['organization']->id, (int) $task->organization_id);
        $this->assertSame('pending', $task->status);
    }

    /** Unassigned work is a real state: the unit has it, nobody owns it yet. */
    public function test_a_targeted_task_with_no_pm_named_is_left_unassigned(): void
    {
        $id = $this->file($this->payload([
            'target_unit_id' => $this->secondUnitA->id,
        ]))->assertCreated()->json('id');

        $this->assertNull($this->task($id)->pm_id);
    }

    public function test_an_admin_can_name_the_pm_the_task_belongs_to(): void
    {
        $id = $this->file($this->payload([
            'target_unit_id' => $this->secondUnitA->id,
            'assigned_pm_id' => $this->secondPmA->id,
        ]))->assertCreated()->json('id');

        $this->assertSame((int) $this->secondPmA->id, (int) $this->task($id)->pm_id);
    }

    /**
     * created_by is the actor even when pm_id is somebody else. The audit trail
     * has to say who actually filed it.
     */
    public function test_the_admin_remains_the_creator_of_a_task_they_targeted(): void
    {
        $id = $this->file($this->payload([
            'target_unit_id' => $this->secondUnitA->id,
            'assigned_pm_id' => $this->secondPmA->id,
        ]))->assertCreated()->json('id');

        $this->assertSame((int) $this->orgA['admin']->id, (int) $this->task($id)->created_by);
    }

    /** Not among the six fields the bot submits, admin or not. */
    public function test_an_admin_still_cannot_assign_an_admin(): void
    {
        $id = $this->file($this->payload([
            'target_unit_id'    => $this->secondUnitA->id,
            'assigned_admin_id' => $this->orgA['admin']->id,
        ]))->assertCreated()->json('id');

        $this->assertNull($this->task($id)->assigned_admin_id);
    }

    // ── What an admin may not name ───────────────────────────────────────────

    /**
     * Required rather than defaulted. There is no sensible unit to fall back
     * to, and guessing one would put work in front of the wrong team silently.
     */
    public function test_an_admin_must_name_a_unit(): void
    {
        $this->file($this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('target_unit_id');
    }

    public function test_an_admin_cannot_file_into_another_agencys_unit(): void
    {
        $this->file($this->payload([
            'target_unit_id' => $this->orgB['unit']->id,
        ]))->assertStatus(422)->assertJsonValidationErrors('target_unit_id');

        $this->assertSame(0, TenantContext::runWithoutScope(
            fn () => Task::where('task_code', 'ADM_001')->count()
        ));
    }

    public function test_an_admin_cannot_file_into_a_unit_that_does_not_exist(): void
    {
        $this->file($this->payload(['target_unit_id' => 999999]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('target_unit_id');
    }

    /**
     * The pairing matters as much as the membership. A PM of unit one cannot
     * be handed a task filed against unit two, or the board would show them
     * work their own unit filter hides.
     */
    public function test_the_named_pm_must_belong_to_the_named_unit(): void
    {
        $this->file($this->payload([
            'target_unit_id' => $this->secondUnitA->id,
            'assigned_pm_id' => $this->orgA['pm']->id,
        ]))->assertStatus(422)->assertJsonValidationErrors('assigned_pm_id');
    }

    public function test_the_named_pm_cannot_come_from_another_agency(): void
    {
        $this->file($this->payload([
            'target_unit_id' => $this->secondUnitA->id,
            'assigned_pm_id' => $this->orgB['pm']->id,
        ]))->assertStatus(422)->assertJsonValidationErrors('assigned_pm_id');
    }

    /** An admin belongs to no unit, so they can never be the answer. */
    public function test_the_named_pm_cannot_be_an_admin(): void
    {
        $this->file($this->payload([
            'target_unit_id' => $this->secondUnitA->id,
            'assigned_pm_id' => $this->orgA['admin']->id,
        ]))->assertStatus(422)->assertJsonValidationErrors('assigned_pm_id');
    }

    /**
     * task_code is unique per unit, so the uniqueness check has to run against
     * the unit being targeted rather than the actor's own — which for an admin
     * is null, and would make every code look free.
     */
    public function test_the_task_code_must_be_free_in_the_targeted_unit(): void
    {
        $this->file($this->payload([
            'task_code'      => 'ADM_DUP',
            'target_unit_id' => $this->secondUnitA->id,
        ]))->assertCreated();

        $this->file($this->payload([
            'task_code'      => 'ADM_DUP',
            'target_unit_id' => $this->secondUnitA->id,
        ]))->assertStatus(422)->assertJsonValidationErrors('task_code');

        // Free again in a different unit — the index is composite.
        $this->file($this->payload([
            'task_code'      => 'ADM_DUP',
            'target_unit_id' => $this->orgA['unit']->id,
        ]))->assertCreated();
    }

    // ── The PM branch is untouched ───────────────────────────────────────────

    /**
     * The security property this change must not weaken. The fields have no
     * rule for a PM, so they never reach validated() and create() cannot see
     * them — the same mechanism that keeps assigned_admin_id out.
     */
    public function test_a_pms_targeting_fields_are_ignored(): void
    {
        $id = $this->file($this->payload([
            'chat_id'        => self::PM_A,
            'task_code'      => 'PM_TARGET',
            'target_unit_id' => $this->secondUnitA->id,
            'assigned_pm_id' => $this->secondPmA->id,
        ]))->assertCreated()->json('id');

        $task = $this->task($id);
        $pm   = $this->orgA['pm'];

        $this->assertSame((int) $pm->unit_id, (int) $task->unit_id);
        $this->assertSame((int) $pm->id, (int) $task->pm_id);
        $this->assertSame((int) $pm->id, (int) $task->created_by);
    }

    /** Including a unit belonging to another agency: still simply ignored. */
    public function test_a_pm_cannot_reach_another_agency_through_the_new_fields(): void
    {
        $id = $this->file($this->payload([
            'chat_id'        => self::PM_A,
            'task_code'      => 'PM_CROSS',
            'target_unit_id' => $this->orgB['unit']->id,
            'assigned_pm_id' => $this->orgB['pm']->id,
        ]))->assertCreated()->json('id');

        $task = $this->task($id);

        $this->assertSame((int) $this->orgA['pm']->unit_id, (int) $task->unit_id);
        $this->assertSame((int) $this->orgA['organization']->id, (int) $task->organization_id);
    }

    /**
     * A PM's uniqueness check still runs against their own unit, not against
     * whatever unit they named.
     */
    public function test_a_pms_task_code_is_still_checked_against_their_own_unit(): void
    {
        $this->file($this->payload([
            'chat_id'   => self::PM_A,
            'task_code' => 'PM_UNIQ',
        ]))->assertCreated();

        $this->file($this->payload([
            'chat_id'        => self::PM_A,
            'task_code'      => 'PM_UNIQ',
            'target_unit_id' => $this->secondUnitA->id,
        ]))->assertStatus(422)->assertJsonValidationErrors('task_code');
    }

    // ── Everything else the endpoint already refused ─────────────────────────

    public function test_an_admin_of_another_agency_files_into_their_own(): void
    {
        $id = $this->file($this->payload([
            'chat_id'        => self::ADMIN_B,
            'task_code'      => 'ADM_B',
            'target_unit_id' => $this->orgB['unit']->id,
        ]))->assertCreated()->json('id');

        $this->assertSame(
            (int) $this->orgB['organization']->id,
            (int) $this->task($id)->organization_id
        );
    }
}
