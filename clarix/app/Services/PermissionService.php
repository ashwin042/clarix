<?php

namespace App\Services;

use App\Models\RolePermission;
use Illuminate\Support\Facades\Cache;

class PermissionService
{
    /**
     * Returns a flat array of allowed permission names for a given role.
     * Result is cached per-role per-request using the request lifecycle cache.
     */
    public static function allowedFor(string $role): array
    {
        // Cache key per role, in-memory for the lifetime of this request
        static $cache = [];

        if (isset($cache[$role])) {
            return $cache[$role];
        }

        // Use a short-lived cache (5 minutes) to avoid repeated DB hits
        $cache[$role] = Cache::remember(
            "permissions_role_{$role}",
            300,
            fn () => RolePermission::with('permission')
                ->where('role', $role)
                ->where('allowed', true)
                ->get()
                ->pluck('permission.name')
                ->filter()
                ->values()
                ->toArray()
        );

        return $cache[$role];
    }

    /**
     * Flush cached permissions for a role (call after saving changes).
     */
    public static function flushFor(string $role): void
    {
        Cache::forget("permissions_role_{$role}");
    }
}
