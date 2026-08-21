<?php

namespace Tests\Feature\Telegram;

use App\Livewire\Profile\ConnectTelegram;
use App\Models\User;
use App\Services\TelegramLinkService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

class ConnectTelegramTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);

        config()->set('services.hermes.bot_username', 'Jarvis_clarix_assistant_bot');

        $this->org = $this->populate($this->makeOrganization('card-a', 'Agency A'), 'A');
        $this->subscribeOrganization($this->org['organization'], 'pro');
    }

    public function test_generating_shows_a_code_and_stores_only_its_hash(): void
    {
        $user = $this->org['pm'];

        $component = Livewire::actingAs($user)->test(ConnectTelegram::class)
            ->call('generate');

        $code = $component->get('code');

        $this->assertNotNull($code);
        $this->assertSame(TelegramLinkService::CODE_LENGTH, strlen($code));

        $user->refresh();
        $this->assertSame(TelegramLinkService::hashOf($code), $user->telegram_link_code_hash);
        $this->assertNotSame($code, $user->telegram_link_code_hash);
    }

    public function test_the_card_shows_the_bot_deep_link(): void
    {
        Livewire::actingAs($this->org['pm'])->test(ConnectTelegram::class)
            ->call('generate')
            ->assertSee('https://t.me/Jarvis_clarix_assistant_bot?start=', false);
    }

    public function test_generating_again_replaces_the_previous_code(): void
    {
        $user = $this->org['pm'];

        $component = Livewire::actingAs($user)->test(ConnectTelegram::class);

        $first  = $component->call('generate')->get('code');
        $second = $component->call('generate')->get('code');

        $this->assertNotSame($first, $second);
        $this->assertSame(TelegramLinkService::hashOf($second), $user->fresh()->telegram_link_code_hash);
    }

    public function test_a_linked_user_sees_the_connected_state(): void
    {
        $user    = $this->org['pm'];
        $service = app(TelegramLinkService::class);
        $service->verify($service->issueFor($user), 5150);

        Livewire::actingAs($user->fresh())->test(ConnectTelegram::class)
            ->assertSee('Connected')
            ->assertSee('Disconnect');
    }

    public function test_disconnecting_clears_the_link(): void
    {
        $user    = $this->org['pm'];
        $service = app(TelegramLinkService::class);
        $service->verify($service->issueFor($user), 5151);

        Livewire::actingAs($user->fresh())->test(ConnectTelegram::class)
            ->call('disconnect');

        $fresh = TenantContext::runWithoutScope(fn () => User::find($user->id));

        $this->assertNull($fresh->telegram_chat_id);
        $this->assertFalse($fresh->hasLinkedTelegram());
    }

    /**
     * The card is embedded in a page every user must reach, so a plan it does
     * not cover has to read as a locked panel rather than a 402 over the whole
     * of /settings.
     */
    public function test_a_base_plan_sees_a_locked_card_rather_than_a_refusal(): void
    {
        $base = $this->populate($this->makeOrganization('card-b', 'Base Co'), 'B');
        $this->subscribeOrganization($base['organization'], 'base');

        Livewire::actingAs($base['pm'])->test(ConnectTelegram::class)
            ->assertOk()
            ->assertSee('Upgrade to Pro')
            ->assertDontSee('Generate code');
    }

    /**
     * The locked state is presentation. A crafted POST to /livewire/update
     * skips the route stack entirely, so the action itself must refuse.
     *
     * Asserted on "in your Base plan" rather than on the whole refusal
     * sentence for two reasons. The sentence EnsurePlanIncludes builds contains
     * "isn't", which Blade escapes to &#039; — assertSee would look for a
     * straight apostrophe and never find it. And the locked card's own copy
     * already says "Upgrade to Pro", so asserting on that would pass even if
     * the refusal were never set; naming the plan is what distinguishes them.
     */
    public function test_a_base_plan_cannot_generate_a_code_by_calling_the_action(): void
    {
        $base = $this->populate($this->makeOrganization('card-c', 'Base Two'), 'C');
        $this->subscribeOrganization($base['organization'], 'base');

        Livewire::actingAs($base['pm'])->test(ConnectTelegram::class)
            ->call('generate')
            ->assertSee('in your Base plan');

        $this->assertNull($base['pm']->fresh()->telegram_link_code_hash);
    }

    public function test_a_base_plan_cannot_disconnect_by_calling_the_action(): void
    {
        $base    = $this->populate($this->makeOrganization('card-d', 'Base Three'), 'D');
        $service = app(TelegramLinkService::class);

        $this->subscribeOrganization($base['organization'], 'pro');
        $service->verify($service->issueFor($base['pm']), 5152);

        // Backdated identically by the helper, so this ties with the pro row on
        // started_at and wins on the id tiebreak — the agency is now Base.
        $this->subscribeOrganization($base['organization'], 'base');

        Livewire::actingAs($base['pm']->fresh())->test(ConnectTelegram::class)
            ->call('disconnect')
            ->assertSee('in your Base plan');

        $fresh = TenantContext::runWithoutScope(fn () => User::find($base['pm']->id));
        $this->assertSame(5152, $fresh->telegram_chat_id);
    }

    /** Minting codes must not be an unbounded operation either. */
    public function test_code_generation_is_rate_limited(): void
    {
        $component = Livewire::actingAs($this->org['pm'])->test(ConnectTelegram::class);

        for ($i = 0; $i < 5; $i++) {
            $component->call('generate');
        }

        $component->call('generate')->assertSee('Too many codes');
    }

    /**
     * The settings page must stay reachable for every plan — and Telegram is no
     * longer part of it. Linking moved to the Telegram card on MCP & Plugins,
     * so a stray mention here would send people to a page that cannot do it.
     */
    public function test_the_settings_page_renders_without_telegram(): void
    {
        $base = $this->populate($this->makeOrganization('card-e', 'Base Four'), 'E');
        $this->subscribeOrganization($base['organization'], 'base');

        $this->actingAs($base['pm'])
            ->get('/settings')
            ->assertOk()
            ->assertSee('Danger Zone')
            ->assertDontSee('Telegram');
    }

    /**
     * The one live integration is reachable where it now lives. Pro, because
     * /ai/mcp is gated on the automation feature.
     */
    public function test_the_mcp_page_carries_the_connect_card(): void
    {
        $this->actingAs($this->org['pm'])
            ->get('/ai/mcp')
            ->assertOk()
            ->assertSee('Generate code');
    }

    /**
     * "Hermes" is the bot's internal name and stays in the wire protocol, the
     * config keys and the class names. It must never reach a user, so this
     * sweeps every rendered template rather than trusting a code review.
     */
    public function test_no_view_says_hermes(): void
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
            // link is excused from this rule whatever handle it carries. The
            // current one happens not to need the excuse; the next one might.
            $body = str_replace('Jarvis_clarix_assistant_bot', '', file_get_contents($view->getPathname()));

            if (stripos($body, 'hermes') !== false) {
                $offenders[] = $view->getPathname();
            }
        }

        $this->assertSame([], $offenders, 'Views must say AXOKAI, never Hermes.');
    }
}
