<?php

namespace Tests\Feature\AI;

use App\Livewire\AI\ScheduledTasks;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScheduledTasksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_it_renders_the_empty_state_and_the_previews(): void
    {
        Livewire::actingAs(User::factory()->create(['role' => 'writer']))
            ->test(ScheduledTasks::class)
            ->assertOk()
            ->assertSee('No automations yet')
            ->assertSee('Trigger: Task Completed')
            ->assertSee('Send delivered files to client')
            ->assertSee('Trigger: Task Update via Chat')
            ->assertSee('Send task details straight to Clarix');
    }

    /**
     * Every integration named on a card has to resolve to a real brand mark,
     * or the flow graph draws an empty circle. This is the guard on the
     * McpPlugins::brand() lookup: a typo in a plugin name fails here.
     */
    public function test_every_integration_resolves_to_a_drawable_mark(): void
    {
        foreach (ScheduledTasks::AUTOMATIONS as $automation) {
            foreach ($automation['integrations'] as $name) {
                $mark = ScheduledTasks::integration($name);

                $this->assertSame($name, $mark['name']);
                $this->assertNotSame('', $mark['logo'], "{$name} has no logo path.");
                $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $mark['colour'], "{$name} has no colour.");
                $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $mark['ink'], "{$name} has no on-dark ink.");
            }
        }
    }

    public function test_every_trigger_kind_has_a_tint(): void
    {
        foreach (ScheduledTasks::AUTOMATIONS as $automation) {
            $this->assertArrayHasKey(
                $automation['kind'],
                ScheduledTasks::TRIGGER_TINT,
                "Trigger kind '{$automation['kind']}' has no tint."
            );
        }
    }

    /**
     * Nothing behind this page works yet, so the create button and every row
     * switch have to stay inert. A stray enabled control would invite a click
     * that silently does nothing.
     */
    public function test_nothing_on_the_page_is_interactive(): void
    {
        $html = Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(ScheduledTasks::class)
            ->html();

        $this->assertStringContainsString('disabled', $html);
        $this->assertSame(
            count(ScheduledTasks::AUTOMATIONS), // one switch per card
            substr_count($html, 'aria-disabled="true"')
        );
        $this->assertStringNotContainsString('wire:click', $html);
    }

    public function test_every_automation_is_complete(): void
    {
        $keys = ['kind', 'trigger', 'trigger_icon', 'title', 'description', 'output', 'output_icon', 'integrations'];

        foreach (ScheduledTasks::AUTOMATIONS as $i => $automation) {
            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $automation, "Automation {$i} has no {$key}.");
                $this->assertNotEmpty($automation[$key], "Automation {$i} has an empty {$key}.");
            }
        }
    }
}
