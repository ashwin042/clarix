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
     * Throttles for the bot endpoints.
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

        /*
         * The task bot's own pair, with the same limits and the same reasoning.
         * Separate buckets rather than shared ones, because the two pipelines
         * run from different hosts and a busy n8n instance must not be able to
         * spend the AXOKAI bot's allowance — sharing a limiter name would make
         * one integration's load into the other's outage.
         *
         * Keyed on IP for the reason above, and because every request from this
         * caller carries the same key by definition: keying on the key would be
         * one global bucket that a single noisy workflow could exhaust for
         * every agency at once.
         */
        RateLimiter::for('n8n-verify', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('n8n-resolve', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        /*
         * Intake. Lower than resolve because there is a person typing behind
         * every one of these — resolve fires on every message including the
         * ones that turn out to be chatter, while a filed task is a deliberate
         * act — and because unlike the other three this endpoint writes.
         *
         * Keyed on the chat rather than on the IP, which is the one place these
         * limiters differ from the AXOKAI pair. Every intake call arrives from
         * the same n8n host, so an IP key would be a single bucket shared by
         * every agency: one busy team filing a morning's work would lock out
         * everybody else on the platform. The chat id is the closest thing this
         * request has to an actor, and it is present by definition — the
         * request is refused without it. Falling back to the IP covers the
         * malformed calls that never reach ResolveN8nActor.
         */
        /*
         * Directory reads, keyed on the chat like intake and for the same
         * reason: every call arrives from the same n8n host, so an IP key would
         * be one bucket shared by every agency on the platform.
         *
         * Higher than intake because a single admin conversation makes two of
         * these before one write, and because they change nothing. Lower than
         * resolve because resolve fires on every message including chatter,
         * while these fire only on the admin branch.
         */
        RateLimiter::for('n8n-directory', function (Request $request) {
            $chatId = $request->input('chat_id');

            return Limit::perMinute(60)->by(
                is_scalar($chatId) ? 'n8n-dir-chat:'.$chatId : 'n8n-dir-ip:'.$request->ip()
            );
        });
        /*
         * Reading tasks back, keyed on the chat like the two above and for the
         * same reason: one n8n host means an IP key would be a single bucket
         * shared by every agency on the platform.
         *
         * The same 60 as the directory, in a bucket of its own. These are the
         * one call a conversation can repeat without anybody deciding anything
         * — "how many are pending" invites being asked again a minute later,
         * and the pre-create code check fires on every filing attempt including
         * the ones that get abandoned — so sharing the directory's allowance
         * would let a curious admin starve the pickers a filing conversation
         * depends on.
         */
        RateLimiter::for('n8n-read', function (Request $request) {
            $chatId = $request->input('chat_id');

            return Limit::perMinute(60)->by(
                is_scalar($chatId) ? 'n8n-read-chat:'.$chatId : 'n8n-read-ip:'.$request->ip()
            );
        });
        RateLimiter::for('n8n-intake', function (Request $request) {
            $chatId = $request->input('chat_id');

            return Limit::perMinute(30)->by(
                is_scalar($chatId) ? 'n8n-chat:'.$chatId : 'n8n-ip:'.$request->ip()
            );
        });
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
