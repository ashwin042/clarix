<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Maintains the per-unit running total of R2 bytes held.
 *
 * Every mutation is a single atomic statement rather than a read-modify-write,
 * so concurrent uploads for the same unit cannot lose an update. The SQL stays
 * portable between MySQL (production) and SQLite (tests).
 */
class StorageUsageService
{
    protected const TABLE = 'unit_storage_usage';

    /**
     * Add bytes to a unit's total, creating the rollup row if this is the
     * unit's first file.
     */
    public function increment(int $unitId, int $bytes): void
    {
        if ($bytes <= 0) {
            return;
        }

        $this->ensureRow($unitId);

        DB::table(self::TABLE)
            ->where('unit_id', $unitId)
            ->increment('bytes_used', $bytes, ['updated_at' => now()]);
    }

    /**
     * Remove bytes from a unit's total.
     *
     * bytes_used is an unsigned column, so the subtraction is clamped at zero
     * in SQL. Without the clamp, a total that has drifted low (an untracked
     * delete, a restored backup) would push the column negative and MySQL
     * would reject the write outright.
     */
    public function decrement(int $unitId, int $bytes): void
    {
        if ($bytes <= 0) {
            return;
        }

        DB::update(
            'update '.self::TABLE.' set bytes_used = case when bytes_used < ? then 0 else bytes_used - ? end, updated_at = ? where unit_id = ?',
            [$bytes, $bytes, now(), $unitId]
        );
    }

    /**
     * Overwrite a unit's total outright. Used by reconciliation, which has
     * counted the real R2 state and is the authority at that point.
     */
    public function set(int $unitId, int $bytes): void
    {
        DB::table(self::TABLE)->updateOrInsert(
            ['unit_id' => $unitId],
            [
                'organization_id' => $this->organizationIdFor($unitId),
                'bytes_used'      => max(0, $bytes),
                'updated_at'      => now(),
                'created_at'      => now(),
            ]
        );
    }

    /**
     * The bytes currently tracked for a unit. Zero when the unit has never
     * held a file.
     */
    public function bytesFor(int $unitId): int
    {
        return (int) DB::table(self::TABLE)->where('unit_id', $unitId)->value('bytes_used');
    }

    /**
     * Create the rollup row if it is missing, ignoring the unique-constraint
     * failure that a concurrent request racing to do the same would cause.
     */
    protected function ensureRow(int $unitId): void
    {
        DB::table(self::TABLE)->insertOrIgnore([
            'organization_id' => $this->organizationIdFor($unitId),
            'unit_id'         => $unitId,
            'bytes_used'      => 0,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    /**
     * The organization that owns a unit.
     *
     * Read straight off the units row rather than from the acting user,
     * because the nightly reconcile runs from the console with nobody
     * authenticated and still has to write a correctly owned rollup. Every
     * statement in this class is deliberately raw SQL — the totals are
     * platform bookkeeping that must stay accurate for every agency — so the
     * owner is resolved explicitly here instead of by the model layer.
     */
    protected function organizationIdFor(int $unitId): ?int
    {
        $organizationId = DB::table('units')->where('id', $unitId)->value('organization_id');

        return $organizationId === null ? null : (int) $organizationId;
    }
}
