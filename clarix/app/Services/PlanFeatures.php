<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * What an organization's plan entitles it to.
 *
 * The second of Clarix's two authorization layers. This one answers "did this
 * agency buy this?"; the role-permission layer answers "may this person do
 * this?". They are deliberately separate and both must pass — a Pro plan does
 * not grant a writer payroll, and an admin holding every permission in the
 * panel cannot reach ERP on Base.
 *
 * The plan is read from organization_subscriptions, never from
 * organizations.subscription_type. The subscription row is what carries price,
 * cycle and status, so it is the record that describes what was actually
 * bought; the column beside it is a legacy label kept in step by a mirror on
 * save. They disagreed in production before this class existed — one agency
 * read 'base' while paying for 'standard' — which is why only one of them is
 * allowed to be the answer.
 */
class PlanFeatures
{
    /**
     * Resolved plans for this request, keyed by organization id.
     *
     * A request-lifetime memo and deliberately nothing more. PermissionService
     * keeps a five-minute cache; doing the same here would mean a superadmin
     * upgrading an agency watched nothing happen for five minutes, and "the
     * change takes effect immediately" is the whole point of resolving the
     * plan live. The cost is one indexed lookup per request.
     *
     * @var array<int|string, string>
     */
    protected static array $memo = [];

    public static function flush(): void
    {
        self::$memo = [];
    }

    /**
     * The plan an organization is on right now.
     */
    public function planFor(?int $organizationId): string
    {
        $fallback = (string) config('plans.default');

        if ($organizationId === null) {
            return $fallback;
        }

        if (isset(self::$memo[$organizationId])) {
            return self::$memo[$organizationId];
        }

        $plan = DB::table('organization_subscriptions')
            ->where('organization_id', $organizationId)
            // Newest start wins, and the later-recorded row breaks a tie. Two
            // subscriptions starting the same day is ordinary — an upgrade
            // taking effect immediately — and without the second key which one
            // counted was left to the database to decide. This matches the
            // ordering OrganizationStorage used before it delegated here.
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->value('plan');

        // An unrecognised name is not trusted just because it is stored.
        if (! in_array($plan, (array) config('plans.order'), true)) {
            $plan = $fallback;
        }

        return self::$memo[$organizationId] = $plan;
    }

    /**
     * Whether an organization's plan reaches a feature.
     */
    public function allows(string $feature, ?int $organizationId): bool
    {
        $minimum = $this->minimumPlanFor($feature);

        // An unnamed feature is denied outright rather than waved through on
        // the top tier. A typo in a gate should close it, not open it.
        if ($minimum === null) {
            return false;
        }

        return $this->rank($this->planFor($organizationId)) >= $this->rank($minimum);
    }

    /**
     * The cheapest plan that includes a feature, or null if unknown.
     */
    public function minimumPlanFor(string $feature): ?string
    {
        $minimum = config('plans.minimum.'.$feature);

        return in_array($minimum, (array) config('plans.order'), true) ? $minimum : null;
    }

    /**
     * How a feature is named to somebody who has just been refused it.
     */
    public function labelFor(string $feature): string
    {
        return (string) (config('plans.labels.'.$feature) ?? 'this feature');
    }

    /**
     * Bring organizations.subscription_type back into line with the truth.
     *
     * The column is a legacy label that a second superadmin screen used to
     * write, which is how it came to disagree with the subscription. Nothing
     * consults it any more, but leaving a wrong number on screen is its own
     * bug, so it is corrected once by migration and mirrored on save
     * thereafter.
     *
     * Lives here rather than inside the migration class so that it is
     * addressable from a test: RefreshDatabase has already run every migration
     * against an empty table by the time a test starts, and an anonymous
     * migration class cannot be reached afterwards.
     */
    public function syncLegacyPlanColumn(): void
    {
        self::flush();

        foreach (DB::table('organizations')->select('id')->get() as $row) {
            DB::table('organizations')
                ->where('id', $row->id)
                ->update(['subscription_type' => $this->planFor((int) $row->id)]);
        }
    }

    /**
     * A plan's position in the order. Unknown names sort below everything.
     */
    protected function rank(string $plan): int
    {
        $position = array_search($plan, (array) config('plans.order'), true);

        return $position === false ? -1 : $position;
    }
}
