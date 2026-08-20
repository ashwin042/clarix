<?php

namespace App\Console\Commands;

use App\Models\Unit;
use App\Services\R2ObjectLister;
use App\Services\StorageUsageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Corrects the tracked per-unit storage totals against what R2 actually holds.
 *
 * The write-path counters can drift: an upload whose database write failed
 * after the object landed, a delete that removed the record but not the
 * object, a restored backup. Running nightly keeps the admin storage view
 * honest and surfaces objects that belong to no unit at all.
 */
class ReconcileStorageUsage extends Command
{
    protected $signature = 'storage:reconcile
                            {--dry-run : Report the drift without writing any corrections}';

    protected $description = 'Reconcile tracked per-unit R2 storage usage against real R2 state';

    public function handle(R2ObjectLister $lister, StorageUsageService $usage): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->comment('Dry run — no corrections will be written.');
        }

        [$actual, $orphanCount, $orphanBytes] = $this->tallyBucket($lister);

        $corrections = $this->applyCorrections($actual, $usage, $dryRun);

        $this->report($corrections, $orphanCount, $orphanBytes, $dryRun);

        return self::SUCCESS;
    }

    /**
     * Read every object in the bucket and total its bytes against the unit
     * that owns it.
     *
     * Keys are resolved back to units in batches so that neither the key list
     * nor the lookup query grows without bound. A key with no matching
     * task_files row belongs to no unit and is counted as an orphan rather
     * than charged to anyone.
     *
     * @return array{0: array<int, int>, 1: int, 2: int}
     */
    protected function tallyBucket(R2ObjectLister $lister): array
    {
        $chunkSize   = max(1, (int) config('storage.reconcile_chunk'));
        $actual      = [];
        $orphanCount = 0;
        $orphanBytes = 0;
        $buffer      = [];

        foreach ($lister->listAll() as $key => $size) {
            $buffer[$key] = $size;

            if (count($buffer) >= $chunkSize) {
                $this->resolveBuffer($buffer, $actual, $orphanCount, $orphanBytes);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            $this->resolveBuffer($buffer, $actual, $orphanCount, $orphanBytes);
        }

        return [$actual, $orphanCount, $orphanBytes];
    }

    /**
     * Attribute one batch of object keys to their units, accumulating totals
     * and orphans into the running tallies.
     *
     * @param  array<string, int>  $buffer  key => size in bytes
     * @param  array<int, int>  $actual  unit id => bytes, updated in place
     */
    protected function resolveBuffer(array $buffer, array &$actual, int &$orphanCount, int &$orphanBytes): void
    {
        $owners = DB::table('task_files')
            ->join('tasks', 'tasks.id', '=', 'task_files.task_id')
            ->whereIn('task_files.file_path', array_keys($buffer))
            ->pluck('tasks.unit_id', 'task_files.file_path');

        foreach ($buffer as $key => $size) {
            $unitId = $owners[$key] ?? null;

            if ($unitId === null) {
                $orphanCount++;
                $orphanBytes += $size;

                continue;
            }

            $actual[(int) $unitId] = ($actual[(int) $unitId] ?? 0) + $size;
        }
    }

    /**
     * Compare every unit's tracked total against the counted one and correct
     * the differences.
     *
     * Every unit is walked, not only those with objects, so a unit whose files
     * have all been removed is brought back down to zero.
     *
     * @param  array<int, int>  $actual
     * @return array<int, array{unit: string, old: int, new: int, delta: int}>
     */
    protected function applyCorrections(array $actual, StorageUsageService $usage, bool $dryRun): array
    {
        $corrections = [];

        Unit::query()->orderBy('id')->each(function (Unit $unit) use ($actual, $usage, $dryRun, &$corrections) {
            $tracked = $usage->bytesFor($unit->id);
            $counted = $actual[$unit->id] ?? 0;

            if ($tracked === $counted) {
                return;
            }

            if (! $dryRun) {
                $usage->set($unit->id, $counted);
            }

            Log::info('Storage usage drift corrected.', [
                'unit_id'   => $unit->id,
                'unit_name' => $unit->name,
                'old_bytes' => $tracked,
                'new_bytes' => $counted,
                'delta'     => $counted - $tracked,
                'dry_run'   => $dryRun,
                'timestamp' => now()->toIso8601String(),
            ]);

            $corrections[] = [
                'unit'  => $unit->name,
                'old'   => $tracked,
                'new'   => $counted,
                'delta' => $counted - $tracked,
            ];
        });

        return $corrections;
    }

    /**
     * Print a summary for whoever is watching the scheduler output.
     *
     * @param  array<int, array{unit: string, old: int, new: int, delta: int}>  $corrections
     */
    protected function report(array $corrections, int $orphanCount, int $orphanBytes, bool $dryRun): void
    {
        if ($corrections === []) {
            $this->info('Storage usage is in sync; no drift found.');
        } else {
            $this->table(
                ['Unit', 'Old', 'New', 'Delta'],
                array_map(fn (array $row) => [
                    $row['unit'],
                    $this->humanBytes($row['old']),
                    $this->humanBytes($row['new']),
                    ($row['delta'] > 0 ? '+' : '').$this->humanBytes(abs($row['delta'])),
                ], $corrections)
            );

            $verb = $dryRun ? 'would be corrected' : 'corrected';
            $this->info(count($corrections)." unit(s) {$verb}.");
        }

        if ($orphanCount > 0) {
            $this->warn("{$orphanCount} object(s) totalling {$this->humanBytes($orphanBytes)} match no task file and are charged to no unit.");

            Log::warning('Orphaned R2 objects found during reconciliation.', [
                'object_count' => $orphanCount,
                'total_bytes'  => $orphanBytes,
                'timestamp'    => now()->toIso8601String(),
            ]);
        }
    }

    protected function humanBytes(int $bytes): string
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

        return round($bytes / 1073741824, 2).' GB';
    }
}
