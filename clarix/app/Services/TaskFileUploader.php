<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskFile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Putting attachments on a task: the object into R2, the row into task_files,
 * the bytes onto the unit's total.
 *
 * Extracted when the API grew a file endpoint, which would otherwise have been
 * the fourth hand-written copy of the same loop. Two of the four now go through
 * here — this service and TaskCreationService. TaskFileController still runs
 * its own copies for the web upload and the completed-file upload; folding
 * those in means editing the live upload path and is deliberately left as
 * separate work.
 *
 * Regular attachments only. Completed files carry a different prefix, a
 * different flag and their own notifications, and belong to the path that
 * already handles them.
 */
class TaskFileUploader
{
    /**
     * Store each file against the task and return the rows created.
     *
     * The R2 put happens outside the transaction because it cannot join one —
     * it is a network call to another service. Only the database row and the
     * unit's running total commit together, which is the pair that has to stay
     * consistent. A failure between the two leaves an object in R2 that no row
     * references; storage:reconcile reports exactly that as an orphan, which is
     * why the arrangement is safe to keep.
     *
     * There is deliberately no transaction spanning the whole loop. Three files
     * in and a failure leaves two attached rather than none, matching what the
     * browser has always done — and an all-or-nothing rollback could not undo
     * the R2 puts anyway, so it would buy consistency in the database at the
     * cost of orphans in the bucket.
     *
     * @param  iterable<mixed>  $files
     * @return Collection<int, TaskFile>
     */
    public function upload(Task $task, iterable $files, User $actor): Collection
    {
        $created = new Collection;

        foreach ($files as $file) {
            $path = $file->store($task->storagePrefix(), 'r2');

            // The file record and the unit's storage total commit together.
            $created->push(DB::transaction(fn () => $task->files()->create([
                'file_path'     => $path,
                'original_name' => $file->getClientOriginalName(),
                'file_size'     => $file->getSize(),
                'mime_type'     => $file->getMimeType(),
                'uploaded_by'   => $actor->getKey(),
            ])));
        }

        return $created;
    }
}
