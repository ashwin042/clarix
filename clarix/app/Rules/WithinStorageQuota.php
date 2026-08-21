<?php

namespace App\Rules;

use App\Services\OrganizationStorage;
use App\Services\StorageQuota;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Refuses an upload that would take an organization past its storage
 * allowance.
 *
 * Applied to the files array rather than to each file, because the limit is
 * about the total arriving in one request. Five 30MB files are individually
 * fine and collectively 150MB, and a per-file rule would wave all five through.
 *
 * The message names the organization's allowance and what it is already
 * holding. A bare "the files field is invalid" on a request whose files are
 * each perfectly valid reads as a bug in the endpoint, and whoever hits it
 * first will be an integrator with no view of the storage page.
 */
class WithinStorageQuota implements ValidationRule
{
    public function __construct(protected ?int $organizationId)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // No organization means no allowance to check against — the console
        // and a platform superadmin both land here, and neither is confined to
        // an agency's quota.
        if ($this->organizationId === null || ! is_array($value)) {
            return;
        }

        $incoming = 0;

        foreach ($value as $file) {
            if (is_object($file) && method_exists($file, 'getSize')) {
                $incoming += (int) $file->getSize();
            }
        }

        $quota = app(StorageQuota::class);

        if (! $quota->wouldExceed($this->organizationId, $incoming)) {
            return;
        }

        $storage = app(OrganizationStorage::class);
        $summary = $storage->summaryFor($this->organizationId);

        $fail(sprintf(
            'This organization is over its storage allowance. It holds %s of %s GB, and these files add %s. Free up space or raise the plan before uploading again.',
            $storage->humanBytes($summary['bytes']),
            $summary['cap_gb'],
            $storage->humanBytes($incoming),
        ));
    }
}
