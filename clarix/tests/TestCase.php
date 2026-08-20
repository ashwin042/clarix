<?php

namespace Tests;

use App\Models\Organization;
use App\Services\PermissionService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    /**
     * Give every test a default organization to build data in.
     *
     * organization_id is NOT NULL and is filled from whoever is acting, so a
     * test that creates a unit or a task before signing anyone in would
     * otherwise have no owner to record. Pointing the process at the founding
     * organization makes those creates behave exactly as they do for a signed
     * in user, without every test having to authenticate first.
     *
     * This is a fallback for unauthenticated contexts only. The moment a test
     * calls actingAs(), that user's own organization takes over, so tests that
     * assert isolation between two organizations are unaffected by it.
     *
     * TenantContext keeps static state, and the test process outlives any one
     * test, so it is reset at both ends to stop one test leaking into the next.
     */
    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::reset();

        // PermissionService memoises each role it resolves, and the memo is
        // static, so it outlives the database RefreshDatabase rebuilds under
        // it. A test that asked about a role before the permissions were
        // seeded would otherwise hand that empty answer to every test after
        // it, in a different file, as a 403 with no obvious cause.
        PermissionService::flushAll();

        if (Schema::hasTable('organizations')) {
            TenantContext::useOrganization(Organization::query()->orderBy('id')->value('id'));
        }
    }

    protected function tearDown(): void
    {
        TenantContext::reset();

        parent::tearDown();
    }
}
