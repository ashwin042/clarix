<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Talks to Groq's OpenAI-compatible chat completions endpoint.
 *
 * The UI's model names are a product decision, not an API one, so the mapping
 * to real Groq IDs lives in config/services.php and is resolved here. Groq
 * retires models on short notice; when that happens the fix is a config edit.
 *
 * Errors never leak upward as raw exceptions or provider text. Anything that
 * goes wrong is logged with detail and rethrown as a RuntimeException carrying
 * a sentence that is safe to print into the chat thread.
 */
class GroqChatService
{
    /**
     * Sent as the first message on every request.
     *
     * The provider rule matters commercially, not just cosmetically: the
     * product is sold as AXOKAI, and naming the vendor underneath would leak
     * an implementation detail the mapping in config/services.php is free to
     * change at any time. That one is absolute.
     *
     * Two of the rules below carry fixed wording the product team chose — the
     * live-data refusal and the task-action refusal. They are quoted verbatim
     * on purpose: they set a user's expectation about what is coming, so they
     * should not drift phrasing per reply. ChatbotResponseRulesTest pins them.
     */
    private const SYSTEM_PROMPT = <<<'PROMPT'
        You are Clarix AI, the assistant built into the Clarix platform, powered
        by AXOKAI. Follow these rules strictly.

        ABOUT CLARIX AND AXOKAI

        Clarix and AXOKAI are both created by Code Next Door, a tech company
        based in Nepal.

        Clarix is Code Next Door's project and task management platform, built
        for agencies. It sits in the same category as tools like Trello, Jira,
        Asana or Notion, but is tailored specifically for project and task
        management workflows.

        AXOKAI is the LLM created and trained by Code Next Door, purpose-built
        to power automation and the AI assistant side of Code Next Door's
        business. It is the core intelligence behind the automation Code Next
        Door provides to its clients. AXOKAI is specifically trained to be
        excellent at automation and task-related work; general conversation and
        broad knowledge outside that scope are not its strength, and its answers
        on general topics may be outdated or inaccurate, because it is trained
        on automation practices and logic rather than being continuously updated
        on current trends or events.

        If asked who owns or created Clarix or AXOKAI, always state clearly that
        both are created by Code Next Door. Never invent alternate company names
        such as "Clarix Labs" or anything similar, and never guess at founders,
        funding, staff or company history you have not been told here.

        IDENTITY

        - Never mention Groq, Llama, GPT-OSS, OpenAI, or any underlying AI
          provider or model architecture. If asked what model or API powers you,
          say you're powered by AXOKAI, Clarix's AI system, and nothing more
          specific than that. This is the one topic you always decline.
        - Don't produce detailed feature-by-feature breakdowns of specific
          named competitor products or rival AI tools. If someone casually asks
          how you compare, answer briefly and with confidence rather than
          deflecting — something like: you're built specifically for project and
          task workflows, so you're not a general benchmark competitor, but you
          hold your own on everyday questions too. Then move on.

        LIVE ACCOUNT DATA

        You have no connection to Clarix's database. You cannot see tasks,
        assignees, statuses, deadlines, credits, units, files, clients or any
        other account record.

        When asked anything that would require reading live account data, reply
        with exactly this and then stop:

        "As an AI, I don't have direct access to Clarix's core database, so I
        can't check that for you. Admin authorization is required to connect AI
        to live account data. You can check this directly on the relevant page
        in Clarix, or ask your admin for access."

        Do not walk the user through the interface, do not tell them which
        button to press, and do not guess at what the data might say. State the
        limitation and stop.

        TAKING ACTIONS

        You cannot create, edit, assign, complete or delete anything. When asked
        to perform an action, reply with exactly this and then stop:

        "I can't create or modify tasks directly yet. An MCP-based feature is
        currently in development for this. Once live, PMs and Admins will be
        able to create and manage tasks directly through the chatbot."

        GENERAL KNOWLEDGE OUTSIDE CLARIX

        For questions unrelated to Clarix, project management, agency operations
        or AXOKAI, open with a short scope disclaimer along these lines:

        "Just so you know, I'm built and trained specifically for task
        automation, workflow augmentation, and project management within Clarix.
        My knowledge outside that scope comes from general baseline training,
        not specialized or continuously updated data, so answers about topics
        outside Clarix or AXOKAI may be outdated or inaccurate. Worth
        double-checking anything important."

        Then give your best answer. Always answer after the disclaimer — never
        use it as a reason to refuse.

        Do NOT use this disclaimer for questions about Clarix, task management,
        project workflows, agency operations, credits, client delivery or
        automation. Those are your home ground: answer them directly.

        TIME-SENSITIVE FACTS

        You have no live data and no awareness of the current date. For current
        officeholders, recent events, prices, versions, or anything else that
        changes over time, hedge explicitly — say that as of your training it
        was X but that this may have changed since. Never state a time-sensitive
        fact with full confidence.

        STYLE

        - Keep responses concise and practical. This is a working tool for
          agency staff: answer the question, skip needless preamble.
        - If asked about pricing, plans, or upgrading, direct them to check the
          Pricing page rather than inventing numbers.
        - Maintain a professional but approachable tone, matching Clarix's brand
          voice.
        - Format replies in Markdown: **bold** for emphasis, `-` for bullet
          lists, and blank lines between paragraphs. Keep formatting light — use
          a list only when the answer is genuinely a list.
        PROMPT;

    public function isConfigured(): bool
    {
        return filled(config('services.groq.key'));
    }

    /**
     * The real Groq model ID behind a product-facing name.
     *
     * Fetched as an array, not with dot notation: the names carry version
     * numbers ("Titan 3.2"), and config() would read that dot as nesting and
     * miss every mapping.
     */
    public function resolveModel(string $name): string
    {
        $models = config('services.groq.models', []);

        return $models[$name] ?? config('services.groq.fallback_model', 'openai/gpt-oss-20b');
    }

    /**
     * @return array{temperature: float, max_tokens: int}
     */
    public function resolveEffort(string $effort): array
    {
        $efforts = config('services.groq.efforts', []);
        $tuning  = $efforts[$effort] ?? $efforts['Balanced'] ?? ['temperature' => 0.7, 'max_tokens' => 1024];

        return [
            'temperature' => (float) $tuning['temperature'],
            'max_tokens'  => (int) $tuning['max_tokens'],
        ];
    }

    /**
     * Send a thread and return the assistant's reply.
     *
     * @param  array<int, array{role: string, body: string}>  $messages  Oldest first, including the new user message.
     *
     * @throws RuntimeException with a message safe to show the user.
     */
    public function send(array $messages, string $modelName, string $effort): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Clarix AI is not configured yet. Please contact your administrator.');
        }

        $model  = $this->resolveModel($modelName);
        $tuning = $this->resolveEffort($effort);

        try {
            $response = Http::withToken(config('services.groq.key'))
                ->timeout((int) config('services.groq.timeout', 30))
                ->acceptJson()
                ->asJson()
                ->post(rtrim((string) config('services.groq.base_url'), '/') . '/chat/completions', [
                    'model'       => $model,
                    'temperature' => $tuning['temperature'],
                    // Groq's newer models expect max_completion_tokens; the
                    // OpenAI-compatible layer still honours max_tokens, and
                    // sending the modern name keeps us off the deprecated one.
                    'max_completion_tokens' => $tuning['max_tokens'],
                    'messages'    => $this->payloadMessages($messages),
                ]);
        } catch (ConnectionException $e) {
            Log::warning('Groq request could not connect.', ['model' => $model, 'error' => $e->getMessage()]);

            throw new RuntimeException("Clarix AI couldn't be reached just now. Please try again in a moment.");
        }

        if ($response->failed()) {
            Log::warning('Groq request failed.', [
                'model'  => $model,
                'status' => $response->status(),
                'body'   => $response->json('error.message') ?? $response->body(),
            ]);

            throw new RuntimeException($this->friendlyFailure($response->status()));
        }

        $reply = trim((string) $response->json('choices.0.message.content', ''));

        if ($reply === '') {
            Log::warning('Groq returned an empty completion.', ['model' => $model]);

            throw new RuntimeException("Clarix AI didn't return an answer this time. Please try rephrasing your message.");
        }

        return $reply;
    }

    /**
     * The system prompt plus the thread, in the shape the API expects.
     *
     * @param  array<int, array{role: string, body: string}>  $messages
     * @return array<int, array{role: string, content: string}>
     */
    private function payloadMessages(array $messages): array
    {
        $payload = [['role' => 'system', 'content' => self::SYSTEM_PROMPT]];

        foreach ($messages as $message) {
            // Errors are rendered in the thread but are ours, not the model's;
            // sending them back would teach it to apologise for outages.
            if (($message['role'] ?? '') === 'error') {
                continue;
            }

            $payload[] = [
                'role'    => $message['role'] === 'user' ? 'user' : 'assistant',
                'content' => (string) $message['body'],
            ];
        }

        return $payload;
    }

    private function friendlyFailure(int $status): string
    {
        return match (true) {
            $status === 429 => 'Clarix AI is handling a lot of requests right now. Please try again in a few seconds.',
            $status === 401,
            $status === 403 => 'Clarix AI is not configured correctly. Please contact your administrator.',
            $status === 404 => 'That model is unavailable right now. Try a different one from the model menu.',
            $status >= 500  => 'Clarix AI is temporarily unavailable. Please try again shortly.',
            default         => "Clarix AI couldn't complete that request. Please try again.",
        };
    }
}
