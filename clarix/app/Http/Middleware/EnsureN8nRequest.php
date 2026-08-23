<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the task-submission pipeline, and deliberately authenticates no
 * user.
 *
 * Same reasoning as EnsureHermesRequest and none of its code. Sanctum is wrong
 * here for the reason it is wrong there: every Sanctum token resolves to a real
 * user row, and that user's organization is what TenantContext reports, which
 * would confine the link-code lookup to a single agency and make every other
 * agency's code read as invalid. Leaving the request unauthenticated is what
 * keeps TenantContext null, and null means "do not filter".
 *
 * What it does *not* copy is the signing. AXOKAI signs each request because it
 * is a bot Clarix operates and the signature bounds how long a captured request
 * stays usable. This caller is an n8n workflow, where the request is assembled
 * in a visual node editor and an HMAC over "{timestamp}.{raw body}" is a
 * function call the editor cannot make without a code node — the practical
 * outcome of demanding one is a workflow that copies a signing secret into a
 * JavaScript step, which is strictly worse than not signing at all. So this is
 * a single static shared key, and the trade is stated rather than hidden:
 *
 *   X-N8n-Key   names the caller, and is the whole of the authentication
 *
 * The consequences to be aware of, given that:
 *
 *   - A captured request is replayable for as long as the key lives. Replaying
 *     verify after the code is burned simply fails, and replaying resolve
 *     reveals nothing the original caller did not already have, so the exposure
 *     is bounded by what these two endpoints can do — which is why the intake
 *     endpoint, when it is built, wants a harder look at this than a copy of
 *     this middleware.
 *   - Anyone holding the key can send any body. There is nothing to stop that
 *     but keeping the key secret and rotating it, so N8N_API_KEY belongs in the
 *     secret store, never in a workflow export.
 *   - TLS is doing real work here: the key is in a header in plaintext.
 *
 * Rotation is a config change on both sides with a moment of overlap where
 * neither value works. If that becomes painful, accept a list here before
 * reaching for signing.
 */
class EnsureN8nRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) config('services.n8n.key');

        // Fail closed. An environment that has not been given a key must reject
        // every caller rather than accept every caller — the opposite mistake
        // publishes an open endpoint that hands out user identities. This
        // matters more here than under a signing scheme, because an empty
        // configured key would otherwise match an absent header exactly.
        if ($key === '') {
            return $this->refuse();
        }

        // hash_equals, not ===. The comparison is against a secret, and a
        // short-circuiting comparison leaks its length and prefix by timing.
        if (! hash_equals($key, (string) $request->header('X-N8n-Key', ''))) {
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
