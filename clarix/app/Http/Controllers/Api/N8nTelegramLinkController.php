<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\N8nTelegramLinkException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ResolveN8nTelegramChatRequest;
use App\Http\Requests\Api\VerifyN8nTelegramLinkRequest;
use App\Http\Resources\N8nTelegramIdentityResource;
use App\Models\User;
use App\Services\N8nPipelineAccess;
use App\Services\N8nTelegramLinkService;
use Illuminate\Http\JsonResponse;

/**
 * What the task pipeline calls: "whose code is this" and "whose chat is this".
 *
 * The commercial checks live here rather than in middleware, and that is forced
 * by the route's own design. EnsureSubscriptionActive and EnsurePlanIncludes
 * both read $request->user(), which is null on a key-authenticated route —
 * attached to this group they would wave every request through while appearing
 * to guard it. So both questions are asked once the chat has resolved to a
 * person, against *that* person's agency.
 *
 * Asking them on resolve as well as on verify is deliberate. resolve runs on
 * every incoming message, so it is the gate that actually stops a suspended
 * agency's work reaching the pipeline; checking only at link time would let an
 * agency that lapsed six months ago keep filing tasks off a link it made while
 * it was paying.
 */
class N8nTelegramLinkController extends Controller
{
    public function __construct(
        protected N8nTelegramLinkService $links,
        protected N8nPipelineAccess $access,
    ) {
    }

    /**
     * Bind a chat id to whoever holds the code, and burn the code.
     */
    public function verify(VerifyN8nTelegramLinkRequest $request): JsonResponse
    {
        try {
            $user = $this->links->verify($request->code(), $request->chatId());
        } catch (N8nTelegramLinkException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status());
        }

        if (($refusal = $this->commercialRefusalFor($user)) !== null) {
            return $refusal;
        }

        return $this->identity($user);
    }

    /**
     * Who owns a chat id. What the pipeline asks on every incoming message.
     */
    public function resolve(ResolveN8nTelegramChatRequest $request): JsonResponse
    {
        $user = $this->links->resolve($request->chatId());

        if ($user === null) {
            return response()->json([
                'message' => 'No Clarix user is linked to that chat.',
                'linked'  => false,
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        if (($refusal = $this->commercialRefusalFor($user)) !== null) {
            return $refusal;
        }

        return $this->identity($user);
    }

    /**
     * The identity envelope, built from the user and nothing cached.
     */
    protected function identity(User $user): JsonResponse
    {
        return (new N8nTelegramIdentityResource($user))->response();
    }

    /**
     * Both commercial gates, asked of the resolved person's agency.
     *
     * A suspended agency's integrations stop, exactly as the task API's do; and
     * bot linking is part of what 'automation' buys, so an agency below Pro is
     * refused here as well as in the card. Refusing in only one of the two
     * places would make the bot a way around the other.
     *
     * The rules themselves live in N8nPipelineAccess, shared with the intake
     * endpoints — the two halves of one integration must not be able to
     * disagree about what a suspended agency may do.
     */
    protected function commercialRefusalFor(User $user): ?JsonResponse
    {
        $refusal = $this->access->refusalFor($user);

        return $refusal === null
            ? null
            : response()->json(['message' => $refusal['message']], $refusal['status']);
    }
}
