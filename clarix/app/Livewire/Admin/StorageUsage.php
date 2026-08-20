<?php

namespace App\Livewire\Admin;

use App\Models\Unit;
use App\Services\OrganizationStorage;
use Livewire\Component;

/**
 * An agency's own storage: one total against the allowance its plan carries,
 * with the per-unit breakdown behind it.
 *
 * The headline figure is the organization's, because that is what an agency
 * buys — one shared quota, not a separate allowance per unit. The table below
 * answers the next question an admin asks, which is where the space has gone.
 *
 * The breakdown is built here from the tenant-scoped Unit model rather than
 * added to OrganizationStorage. That service takes an organization id and is
 * what the platform view calls; giving it a per-unit method would put the one
 * thing a superadmin must not see within easy reach of the screen they use.
 * Reached through the scope instead, a per-unit breakdown can only be obtained
 * by someone acting inside the organization.
 */
class StorageUsage extends Component
{
    public function render()
    {
        $user = auth()->user();

        abort_unless($user?->isAdmin() && $user->organization_id !== null, 403);

        $storage = app(OrganizationStorage::class);
        $summary = $storage->summaryFor((int) $user->organization_id);

        return view('livewire.admin.storage-usage', [
            'summary' => $summary,
            'units'   => $this->unitBreakdown($summary['bytes']),
            'storage' => $storage,
        ])->layout('layouts.app', ['pageTitle' => 'Storage Usage']);
    }

    /**
     * Every unit in this organization and what it holds, largest first.
     *
     * The share is of the organization's own total, not of the plan cap: this
     * table answers "where has the space gone", while the card above answers
     * "how much is left". Reading the percentages as quota consumption would
     * be wrong, so they are labelled as a share in the view.
     *
     * Units holding nothing are listed too. An admin looking for the cause of
     * a large total is helped by seeing which units are not it.
     *
     * @return \Illuminate\Support\Collection<int, array{name: string, bytes: int, share: float}>
     */
    protected function unitBreakdown(int $organizationTotal)
    {
        return Unit::query()
            // The global scope on Unit confines this to the acting admin's
            // organization; no organization id is named anywhere here.
            ->leftJoin('unit_storage_usage', 'unit_storage_usage.unit_id', '=', 'units.id')
            ->select('units.id', 'units.name')
            ->selectRaw('coalesce(unit_storage_usage.bytes_used, 0) as bytes_used')
            ->orderByDesc('bytes_used')
            ->orderBy('units.name')
            ->get()
            ->map(fn ($unit) => [
                'name'  => $unit->name,
                'bytes' => (int) $unit->bytes_used,
                // An organization holding nothing has no shares to report,
                // and dividing by its total would be a division by zero.
                'share' => $organizationTotal > 0
                    ? round((int) $unit->bytes_used * 100 / $organizationTotal, 2)
                    : 0.0,
            ]);
    }
}
