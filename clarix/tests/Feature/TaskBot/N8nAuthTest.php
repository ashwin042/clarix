<?php

namespace Tests\Feature\TaskBot;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The pipeline's half of the handshake.
 *
 * Tested against a route defined here rather than against a real endpoint, so
 * that a failure can only mean the middleware: there is no controller, no
 * validation and no database behind it to produce a status of their own.
 */
class N8nAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.n8n.key', 'test-n8n-key');

        Route::middleware('n8n')->post('/test-n8n', fn () => response()->json(['ok' => true]));
    }

    /** @param array<string, string> $headers */
    private function hit(array $headers = [])
    {
        return $this->postJson('/test-n8n', ['chat_id' => '12345'], $headers);
    }

    public function test_the_right_key_passes(): void
    {
        $this->hit(['X-N8n-Key' => 'test-n8n-key'])
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_a_wrong_key_is_refused(): void
    {
        $this->hit(['X-N8n-Key' => 'nope'])->assertUnauthorized();
    }

    public function test_a_missing_key_is_refused(): void
    {
        $this->hit()->assertUnauthorized();
    }

    public function test_an_empty_key_header_is_refused(): void
    {
        $this->hit(['X-N8n-Key' => ''])->assertUnauthorized();
    }

    /**
     * An unconfigured deploy must reject everything rather than accept
     * everything. This matters more here than under a signing scheme: an empty
     * configured key would otherwise compare equal to an absent header and
     * publish an open endpoint that hands out user identities.
     */
    public function test_an_unconfigured_key_refuses_every_request(): void
    {
        config()->set('services.n8n.key', null);

        $this->hit()->assertUnauthorized();
        $this->hit(['X-N8n-Key' => ''])->assertUnauthorized();
        $this->hit(['X-N8n-Key' => 'anything'])->assertUnauthorized();
    }

    /**
     * The two bots authenticate separately. A caller holding one integration's
     * credentials must not thereby hold the other's — otherwise rotating one
     * key would be doing half a job.
     */
    public function test_the_task_bot_key_does_not_open_the_axokai_endpoints(): void
    {
        config()->set('services.hermes.key', 'hermes-key');
        config()->set('services.hermes.secret', 'hermes-secret');

        $this->postJson('/api/v1/telegram/resolve', ['chat_id' => 1], [
            'X-N8n-Key' => 'test-n8n-key',
        ])->assertUnauthorized();
    }

    public function test_the_axokai_key_does_not_open_the_task_bot_endpoints(): void
    {
        config()->set('services.hermes.key', 'hermes-key');
        config()->set('services.hermes.secret', 'hermes-secret');

        $this->postJson('/api/v1/n8n/telegram/resolve', ['chat_id' => '1'], [
            'X-Hermes-Key' => 'hermes-key',
        ])->assertUnauthorized();
    }
}
