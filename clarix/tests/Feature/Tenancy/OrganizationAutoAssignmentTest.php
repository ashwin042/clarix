<?php

namespace Tests\Feature\Tenancy;

use App\Models\Issue;
use App\Models\Payment;
use App\Models\Task;
use App\Models\TaskFile;
use App\Models\TaskNote;
use App\Models\Unit;
use App\Models\User;
use App\Services\StorageUsageService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * organization_id is NOT NULL and is never mass-assignable, so every write
 * path has to acquire it from the server's own idea of who is acting. These
 * tests pin that down for each shape of write the application makes.
 */
class OrganizationAutoAssignmentTest extends TestCase
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

    public function test_new_records_take_the_acting_users_organization(): void
    {
        $this->actingAs($this->a['admin']);

        $unit = Unit::create(['name' => 'Fresh Unit']);
        $this->assertSame($this->a['organization']->id, $unit->organization_id);

        $task = Task::create([
            'title'         => 'Fresh Task',
            'task_code'     => 'FRESH_1',
            'unit_id'       => $unit->id,
            'created_by'    => $this->a['admin']->id,
            'pm_id'         => $this->a['pm']->id,
            'priority'      => 'low',
            'status'        => 'pending',
            'deadline'      => now()->addDay(),
            'credit_amount' => 1,
        ]);
        $this->assertSame($this->a['organization']->id, $task->organization_id);

        $note = TaskNote::create([
            'task_id'    => $task->id,
            'note'       => 'A note',
            'created_by' => $this->a['admin']->id,
        ]);
        $this->assertSame($this->a['organization']->id, $note->organization_id);

        $file = $task->files()->create([
            'file_path'     => 'task-files/x/FRESH_1/a.pdf',
            'original_name' => 'a.pdf',
            'file_size'     => 10,
            'mime_type'     => 'application/pdf',
            'uploaded_by'   => $this->a['admin']->id,
        ]);
        $this->assertSame($this->a['organization']->id, $file->organization_id);

        $issue = Issue::create([
            'title'      => 'Fresh Issue',
            'message'    => 'text',
            'priority'   => 'low',
            'status'     => 'open',
            'created_by' => $this->a['admin']->id,
        ]);
        $this->assertSame($this->a['organization']->id, $issue->organization_id);

        $payment = Payment::create([
            'payer_name'     => 'Someone',
            'amount'         => 1,
            'total_credit'   => 1,
            'unit_id'        => $unit->id,
            'from_date'      => now()->subDay(),
            'to_date'        => now(),
            'payment_method' => 'cash',
            'created_by'     => $this->a['admin']->id,
        ]);
        $this->assertSame($this->a['organization']->id, $payment->organization_id);

        $user = User::factory()->create(['role' => 'writer', 'email' => 'new.writer@example.test']);
        $this->assertSame($this->a['organization']->id, $user->organization_id);
    }

    public function test_two_users_creating_the_same_shape_of_record_get_different_owners(): void
    {
        $this->actingAs($this->a['admin']);
        $unitA = Unit::create(['name' => 'From A']);

        $this->actingAs($this->b['admin']);
        $unitB = Unit::create(['name' => 'From B']);

        $this->assertSame($this->a['organization']->id, $unitA->organization_id);
        $this->assertSame($this->b['organization']->id, $unitB->organization_id);
        $this->assertNotSame($unitA->organization_id, $unitB->organization_id);
    }

    /**
     * The reason organization_id was kept out of every $fillable in phase 1:
     * a request that supplies it must not be able to plant a record in
     * somebody else's agency.
     */
    public function test_organization_id_cannot_be_mass_assigned(): void
    {
        $this->actingAs($this->a['admin']);

        $unit = Unit::create([
            'name'            => 'Attempted Hijack',
            'organization_id' => $this->b['organization']->id,
        ]);

        $this->assertSame(
            $this->a['organization']->id,
            $unit->organization_id,
            'a supplied organization_id must be ignored in favour of the acting user\'s'
        );

        $task = Task::create([
            'title'           => 'Attempted Hijack',
            'task_code'       => 'HIJACK_1',
            'organization_id' => $this->b['organization']->id,
            'unit_id'         => $unit->id,
            'created_by'      => $this->a['admin']->id,
            'pm_id'           => $this->a['pm']->id,
            'priority'        => 'low',
            'status'          => 'pending',
            'deadline'        => now()->addDay(),
            'credit_amount'   => 1,
        ]);

        $this->assertSame($this->a['organization']->id, $task->organization_id);
    }

    /**
     * The nightly reconcile writes storage rollups from the console, where
     * nobody is authenticated. It has to resolve the owner from the unit
     * instead, or the NOT NULL column would stop it.
     */
    public function test_storage_rollups_written_from_the_console_are_owned(): void
    {
        $unitId = $this->a['unit']->id;

        TenantContext::runWithoutScope(function () use ($unitId) {
            app(StorageUsageService::class)->set($unitId, 2048);
        });

        $row = DB::table('unit_storage_usage')->where('unit_id', $unitId)->first();

        $this->assertNotNull($row);
        $this->assertSame(2048, (int) $row->bytes_used);
        $this->assertSame(
            $this->a['organization']->id,
            (int) $row->organization_id,
            'the rollup must be owned by the unit\'s organization, not left null'
        );
    }

    public function test_child_records_fall_back_to_their_parent_when_nobody_is_authenticated(): void
    {
        $task = $this->b['task'];

        // No authenticated user and no forced organization: the only thing
        // left to resolve the owner from is the parent task.
        $file = TenantContext::actingAsOrganization(null, fn () => TaskFile::create([
            'task_id'       => $task->id,
            'file_path'     => 'task-files/console/doc.pdf',
            'original_name' => 'doc.pdf',
            'file_size'     => 5,
            'mime_type'     => 'application/pdf',
            'uploaded_by'   => $this->b['pm']->id,
        ]));

        $this->assertSame(
            $this->b['organization']->id,
            (int) $file->organization_id,
            'the file should inherit its task\'s organization'
        );
    }
}
