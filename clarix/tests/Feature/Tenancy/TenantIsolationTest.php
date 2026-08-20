<?php

namespace Tests\Feature\Tenancy;

use App\Models\DailyChatRequest;
use App\Models\Issue;
use App\Models\IssueReply;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskFile;
use App\Models\TaskNote;
use App\Models\Unit;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The load-bearing test of the whole multi-tenant design: a member of one
 * agency must not be able to reach another agency's rows, by any route.
 *
 * These assertions go through Eloquent directly rather than through the UI on
 * purpose. Controller and policy checks can be bypassed by any new code path
 * that forgets them; the global scope is the layer that is meant to hold even
 * when the caller is careless, so that is what is tested here.
 */
class TenantIsolationTest extends TestCase
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

    /**
     * Both agencies' rows really are in the database. Without this, every
     * assertion below could pass simply because the fixtures never wrote
     * anything.
     */
    public function test_the_fixtures_wrote_both_organizations_data(): void
    {
        foreach ([
            'units' => 2, 'tasks' => 2, 'task_files' => 2, 'task_notes' => 2,
            'task_assignments' => 2, 'issues' => 2, 'issue_replies' => 2,
            'payments' => 2, 'daily_chat_requests' => 2,
        ] as $table => $expected) {
            $this->assertSame(
                $expected,
                DB::table($table)->count(),
                "{$table} should hold one row per organization"
            );

            $this->assertSame(
                0,
                DB::table($table)->whereNull('organization_id')->count(),
                "{$table} should have no unowned rows"
            );
        }

        // 3 users per agency, plus the superadmin the migrations create.
        $this->assertSame(7, DB::table('users')->count());
    }

    public function test_a_user_cannot_read_another_organizations_rows(): void
    {
        $this->actingAs($this->a['admin']);

        $cases = [
            Unit::class            => [$this->a['unit'],       $this->b['unit']],
            Task::class            => [$this->a['task'],       $this->b['task']],
            TaskFile::class        => [$this->a['file'],       $this->b['file']],
            TaskNote::class        => [$this->a['note'],       $this->b['note']],
            TaskAssignment::class  => [$this->a['assignment'], $this->b['assignment']],
            Issue::class           => [$this->a['issue'],      $this->b['issue']],
            IssueReply::class      => [$this->a['reply'],      $this->b['reply']],
            Payment::class         => [$this->a['payment'],    $this->b['payment']],
            DailyChatRequest::class => [$this->a['chat'],      $this->b['chat']],
        ];

        foreach ($cases as $model => [$mine, $theirs]) {
            $this->assertSame(1, $model::count(), "{$model} should only count agency A's row");
            $this->assertNotNull($model::find($mine->id), "{$model}: own row should be readable");
            $this->assertNull($model::find($theirs->id), "{$model}: other agency's row must be invisible");

            $ids = $model::pluck('id')->all();
            $this->assertContains($mine->id, $ids);
            $this->assertNotContains($theirs->id, $ids);

            // An explicit where on the other agency's id must still find
            // nothing — the scope is not something a caller can out-run.
            $this->assertSame(0, $model::where('id', $theirs->id)->count());
        }
    }

    public function test_users_themselves_are_isolated(): void
    {
        $this->actingAs($this->a['admin']);

        $this->assertNull(User::find($this->b['admin']->id));
        $this->assertNull(User::where('email', 'pm.B@example.test')->first());
        $this->assertSame(3, User::count(), 'only agency A\'s three users are visible');
    }

    public function test_a_user_cannot_update_another_organizations_row(): void
    {
        $this->actingAs($this->a['admin']);

        $affected = Task::where('id', $this->b['task']->id)->update(['title' => 'hijacked']);

        $this->assertSame(0, $affected, 'the update must match no rows');
        $this->assertSame(
            'Task B',
            DB::table('tasks')->where('id', $this->b['task']->id)->value('title'),
            'agency B\'s task must be untouched'
        );
    }

    public function test_a_user_cannot_mass_update_across_organizations(): void
    {
        $this->actingAs($this->a['admin']);

        // A deliberately unqualified update: without the scope this would
        // rewrite every task on the platform.
        Task::query()->update(['status' => 'cancelled']);

        $this->assertSame('cancelled', DB::table('tasks')->where('id', $this->a['task']->id)->value('status'));
        $this->assertSame('pending', DB::table('tasks')->where('id', $this->b['task']->id)->value('status'));
    }

    public function test_a_user_cannot_delete_another_organizations_row(): void
    {
        $this->actingAs($this->a['admin']);

        $deleted = Task::where('id', $this->b['task']->id)->delete();

        $this->assertSame(0, $deleted);
        $this->assertDatabaseHas('tasks', ['id' => $this->b['task']->id]);
    }

    public function test_a_user_cannot_mass_delete_across_organizations(): void
    {
        $this->actingAs($this->a['admin']);

        Task::query()->delete();

        $this->assertDatabaseMissing('tasks', ['id' => $this->a['task']->id]);
        $this->assertDatabaseHas('tasks', ['id' => $this->b['task']->id]);
    }

    public function test_findOrFail_on_another_organizations_row_throws(): void
    {
        $this->actingAs($this->a['admin']);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Task::findOrFail($this->b['task']->id);
    }

    public function test_relations_do_not_leak_across_organizations(): void
    {
        $this->actingAs($this->a['admin']);

        $unit = Unit::find($this->a['unit']->id);

        $this->assertSame(1, $unit->tasks()->count());
        // Only the PM is attached to a unit; admins and writers are not.
        $this->assertSame(1, $unit->users()->count());

        // Reaching for the other agency's task through a relation finds
        // nothing, even though the id is real.
        $this->assertNull($unit->tasks()->find($this->b['task']->id));

        $task = Task::find($this->a['task']->id);
        $this->assertSame(1, $task->files()->count());
        $this->assertNull($task->files()->find($this->b['file']->id));
    }

    public function test_route_model_binding_cannot_reach_another_organizations_task(): void
    {
        $this->actingAs($this->a['admin']);

        $this->get(route('tasks.show', $this->a['task']->id))->assertSuccessful();
        $this->get(route('tasks.show', $this->b['task']->id))->assertNotFound();
    }

    public function test_notifications_are_isolated(): void
    {
        // One notification for a user in each agency, written through the
        // same relation the database channel uses.
        foreach ([$this->a, $this->b] as $set) {
            TenantContext::actingAsOrganization($set['organization']->id, fn () => $set['pm']->notifications()->create([
                'id'   => (string) \Illuminate\Support\Str::uuid(),
                'type' => 'TestNotification',
                'data' => ['message' => 'hello'],
            ]));
        }

        $this->assertSame(2, DB::table('notifications')->count());
        $this->assertSame(0, DB::table('notifications')->whereNull('organization_id')->count());

        $this->actingAs($this->a['admin']);

        $this->assertSame(1, Notification::count());
        $this->assertSame(
            $this->a['organization']->id,
            (int) Notification::first()->organization_id
        );
    }

    public function test_validation_rejects_a_unit_belonging_to_another_organization(): void
    {
        $this->actingAs($this->a['admin']);

        // The validator queries the database directly and never sees Eloquent's
        // scopes, so this is the one isolation path the global scope cannot
        // cover on its own.
        $validator = validator(
            ['unit_id' => $this->b['unit']->id],
            ['unit_id' => \App\Rules\TenantExists::in('units')]
        );

        $this->assertTrue($validator->fails(), 'another agency\'s unit id must not validate');

        $ok = validator(
            ['unit_id' => $this->a['unit']->id],
            ['unit_id' => \App\Rules\TenantExists::in('units')]
        );

        $this->assertFalse($ok->fails(), 'the user\'s own unit must still validate');
    }
}
