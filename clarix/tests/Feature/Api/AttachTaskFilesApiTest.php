<?php

namespace Tests\Feature\Api;

use App\Models\Task;
use App\Models\TaskFile;
use App\Models\Unit;
use App\Models\User;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * POST /api/v1/tasks/{task}/files — attachments for a task that already exists.
 */
class AttachTaskFilesApiTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    protected array $acme;
    protected array $globex;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        Storage::fake('r2');

        $this->acme   = $this->populate($this->makeOrganization('acme', 'Acme Agency'), 'acme');
        $this->globex = $this->populate($this->makeOrganization('globex', 'Globex Agency'), 'globex');
    }

    private function url(?Task $task = null): string
    {
        return '/api/v1/tasks/'.($task ?? $this->acme['task'])->id.'/files';
    }

    /** @param array<int, mixed> $files */
    private function attach(array $files, ?Task $task = null)
    {
        return $this->post($this->url($task), ['files' => $files], ['Accept' => 'application/json']);
    }

    public function test_service_account_can_attach_a_file(): void
    {
        Sanctum::actingAs($this->acme['pm'], ['files:write']);

        $this->attach([UploadedFile::fake()->create('brief.pdf', 40)])
            ->assertCreated()
            ->assertJsonPath('data.0.original_name', 'brief.pdf')
            ->assertJsonCount(1, 'data');
    }

    public function test_the_object_lands_under_the_tasks_unit_prefix(): void
    {
        $task = $this->acme['task'];

        Sanctum::actingAs($this->acme['pm'], ['files:write']);

        $this->attach([UploadedFile::fake()->create('brief.pdf', 10)])->assertCreated();

        $stored = Storage::disk('r2')->allFiles($task->storagePrefix());

        $this->assertCount(1, $stored);
        $this->assertStringStartsWith('task-files/'.$task->unit_id.'/'.$task->task_code, $stored[0]);
    }

    public function test_multiple_files_are_all_attached(): void
    {
        Sanctum::actingAs($this->acme['pm'], ['files:write']);

        $this->attach([
            UploadedFile::fake()->create('a.pdf', 10),
            UploadedFile::fake()->create('b.docx', 10),
            UploadedFile::fake()->image('c.png'),
        ])->assertCreated()->assertJsonCount(3, 'data');

        // The fixture already attached one file, so four in total.
        $this->assertSame(4, $this->acme['task']->fresh()->regularFiles()->count());
    }

    public function test_the_upload_counts_against_the_units_storage_total(): void
    {
        $task = $this->acme['task'];

        $before = (int) DB::table('unit_storage_usage')
            ->where('unit_id', $task->unit_id)->value('bytes_used');

        Sanctum::actingAs($this->acme['pm'], ['files:write']);

        $this->attach([UploadedFile::fake()->create('brief.pdf', 10)])->assertCreated();

        $after = (int) DB::table('unit_storage_usage')
            ->where('unit_id', $task->unit_id)->value('bytes_used');

        $this->assertGreaterThan($before, $after);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->attach([UploadedFile::fake()->create('brief.pdf', 10)])->assertUnauthorized();
    }

    /**
     * A token minted only to file tasks must not also be able to write objects
     * into the agency's bucket.
     */
    public function test_a_tasks_create_token_cannot_attach_files(): void
    {
        Sanctum::actingAs($this->acme['pm'], ['tasks:create']);

        $this->attach([UploadedFile::fake()->create('brief.pdf', 10)])->assertForbidden();
    }

    /**
     * The tenant scope on route binding stops this before the policy is even
     * consulted, so it is a 404 rather than a 403 — the task does not exist as
     * far as this token is concerned.
     */
    public function test_cannot_attach_to_another_organizations_task(): void
    {
        Sanctum::actingAs($this->acme['pm'], ['files:write']);

        $this->attach([UploadedFile::fake()->create('brief.pdf', 10)], $this->globex['task'])
            ->assertNotFound();

        $this->assertSame(
            1,
            TenantContext::runWithoutScope(
                fn () => TaskFile::where('task_id', $this->globex['task']->id)->count()
            ),
            'Globex should still hold only its own fixture file.'
        );
    }

    /**
     * The ownership half of TaskPolicy::uploadFiles(). This is the check the
     * web upload route does not make, and the reason this endpoint defers to
     * the policy rather than to the permission alone.
     */
    public function test_a_pm_cannot_attach_to_another_units_task_in_the_same_organization(): void
    {
        $stranger = TenantContext::actingAsOrganization(
            $this->acme['organization']->id,
            function () {
                $unit = Unit::create(['name' => 'Other Unit']);

                return User::factory()->create([
                    'name'    => 'Stranger PM',
                    'email'   => 'stranger@example.test',
                    'role'    => 'pm',
                    'unit_id' => $unit->id,
                ]);
            }
        );

        Sanctum::actingAs($stranger, ['files:write']);

        $this->attach([UploadedFile::fake()->create('brief.pdf', 10)])->assertForbidden();
    }

    public function test_a_disallowed_file_type_is_rejected(): void
    {
        Sanctum::actingAs($this->acme['pm'], ['files:write']);

        $this->attach([UploadedFile::fake()->create('payload.exe', 10)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['files.0']);
    }

    /**
     * SVG is an image by extension and a script container by format, and these
     * objects are served back out of storage. Pinned so it cannot drift back
     * into the allowlist unnoticed.
     */
    public function test_svg_is_not_an_accepted_image(): void
    {
        Sanctum::actingAs($this->acme['pm'], ['files:write']);

        $this->attach([UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['files.0']);
    }

    public function test_an_oversized_file_is_rejected(): void
    {
        Sanctum::actingAs($this->acme['pm'], ['files:write']);

        $this->attach([UploadedFile::fake()->create('huge.pdf', 51201)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['files.0']);
    }

    public function test_too_many_files_are_rejected(): void
    {
        Sanctum::actingAs($this->acme['pm'], ['files:write']);

        $files = [];

        for ($i = 0; $i < 11; $i++) {
            $files[] = UploadedFile::fake()->create('f'.$i.'.pdf', 1);
        }

        $this->attach($files)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['files']);
    }

    public function test_a_request_with_no_files_is_rejected(): void
    {
        Sanctum::actingAs($this->acme['pm'], ['files:write']);

        $this->post($this->url(), [], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['files']);
    }

    /**
     * Nothing may reach R2 when validation refuses the request — a rejected
     * upload that had already written its object would leave an orphan behind.
     */
    public function test_a_rejected_upload_stores_nothing(): void
    {
        Sanctum::actingAs($this->acme['pm'], ['files:write']);

        $before = Storage::disk('r2')->allFiles($this->acme['task']->storagePrefix());

        $this->attach([UploadedFile::fake()->create('payload.exe', 10)])->assertStatus(422);

        $this->assertSame($before, Storage::disk('r2')->allFiles($this->acme['task']->storagePrefix()));
    }
}
