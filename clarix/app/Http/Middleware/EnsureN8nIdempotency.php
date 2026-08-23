<?php

namespace App\Http\Middleware;

use App\Services\N8nIdempotencyStore;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Makes a task bot write safe to retry, using a key the caller supplies.
 *
 * The gap this closes is narrow and real. A replayed *create* is answered by
 * the schema — task_code is unique per unit, so the second attempt is a 422
 * rather than a second task. Attaching a file has no such natural key: the same
 * bytes posted twice are two perfectly valid attachments, and a static shared
 * key leaves a captured request replayable indefinitely (see EnsureN8nRequest
 * for why that trade was taken deliberately).
 *
 * Signing would close it differently and was rejected on purpose: an HMAC over
 * the raw body is a function call an n8n workflow cannot make without a code
 * node, and the practical result of demanding one is a signing secret pasted
 * into a JavaScript step. A key the workflow generates per submission asks for
 * nothing the visual editor cannot already do.
 *
 * Four outcomes, and the second is the one that matters most:
 *
 *   no holder            the claim is taken, the work runs, the answer is kept
 *   holder, completed    the original response is returned verbatim, 200s and
 *                        all, because a retry usually means the first call
 *                        timed out rather than failed — the work may well have
 *                        happened, and the workflow needs the same answer to
 *                        carry on with
 *   holder, in flight    409, and the caller should retry shortly
 *   holder, different    422. A workflow reusing one key across two different
 *   request              submissions would otherwise be handed the first one's
 *                        response for the second file, silently, and the second
 *                        file would never be stored — a lost attachment that
 *                        looks like a success in every log
 *
 * Only successful responses are remembered. A refusal has left nothing behind
 * to duplicate, so the key goes back and the caller can correct the request and
 * try again — which, for a workflow re-fetching a file from Telegram, is the
 * ordinary path rather than an edge case.
 *
 * Runs after ResolveN8nActor, and depends on it: keys are scoped per user, and
 * the user is what that middleware establishes. Registered as 'n8n.idempotent'
 * and takes the scope as a parameter, so the same mechanism can cover a second
 * operation later without one operation's key satisfying another's.
 */
class EnsureN8nIdempotency
{
    /**
     * Kept short deliberately. A long minimum would look like a check on
     * entropy while measuring nothing — a constant 40-character string is a
     * worse key than a 9-character counter. The fingerprint comparison is what
     * actually catches a badly built workflow; this only rejects the values
     * that are obviously not identifiers at all.
     */
    protected const MIN_LENGTH = 8;

    protected const MAX_LENGTH = 128;

    public function __construct(protected N8nIdempotencyStore $store)
    {
    }

    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $user = $request->user();

        // ResolveN8nActor runs first and refuses anything it cannot resolve, so
        // reaching here without an actor means the middleware were ordered
        // wrongly. Refusing is better than quietly skipping the protection.
        if ($user === null) {
            return $this->error(
                'The request could not be attributed to a user.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        $key = trim((string) $request->header('Idempotency-Key', ''));

        if (! $this->looksLikeAKey($key)) {
            return $this->error(
                'An Idempotency-Key header is required, unique per submission, '
                .self::MIN_LENGTH.'-'.self::MAX_LENGTH.' characters of letters, digits, dot, colon, underscore or hyphen.',
                Response::HTTP_BAD_REQUEST
            );
        }

        // input(), not except() or all(): those two fold the uploaded files
        // into the array, and an UploadedFile is neither stable between
        // requests nor safely encodable. The files are covered separately, by
        // name and size.
        $fingerprint = N8nIdempotencyStore::fingerprint(
            $request->getMethod(),
            $request->path(),
            Arr::except($request->input(), ['_token']),
            array_values(Arr::wrap($request->allFiles()['files'] ?? []))
        );

        $holder = $this->store->claim($user, $scope, $key, $fingerprint);

        if ($holder !== null) {
            return $this->answerFor($holder, $fingerprint);
        }

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            // The work blew up, so nothing was completed and the key must not
            // be left holding a claim that will never be answered — the caller
            // would get 409 for the next day rather than a retry.
            $this->store->release($user, $scope, $key);

            throw $e;
        }

        $this->remember($request, $scope, $key, $response);

        return $response;
    }

    /**
     * What to say to a request whose key is already held.
     */
    protected function answerFor(\App\Models\N8nIdempotencyKey $holder, string $fingerprint): Response
    {
        if (! hash_equals($holder->request_fingerprint, $fingerprint)) {
            return $this->error(
                'That Idempotency-Key was already used for a different request. '
                .'Generate a new key for each submission.',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if (! $holder->isComplete()) {
            return $this->error(
                'A request with that Idempotency-Key is still being processed. Retry shortly.',
                Response::HTTP_CONFLICT
            );
        }

        return response()
            ->json($holder->response_body, $holder->response_status)
            ->header('Idempotent-Replay', 'true');
    }

    /**
     * Keep a successful answer; give the key back for anything else.
     */
    protected function remember(Request $request, string $scope, string $key, Response $response): void
    {
        $user = $request->user();

        $body = json_decode((string) $response->getContent(), true);

        // A non-2xx has left nothing to duplicate. So has a response this
        // cannot re-emit faithfully — replaying a body it failed to parse would
        // be worse than making the caller retry.
        if (! $response->isSuccessful() || json_last_error() !== JSON_ERROR_NONE) {
            $this->store->release($user, $scope, $key);

            return;
        }

        $this->store->complete($user, $scope, $key, $response->getStatusCode(), $body);
    }

    protected function looksLikeAKey(string $key): bool
    {
        return preg_match(
            '/^[A-Za-z0-9_.:-]{'.self::MIN_LENGTH.','.self::MAX_LENGTH.'}$/',
            $key
        ) === 1;
    }

    protected function error(string $message, int $status): Response
    {
        return response()->json(['message' => $message], $status);
    }
}
