<?php

namespace App\Services;

use Generator;
use Illuminate\Support\Facades\Storage;

/**
 * Walks every object in the R2 bucket via the S3-compatible ListObjectsV2 API.
 *
 * Task files are keyed by task code rather than by unit, so there is no
 * per-unit prefix to list against — reconciliation reads the bucket once and
 * attributes each key back to a unit through the task_files table.
 *
 * Listing is isolated here so the reconcile command can be tested against a
 * stand-in without reaching for real object storage.
 */
class R2ObjectLister
{
    /**
     * Yield every object in the bucket as key => size in bytes.
     *
     * This is a generator: pages arrive lazily, so a large bucket never has to
     * sit in memory all at once.
     *
     * @return Generator<string, int>
     */
    public function listAll(): Generator
    {
        $disk   = Storage::disk('r2');
        $client = $disk->getClient();
        $bucket = config('filesystems.disks.r2.bucket');

        $paginator = $client->getPaginator('ListObjectsV2', [
            'Bucket' => $bucket,
        ]);

        foreach ($paginator as $page) {
            foreach ($page['Contents'] ?? [] as $object) {
                yield $object['Key'] => (int) $object['Size'];
            }
        }
    }
}
