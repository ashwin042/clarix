<?php

namespace App\Services;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * The single source of truth for "which organization is this request acting
 * as", used by OrganizationScope and by the auto-fill on create.
 *
 * Every decision about tenant visibility funnels through organizationId().
 * A null answer means "do not filter", which happens in four situations:
 *
 *   - nobody is authenticated (console commands, queued jobs, the login
 *     screen itself). Route middleware is what keeps unauthenticated HTTP
 *     traffic away from tenant data; the scope cannot help there because it
 *     has no user to scope to.
 *   - the authenticated user is a superadmin, who administers the platform
 *     rather than any one agency and is meant to see across all of them —
 *     unless the portal has explicitly pointed them at one agency, in which
 *     case that one applies.
 *   - the caller explicitly asked for an unscoped read via runWithoutScope(),
 *     which platform-level maintenance uses.
 *   - the scope is re-entered while the authenticated user is still being
 *     loaded (see below).
 *
 * The re-entrancy guard matters more than it looks. User is itself a scoped
 * model, so resolving Auth::user() runs a query on users, which consults this
 * class, which would call Auth::user() again — an unbounded loop. While that
 * first load is in flight the guard reports "no organization", so the query
 * that fetches the authenticated user is deliberately unscoped. That is the
 * correct answer: the user's own row has to be readable before anyone knows
 * which organization to filter by.
 *
 * Nothing is cached between calls. Auth::user() memoises the resolved user on
 * the guard, so repeat calls are cheap, and reading through it every time
 * means a mid-request identity change — actingAs() in a test, a login, a
 * logout — is picked up immediately instead of serving a stale organization.
 */
class TenantContext
{
    /** True while the authenticated user is being loaded. */
    protected static bool $resolving = false;

    /** Depth of nested runWithoutScope() calls. */
    protected static int $unscoped = 0;

    /**
     * Organization named explicitly by actingAsOrganization(). Applies to a
     * superadmin as well as to unauthenticated code, because it is a
     * deliberate, scoped instruction rather than an ambient setting.
     */
    protected static ?int $acting = null;

    protected static bool $actingActive = false;

    /**
     * Ambient default set by useOrganization(), for seeders and the test
     * harness. Applies only when nobody is authenticated: a process-wide
     * default must never quietly redefine what a signed-in superadmin can see.
     */
    protected static ?int $default = null;

    protected static bool $defaultActive = false;

    /**
     * The organization the current actor is confined to, or null to apply no
     * filtering at all.
     */
    public static function organizationId(): ?int
    {
        if (self::$unscoped > 0 || self::$resolving) {
            return null;
        }

        $user = self::authenticatedUser();

        // An ordinary user is pinned to their own organization, and nothing
        // can move them out of it. This case is checked first precisely so
        // that a forced organization can never lift a signed-in member of one
        // agency into another.
        if ($user !== null && ! $user->isSuperadmin()) {
            return $user->organization_id === null ? null : (int) $user->organization_id;
        }

        // Left here: nobody is authenticated, or the actor is a superadmin.
        // Neither is confined to an organization to begin with, so an
        // explicitly named one may take effect. For a superadmin this is what
        // makes acting *into* an agency possible — creating its first admin,
        // for instance — and it grants no reach they did not already have,
        // since the alternative for them is seeing everything.
        if (self::$actingActive) {
            return self::$acting;
        }

        // The ambient default is narrower on purpose: it only reaches code
        // with no authenticated user at all. A superadmin who has named no
        // organization sees the whole platform, and no process-wide setting
        // left over from a seeder or a test harness may change that.
        if ($user === null && self::$defaultActive) {
            return self::$default;
        }

        return null;
    }

    /**
     * Whether a query should currently be filtered by organization.
     */
    public static function shouldScope(): bool
    {
        return self::organizationId() !== null;
    }

    /**
     * Whether the actor is a superadmin reading the platform at large, rather
     * than any one organization.
     *
     * This is the state in which a model that is not PlatformVisible must
     * return nothing at all. It is deliberately narrower than "organizationId()
     * happens to be null": the console, queued jobs and the login screen also
     * have no organization, and they are ordinary unscoped contexts rather
     * than a person browsing. runWithoutScope() switches it off too, because
     * that is the explicit escape hatch platform maintenance runs inside.
     */
    public static function isUnconfinedSuperadmin(): bool
    {
        if (self::$unscoped > 0 || self::$resolving) {
            return false;
        }

        // A superadmin who has named an organization is acting inside it, and
        // is bound by that organization like anyone else.
        if (self::$actingActive) {
            return false;
        }

        return self::authenticatedUser()?->isSuperadmin() === true;
    }

    /**
     * Load the authenticated user with the re-entrancy guard raised.
     */
    protected static function authenticatedUser(): ?User
    {
        self::$resolving = true;

        try {
            return Auth::user();
        } finally {
            self::$resolving = false;
        }
    }

    /**
     * Run a callback with tenant filtering switched off.
     *
     * For platform-level work that legitimately spans agencies: the nightly
     * storage reconcile, the superadmin portal, maintenance commands. Nesting
     * is safe.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function runWithoutScope(Closure $callback): mixed
    {
        self::$unscoped++;

        try {
            return $callback();
        } finally {
            self::$unscoped--;
        }
    }

    /**
     * Set the ambient organization for the rest of the process.
     *
     * For seeders and the test harness, which build agency data with nobody
     * signed in. Deliberately does not reach an authenticated superadmin: a
     * default left lying around by test setup must not narrow what the portal
     * can see. Use actingAsOrganization() for that.
     */
    public static function useOrganization(?int $organizationId): void
    {
        self::$default       = $organizationId;
        self::$defaultActive = $organizationId !== null;
    }

    /**
     * Run a callback as though the given organization were the current one.
     *
     * Names one organization for the duration of a callback, restoring
     * whatever was in effect afterwards.
     *
     * This is the deliberate form, and it reaches a superadmin as well as
     * unauthenticated code — it is how the portal writes a record into an
     * agency it is administering. It never reaches an ordinary user, who
     * stays pinned to their own organization no matter what.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function actingAsOrganization(?int $organizationId, Closure $callback): mixed
    {
        $previous       = self::$acting;
        $previousActive = self::$actingActive;

        self::$acting       = $organizationId;
        self::$actingActive = true;

        try {
            return $callback();
        } finally {
            self::$acting       = $previous;
            self::$actingActive = $previousActive;
        }
    }

    /**
     * Drop all forced state. Only needed where the process outlives a single
     * request, such as between tests.
     */
    public static function reset(): void
    {
        self::$resolving     = false;
        self::$unscoped      = 0;
        self::$acting        = null;
        self::$actingActive  = false;
        self::$default       = null;
        self::$defaultActive = false;
    }
}
