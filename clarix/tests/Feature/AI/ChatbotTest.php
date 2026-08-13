<?php

namespace Tests\Feature\AI;

use App\Livewire\AI\Chatbot;
use App\Models\DailyChatRequest;
use App\Models\User;
use App\Services\ChatQuota;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ChatbotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        config()->set('services.groq.key', 'test-key');
        config()->set('services.groq.daily_limit', 15);
    }

    private function fakeReply(string $content = 'A real answer.'): void
    {
        Http::fake(['api.groq.com/*' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => $content]]],
        ])]);
    }

    public function test_sending_shows_the_message_then_the_reply(): void
    {
        $this->fakeReply('Groq says hello.');

        $component = Livewire::actingAs(User::factory()->create(['role' => 'writer']))
            ->test(Chatbot::class)
            ->call('send', 'What is a task?');

        // First pass: the user's message is up and a reply is in flight.
        $component->assertSet('pending', true)
            ->assertSet('messages.0.role', 'user')
            ->assertSet('messages.0.body', 'What is a task?');

        // Second pass: the call itself.
        $component->call('reply')
            ->assertSet('pending', false)
            ->assertSet('messages.1.role', 'assistant')
            ->assertSet('messages.1.body', 'Groq says hello.');
    }

    public function test_a_successful_send_consumes_exactly_one_message(): void
    {
        $this->fakeReply();
        $user = User::factory()->create(['role' => 'writer']);

        Livewire::actingAs($user)->test(Chatbot::class)
            ->call('send', 'Hello')
            ->call('reply');

        $this->assertSame(1, app(ChatQuota::class)->used($user));
    }

    public function test_an_empty_message_does_nothing(): void
    {
        Http::fake();
        $user = User::factory()->create(['role' => 'writer']);

        Livewire::actingAs($user)->test(Chatbot::class)
            ->call('send', '   ')
            ->assertSet('messages', [])
            ->assertSet('pending', false);

        $this->assertSame(0, app(ChatQuota::class)->used($user));
        Http::assertNothingSent();
    }

    public function test_at_the_limit_it_refuses_without_calling_groq(): void
    {
        Http::fake();
        $user = User::factory()->create(['role' => 'writer']);

        DailyChatRequest::create([
            'user_id'       => $user->id,
            'date'          => today()->toDateString(),
            'request_count' => 15,
        ]);

        Livewire::actingAs($user)->test(Chatbot::class)
            ->call('send', 'One more?')
            ->assertSet('pending', false)
            ->assertSet('messages.0.role', 'error')
            ->assertSet('messages.0.body', "You've reached your daily limit of 15 messages. This resets tomorrow.");

        Http::assertNothingSent();
    }

    public function test_the_composer_is_disabled_once_the_limit_is_reached(): void
    {
        $user = User::factory()->create(['role' => 'writer']);

        DailyChatRequest::create([
            'user_id'       => $user->id,
            'date'          => today()->toDateString(),
            'request_count' => 15,
        ]);

        Livewire::actingAs($user)->test(Chatbot::class)
            ->assertSee('Daily limit reached')
            ->assertSee('Daily limit of 15 messages reached. This resets tomorrow.');
    }

    public function test_the_remaining_count_is_shown_near_the_composer(): void
    {
        $user = User::factory()->create(['role' => 'writer']);

        DailyChatRequest::create([
            'user_id'       => $user->id,
            'date'          => today()->toDateString(),
            'request_count' => 3,
        ]);

        Livewire::actingAs($user)->test(Chatbot::class)
            ->assertSee('12 messages remaining today');
    }

    public function test_an_api_failure_shows_an_inline_error_and_refunds_the_message(): void
    {
        Http::fake(['api.groq.com/*' => Http::response(['error' => ['message' => 'upstream']], 500)]);
        $user = User::factory()->create(['role' => 'writer']);

        $component = Livewire::actingAs($user)->test(Chatbot::class)
            ->call('send', 'Hello')
            ->call('reply');

        $component->assertSet('pending', false)
            ->assertSet('messages.1.role', 'error');

        $this->assertStringContainsString(
            'temporarily unavailable',
            $component->get('messages')[1]['body']
        );

        // A failed call must not cost the user an allowance.
        $this->assertSame(0, app(ChatQuota::class)->used($user));
    }

    public function test_assistant_replies_render_as_markdown_in_the_thread(): void
    {
        $this->fakeReply("Here's the plan:\n\n- **Ship** the brief\n- Chase the client");

        $html = Livewire::actingAs(User::factory()->create(['role' => 'writer']))
            ->test(Chatbot::class)
            ->call('send', 'Plan?')
            ->call('reply')
            ->html();

        $this->assertStringContainsString('<strong>Ship</strong>', $html);
        $this->assertStringContainsString('<li>', $html);
        $this->assertStringNotContainsString('- **Ship**', $html);
    }

    /**
     * Assistant output is rendered unescaped; the user's own message must not
     * be, or a user could inject script into their own page.
     */
    public function test_a_users_own_message_is_never_rendered_as_html(): void
    {
        Http::fake();

        $html = Livewire::actingAs(User::factory()->create(['role' => 'writer']))
            ->test(Chatbot::class)
            ->call('send', '<script>alert("xss")</script>')
            ->html();

        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_a_malicious_reply_from_the_model_is_stripped(): void
    {
        $this->fakeReply('Sure <script>alert("xss")</script> here you go');

        $html = Livewire::actingAs(User::factory()->create(['role' => 'writer']))
            ->test(Chatbot::class)
            ->call('send', 'Hi')
            ->call('reply')
            ->html();

        $this->assertStringNotContainsString('<script>alert', $html);
    }

    public function test_the_coming_soon_copy_is_gone(): void
    {
        Livewire::actingAs(User::factory()->create(['role' => 'writer']))
            ->test(Chatbot::class)
            ->assertDontSee('This feature is coming soon')
            ->assertDontSee('not connected to a model yet')
            ->assertDontSee('placeholders');
    }

    public function test_the_model_and_effort_selectors_still_work(): void
    {
        Livewire::actingAs(User::factory()->create(['role' => 'writer']))
            ->test(Chatbot::class)
            ->call('setModel', 'Olympus Max')->assertSet('model', 'Olympus Max')
            ->call('setEffort', 'Deep')->assertSet('effort', 'Deep')
            // and reject anything not on the menu
            ->call('setModel', 'Not A Model')->assertSet('model', 'Olympus Max')
            ->call('setEffort', 'Turbo')->assertSet('effort', 'Deep');
    }

    public function test_the_chosen_model_and_effort_reach_groq(): void
    {
        $this->fakeReply();

        Livewire::actingAs(User::factory()->create(['role' => 'writer']))
            ->test(Chatbot::class)
            ->call('setModel', 'Gaia 2.0')
            ->call('setEffort', 'Fast')
            ->call('send', 'Hi')
            ->call('reply');

        Http::assertSent(function ($request) {
            $this->assertSame(config('services.groq.models')['Gaia 2.0'], $request->data()['model']);
            $this->assertSame(512, $request->data()['max_completion_tokens']);

            return true;
        });
    }

    public function test_clearing_empties_the_thread(): void
    {
        $this->fakeReply();

        Livewire::actingAs(User::factory()->create(['role' => 'writer']))
            ->test(Chatbot::class)
            ->call('send', 'Hi')
            ->call('reply')
            ->call('clear')
            ->assertSet('messages', [])
            ->assertSet('pending', false);
    }
}
