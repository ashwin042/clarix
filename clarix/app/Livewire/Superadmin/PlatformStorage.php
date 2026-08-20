<?php

namespace App\Livewire\Superadmin;

use App\Services\OrganizationStorage;
use Livewire\Component;

/**
 * Storage across every agency, fullest first.
 *
 * A deliberate and narrow exception to the rule that operational data is
 * closed to the platform. What an organization is storing against a quota it
 * pays for is billing information, and it is the one number the platform needs
 * in order to know who is about to outgrow their plan.
 *
 * The exception stops at the total. UnitStorageUsage remains invisible to a
 * superadmin — no per-unit breakdown, no unit names, no file or task detail —
 * because that would describe how each agency is structured internally, which
 * is not billing information by any reading.
 */
class PlatformStorage extends Component
{
    public string $search = '';

    public function mount(): void
    {
        $this->authorizeSuperadmin();
    }

    protected function authorizeSuperadmin(): void
    {
        abort_unless(auth()->user()?->isSuperadmin(), 403);
    }

    public function render()
    {
        $this->authorizeSuperadmin();

        $storage = app(OrganizationStorage::class);
        $rows    = $storage->platformSummary($this->search ?: null);

        return view('livewire.superadmin.platform-storage', [
            'rows'    => $rows,
            'storage' => $storage,
            'totals'  => [
                'bytes'         => array_sum(array_column($rows, 'bytes')),
                'organizations' => count($rows),
            ],
        ])->layout('layouts.superadmin', ['pageTitle' => 'Storage']);
    }
}
