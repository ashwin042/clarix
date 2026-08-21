<?php

namespace Tests\Feature\Storage;

use App\Providers\AppServiceProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * The boot-time refusal to run production against anything but the real bucket.
 *
 * R2_DRIVER and R2_ROOT exist so the upload path can be exercised locally
 * without writing into the live bucket, and both fail silently when set wrongly
 * in production — one loses files to an ephemeral disk, the other hides them
 * behind a key prefix. The guard turns either into an immediate, obvious
 * outage.
 *
 * The provider method is called directly rather than by re-booting the
 * application under APP_ENV=production. Booting a second application inside a
 * test to assert that it refuses to boot is more machinery than the check
 * deserves, and this exercises the same code the provider runs.
 */
class ProductionObjectStorageGuardTest extends TestCase
{
    private function guard(): void
    {
        $provider = new AppServiceProvider($this->app);

        $method = new \ReflectionMethod($provider, 'assertRealObjectStorage');
        $method->setAccessible(true);
        $method->invoke($provider);
    }

    public function test_it_passes_with_the_real_s3_disk(): void
    {
        config(['filesystems.disks.r2.driver' => 's3', 'filesystems.disks.r2.root' => null]);

        $this->guard();

        // Reaching here without an exception is the assertion.
        $this->assertTrue(true);
    }

    public function test_an_empty_string_root_is_treated_as_no_prefix(): void
    {
        config(['filesystems.disks.r2.driver' => 's3', 'filesystems.disks.r2.root' => '']);

        $this->guard();

        $this->assertTrue(true);
    }

    public function test_it_refuses_a_local_driver(): void
    {
        config(['filesystems.disks.r2.driver' => 'local', 'filesystems.disks.r2.root' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/must use the s3 driver/');

        $this->guard();
    }

    public function test_the_driver_refusal_names_the_variable_to_unset(): void
    {
        config(['filesystems.disks.r2.driver' => 'local', 'filesystems.disks.r2.root' => null]);

        try {
            $this->guard();
            $this->fail('Expected the guard to refuse a local driver.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('R2_DRIVER', $e->getMessage());
            $this->assertStringContainsString('lost on the next deploy', $e->getMessage());
        }
    }

    public function test_it_refuses_a_key_prefix(): void
    {
        config(['filesystems.disks.r2.driver' => 's3', 'filesystems.disks.r2.root' => 'some/prefix']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/must have no key prefix/');

        $this->guard();
    }

    public function test_the_prefix_refusal_names_the_variable_to_unset(): void
    {
        config(['filesystems.disks.r2.driver' => 's3', 'filesystems.disks.r2.root' => 'some/prefix']);

        try {
            $this->guard();
            $this->fail('Expected the guard to refuse a key prefix.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('R2_ROOT', $e->getMessage());
        }
    }

    /**
     * The shipped defaults must satisfy the guard, or a correct production
     * deploy would refuse to boot. This is the case that matters most: the
     * other tests prove the guard bites, this one proves it does not bite the
     * configuration everyone actually runs.
     */
    public function test_the_shipped_configuration_passes(): void
    {
        $config = require base_path('config/filesystems.php');

        $this->assertSame('s3', $config['disks']['r2']['driver'], 'R2_DRIVER must default to s3.');
        $this->assertSame('', (string) ($config['disks']['r2']['root'] ?? ''), 'R2_ROOT must default to no prefix.');
    }
}
