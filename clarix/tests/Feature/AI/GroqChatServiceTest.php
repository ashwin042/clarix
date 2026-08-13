<?php

namespace Tests\Feature\AI;

use App\Livewire\AI\Chatbot;
use App\Services\GroqChatService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GroqChatServiceTest extends TestCase
{
    private GroqChatService $groq;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.groq.key', 'test-key');
        $this->groq = app(GroqChatService::class);
    }

    private function reply(string $content): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => $content]]]];
    }

    public function test_it_returns_the_assistant_reply(): void
    {
        Http::fake(['api.groq.com/*' => Http::response($this->reply('Hello from Groq.'))]);

        $answer = $this->groq->send(
            [['role' => 'user', 'body' => 'Hi']],
            'Titan 3.2',
            'Balanced'
        );

        $this->assertSame('Hello from Groq.', $answer);
    }

    /** Every name in the picker must map to a real, configured Groq model ID. */
    public function test_every_ui_model_resolves_to_a_configured_groq_id(): void
    {
        foreach (Chatbot::MODELS as $name) {
            $id = $this->groq->resolveModel($name);

            $this->assertNotSame('', $id, "{$name} resolved to nothing.");
            $this->assertArrayHasKey($name, config('services.groq.models'), "{$name} has no mapping.");
        }
    }

    /**
     * The names carry dots ("Titan 3.2"). Resolving them with config() dot
     * notation silently misses every mapping and hands back the fallback, so
     * each name must resolve to its own configured ID, not the fallback by
     * accident. Titan is checked explicitly because its ID differs from it.
     */
    public function test_names_containing_dots_resolve_to_their_own_mapping(): void
    {
        $configured = config('services.groq.models');

        foreach ($configured as $name => $expected) {
            $this->assertSame($expected, $this->groq->resolveModel($name), "{$name} did not resolve to its mapping.");
        }

        config()->set('services.groq.fallback_model', 'sentinel-fallback');
        $this->assertSame($configured['Titan 3.2'], $this->groq->resolveModel('Titan 3.2'));
        $this->assertSame('sentinel-fallback', $this->groq->resolveModel('Unmapped Name'));
    }

    /**
     * That a system prompt is sent at all, and that it carries the identity
     * rules. The behaviour rules inside it — live data, task actions, the
     * general-knowledge disclaimer, hedging — belong to
     * ChatbotResponseRulesTest and are not repeated here.
     */
    public function test_a_system_prompt_is_sent_and_carries_the_identity_rules(): void
    {
        Http::fake(['api.groq.com/*' => Http::response($this->reply('ok'))]);

        $this->groq->send([['role' => 'user', 'body' => 'Hi']], 'Titan 3.2', 'Fast');

        Http::assertSent(function (Request $request) {
            $prompt = $request->data()['messages'][0]['content'];

            $this->assertSame('system', $request->data()['messages'][0]['role']);
            $this->assertStringContainsString('Clarix AI', $prompt);
            $this->assertStringContainsString('powered by AXOKAI', $prompt);

            // never name the vendor or the architecture
            foreach (['Groq', 'Llama', 'GPT-OSS', 'OpenAI'] as $forbidden) {
                $this->assertStringContainsString($forbidden, $prompt, "The prompt must name {$forbidden} as off-limits.");
            }
            $this->assertStringContainsString('Never mention', $prompt);

            return true;
        });
    }

    /** Models Groq has retired must never appear in the mapping. */
    public function test_the_mapping_uses_no_retired_models(): void
    {
        $retired = ['gemma2-9b-it', 'llama3-70b-8192', 'llama3-8b-8192', 'mixtral-8x7b-32768', 'gemma-7b-it'];

        foreach (config('services.groq.models') as $name => $id) {
            $this->assertNotContains($id, $retired, "{$name} points at retired model {$id}.");
        }
    }

    public function test_effort_changes_the_temperature_and_token_ceiling(): void
    {
        $fast = $this->groq->resolveEffort('Fast');
        $deep = $this->groq->resolveEffort('Deep');

        $this->assertLessThan($deep['max_tokens'], $fast['max_tokens']);
        $this->assertLessThan($deep['temperature'], $fast['temperature']);
    }

    public function test_the_request_carries_the_model_effort_and_system_prompt(): void
    {
        Http::fake(['api.groq.com/*' => Http::response($this->reply('ok'))]);

        $this->groq->send([['role' => 'user', 'body' => 'Hi']], 'Gaia 2.0', 'Deep');

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            $this->assertSame(config('services.groq.models')['Gaia 2.0'], $body['model']);
            $this->assertSame(2048, $body['max_completion_tokens']);
            $this->assertSame('system', $body['messages'][0]['role']);
            $this->assertSame('user', $body['messages'][1]['role']);
            $this->assertSame('Bearer test-key', $request->header('Authorization')[0]);

            return true;
        });
    }

    /** Our own error bubbles must not be replayed to the model as context. */
    public function test_error_messages_are_stripped_from_the_thread_sent_upstream(): void
    {
        Http::fake(['api.groq.com/*' => Http::response($this->reply('ok'))]);

        $this->groq->send([
            ['role' => 'user', 'body' => 'One'],
            ['role' => 'error', 'body' => 'Something broke'],
            ['role' => 'user', 'body' => 'Two'],
        ], 'Titan 3.2', 'Fast');

        Http::assertSent(function (Request $request) {
            $roles = array_column($request->data()['messages'], 'role');

            $this->assertSame(['system', 'user', 'user'], $roles);

            return true;
        });
    }

    public function test_an_api_failure_becomes_a_friendly_message(): void
    {
        Http::fake(['api.groq.com/*' => Http::response(['error' => ['message' => 'rate limit']], 429)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/try again/i');

        $this->groq->send([['role' => 'user', 'body' => 'Hi']], 'Titan 3.2', 'Fast');
    }

    public function test_a_server_error_becomes_a_friendly_message(): void
    {
        Http::fake(['api.groq.com/*' => Http::response('boom', 500)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/temporarily unavailable/i');

        $this->groq->send([['role' => 'user', 'body' => 'Hi']], 'Titan 3.2', 'Fast');
    }

    public function test_an_empty_completion_becomes_a_friendly_message(): void
    {
        Http::fake(['api.groq.com/*' => Http::response($this->reply('   '))]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/rephrasing/i');

        $this->groq->send([['role' => 'user', 'body' => 'Hi']], 'Titan 3.2', 'Fast');
    }

    public function test_a_missing_api_key_is_reported_without_calling_out(): void
    {
        config()->set('services.groq.key', null);
        Http::fake();

        $this->assertFalse($this->groq->isConfigured());

        try {
            $this->groq->send([['role' => 'user', 'body' => 'Hi']], 'Titan 3.2', 'Fast');
            $this->fail('Expected a RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('not configured', $e->getMessage());
        }

        Http::assertNothingSent();
    }
}
