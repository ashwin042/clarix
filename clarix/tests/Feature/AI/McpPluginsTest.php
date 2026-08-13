<?php

namespace Tests\Feature\AI;

use App\Livewire\AI\McpPlugins;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class McpPluginsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_the_plugin_library_still_renders(): void
    {
        Livewire::actingAs(User::factory()->create(['role' => 'writer']))
            ->test(McpPlugins::class)
            ->assertOk()
            ->assertSee('Connect your tools')
            ->assertSee('Slack')
            ->assertSee('Cloudflare R2');
    }

    /** brand() is what Scheduled Tasks draws its integration marks from. */
    public function test_brand_returns_a_mark_for_a_known_plugin(): void
    {
        $slack = McpPlugins::brand('Slack');

        $this->assertNotNull($slack);
        $this->assertSame('Slack', $slack['name']);
        $this->assertSame('#4A154B', $slack['colour']);
        $this->assertNotSame('', $slack['logo']);
    }

    public function test_brand_returns_null_for_an_unknown_plugin(): void
    {
        $this->assertNull(McpPlugins::brand('Nothing Here'));
    }

    /**
     * The cards are an accordion: one shared openPlugin on the grid, not a
     * per-card open flag. A stray x-data on a card would give it a local scope
     * again and silently restore multi-open behaviour.
     */
    public function test_the_plugin_grid_holds_one_shared_open_state(): void
    {
        $html = Livewire::actingAs(User::factory()->create(['role' => 'writer']))
            ->test(McpPlugins::class)
            ->html();

        $this->assertSame(1, substr_count($html, 'x-data'), 'The grid should own the only x-data scope.');
        $this->assertStringContainsString('{ openPlugin: null }', $html);
        $this->assertStringNotContainsString('{ open: false }', $html);
    }

    /** Each card toggles its own index, and reclicking the open one shuts it. */
    public function test_each_card_toggles_its_own_index(): void
    {
        $html = Livewire::actingAs(User::factory()->create(['role' => 'writer']))
            ->test(McpPlugins::class)
            ->html();

        $count = count(McpPlugins::plugins());

        for ($i = 0; $i < $count; $i++) {
            $this->assertStringContainsString(
                "openPlugin = openPlugin === {$i} ? null : {$i}",
                $html,
                "Card {$i} does not toggle its own index."
            );
            $this->assertStringContainsString("x-show=\"openPlugin === {$i}\"", $html);
            $this->assertStringContainsString("id=\"plugin-panel-{$i}\"", $html);
        }
    }

    public function test_every_plugin_in_the_library_has_a_categorised_tint(): void
    {
        foreach (McpPlugins::plugins() as $plugin) {
            $this->assertArrayHasKey(
                $plugin['category'],
                McpPlugins::CATEGORY_TINT,
                "{$plugin['name']} is in an untinted category."
            );
        }
    }
}
