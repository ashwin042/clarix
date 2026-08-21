<?php

namespace App\Livewire\Profile;

use App\Http\Middleware\EnsurePlanIncludes;
use App\Services\TelegramLinkService;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * "Connect Telegram", in the Settings page.
 *
 * Unlike every other plan-gated component in the app, this one does not abort
 * in mount(). Those each own a whole gated route; this one is embedded in
 * /settings, which is where a user changes their password and closes their
 * account. Aborting here would 402 the entire page for every agency below Pro
 * and lock them out of their own account controls over a feature they never
 * asked for. So the plan decides what the card *renders*, and the actions
 * refuse on their own behalf — which is also what keeps a crafted POST to
 * /livewire/update from being a way round the lock.
 *
 * ProfileOverview settled this shape already: the page is never refused, and a
 * section the viewer may not use is replaced with a note rather than an error.
 *
 * The plaintext code lives in a public property for as long as it is on screen,
 * so it travels in Livewire's component payload. That is the same trust
 * boundary as rendering it into the HTML — it reaches the browser either way —
 * but it is worth knowing the code exists somewhere other than the user's eyes
 * for those fifteen minutes.
 */
class ConnectTelegram extends Component
{
    /** The plaintext code, readable only until the page is left. */
    public ?string $code = null;

    /** When the shown code lapses, for the line under it. */
    public ?string $expiresAt = null;

    /** Set when an action refuses, so the card can say why. */
    public ?string $refusal = null;

    /** Codes one user may mint per quarter hour. */
    protected const CODES_PER_WINDOW = 5;

    public function generate(TelegramLinkService $links): void
    {
        if (! $this->planAllows()) {
            return;
        }

        $key = 'telegram-code|'.auth()->id();

        // Minting is cheap but not free: each code invalidates the last, so an
        // unbounded loop is a way to keep somebody permanently unable to finish
        // linking, and it writes to the users table every time.
        if (RateLimiter::tooManyAttempts($key, self::CODES_PER_WINDOW)) {
            $this->refusal = 'Too many codes requested. Try again in '
                .ceil(RateLimiter::availableIn($key) / 60).' minute(s).';

            return;
        }

        RateLimiter::hit($key, TelegramLinkService::TTL_MINUTES * 60);

        $this->refusal   = null;
        $this->code      = $links->issueFor(auth()->user());
        $this->expiresAt = now()->addMinutes(TelegramLinkService::TTL_MINUTES)->toIso8601String();
    }

    public function disconnect(TelegramLinkService $links): void
    {
        if (! $this->planAllows()) {
            return;
        }

        $links->unlink(auth()->user());

        $this->code      = null;
        $this->expiresAt = null;
        $this->refusal   = null;

        $this->dispatch('notify', message: 'Telegram disconnected.');
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

        return view('livewire.profile.connect-telegram', [
            'linked'      => $user->hasLinkedTelegram(),
            'linkedAt'    => $user->telegram_linked_at,
            'planAllows'  => (bool) $user->planAllows('automation'),
            'botUsername' => (string) config('services.hermes.bot_username'),
            'ttlMinutes'  => TelegramLinkService::TTL_MINUTES,
        ]);
    }
}
