<?php

namespace Tests\Feature\Authorization;

use App\Models\Permission;
use App\Models\RolePermission;
use App\Services\PermissionService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * flushAll() has to clear what it did not put there.
 *
 * allowedFor() writes to two places: a static per-process memo and the
 * persistent cache store, the latter for five minutes. flushAll() walked the
 * memo's keys and forgot those — which works inside a request that had already
 * read them, and does nothing at all anywhere else.
 *
 * Anywhere else is where it matters. A migration, an artisan command and a
 * queue worker all start with an empty memo, so a permission change made by
 * one of them left every stale entry sitting in the cache store, and the
 * change appeared not to have taken effect until the TTL lapsed. That is
 * precisely the shape of "I migrated and the panel still doesn't show it".
 */
class PermissionCacheFlushTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        PermissionService::flushAll();

        $this->org = $this->populate($this->makeOrganization('pcf', 'Agency A'), 'A');
    }

    /**
     * The regression, stated the way it actually happens: something else warms
     * the cache, this process never reads it, and then flushes.
     */
    public function test_flush_all_clears_an_entry_this_process_never_read(): void
    {
        $organizationId = $this->org['organization']->id;
        $key = "permissions_role_{$organizationId}_pm";

        // Warm it the way a previous request would have, without going through
        // allowedFor() — so the static memo stays empty, as it would in a
        // fresh process.
        Cache::put($key, ['tasks.view'], 300);
        $this->assertTrue(Cache::has($key));

        PermissionService::flushAll();

        $this->assertFalse(
            Cache::has($key),
            'flushAll() must clear the persistent entry even with an empty memo'
        );
    }

    /**
     * The consequence, end to end: a permission written directly to the
     * database is visible immediately after a flush, rather than after the
     * five-minute TTL.
     */
    public function test_a_grant_written_behind_the_service_is_visible_after_a_flush(): void
    {
        $pm = $this->org['pm'];

        $this->assertFalse($pm->hasPermission('users.update'));

        TenantContext::actingAsOrganization($this->org['organization']->id, function () {
            RolePermission::updateOrCreate(
                ['role' => 'pm', 'permission_id' => Permission::where('name', 'users.update')->firstOrFail()->id],
                ['allowed' => true]
            );
        });

        PermissionService::flushAll();

        $this->assertTrue(
            $pm->fresh()->hasPermission('users.update'),
            'the grant should be live once the cache is flushed'
        );
    }
}
