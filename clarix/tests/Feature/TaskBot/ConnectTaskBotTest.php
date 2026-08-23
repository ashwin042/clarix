<?php

namespace Tests\Feature\TaskBot;

use App\Livewire\Profile\ConnectTaskBot;
use App\Services\N8nTelegramLinkService;
use App\Services\TelegramLinkService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

class ConnectTaskBotTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);

        config()->set('services.n8n.bot_username', 'clarix_task_bot');

        $this->org = $this->populate($this->makeOrganization('tbc-a', 'Agency A'), 'A');
        $this->subscribeOrganization($this->org['organization'], 'pro');
    }

    public function test_generating_shows_a_code_and_stores_only_its_hash(): void
    {
        $user = $this->org['pm'];

        $component = Livewire::actingAs($user)->test(ConnectTaskBot::class)
            ->call('generate');

        $code = $component->get('code');

        $this->assertNotNull($code);
        $this->assertSame(N8nTelegramLinkService::CODE_LENGTH, strlen($code));

        $link = app(N8nTelegramLinkService::class)->linkFor($user);

        $this->assertSame(N8nTelegramLinkService::hashOf($code), $link->link_code_hash);
        $this->assertNotSame($code, $link->link_code_hash);
    }

    public function test_the_card_shows_the_task_bot_deep_link(): void
    {
        Livewire::actingAs($this->org['pm'])->test(ConnectTaskBot::class)
            ->call('generate')
            ->assertSee('https://t.me/clarix_task_bot?start=', false);
    }

    /** Two bots, two handles. Pointing both cards at one is the obvious slip. */
    public function test_the_card_does_not_point_at_the_axokai_bot(): void
    {
        config()->set('services.hermes.bot_username', 'Jarvis_clarix_assistant_bot');

        Livewire::actingAs($this->org['pm'])->test(ConnectTaskBot::class)
            ->call('generate')
            ->assertDontSee('Jarvis_clarix_assistant_bot', false);
    }

    public function test_the_opening_state_offers_a_code(): void
    {
        Livewire::actingAs($this->org['pm'])->test(ConnectTaskBot::class)
            ->assertOk()
            ->assertSee('Generate Task Bot code')
            ->assertDontSee('Connected');
    }

    public function test_generating_again_replaces_the_previous_code(): void
    {
        $user      = $this->org['pm'];
        $component = Livewire::actingAs($user)->test(ConnectTaskBot::class);

        $first  = $component->call('generate')->get('code');
        $second = $component->call('generate')->get('code');

        $this->assertNotSame($first, $second);
        $this->assertSame(
            N8nTelegramLinkService::hashOf($second),
            app(N8nTelegramLinkService::class)->linkFor($user)->link_code_hash
        );
    }

    public function test_a_linked_user_sees_the_connected_state(): void
    {
        $user    = $this->org['pm'];
        $service = app(N8nTelegramLinkService::class);
        $service->verify($service->issueCode($user), '5150');

        Livewire::actingAs($user->fresh())->test(ConnectTaskBot::class)
            ->assertSee('Connected')
            ->assertSee('Disconnect');
    }

    /**
     * A row exists from the moment a code is minted and is_active defaults to
     * true, so the card must read isLive() rather than the flag — otherwise
     * asking for a code would show "Connected" before anything was.
     */
    public function test_an_outstanding_code_is_not_the_connected_state(): void
    {
        Livewire::actingAs($this->org['pm'])->test(ConnectTaskBot::class)
            ->call('generate')
            ->assertDontSee('Disconnect')
            ->assertSee('It works once');
    }

    public function test_disconnecting_clears_the_link(): void
    {
        $user    = $this->org['pm'];
        $service = app(N8nTelegramLinkService::class);
        $service->verify($service->issueCode($user), '5151');

        Livewire::actingAs($user->fresh())->test(ConnectTaskBot::class)
            ->call('disconnect');

        $this->assertFalse($service->linkFor($user)->isLive());
        $this->assertNull($service->resolve('5151'));
    }

    /**
     * The card is embedded in a page every user must reach, so a plan it does
     * not cover has to read as a locked panel rather than a 402 over the whole
     * page.
     */
    public function test_a_base_plan_sees_a_locked_card_rather_than_a_refusal(): void
    {
        $base = $this->populate($this->makeOrganization('tbc-b', 'Base Co'), 'B');
        $this->subscribeOrganization($base['organization'], 'base');

        Livewire::actingAs($base['pm'])->test(ConnectTaskBot::class)
            ->assertOk()
            ->assertSee('Upgrade to Pro')
            ->assertDontSee('Generate Task Bot code');
    }

    /**
     * The locked state is presentation. A crafted POST to /livewire/update
     * skips the route stack entirely, so the action itself must refuse.
     *
     * Asserted on "in your Base plan" rather than on the whole refusal
     * sentence, because the sentence EnsurePlanIncludes builds contains
     * "isn't", which Blade escapes to &#039; — assertSee would look for a
     * straight apostrophe and never find it.
     */
    public function test_a_base_plan_cannot_generate_a_code_by_calling_the_action(): void
    {
        $base = $this->populate($this->makeOrganization('tbc-c', 'Base Two'), 'C');
        $this->subscribeOrganization($base['organization'], 'base');

        Livewire::actingAs($base['pm'])->test(ConnectTaskBot::class)
            ->call('generate')
            ->assertSee('in your Base plan');

        $this->assertNull(app(N8nTelegramLinkService::class)->linkFor($base['pm']));
    }

    public function test_a_base_plan_cannot_disconnect_by_calling_the_action(): void
    {
        $base    = $this->populate($this->makeOrganization('tbc-d', 'Base Three'), 'D');
        $service = app(N8nTelegramLinkService::class);

        $this->subscribeOrganization($base['organization'], 'pro');
        $service->verify($service->issueCode($base['pm']), '5152');

        // Backdated identically by the helper, so this ties with the pro row on
        // started_at and wins on the id tiebreak — the agency is now Base.
        $this->subscribeOrganization($base['organization'], 'base');

        Livewire::actingAs($base['pm']->fresh())->test(ConnectTaskBot::class)
            ->call('disconnect')
            ->assertSee('in your Base plan');

        $this->assertTrue($service->linkFor($base['pm'])->isLive());
    }

    /** Minting codes must not be an unbounded operation either. */
    public function test_code_generation_is_rate_limited(): void
    {
        $component = Livewire::actingAs($this->org['pm'])->test(ConnectTaskBot::class);

        for ($i = 0; $i < 5; $i++) {
            $component->call('generate');
        }

        $component->call('generate')->assertSee('Too many codes');
    }

    /**
     * The two cards hold separate budgets. Spending one on both would mean
     * connecting one bot leaves you unable to connect the other for a quarter
     * of an hour, for no reason a user could possibly infer.
     */
    public function test_the_two_cards_do_not_share_a_code_budget(): void
    {
        $user = $this->org['pm'];

        $taskBot = Livewire::actingAs($user)->test(ConnectTaskBot::class);

        for ($i = 0; $i < 5; $i++) {
            $taskBot->call('generate');
        }

        $taskBot->call('generate')->assertSee('Too many codes');

        Livewire::actingAs($user)->test(\App\Livewire\Profile\ConnectTelegram::class)
            ->call('generate')
            ->assertDontSee('Too many codes');
    }

    /**
     * The cards report independent state. Connecting AXOKAI must not make the
     * task bot card claim a link that does not exist.
     */
    public function test_connecting_the_other_bot_does_not_connect_this_one(): void
    {
        $user   = $this->org['pm'];
        $hermes = app(TelegramLinkService::class);

        $hermes->verify($hermes->issueFor($user), 5153);

        Livewire::actingAs($user->fresh())->test(ConnectTaskBot::class)
            ->assertSee('Generate Task Bot code')
            ->assertDontSee('Disconnect');
    }

    /** Both live cards reach the page they live on. */
    public function test_the_mcp_page_carries_both_connect_cards(): void
    {
        $this->actingAs($this->org['pm'])
            ->get('/ai/mcp')
            ->assertOk()
            ->assertSee('Generate code')
            ->assertSee('Generate Task Bot code')
            ->assertSee('Task Bot');
    }

    /**
     * "n8n" is this integration's internal name and stays in the wire protocol,
     * the config keys, the table name and the class names. It must never reach
     * a user, so this sweeps every rendered template rather than trusting a
     * code review — the same rule, and the same sweep, that keeps "Hermes" out
     * of the views.
     */
    public function test_no_view_says_n8n(): void
    {
        $offenders = [];

        $views = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($views as $view) {
            if (! $view->isFile() || ! str_ends_with($view->getFilename(), '.blade.php')) {
                continue;
            }

            // The bot's @username is set in BotFather, not here, so the deep
            // link is excused from this rule whatever handle it carries — and
            // it is interpolated rather than written out in any case.
            $body = str_replace('clarix_task_bot', '', file_get_contents($view->getPathname()));

            if (stripos($body, 'n8n') !== false) {
                $offenders[] = $view->getPathname();
            }
        }

        $this->assertSame([], $offenders, 'Views must say Task Bot, never n8n.');
    }

    /** The user-facing copy of the card names the bot the way the library does. */
    public function test_the_card_copy_uses_the_product_name(): void
    {
        $html = Livewire::actingAs($this->org['pm'])->test(ConnectTaskBot::class)->html();

        $this->assertStringContainsString('Task Bot', $html);
        $this->assertStringNotContainsStringIgnoringCase('n8n', $html);
        $this->assertStringNotContainsStringIgnoringCase('hermes', $html);
    }
}
