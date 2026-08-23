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

    /**
     * Telegram is the one live integration, and this page is now where it is
     * connected. The card mounts the real component, so the mock bot-token form
     * that used to stand in for it must be gone rather than merely disabled.
     */
    public function test_the_telegram_card_mounts_the_live_connect_flow(): void
    {
        $html = Livewire::actingAs(User::factory()->create(['role' => 'writer']))
            ->test(McpPlugins::class)
            ->html();

        $this->assertStringContainsString('Generate code', $html);
        $this->assertStringContainsString('Link your Telegram account', $html);

        $this->assertStringNotContainsString('Bot token', $html);
        $this->assertStringNotContainsString('Chat ID', $html);
        $this->assertStringNotContainsString('123456789:AAE', $html);
        $this->assertStringNotContainsString('-1001234567890', $html);
    }

    /** Every other card keeps its disabled form; only the live ones lose it. */
    public function test_only_the_live_plugins_drop_their_save_button(): void
    {
        $html = Livewire::actingAs(User::factory()->create(['role' => 'writer']))
            ->test(McpPlugins::class)
            ->html();

        $live = count(array_filter(
            McpPlugins::plugins(),
            fn (array $plugin) => $plugin['connect'] ?? false
        ));

        // Two: the AXOKAI link and the Task Bot link. Asserted as a number
        // rather than derived, so that marking a third card live is a decision
        // somebody has to make here as well as in the library.
        $this->assertSame(2, $live, 'Telegram and Task Bot should be the only live plugins.');
        $this->assertSame(
            count(McpPlugins::plugins()) - $live,
            substr_count($html, 'Save Setting'),
            'A live card should not render a Save button.'
        );
    }

    /**
     * A live entry is mounted by the component it names, not by its display
     * name. Every one of them must name a component the view actually has a
     * branch for, or the card silently renders an empty panel.
     */
    public function test_every_live_plugin_names_a_component_the_view_can_mount(): void
    {
        $mountable = [
            'profile.connect-telegram',
            'profile.connect-task-bot',
        ];

        $view = file_get_contents(resource_path('views/livewire/ai/mcp-plugins.blade.php'));

        foreach (McpPlugins::plugins() as $plugin) {
            if (! ($plugin['connect'] ?? false)) {
                $this->assertArrayNotHasKey('component', $plugin, "{$plugin['name']} names a component but is not live.");

                continue;
            }

            $this->assertContains(
                $plugin['component'] ?? null,
                $mountable,
                "{$plugin['name']} names a component nothing can mount."
            );

            $this->assertStringContainsString(
                "<livewire:{$plugin['component']}",
                $view,
                "The view has no branch mounting {$plugin['component']}."
            );
        }
    }

    /**
     * The task bot is a second, separate Telegram bot, and the card has to read
     * that way. Two cards wearing the same mark is how somebody links the wrong
     * bot and spends an afternoon wondering why nothing arrives.
     */
    public function test_the_task_bot_card_is_distinct_from_the_telegram_card(): void
    {
        $telegram = collect(McpPlugins::plugins())->firstWhere('name', 'Telegram');
        $taskBot  = collect(McpPlugins::plugins())->firstWhere('name', 'Task Bot');

        $this->assertNotNull($taskBot, 'The Task Bot card is missing from the library.');
        $this->assertTrue($taskBot['connect']);
        $this->assertNotSame($telegram['logo'], $taskBot['logo'], 'The two bots must not share a mark.');
        $this->assertNotSame($telegram['colour'], $taskBot['colour'], 'The two bots must not share a colour.');
        $this->assertNotSame($telegram['component'], $taskBot['component']);
    }

    public function test_the_task_bot_card_mounts_its_own_connect_flow(): void
    {
        $html = Livewire::actingAs(User::factory()->create(['role' => 'writer']))
            ->test(McpPlugins::class)
            ->html();

        $this->assertStringContainsString('Generate Task Bot code', $html);
        $this->assertStringContainsString('File tasks from Telegram', $html);
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
