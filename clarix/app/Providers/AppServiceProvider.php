<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useTailwind();

        $this->registerRateLimiters();

         if (config('app.env') === 'production') {
            URL::forceScheme('https');

            $this->assertRealObjectStorage();
        }
    }

    /**
     * Throttles for the Hermes endpoints.
     *
     * Named here because the api middleware group carries no throttle at all —
     * the task API is protected by needing a bearer token, and nothing else in
     * routes/api.php has ever needed a limit. The link endpoint is different in
     * kind: it answers "is this code real", which makes it a guessing oracle,
     * and an eight-character code is only safe behind a limit.
     *
     * Keyed on IP rather than on the key, because every Hermes request carries
     * the same key by definition — keying on it would be one global bucket that
     * a single noisy caller could exhaust for everyone.
     */
    protected function registerRateLimiters(): void
    {
        RateLimiter::for('hermes-verify', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        // Resolve is not a guessing oracle — the caller already holds the chat
        // id — so this bounds abuse rather than protecting a secret.
        RateLimiter::for('hermes-resolve', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
    }

    /**
     * Refuse to run in production against anything but the real R2 bucket.
     *
     * The r2 disk gained R2_DRIVER and R2_ROOT so the upload path could be
     * exercised end to end locally without writing into the live bucket. Both
     * default to production behaviour when unset, but both fail silently when
     * set wrongly, and silently is the problem:
     *
     *   driver=local   uploads answer 201 and write to the container
     *                  filesystem, which is ephemeral. The files are gone on
     *                  the next redeploy, and nothing anywhere reports it.
     *   root=anything  every object key gains a prefix, so new uploads land
     *                  somewhere the reads for existing files never look. The
     *                  bucket is intact and the application cannot see it.
     *
     * Both would be discovered days later as missing customer files. Refusing
     * to boot is deliberately the loudest possible failure: a misconfigured
     * deploy is down immediately and obviously, instead of quietly destroying
     * uploads while appearing to work.
     *
     * Production only. Local verification depends on overriding these, and the
     * test suite fakes the disk entirely.
     */
    protected function assertRealObjectStorage(): void
    {
        $driver = config('filesystems.disks.r2.driver');

        if ($driver !== 's3') {
            throw new RuntimeException(
                "The r2 disk must use the s3 driver in production, but R2_DRIVER resolved to '{$driver}'. "
                .'Uploads would be written to the container filesystem and lost on the next deploy. '
                .'Unset R2_DRIVER in the production environment.'
            );
        }

        $root = (string) (config('filesystems.disks.r2.root') ?? '');

        if ($root !== '') {
            throw new RuntimeException(
                "The r2 disk must have no key prefix in production, but R2_ROOT resolved to '{$root}'. "
                .'New uploads would be stored under a prefix that reads for existing files never consult. '
                .'Unset R2_ROOT in the production environment.'
            );
        }
    }
}
