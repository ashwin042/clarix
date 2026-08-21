<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the Hermes bot, and deliberately authenticates no user.
 *
 * This is the one endpoint group in Clarix that must not go through Sanctum.
 * Every Sanctum token resolves to a real user row, and that user's organization
 * is what TenantContext reports — which would confine the link-code lookup to a
 * single agency and make every other agency's code read as invalid. Leaving the
 * request unauthenticated is what keeps TenantContext null, and null means "do
 * not filter", which is exactly what a platform-wide bot needs.
 *
 * The trade is that the endpoint has no user to derive authority from, so the
 * authority has to be in the request itself:
 *
 *   X-Hermes-Key        names the caller
 *   X-Hermes-Timestamp  bounds how long a captured request stays usable
 *   X-Hermes-Signature  hmac-sha256 over "{timestamp}.{raw body}"
 *
 * Signing the body as well as the timestamp is what stops a captured request
 * being edited into a different one — without it, the key alone would authorise
 * any body at all.
 *
 * No nonce store, on purpose. Replaying a verify call after the code is burned
 * simply fails, and replaying a resolve call reveals nothing the original
 * caller did not already have; the timestamp window covers the rest, and a
 * nonce table would be a write on every bot message for no gain.
 */
class EnsureHermesRequest
{
    /** How far out of date a signed request may be, in seconds. */
    protected const TOLERANCE = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $key    = (string) config('services.hermes.key');
        $secret = (string) config('services.hermes.secret');

        // Fail closed. An environment that has not been given credentials must
        // reject every caller rather than accept every caller — the opposite
        // mistake publishes an open endpoint that hands out user identities.
        if ($key === '' || $secret === '') {
            return $this->refuse();
        }

        if (! hash_equals($key, (string) $request->header('X-Hermes-Key', ''))) {
            return $this->refuse();
        }

        $timestamp = (string) $request->header('X-Hermes-Timestamp', '');

        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > self::TOLERANCE) {
            return $this->refuse();
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        if (! hash_equals($expected, (string) $request->header('X-Hermes-Signature', ''))) {
            return $this->refuse();
        }

        return $next($request);
    }

    /**
     * One shape for every refusal. Which check failed is not the caller's
     * business, and saying would help an attacker tune a forgery.
     */
    protected function refuse(): Response
    {
        return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
    }
}
