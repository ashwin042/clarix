<?php

namespace Tests\Feature\Api;

use App\Models\Task;
use App\Models\Unit;
use App\Services\OrganizationStorage;
use App\Services\PlanFeatures;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * The storage allowance, enforced for the first time anywhere in the
 * application.
 *
 * Only the API path is enforced. The browser upload path is deliberately left
 * as it was and is covered by nothing here, which is the staged rollout rather
 * than an omission — see StorageQuota.
 */
class AttachTaskFilesQuotaTest extends TestCase
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

        PlanFeatures::flush();
    }

    /** Park bytes on a unit's rollup row, as the observer would have. */
    private function hold(int $unitId, int $organizationId, int $bytes): void
    {
        DB::table('unit_storage_usage')->updateOrInsert(
            ['unit_id' => $unitId],
            [
                'organization_id' => $organizationId,
                'bytes_used'      => $bytes,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]
        );
    }

    /** @param array<int, mixed> $files */
    private function attach(array $files, ?Task $task = null)
    {
        $task ??= $this->acme['task'];

        return $this->post('/api/v1/tasks/'.$task->id.'/files', ['files' => $files], ['Accept' => 'application/json']);
    }

    public function test_an_upload_within_the_allowance_is_accepted(): void
    {
        Sanctum::actingAs($this->acme['pm'], ['files:write']);

        $this->attach([UploadedFile::fake()->create('brief.pdf', 100)])->assertCreated();
    }

    public function test_an_upload_past_the_allowance_is_refused_with_422(): void
    {
        // The whole 5GB default cap, already spoken for.
        $this->hold($this->acme['unit']->id, $this->acme['organization']->id, 5 * OrganizationStorage::BYTES_PER_GB);

        Sanctum::actingAs($this->acme['pm'], ['files:write']);

        $this->attach([UploadedFile::fake()->create('brief.pdf', 100)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['files']);
    }

    /**
     * The wording matters as much as the status.
     *
     * Whoever hits this first is an integrator with no view of the storage
     * page, and a generic "the files field is invalid" on a request whose files
     * are each individually valid reads as a bug in the endpoint rather than as
     * a deliberate refusal.
     */
    public function test_the_refusal_says_the_organization_is_over_its_allowance(): void
    {
        $this->hold($this->acme['unit']->id, $this->acme['organization']->id, 5 * OrganizationStorage::BYTES_PER_GB);

        Sanctum::actingAs($this->acme['pm'], ['files:write']);

        $message = $this->attach([UploadedFile::fake()->create('brief.pdf', 100)])
            ->assertStatus(422)
            ->json('errors.files.0');

        $this->assertStringContainsString('storage allowance', $message);
        $this->assertStringContainsString('5 GB', $message);
        $this->assertMatchesRegularExpression('/holds .* of 5 GB/', $message);
    }

    public function test_nothing_is_stored_when_the_quota_refuses(): void
    {
        $this->hold($this->acme['unit']->id, $this->acme['organization']->id, 5 * OrganizationStorage::BYTES_PER_GB);

        Sanctum::actingAs($this->acme['pm'], ['files:write']);

        $before = Storage::disk('r2')->allFiles($this->acme['task']->storagePrefix());

        $this->attach([UploadedFile::fake()->create('brief.pdf', 100)])->assertStatus(422);

        $this->assertSame($before, Storage::disk('r2')->allFiles($this->acme['task']->storagePrefix()));
    }

    /**
     * The limit is about the total arriving in one request. Each of these is
     * individually fine; together they are not, which a per-file rule would
     * have waved straight through.
     */
    public function test_files_are_measured_together_not_individually(): void
    {
        // 5GB cap, leaving roughly 30MB of headroom.
        $this->hold(
            $this->acme['unit']->id,
            $this->acme['organization']->id,
            5 * OrganizationStorage::BYTES_PER_GB - (30 * 1024 * 1024)
        );

        Sanctum::actingAs($this->acme['pm'], ['files:write']);

        // Four 20MB files: none exceeds the 50MB per-file limit, but 80MB
        // together comfortably exceeds the 30MB that remains.
        $this->attach([
            UploadedFile::fake()->create('a.pdf', 20480),
            UploadedFile::fake()->create('b.pdf', 20480),
            UploadedFile::fake()->create('c.pdf', 20480),
            UploadedFile::fake()->create('d.pdf', 20480),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['files']);
    }

    /**
     * The allowance belongs to the organization, so bytes held by one unit
     * reduce what a task in another unit may take on.
     */
    public function test_another_units_usage_counts_against_the_same_organization(): void
    {
        $otherUnit = \App\Services\TenantContext::actingAsOrganization(
            $this->acme['organization']->id,
            fn () => Unit::create(['name' => 'Hungry Unit'])
        );

        $this->hold($otherUnit->id, $this->acme['organization']->id, 5 * OrganizationStorage::BYTES_PER_GB);

        Sanctum::actingAs($this->acme['pm'], ['files:write']);

        $this->attach([UploadedFile::fake()->create('brief.pdf', 100)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['files']);
    }

    /**
     * And one agency's usage must never constrain another's.
     */
    public function test_another_organizations_usage_does_not_constrain_this_one(): void
    {
        $this->hold($this->globex['unit']->id, $this->globex['organization']->id, 5 * OrganizationStorage::BYTES_PER_GB);

        Sanctum::actingAs($this->acme['pm'], ['files:write']);

        $this->attach([UploadedFile::fake()->create('brief.pdf', 100)])->assertCreated();
    }

    /**
     * A hand-set override is how the extra-storage arrangement is applied, and
     * it has to reach the enforcement path, not just the reporting page.
     */
    public function test_a_per_organization_override_raises_what_is_accepted(): void
    {
        $this->hold($this->acme['unit']->id, $this->acme['organization']->id, 5 * OrganizationStorage::BYTES_PER_GB);

        Sanctum::actingAs($this->acme['pm'], ['files:write']);

        // Refused at the 5GB plan cap.
        $this->attach([UploadedFile::fake()->create('brief.pdf', 100)])->assertStatus(422);

        DB::table('organizations')
            ->where('id', $this->acme['organization']->id)
            ->update(['storage_cap_override_gb' => 50]);

        // Accepted once the agency is given a larger allowance.
        $this->attach([UploadedFile::fake()->create('brief.pdf', 100)])->assertCreated();
    }
}
