<?php

namespace App\Console\Commands;

use App\Models\TaskFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Moves files stored under the old task-files/{task_code}/ layout to the
 * tenant-addressable task-files/{unit_id}/{task_code}/ layout.
 *
 * task_code is only unique per unit, so the old prefix could not identify
 * whose file an object was. New uploads already use the unit-led path; this
 * brings the objects written before that change into line.
 *
 * Each file is moved copy -> verify -> update file_path -> delete source, in
 * that order, so a reader always resolves to an object that exists. The
 * command is idempotent and safe to re-run after an interruption: a file whose
 * destination already exists skips the copy and finishes the remaining steps.
 */
class BackfillStoragePrefixes extends Command
{
    protected $signature = 'storage:backfill-prefix
                            {--dry-run : Report what would move without copying, updating or deleting}
                            {--limit= : Stop after this many files, for a cautious first pass}';

    protected $description = 'Move legacy task files to the unit-scoped R2 prefix';

    protected int $moved = 0;

    protected int $resumed = 0;

    protected int $missing = 0;

    protected int $failed = 0;

    public function handle(): int
    {
        // Artisan resolves a command once and reuses the instance, so a second
        // invocation in the same process would otherwise inherit the first
        // run's tallies.
        $this->moved = $this->resumed = $this->missing = $this->failed = 0;

        $dryRun = (bool) $this->option('dry-run');
        $limit  = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        if ($dryRun) {
            $this->comment('Dry run — nothing will be copied, updated or deleted.');
        }

        $disk      = Storage::disk('r2');
        $processed = 0;
        $stopped   = false;

        TaskFile::with('task')->chunkById(100, function ($files) use ($disk, $dryRun, $limit, &$processed, &$stopped) {
            foreach ($files as $file) {
                if ($limit !== null && $processed >= $limit) {
                    $stopped = true;

                    return false;
                }

                $target = $this->targetKeyFor($file);

                // Already in the new layout, or the task is gone and there is
                // nothing to key the move on.
                if ($target === null || $target === $file->file_path) {
                    continue;
                }

                $processed++;
                $this->migrate($disk, $file, $target, $dryRun);
            }

            return true;
        });

        if ($stopped) {
            $this->comment("Stopped at the --limit of {$limit} file(s).");
        }

        $this->summarise($dryRun);

        return $this->failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Where this file belongs under the unit-led layout, keeping its existing
     * object name. Null when the file is already correctly placed or its task
     * has gone.
     */
    protected function targetKeyFor(TaskFile $file): ?string
    {
        $task = $file->task;

        if (! $task || ! $task->unit_id) {
            return null;
        }

        $prefix = $file->is_completed_file
            ? $task->completedStoragePrefix()
            : $task->storagePrefix();

        return $prefix.'/'.basename($file->file_path);
    }

    /**
     * Move one file. Order matters: the source is only removed once the copy
     * is verified and the database points at the new key.
     */
    protected function migrate($disk, TaskFile $file, string $target, bool $dryRun): void
    {
        $source = $file->file_path;

        $sourceExists = $disk->exists($source);
        $targetExists = $disk->exists($target);

        // Nothing to move. The row still points at a key that is not there, so
        // leave it alone and let reconciliation report the discrepancy rather
        // than silently rewriting the path to another absent object.
        if (! $sourceExists && ! $targetExists) {
            $this->missing++;
            $this->warn("missing source, skipped: {$source}");
            Log::warning('Storage backfill found no object for a task file.', [
                'task_file_id' => $file->id,
                'file_path'    => $source,
            ]);

            return;
        }

        if ($dryRun) {
            $this->line(($targetExists ? 'would resume: ' : 'would move:   ')."{$source}  ->  {$target}");
            $targetExists ? $this->resumed++ : $this->moved++;

            return;
        }

        // A destination that already exists means an earlier run copied it and
        // stopped before finishing. Skip straight to the remaining steps.
        if (! $targetExists) {
            $size = $disk->size($source);

            if (! $disk->copy($source, $target)) {
                $this->failed++;
                $this->error("copy failed: {$source}");
                Log::error('Storage backfill copy failed.', [
                    'task_file_id' => $file->id,
                    'from'         => $source,
                    'to'           => $target,
                ]);

                return;
            }

            if (! $disk->exists($target) || $disk->size($target) !== $size) {
                $this->failed++;
                $this->error("verification failed, source kept: {$target}");
                Log::error('Storage backfill verification failed; source left in place.', [
                    'task_file_id'  => $file->id,
                    'from'          => $source,
                    'to'            => $target,
                    'expected_size' => $size,
                ]);

                return;
            }

            $this->moved++;
        } else {
            $this->resumed++;
        }

        // Point the record at the new key before removing the old object, so a
        // concurrent read never lands between the two.
        DB::table('task_files')->where('id', $file->id)->update(['file_path' => $target]);

        if ($sourceExists && ! $disk->delete($source)) {
            // The move itself succeeded; the leftover object simply becomes an
            // orphan that storage:reconcile will report.
            Log::warning('Storage backfill could not remove the source object; it is now an orphan.', [
                'task_file_id' => $file->id,
                'file_path'    => $source,
            ]);
        }

        Log::info('Storage backfill moved a task file.', [
            'task_file_id' => $file->id,
            'from'         => $source,
            'to'           => $target,
            'timestamp'    => now()->toIso8601String(),
        ]);
    }

    protected function summarise(bool $dryRun): void
    {
        $verb = $dryRun ? 'would be ' : '';

        $this->newLine();
        $this->table(['Outcome', 'Files'], [
            ['Moved '.$verb, $this->moved],
            ['Resumed from a previous run', $this->resumed],
            ['Skipped, object missing', $this->missing],
            ['Failed', $this->failed],
        ]);

        if ($this->failed > 0) {
            $this->error('Some files did not move. Their sources were left in place; re-run to retry.');

            return;
        }

        if ($this->moved === 0 && $this->resumed === 0) {
            $this->info('Nothing to move; every task file is already unit-scoped.');

            return;
        }

        if (! $dryRun) {
            $this->info('Backfill complete. Run storage:reconcile to confirm totals are unchanged.');
        }
    }
}
