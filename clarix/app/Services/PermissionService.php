<?php

namespace App\Services;

use App\Models\RolePermission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves what a role may do, for one agency at a time.
 *
 * Every key here carries the organization. Before it did not, and the
 * consequence was not theoretical: the cache store is `database` in
 * production, shared by every request of every agency, so the first agency to
 * ask for "pm" wrote its own answer under a global key and every other agency
 * read it back for the next five minutes. Two agencies with different
 * permission maps would have served each other's.
 */
class PermissionService
{
    /**
     * Resolved permission names per organization and role, for the lifetime of
     * this request.
     *
     * Held on the class rather than in a function static so that flushFor()
     * can actually clear it. As a function static it was unreachable, which
     * meant a role read once kept its answer for the rest of the process even
     * after an admin toggled a permission.
     *
     * @var array<string, list<string>>
     */
    protected static array $memo = [];

    /**
     * Allowed permission names for a role within one organization.
     *
     * The organization is passed in rather than read from TenantContext, and
     * the query lifts the global scope to filter explicitly. Both are
     * deliberate: a user's permissions must come from that user's own agency,
     * whoever happens to be acting. A queued job, a console command or a
     * superadmin acting into a different agency would otherwise resolve the
     * ambient organization and answer for the wrong one.
     *
     * A null organization — the platform superadmin, who belongs to no agency
     * — has no permission map by definition and is answered with nothing.
     *
     * @return list<string>
     */
    public static function allowedFor(string $role, ?int $organizationId): array
    {
        if ($organizationId === null) {
            return [];
        }

        $key = self::keyFor($role, $organizationId);

        if (isset(self::$memo[$key])) {
            return self::$memo[$key];
        }

        // Use a short-lived cache (5 minutes) to avoid repeated DB hits
        self::$memo[$key] = Cache::remember(
            $key,
            300,
            fn () => RolePermission::withoutGlobalScopes()
                ->with('permission')
                ->where('organization_id', $organizationId)
                ->where('role', $role)
                ->where('allowed', true)
                ->get()
                ->pluck('permission.name')
                ->filter()
                ->values()
                ->toArray()
        );

        return self::$memo[$key];
    }

    /**
     * Flush one organization's cached answer for a role, after saving changes.
     */
    public static function flushFor(string $role, ?int $organizationId): void
    {
        if ($organizationId === null) {
            return;
        }

        $key = self::keyFor($role, $organizationId);

        unset(self::$memo[$key]);

        Cache::forget($key);
    }

    /**
     * Drop every cached role, for contexts that outlive a single request.
     */
    public static function flushAll(): void
    {
        foreach (array_keys(self::$memo) as $key) {
            Cache::forget($key);
        }

        self::$memo = [];

        /*
         * The memo only knows what this process happened to read, and walking
         * it was the whole of this method — which meant that in a process that
         * had read nothing, flushAll() forgot nothing.
         *
         * That is every process where it matters. A migration, an artisan
         * command and a queue worker all start empty, so a permission change
         * made by one of them left the stale entries sitting in the cache
         * store and the change looked like it had not taken effect until the
         * five-minute TTL lapsed.
         *
         * role_permissions is the exact set of keys that can exist: allowedFor()
         * builds one per (organization, role), and a pair with no rows has
         * nothing to cache.
         */
        // Callable before the table exists — TestCase::setUp() flushes for
        // isolation whether or not the case migrates, and a migration running
        // against an empty database reaches this before the table is created.
        // Nothing cached means nothing to forget.
        if (! Schema::hasTable('role_permissions')) {
            return;
        }

        RolePermission::withoutGlobalScopes()
            ->select('role', 'organization_id')
            ->distinct()
            ->get()
            ->each(function ($row): void {
                if ($row->organization_id === null) {
                    return;
                }

                Cache::forget(self::keyFor($row->role, (int) $row->organization_id));
            });
    }

    protected static function keyFor(string $role, int $organizationId): string
    {
        return "permissions_role_{$organizationId}_{$role}";
    }
}
