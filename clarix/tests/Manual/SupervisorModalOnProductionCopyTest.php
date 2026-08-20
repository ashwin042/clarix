<?php

namespace Tests\Manual;

use App\Livewire\Tasks\ManageTasks;
use App\Models\User;
use App\Services\TenantContext;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The board modal, rendered as a supervisor against a clone of the production
 * copy — real units, real PMs, real permission rows.
 *
 * Deliberately outside tests/Feature so `php artisan test` never picks it up,
 * and deliberately without RefreshDatabase so the production-shaped data it
 * exists to exercise is still there. Run it with phpunit-clone.xml, which
 * points at clarix_supclone. It creates one supervisor and removes it again.
 *
 * The sqlite suite proves the branch is right. This proves the branch is right
 * over the data the agency actually has: 15 units and 39 PMs rather than the
 * two units a fixture builds.
 */
class SupervisorModalOnProductionCopyTest extends TestCase
{
    /** A real unit in the clone, the one carrying the most PMs. */
    private const UNIT_ID = 36;

    private const ORGANIZATION_ID = 1;

    protected ?User $supervisor = null;

    protected function tearDown(): void
    {
        if ($this->supervisor) {
            User::withoutGlobalScopes()->whereKey($this->supervisor->id)->forceDelete();
        }

        parent::tearDown();
    }

    private function makeSupervisor(): User
    {
        return $this->supervisor = TenantContext::actingAsOrganization(
            self::ORGANIZATION_ID,
            fn () => User::factory()->create([
                'name'    => 'Clone Probe Supervisor',
                'email'   => 'clone.probe.supervisor@example.test',
                'role'    => 'supervisor',
                'unit_id' => null,
            ])
        );
    }

    public function test_the_modal_offers_a_real_supervisor_the_agencys_units_and_pms(): void
    {
        $supervisor = $this->makeSupervisor();

        $this->assertNull($supervisor->unit_id, 'the role carries no unit, which is the whole problem');

        $component = Livewire::actingAs($supervisor)
            ->test(ManageTasks::class)
            ->call('openCreate')
            ->assertSee('wire:model.live="unit_id"', false)
            ->assertDontSee('Automatically assigned to you', false);

        // Every unit the agency owns, not the empty list a unit-keyed query
        // would return for somebody with no unit.
        $units = $component->viewData('units');
        $this->assertGreaterThan(1, $units->count());
        $this->assertTrue($units->contains('id', self::UNIT_ID));

        // ...and choosing one fills the PM picker from that unit's real PMs.
        $component->set('unit_id', (string) self::UNIT_ID)
            ->assertSee('wire:model="pm_id"', false);

        $pms = $component->viewData('pmsForUnit');
        $this->assertGreaterThan(0, $pms->count());

        foreach ($pms as $pm) {
            $this->assertSame('pm', $pm->role);
            $this->assertSame(self::UNIT_ID, (int) $pm->unit_id);
        }
    }
}
