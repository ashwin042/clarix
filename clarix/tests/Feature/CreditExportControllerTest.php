<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class CreditExportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makePm(Unit $unit): User
    {
        return User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
    }

    private function makeWriter(): User
    {
        return User::factory()->create(['role' => 'writer']);
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $response = $this->get(route('credits.export'));
        $response->assertRedirect(route('login'));
    }

    public function test_admin_can_download_export(): void
    {
        Excel::fake();

        $this->actingAs($this->makeAdmin());

        $this->get(route('credits.export'))->assertOk();

        Excel::assertDownloaded('credit-list-export-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function test_pm_can_download_export(): void
    {
        Excel::fake();

        $unit = Unit::create(['name' => 'PM Unit']);
        $this->actingAs($this->makePm($unit));

        $this->get(route('credits.export'))->assertOk();

        Excel::assertDownloaded('credit-list-export-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function test_writer_without_credits_view_permission_is_forbidden(): void
    {
        // By default in PermissionSeeder, writers have credits.view = false
        $this->actingAs($this->makeWriter());

        $this->get(route('credits.export'))->assertForbidden();
    }

    public function test_export_accepts_all_filter_query_params(): void
    {
        Excel::fake();

        $admin = $this->makeAdmin();
        $unit  = Unit::create(['name' => 'Filter Unit']);
        $this->actingAs($admin);

        $this->get(route('credits.export', [
            'dateFrom'   => '2026-01-01',
            'dateTo'     => '2026-12-31',
            'filterUnit' => $unit->id,
            'viewMode'   => 'unified',
        ]))->assertOk();

        Excel::assertDownloaded('credit-list-export-' . now()->format('Y-m-d') . '.xlsx');
    }
}
