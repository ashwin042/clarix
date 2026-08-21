<?php

namespace Tests\Feature\Telegram;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The bot's half of the handshake.
 *
 * Tested against a route defined here rather than against a real endpoint, so
 * that a failure can only mean the middleware: there is no controller, no
 * validation and no database behind it to produce a status of their own.
 */
class HermesAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.hermes.key', 'test-key');
        config()->set('services.hermes.secret', 'test-secret');

        Route::middleware('hermes')->post('/test-hermes', fn () => response()->json(['ok' => true]));
    }

    /**
     * Headers for a correctly signed request, before any override is applied.
     *
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function signed(string $payload, array $overrides = []): array
    {
        $timestamp = (string) now()->getTimestamp();

        return array_merge([
            'X-Hermes-Key'       => 'test-key',
            'X-Hermes-Timestamp' => $timestamp,
            'X-Hermes-Signature' => hash_hmac('sha256', $timestamp.'.'.$payload, 'test-secret'),
            'Content-Type'       => 'application/json',
            'Accept'             => 'application/json',
        ], $overrides);
    }

    /**
     * Send a request whose signature covers $signedBody but whose wire body is
     * $sentBody. They differ only in the tampering test.
     *
     * @param  array<string, mixed>   $signedBody
     * @param  array<string, string>  $overrides
     * @param  array<string, mixed>|null  $sentBody
     */
    private function hit(array $signedBody, array $overrides = [], ?array $sentBody = null)
    {
        $signedPayload = json_encode($signedBody);
        $sentPayload   = $sentBody === null ? $signedPayload : json_encode($sentBody);

        return $this->call(
            'POST',
            '/test-hermes',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($this->signed($signedPayload, $overrides)),
            $sentPayload
        );
    }

    public function test_a_correctly_signed_request_passes(): void
    {
        $this->hit(['chat_id' => 12345])
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_a_wrong_key_is_refused(): void
    {
        $this->hit(['chat_id' => 12345], ['X-Hermes-Key' => 'nope'])
            ->assertUnauthorized();
    }

    public function test_a_wrong_signature_is_refused(): void
    {
        $this->hit(['chat_id' => 12345], ['X-Hermes-Signature' => str_repeat('a', 64)])
            ->assertUnauthorized();
    }

    public function test_a_missing_signature_is_refused(): void
    {
        $this->hit(['chat_id' => 12345], ['X-Hermes-Signature' => ''])
            ->assertUnauthorized();
    }

    /** A captured request must not stay replayable for ever. */
    public function test_a_stale_timestamp_is_refused(): void
    {
        $payload = json_encode(['chat_id' => 12345]);
        $stale   = (string) now()->subMinutes(10)->getTimestamp();

        $this->hit(['chat_id' => 12345], [
            'X-Hermes-Timestamp' => $stale,
            'X-Hermes-Signature' => hash_hmac('sha256', $stale.'.'.$payload, 'test-secret'),
        ])->assertUnauthorized();
    }

    public function test_a_non_numeric_timestamp_is_refused(): void
    {
        $this->hit(['chat_id' => 12345], ['X-Hermes-Timestamp' => 'not-a-time'])
            ->assertUnauthorized();
    }

    /**
     * Signing one body and sending another must not pass — otherwise the key
     * alone would authorise any body at all.
     */
    public function test_a_tampered_body_is_refused(): void
    {
        $this->hit(['chat_id' => 12345], [], ['chat_id' => 99999])
            ->assertUnauthorized();
    }

    /**
     * An unconfigured deploy must reject everything rather than accept
     * everything. Failing open here would publish an unauthenticated endpoint
     * that hands out user identities.
     */
    public function test_an_unconfigured_secret_refuses_every_request(): void
    {
        config()->set('services.hermes.key', null);
        config()->set('services.hermes.secret', null);

        $this->hit(['chat_id' => 12345])->assertUnauthorized();
    }
}
