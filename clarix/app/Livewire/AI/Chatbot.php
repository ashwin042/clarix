<?php

namespace App\Livewire\AI;

use App\Services\ChatMarkdown;
use App\Services\ChatQuota;
use App\Services\GroqChatService;
use Livewire\Component;
use RuntimeException;

/**
 * Clarix AI chatbot.
 *
 * Live against Groq. The product-facing model names are mapped to real Groq
 * IDs in config/services.php; the thinking effort maps to temperature and a
 * token ceiling. See App\Services\GroqChatService.
 *
 * Chat and forget — nothing is persisted. The thread lives in component state
 * for the life of the page and is gone on reload, which is why there is no
 * history rail and no conversation title. What IS persisted is the daily
 * message count, in daily_chat_requests, via App\Services\ChatQuota.
 *
 * send() runs in two passes so the UI can show the user's message and a typing
 * indicator before the API call blocks: the first pass appends the message and
 * dispatches a browser event, the second (triggered by wire:poll-free
 * `$wire.reply()`) does the network call. $pending is what tells the view a
 * reply is in flight.
 */
class Chatbot extends Component
{
    public const MODELS = ['Titan 3.2', 'Gaia 2.0', 'Kronos 1.5', 'Helios 4.0', 'Olympus Max'];

    /** Label => one-line description shown in the dropdown. */
    public const EFFORTS = [
        'Fast'     => 'Quick answers, less reasoning',
        'Balanced' => 'The default for most questions',
        'Deep'     => 'Slower, works through the detail',
    ];

    public string $model = 'Titan 3.2';

    public string $effort = 'Balanced';

    /** @var array<int, array{role: string, body: string}> role: user|assistant|error */
    public array $messages = [];

    /** True between the user's message landing and the reply arriving. */
    public bool $pending = false;

    public function send(string $body = ''): void
    {
        $body = trim($body);

        if ($body === '' || $this->pending) {
            return;
        }

        if ($this->limitReached()) {
            $this->messages[] = ['role' => 'error', 'body' => $this->limitMessage()];
            $this->dispatch('chat-updated');

            return;
        }

        $this->messages[] = ['role' => 'user', 'body' => $body];
        $this->pending    = true;

        $this->dispatch('chat-updated');
    }

    /**
     * Second pass: the actual call. Separated from send() so the thread paints
     * the user's message and the typing indicator first.
     */
    public function reply(ChatQuota $quota, GroqChatService $groq): void
    {
        if (! $this->pending) {
            return;
        }

        $user = auth()->user();

        // Claimed before the call, refunded below if the call never lands, so
        // a failed request costs the user nothing.
        if (! $quota->consume($user)) {
            $this->messages[] = ['role' => 'error', 'body' => $this->limitMessage()];
            $this->pending    = false;
            $this->dispatch('chat-updated');

            return;
        }

        try {
            $reply = $groq->send($this->messages, $this->model, $this->effort);

            $this->messages[] = ['role' => 'assistant', 'body' => $reply];
        } catch (RuntimeException $e) {
            $quota->refund($user);

            $this->messages[] = ['role' => 'error', 'body' => $e->getMessage()];
        } finally {
            $this->pending = false;
            $this->dispatch('chat-updated');
        }
    }

    public function setModel(string $model): void
    {
        if (in_array($model, self::MODELS, true)) {
            $this->model = $model;
        }
    }

    public function setEffort(string $effort): void
    {
        if (array_key_exists($effort, self::EFFORTS)) {
            $this->effort = $effort;
        }
    }

    public function clear(): void
    {
        $this->messages = [];
        $this->pending  = false;
        $this->dispatch('chat-updated');
    }

    private function limitReached(): bool
    {
        return app(ChatQuota::class)->hasReachedLimit(auth()->user());
    }

    private function limitMessage(): string
    {
        return "You've reached your daily limit of " . app(ChatQuota::class)->limit()
            . ' messages. This resets tomorrow.';
    }

    public function render()
    {
        $quota = app(ChatQuota::class);
        $user  = auth()->user();

        return view('livewire.ai.chatbot', [
            'models'    => self::MODELS,
            'efforts'   => self::EFFORTS,
            'remaining' => $quota->remaining($user),
            'limit'     => $quota->limit(),
            // Assistant replies are Markdown; the view renders them through
            // this rather than printing the raw source.
            'markdown'  => app(ChatMarkdown::class),
        ])->layout('layouts.app', ['pageTitle' => 'Chatbot']);
    }
}
