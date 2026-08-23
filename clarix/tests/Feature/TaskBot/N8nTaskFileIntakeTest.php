<?php

namespace Tests\Feature\TaskBot;

use App\Models\Task;
use App\Models\TaskFile;
use App\Models\Unit;
use App\Models\User;
use App\Services\N8nTelegramLinkService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * POST /api/v1/n8n/telegram/tasks/{task}/files — the brief that came with the
 * submission.
 *
 * The interesting half is the task lookup. {task} is not model-bound, because
 * implicit binding would resolve it before anything had established who is
 * acting; these tests are largely about proving that another agency's id in the
 * path is a 404 rather than a target.
 */
class N8nTaskFileIntakeTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    protected N8nTelegramLinkService $links;

    /** @var array<string, mixed> */
    protected array $orgA;

    /** @var array<string, mixed> */
    protected array $orgB;

    protected const CHAT_A = '6000001';

    protected const CHAT_B = '6000002';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        Storage::fake('r2');

        config()->set('services.n8n.key', 'test-n8n-key');

        $this->links = app(N8nTelegramLinkService::class);
        $this->orgA  = $this->populate($this->makeOrganization('tf-a', 'Agency A'), 'A');
        $this->orgB  = $this->populate($this->makeOrganization('tf-b', 'Agency B'), 'B');

        $this->subscribeOrganization($this->orgA['organization'], 'pro');
        $this->subscribeOrganization($this->orgB['organization'], 'pro');

        $this->link($this->orgA['pm'], self::CHAT_A);
        $this->link($this->orgB['pm'], self::CHAT_B);
    }

    private function link(User $user, string $chatId): void
    {
        $this->links->verify($this->links->issueCode($user), $chatId);
    }

    /**
     * A fresh idempotency key per call, because that is what a correctly built
     * workflow does — one key per submission. The tests that are about the key
     * itself pass their own.
     *
     * @param array<int, mixed> $files
     */
    private function attach(array $files, ?Task $task = null, string $chatId = self::CHAT_A, ?string $key = null)
    {
        $task ??= $this->orgA['task'];

        return $this->post(
            '/api/v1/n8n/telegram/tasks/'.$task->id.'/files',
            ['chat_id' => $chatId, 'files' => $files],
            [
                'X-N8n-Key'       => 'test-n8n-key',
                'Idempotency-Key' => $key ?? (string) \Illuminate\Support\Str::uuid(),
                'Accept'          => 'application/json',
            ]
        );
    }

    // ── The happy path ───────────────────────────────────────────────────────

    public function test_a_linked_pm_can_attach_the_submissions_file(): void
    {
        $this->attach([UploadedFile::fake()->create('brief.pdf', 40)])
            ->assertCreated()
            ->assertJsonPath('0.original_name', 'brief.pdf')
            ->assertJsonCount(1);
    }

    /** Flat, like everything else the pipeline reads. */
    public function test_the_response_is_a_bare_array(): void
    {
        $body = $this->attach([UploadedFile::fake()->create('brief.pdf', 10)])
            ->assertCreated()
            ->json();

        $this->assertArrayNotHasKey('data', $body);
        $this->assertArrayHasKey(0, $body);
        $this->assertArrayNotHasKey('file_path', $body[0], 'The bucket key is not the caller\'s business.');
    }

    public function test_the_object_lands_under_the_tasks_own_prefix(): void
    {
        $this->attach([UploadedFile::fake()->create('brief.pdf', 10)])->assertCreated();

        $file = TenantContext::runWithoutScope(fn () => TaskFile::query()->latest('id')->first());

        $this->assertStringStartsWith($this->orgA['task']->storagePrefix(), $file->file_path);
        Storage::disk('r2')->assertExists($file->file_path);
    }

    public function test_the_upload_is_recorded_against_the_task(): void
    {
        $this->attach([UploadedFile::fake()->create('brief.pdf', 10)])->assertCreated();

        $file = TenantContext::runWithoutScope(fn () => TaskFile::query()->latest('id')->first());

        $this->assertSame((int) $this->orgA['task']->id, (int) $file->task_id);
        $this->assertSame((int) $this->orgA['pm']->id, (int) $file->uploaded_by);
        $this->assertSame((int) $this->orgA['organization']->id, (int) $file->organization_id);
    }

    // ── Who may attach ───────────────────────────────────────────────────────

    public function test_an_unauthenticated_request_is_refused(): void
    {
        $this->post(
            '/api/v1/n8n/telegram/tasks/'.$this->orgA['task']->id.'/files',
            ['chat_id' => self::CHAT_A, 'files' => [UploadedFile::fake()->create('b.pdf', 5)]],
            ['Idempotency-Key' => (string) \Illuminate\Support\Str::uuid(), 'Accept' => 'application/json']
        )->assertUnauthorized();
    }

    public function test_an_unlinked_chat_cannot_attach(): void
    {
        $before = TenantContext::runWithoutScope(fn () => TaskFile::query()->count());

        $this->attach([UploadedFile::fake()->create('b.pdf', 5)], null, '9999999')
            ->assertNotFound()
            ->assertJsonPath('linked', false);

        $this->assertSame(
            $before,
            TenantContext::runWithoutScope(fn () => TaskFile::query()->count())
        );
    }

    /**
     * The reason {task} is not model-bound. A bound task would be fetched with
     * no tenant context, and another agency's id would resolve happily with
     * only a policy standing between it and an upload.
     */
    public function test_another_agencys_task_is_not_found(): void
    {
        $this->attach([UploadedFile::fake()->create('b.pdf', 5)], $this->orgB['task'], self::CHAT_A)
            ->assertNotFound();

        Storage::disk('r2')->assertDirectoryEmpty('/');
    }

    /** And it must read as "no such task", never as "not yours". */
    public function test_another_agencys_task_is_indistinguishable_from_a_missing_one(): void
    {
        $foreign = $this->attach([UploadedFile::fake()->create('b.pdf', 5)], $this->orgB['task'], self::CHAT_A);
        $missing = $this->post(
            '/api/v1/n8n/telegram/tasks/99999999/files',
            ['chat_id' => self::CHAT_A, 'files' => [UploadedFile::fake()->create('b.pdf', 5)]],
            [
                'X-N8n-Key'       => 'test-n8n-key',
                'Idempotency-Key' => (string) \Illuminate\Support\Str::uuid(),
                'Accept'          => 'application/json',
            ]
        );

        $this->assertSame($foreign->status(), $missing->status());
        $this->assertSame($foreign->json('message'), $missing->json('message'));
    }

    /**
     * Ownership within an agency still applies. TaskPolicy::uploadFiles() asks
     * whether the actor owns the task, and a PM owns their own unit's work.
     */
    public function test_a_pm_cannot_attach_to_another_units_task_in_the_same_agency(): void
    {
        [$otherUnitTask, $otherPm] = TenantContext::actingAsOrganization(
            $this->orgA['organization']->id,
            function () {
                $unit = Unit::create(['name' => 'Other Unit']);

                $pm = User::factory()->create([
                    'name'    => 'Other PM',
                    'email'   => 'other.pm@example.test',
                    'role'    => 'pm',
                    'unit_id' => $unit->id,
                ]);

                $task = Task::create([
                    'title'         => 'Not yours',
                    'task_code'     => 'OTHER_UNIT',
                    'unit_id'       => $unit->id,
                    'created_by'    => $pm->id,
                    'pm_id'         => $pm->id,
                    'priority'      => 'medium',
                    'status'        => 'pending',
                    'deadline'      => now()->addDays(7),
                    'credit_amount' => 10.00,
                ]);

                return [$task, $pm];
            }
        );

        // The intruder is the agency's *other* PM, linked to their own chat.
        $this->link($otherPm, '6000003');

        $this->attach([UploadedFile::fake()->create('b.pdf', 5)], $this->orgA['task'], '6000003')
            ->assertForbidden();

        $this->assertNotNull($otherUnitTask);
    }

    public function test_a_suspended_organization_cannot_attach(): void
    {
        TenantContext::actingAsOrganization($this->orgA['organization']->id, function () {
            \App\Models\OrganizationSubscription::query()->update(['status' => 'suspended']);
        });

        $this->attach([UploadedFile::fake()->create('b.pdf', 5)])->assertStatus(402);
    }

    // ── What may be attached ─────────────────────────────────────────────────

    public function test_multiple_files_are_all_attached(): void
    {
        $this->attach([
            UploadedFile::fake()->create('one.pdf', 5),
            UploadedFile::fake()->create('two.docx', 5),
        ])->assertCreated()->assertJsonCount(2);
    }

    public function test_a_disallowed_file_type_is_rejected(): void
    {
        $this->attach([UploadedFile::fake()->create('payload.exe', 5)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('files.0');
    }

    /** An image by extension, a script container by format. */
    public function test_svg_is_not_an_accepted_image(): void
    {
        $this->attach([UploadedFile::fake()->create('logo.svg', 5)])
            ->assertStatus(422);
    }

    public function test_an_oversized_file_is_rejected(): void
    {
        $tooBig = ((int) config('storage.api_max_file_kb')) + 1;

        $this->attach([UploadedFile::fake()->create('huge.pdf', $tooBig)])
            ->assertStatus(422);
    }

    public function test_too_many_files_are_rejected(): void
    {
        $files = [];

        for ($i = 0; $i <= (int) config('storage.api_max_files'); $i++) {
            $files[] = UploadedFile::fake()->create("f{$i}.pdf", 1);
        }

        $this->attach($files)->assertStatus(422)->assertJsonValidationErrors('files');
    }

    public function test_a_request_with_no_files_is_rejected(): void
    {
        $this->attach([])->assertStatus(422)->assertJsonValidationErrors('files');
    }

    public function test_a_rejected_upload_stores_nothing(): void
    {
        $this->attach([UploadedFile::fake()->create('payload.exe', 5)])->assertStatus(422);

        Storage::disk('r2')->assertDirectoryEmpty('/');
    }

    /** Files count against the agency's allowance here as anywhere else. */
    public function test_an_upload_past_the_allowance_is_refused(): void
    {
        config()->set('storage.plan_caps_gb.pro', 0);

        $this->attach([UploadedFile::fake()->create('brief.pdf', 1024)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('files');

        Storage::disk('r2')->assertDirectoryEmpty('/');
    }
}
