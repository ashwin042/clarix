<?php

namespace App\Services;

/**
 * Whether an organization has room for more bytes.
 *
 * The first place in the application that treats the storage cap as a limit
 * rather than a number to display. OrganizationStorage has always been able to
 * say how much an agency holds and how much it is allowed; nothing ever
 * compared the two before a write, so an agency at 400% of its plan uploaded
 * exactly as freely as one at 2%.
 *
 * Applied on the API upload path only, for now. The browser path is unchanged
 * and still unenforced, which is a deliberate staging decision rather than an
 * oversight: an unattended integration can fill a bucket far faster than a
 * person clicking a file picker, and turning the cap into a hard limit for
 * everyone at once would start refusing uploads for agencies that are already
 * over it.
 *
 * The allowance belongs to the organization, not the unit. Every unit inside an
 * agency draws on one shared quota, so bytes held by one unit reduce what
 * another may upload.
 */
class StorageQuota
{
    public function __construct(protected OrganizationStorage $storage)
    {
    }

    /**
     * Bytes the organization may still take on, never negative.
     *
     * An agency already past its cap reads zero rather than a negative number,
     * so callers can treat the result as "how much fits" without special-casing
     * the overshoot.
     */
    public function remainingBytesFor(int $organizationId): int
    {
        $capBytes = $this->storage->capGbFor($organizationId) * OrganizationStorage::BYTES_PER_GB;

        return max(0, $capBytes - $this->storage->bytesFor($organizationId));
    }

    /**
     * Whether taking on $bytes more would put the organization past its cap.
     *
     * Read-then-write, so two uploads arriving together can both be told yes
     * and jointly overshoot. That is accepted: the running totals themselves
     * are maintained by single atomic statements and stay accurate, so the
     * consequence is a bounded overshoot of at most one request's worth rather
     * than a number that drifts. Closing the race properly means reserving
     * bytes before the R2 put and releasing them on failure, which is a much
     * larger change than the limit is worth today.
     */
    public function wouldExceed(int $organizationId, int $bytes): bool
    {
        if ($bytes <= 0) {
            return false;
        }

        return $bytes > $this->remainingBytesFor($organizationId);
    }

    /**
     * Bytes as something a person can read, for the refusal message.
     */
    public function humanBytes(int $bytes): string
    {
        return $this->storage->humanBytes($bytes);
    }
}
