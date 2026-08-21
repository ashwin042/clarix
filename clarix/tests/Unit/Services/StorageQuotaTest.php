<?php

namespace Tests\Unit\Services;

use App\Models\Organization;
use App\Services\OrganizationStorage;
use App\Services\PlanFeatures;
use App\Services\StorageQuota;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * The arithmetic behind the first hard storage limit in the application.
 */
class StorageQuotaTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /**
     * BuildsOrganizations::populate() attaches one 100-byte file to the task it
     * creates, and TaskFileObserver charges it to the unit on the way in. So a
     * freshly populated organization is not empty, and every expectation here
     * has to allow for it. Named rather than inlined so that the day the
     * fixture changes size, the failure points at this line.
     */
    private const FIXTURE_BYTES = 100;

    protected Organization $organization;
    protected array $data;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = $this->makeOrganization('acme', 'Acme Agency');
        $this->data         = $this->populate($this->organization, 'acme');

        PlanFeatures::flush();
    }

    protected function quota(): StorageQuota
    {
        return app(StorageQuota::class);
    }

    /** Write a rollup row directly, the way the observer would have. */
    protected function hold(int $unitId, int $bytes): void
    {
        DB::table('unit_storage_usage')->updateOrInsert(
            ['unit_id' => $unitId],
            [
                'organization_id' => $this->organization->id,
                'bytes_used'      => $bytes,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]
        );
    }

    public function test_a_fresh_organization_has_its_cap_less_what_it_already_holds(): void
    {
        // No subscription, so the smallest tier: 5GB, less the fixture's file.
        $this->assertSame(
            5 * OrganizationStorage::BYTES_PER_GB - self::FIXTURE_BYTES,
            $this->quota()->remainingBytesFor($this->organization->id)
        );
    }

    public function test_held_bytes_reduce_what_remains(): void
    {
        // hold() overwrites the rollup row, replacing the fixture's bytes.
        $this->hold($this->data['unit']->id, 2 * OrganizationStorage::BYTES_PER_GB);

        $this->assertSame(
            3 * OrganizationStorage::BYTES_PER_GB,
            $this->quota()->remainingBytesFor($this->organization->id)
        );
    }

    public function test_an_organization_past_its_cap_reads_zero_not_negative(): void
    {
        $this->hold($this->data['unit']->id, 9 * OrganizationStorage::BYTES_PER_GB);

        $this->assertSame(0, $this->quota()->remainingBytesFor($this->organization->id));
    }

    public function test_an_upload_that_fits_is_allowed(): void
    {
        $this->assertFalse($this->quota()->wouldExceed($this->organization->id, 1024));
    }

    public function test_an_upload_larger_than_the_remainder_is_refused(): void
    {
        $this->hold($this->data['unit']->id, 5 * OrganizationStorage::BYTES_PER_GB);

        $this->assertTrue($this->quota()->wouldExceed($this->organization->id, 1));
    }

    /**
     * Exactly filling the cap is allowed; one byte past it is not. Pinned
     * because an off-by-one here is invisible until an agency sits on the
     * boundary.
     */
    public function test_the_boundary_is_inclusive(): void
    {
        $this->hold($this->data['unit']->id, 4 * OrganizationStorage::BYTES_PER_GB);

        $remaining = OrganizationStorage::BYTES_PER_GB;

        $this->assertFalse($this->quota()->wouldExceed($this->organization->id, $remaining));
        $this->assertTrue($this->quota()->wouldExceed($this->organization->id, $remaining + 1));
    }

    public function test_a_zero_byte_upload_is_never_refused(): void
    {
        $this->hold($this->data['unit']->id, 99 * OrganizationStorage::BYTES_PER_GB);

        $this->assertFalse($this->quota()->wouldExceed($this->organization->id, 0));
    }

    public function test_a_per_organization_override_raises_the_limit(): void
    {
        DB::table('organizations')
            ->where('id', $this->organization->id)
            ->update(['storage_cap_override_gb' => 20]);

        $this->hold($this->data['unit']->id, 5 * OrganizationStorage::BYTES_PER_GB);

        $this->assertSame(
            15 * OrganizationStorage::BYTES_PER_GB,
            $this->quota()->remainingBytesFor($this->organization->id)
        );
    }

    /**
     * The allowance is the organization's, not the unit's — every unit inside
     * an agency draws on one pool.
     */
    public function test_bytes_held_by_one_unit_reduce_what_another_may_upload(): void
    {
        $second = TenantContext::actingAsOrganization(
            $this->organization->id,
            fn () => \App\Models\Unit::create(['name' => 'Second Unit'])
        );

        $this->hold($this->data['unit']->id, 3 * OrganizationStorage::BYTES_PER_GB);
        $this->hold($second->id, 1 * OrganizationStorage::BYTES_PER_GB);

        $this->assertSame(
            1 * OrganizationStorage::BYTES_PER_GB,
            $this->quota()->remainingBytesFor($this->organization->id)
        );
    }

    public function test_another_organizations_usage_does_not_count(): void
    {
        $other     = $this->makeOrganization('globex', 'Globex Agency');
        $otherData = $this->populate($other, 'globex');

        DB::table('unit_storage_usage')->updateOrInsert(
            ['unit_id' => $otherData['unit']->id],
            [
                'organization_id' => $other->id,
                'bytes_used'      => 5 * OrganizationStorage::BYTES_PER_GB,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]
        );

        // Acme still holds only its own fixture file; Globex's 5GB is
        // invisible to it.
        $this->assertSame(
            5 * OrganizationStorage::BYTES_PER_GB - self::FIXTURE_BYTES,
            $this->quota()->remainingBytesFor($this->organization->id)
        );
    }
}
