<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
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

         if (config('app.env') === 'production') {
            URL::forceScheme('https');

            $this->assertRealObjectStorage();
        }
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
