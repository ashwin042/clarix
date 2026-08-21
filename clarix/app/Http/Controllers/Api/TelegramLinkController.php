<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\TelegramLinkException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ResolveTelegramChatRequest;
use App\Http\Requests\Api\VerifyTelegramLinkRequest;
use App\Http\Resources\TelegramIdentityResource;
use App\Models\OrganizationSubscription;
use App\Models\User;
use App\Services\TelegramLinkService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;

/**
 * What Hermes calls: "whose code is this" and "whose chat is this".
 *
 * The commercial checks live here rather than in middleware, and that is forced
 * by the route's own design. EnsureSubscriptionActive and EnsurePlanIncludes
 * both read $request->user(), which is null on a bot-authenticated route —
 * attached to this group they would wave every request through while appearing
 * to guard it. So both questions are asked once the code has resolved to a
 * person, against *that* person's agency.
 */
class TelegramLinkController extends Controller
{
    public function __construct(protected TelegramLinkService $links)
    {
    }

    /**
     * Bind a chat id to whoever holds the code, and burn the code.
     */
    public function verify(VerifyTelegramLinkRequest $request): JsonResponse
    {
        try {
            $user = $this->links->verify($request->code(), $request->chatId());
        } catch (TelegramLinkException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status());
        }

        if (($refusal = $this->commercialRefusalFor($user)) !== null) {
            return $refusal;
        }

        return $this->identity($user);
    }

    /**
     * Who owns a chat id. What the bot asks on every later message.
     */
    public function resolve(ResolveTelegramChatRequest $request): JsonResponse
    {
        $user = $this->links->resolve($request->chatId());

        if ($user === null) {
            return response()->json(
                ['message' => 'No Clarix user is linked to that chat.'],
                JsonResponse::HTTP_NOT_FOUND
            );
        }

        if (($refusal = $this->commercialRefusalFor($user)) !== null) {
            return $refusal;
        }

        return $this->identity($user);
    }

    /**
     * The relations are loaded unscoped for the same reason the lookup is: no
     * user is authenticated, so a scoped read would return nothing and the
     * envelope would name a null agency.
     */
    protected function identity(User $user): JsonResponse
    {
        TenantContext::runWithoutScope(fn () => $user->loadMissing(['organization', 'unit']));

        return (new TelegramIdentityResource($user))->response();
    }

    /**
     * Both commercial gates, asked of the resolved person's agency.
     *
     * A suspended agency's integrations stop, exactly as the task API's do; and
     * linking is part of what 'automation' buys, so an agency below Pro is
     * refused here as well as in the card. Refusing in only one of the two
     * places would make the bot a way around the other.
     */
    protected function commercialRefusalFor(User $user): ?JsonResponse
    {
        $organizationId = $user->organization_id === null ? null : (int) $user->organization_id;

        $subscription = TenantContext::actingAsOrganization(
            $organizationId,
            fn () => OrganizationSubscription::query()->latest('started_at')->first()
        );

        if ($subscription !== null && $subscription->isSuspended()) {
            return response()->json([
                'message' => 'This organization\'s subscription is suspended.',
            ], JsonResponse::HTTP_PAYMENT_REQUIRED);
        }

        if (! $user->planAllows('automation')) {
            return response()->json([
                'message' => 'Telegram linking is not included in this organization\'s plan.',
            ], JsonResponse::HTTP_PAYMENT_REQUIRED);
        }

        return null;
    }
}
