<?php

namespace App\Livewire\Profile;

use App\Http\Middleware\EnsurePlanIncludes;
use App\Services\N8nTelegramLinkService;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * "Connect Task Bot", in the Task Bot card on MCP & Plugins.
 *
 * A near-twin of ConnectTelegram, and separate from it for the reason the whole
 * integration is separate: it binds a different bot through a different table,
 * and a shared component would mean one card's state and one card's rate limit
 * standing in for two independent links. Somebody connected to AXOKAI is not
 * thereby connected to the task bot, and the two cards have to be able to
 * disagree.
 *
 * Lives in the Livewire\Profile namespace because what it edits is one person's
 * account, not the agency's.
 *
 * It does not abort in mount(), unlike most plan-gated components. The page it
 * sits on aborts for it: /ai/mcp carries plan:automation on the route and
 * McpPlugins repeats the check in render(), so a viewer without the feature
 * never reaches this component at all. The plan check below is therefore the
 * belt to those braces rather than the only lock — it is what stops a crafted
 * POST to /livewire/update mounting the component directly and minting a code
 * without ever loading the page. That is also why it sets $refusal instead of
 * aborting: an action refusing needs to say so inside the card.
 *
 * The plaintext code lives in a public property for as long as it is on screen,
 * so it travels in Livewire's component payload. That is the same trust
 * boundary as rendering it into the HTML — it reaches the browser either way —
 * but it is worth knowing the code exists somewhere other than the user's eyes
 * for those fifteen minutes.
 */
class ConnectTaskBot extends Component
{
    /** The plaintext code, readable only until the page is left. */
    public ?string $code = null;

    /** When the shown code lapses, for the line under it. */
    public ?string $expiresAt = null;

    /** Set when an action refuses, so the card can say why. */
    public ?string $refusal = null;

    /** Codes one user may mint per quarter hour. */
    protected const CODES_PER_WINDOW = 5;

    public function generate(N8nTelegramLinkService $links): void
    {
        if (! $this->planAllows()) {
            return;
        }

        // Its own limiter key, not shared with the AXOKAI card's. Spending one
        // budget on both would mean connecting one bot leaves you unable to
        // connect the other for a quarter of an hour, for no reason a user
        // could possibly infer.
        $key = 'task-bot-code|'.auth()->id();

        // Minting is cheap but not free: each code invalidates the last, so an
        // unbounded loop is a way to keep somebody permanently unable to finish
        // linking, and it writes to the table every time.
        if (RateLimiter::tooManyAttempts($key, self::CODES_PER_WINDOW)) {
            $this->refusal = 'Too many codes requested. Try again in '
                .ceil(RateLimiter::availableIn($key) / 60).' minute(s).';

            return;
        }

        RateLimiter::hit($key, N8nTelegramLinkService::TTL_MINUTES * 60);

        $this->refusal   = null;
        $this->code      = $links->issueCode(auth()->user());
        $this->expiresAt = now()->addMinutes(N8nTelegramLinkService::TTL_MINUTES)->toIso8601String();
    }

    public function disconnect(N8nTelegramLinkService $links): void
    {
        if (! $this->planAllows()) {
            return;
        }

        $links->unlink(auth()->user());

        $this->code      = null;
        $this->expiresAt = null;
        $this->refusal   = null;

        $this->dispatch('notify', message: 'Task Bot disconnected.');
    }

    /**
     * The plan check, in the form the actions need it.
     *
     * Sets the refusal rather than aborting, for the reason in the class
     * docblock — but it is the same sentence the middleware and every other
     * component guard use, so somebody refused here and somebody refused at a
     * gated route read the same words.
     */
    protected function planAllows(): bool
    {
        $user = auth()->user();

        if ($user?->planAllows('automation')) {
            return true;
        }

        $this->refusal = EnsurePlanIncludes::refusalFor('automation', $user?->organization_id);

        return false;
    }

    public function render()
    {
        $user = auth()->user();
        $link = app(N8nTelegramLinkService::class)->linkFor($user);

        return view('livewire.profile.connect-task-bot', [
            // isLive(), not is_active: the row exists from the moment a code is
            // minted and defaults to active, so is_active alone would show
            // "Connected" to somebody who has only ever been shown a code.
            'linked'      => $link?->isLive() ?? false,
            'linkedAt'    => $link?->linked_at,
            'planAllows'  => (bool) $user->planAllows('automation'),
            'botUsername' => (string) config('services.n8n.bot_username'),
            'ttlMinutes'  => N8nTelegramLinkService::TTL_MINUTES,
        ]);
    }
}
