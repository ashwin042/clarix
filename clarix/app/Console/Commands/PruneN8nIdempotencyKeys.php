<?php

namespace App\Console\Commands;

use App\Models\N8nIdempotencyKey;
use App\Services\TenantContext;
use Illuminate\Console\Command;

/**
 * Clears out task bot idempotency keys that have outlived their window.
 *
 * The table would otherwise grow by one row per file a bot user ever submits
 * and never shrink. Nothing depends on this running — claim() deletes an
 * expired row before taking the key, so a key is always reusable on time
 * whether or not this has run — which makes a missed night cost storage and
 * nothing else.
 *
 * Chunked rather than one DELETE, because a table left unpruned for months on
 * a busy agency is a single statement holding a lot of locks on a table that
 * every submission writes to.
 */
class PruneN8nIdempotencyKeys extends Command
{
    protected $signature = 'n8n:prune-idempotency-keys
                            {--chunk=1000 : Rows to delete per statement}';

    protected $description = 'Delete expired task bot idempotency keys';

    public function handle(): int
    {
        $chunk   = max(1, (int) $this->option('chunk'));
        $deleted = 0;

        // Runs from the console with nobody authenticated, so no scope is in
        // play — but stated explicitly, because this job is meant to see every
        // organization's rows and that should not rest on an accident of
        // context.
        TenantContext::runWithoutScope(function () use ($chunk, &$deleted) {
            do {
                $removed = N8nIdempotencyKey::query()
                    ->where('expires_at', '<=', now())
                    ->limit($chunk)
                    ->delete();

                $deleted += $removed;
            } while ($removed > 0);
        });

        $this->info("Pruned {$deleted} expired idempotency key(s).");

        return self::SUCCESS;
    }
}
