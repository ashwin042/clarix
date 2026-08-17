<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Support\Facades\DB;

/**
 * Storage as the organization actually experiences it: one total, one cap.
 *
 * Usage was previously tracked and shown per unit, each unit measured against
 * its own allowance. That never matched what an agency buys — a plan is sold
 * to the organization, and the units inside it draw on one shared quota. This
 * class aggregates the per-unit rollups, which are still the unit of
 * bookkeeping on the write path, into the figure that means something.
 *
 * Nothing here changes how files are stored. Objects stay at
 * task-files/{unit_id}/{task_code}/..., the organization is not part of any
 * key, and ownership is resolved exactly as before — through task_files to
 * tasks to units to units.organization_id.
 *
 * The queries are raw on purpose, matching StorageUsageService. These totals
 * are platform bookkeeping that has to stay correct for every agency including
 * from the console, where there is no acting user for a scope to read.
 */
class OrganizationStorage
{
    public const BYTES_PER_GB = 1073741824;

    /**
     * Bytes held by every unit inside one organization.
     */
    public function bytesFor(int $organizationId): int
    {
        return (int) DB::table('unit_storage_usage')
            ->where('organization_id', $organizationId)
            ->sum('bytes_used');
    }

    /**
     * The allowance an organization has, in gigabytes.
     *
     * A per-organization override wins when set — that is how the Pro extra
     * storage arrangement is applied, by hand. Otherwise the plan decides, and
     * the plan comes from PlanFeatures so this class and the feature gates
     * cannot disagree about what an agency is on. An agency with no
     * subscription row falls back to the smallest tier.
     */
    public function capGbFor(int $organizationId): int
    {
        $override = DB::table('organizations')
            ->where('id', $organizationId)
            ->value('storage_cap_override_gb');

        // Null means "use the plan"; zero would mean "no allowance", so the
        // test is for null rather than for falsiness.
        if ($override !== null) {
            return (int) $override;
        }

        return $this->capGbForPlan($this->planFor($organizationId));
    }

    public function capGbForPlan(?string $plan): int
    {
        $caps = (array) config('storage.plan_caps_gb');

        return (int) ($caps[$plan] ?? config('storage.default_cap_gb'));
    }

    /**
     * The plan an organization is on.
     *
     * Delegated so there is one answer in the application. This method used to
     * be an inline subscription query written out twice in this file, which is
     * exactly the shape of thing that drifts.
     */
    protected function planFor(int $organizationId): string
    {
        return app(PlanFeatures::class)->planFor($organizationId);
    }

    /**
     * Everything one organization's storage row needs.
     *
     * @return array{bytes: int, cap_gb: int, cap_bytes: int, percent: float, plan: string|null}
     */
    public function summaryFor(int $organizationId): array
    {
        $plan  = $this->planFor($organizationId);
        $bytes = $this->bytesFor($organizationId);

        // capGbFor rather than capGbForPlan, so the row reports the allowance
        // the agency actually has — a hand-set override included.
        $capGb    = $this->capGbFor($organizationId);
        $capBytes = $capGb * self::BYTES_PER_GB;

        return [
            'bytes'     => $bytes,
            'cap_gb'    => $capGb,
            'cap_bytes' => $capBytes,
            // A cap of zero would divide by zero; it reads as "no allowance",
            // which is fully used rather than unused.
            'percent'   => $capBytes > 0 ? round($bytes * 100 / $capBytes, 2) : 100.0,
            'plan'      => $plan,
        ];
    }

    /**
     * Every organization's storage, fullest first.
     *
     * This is the platform view. It is deliberately org-level only: a total
     * against a paid quota is billing information, while the per-unit rows
     * behind it would describe how each agency is structured internally and
     * stay closed to the platform. See the note on UnitStorageUsage.
     *
     * @return list<array{organization: object, bytes: int, cap_gb: int, percent: float, plan: string|null}>
     */
    public function platformSummary(?string $search = null): array
    {
        $organizations = DB::table('organizations')
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $rows = $organizations->map(function (object $organization) {
            return ['organization' => $organization] + $this->summaryFor((int) $organization->id);
        })->all();

        // Closest to the cap first, so whoever is about to run out is at the
        // top rather than wherever the alphabet put them.
        usort($rows, fn (array $a, array $b) => $b['percent'] <=> $a['percent']);

        return $rows;
    }

    /**
     * Bytes as something a person can read.
     */
    public function humanBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        if ($bytes < 1073741824) {
            return round($bytes / 1048576, 1).' MB';
        }

        return round($bytes / self::BYTES_PER_GB, 2).' GB';
    }
}
