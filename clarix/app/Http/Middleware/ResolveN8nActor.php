<?php

namespace App\Http\Middleware;

use App\Services\N8nPipelineAccess;
use App\Services\N8nTelegramLinkService;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns a chat_id in the body into an acting person, for the intake endpoints.
 *
 * EnsureN8nRequest proves *that the pipeline is calling*. This proves *who it
 * is calling for*, which is a different question and the one that matters the
 * moment an endpoint writes. Two middleware rather than one, because the link
 * endpoints need the first without the second: verify() is how a chat becomes
 * known, so it cannot require the chat to already be known.
 *
 * Three things happen here, and each is load-bearing:
 *
 *  1. The chat is resolved to a user, live, through the link service. An
 *     unlinked chat is answered exactly as /resolve answers it, so a workflow
 *     handles one shape of "not linked" rather than two.
 *
 *  2. The commercial gates are asked of that person's agency. Intake is a write
 *     — a suspended agency filing work would be the integration paying for
 *     itself — so the same refusal the link endpoints give applies here.
 *
 *  3. The rest of the request runs inside actingAsOrganization(). This is the
 *     part with no equivalent anywhere else in the codebase, and without it the
 *     endpoint is silently broken rather than loudly:
 *
 *       - Task::create() stamps organization_id from TenantContext. With no
 *         authenticated user that is null, and null is not "the actor's
 *         agency", it is a row belonging to nobody that OrganizationScope will
 *         never filter and every agency's task list may show.
 *       - notifyAdmins() queries the tenant-scoped User model. Unscoped it
 *         would notify every admin on the platform about one agency's task.
 *       - The task lookup behind the attach endpoint is scoped by it, which is
 *         what makes another agency's task a 404 rather than a target.
 *
 *     Wrapping $next() rather than setting and restoring by hand means the
 *     context covers form-request validation, the policy check and the write,
 *     and is unwound even if any of them throws.
 *
 * setUserResolver, deliberately, rather than Auth::setUser. It gives
 * $request->user() to the form requests, the policy and the creation service —
 * everything that expects an actor — without logging anyone in. Authenticating
 * the guard would make TenantContext report the user's own organization
 * ambiently, which sounds equivalent and is not: it would apply to the link
 * lookups too, and those must stay unscoped to reach across agencies.
 */
class ResolveN8nActor
{
    public function __construct(
        protected N8nTelegramLinkService $links,
        protected N8nPipelineAccess $access,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $chatId = $request->input('chat_id');

        // Shape is checked here rather than left to the form request, because
        // the form request cannot run until it knows who is acting. Kept in the
        // validation-error shape so a workflow parses one error format.
        if (! is_scalar($chatId) || preg_match('/^-?\d{1,19}$/', trim((string) $chatId)) !== 1) {
            return response()->json([
                'message' => 'The chat id field is required and must be a Telegram chat id.',
                'errors'  => ['chat_id' => ['The chat id field is required and must be a Telegram chat id.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $this->links->resolve((string) $chatId);

        if ($user === null) {
            return response()->json([
                'message' => 'No Clarix user is linked to that chat.',
                'linked'  => false,
            ], Response::HTTP_NOT_FOUND);
        }

        if (($refusal = $this->access->refusalFor($user)) !== null) {
            return response()->json(['message' => $refusal['message']], $refusal['status']);
        }

        $request->setUserResolver(fn () => $user);

        $organizationId = $user->organization_id === null ? null : (int) $user->organization_id;

        return TenantContext::actingAsOrganization($organizationId, fn () => $next($request));
    }
}
