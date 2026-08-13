<?php

namespace Tests\Feature\AI;

use App\Services\GroqChatService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The behaviour rules in the system prompt.
 *
 * Two of them carry wording the product team fixed — the live-data refusal and
 * the task-action refusal. Both set an expectation with the user about what is
 * coming next, so they are asserted character-for-character rather than by
 * keyword: a reworded promise is a different promise.
 */
class ChatbotResponseRulesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.groq.key', 'test-key');
    }

    /**
     * The prompt with runs of whitespace collapsed to single spaces.
     *
     * The heredoc wraps its lines for readability, so a quoted sentence is
     * split across newlines and indented in the source. That wrapping is a
     * formatting artifact, not part of the message the model reads as
     * meaningful, so wording is asserted against the flattened text.
     */
    private function flatPrompt(): string
    {
        return trim(preg_replace('/\s+/', ' ', $this->prompt()));
    }

    /** The system prompt as it is actually sent to the API. */
    private function prompt(): string
    {
        Http::fake(['api.groq.com/*' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'ok']]],
        ])]);

        app(GroqChatService::class)->send([['role' => 'user', 'body' => 'Hi']], 'Titan 3.2', 'Fast');

        $prompt = null;

        Http::assertSent(function (Request $request) use (&$prompt) {
            $prompt = $request->data()['messages'][0]['content'];

            return true;
        });

        return $prompt;
    }

    public function test_the_live_data_refusal_is_quoted_verbatim(): void
    {
        $expected = "As an AI, I don't have direct access to Clarix's core database, so I "
            . "can't check that for you. Admin authorization is required to connect AI to "
            . 'live account data. You can check this directly on the relevant page in '
            . 'Clarix, or ask your admin for access.';

        $this->assertStringContainsString($expected, $this->flatPrompt());
    }

    public function test_the_task_action_refusal_is_quoted_verbatim(): void
    {
        $expected = "I can't create or modify tasks directly yet. An MCP-based feature is "
            . 'currently in development for this. Once live, PMs and Admins will be able '
            . 'to create and manage tasks directly through the chatbot.';

        $this->assertStringContainsString($expected, $this->flatPrompt());
    }

    /**
     * Ownership is a factual claim about a real company, and a model with no
     * grounding will happily invent "Clarix Labs" instead. Both products and
     * the maker's name are pinned.
     */
    public function test_the_prompt_attributes_both_products_to_code_next_door(): void
    {
        $prompt = $this->flatPrompt();

        $this->assertStringContainsString('Clarix and AXOKAI are both created by Code Next Door', $prompt);
        $this->assertStringContainsString('a tech company based in Nepal', $prompt);
        $this->assertStringContainsString('both are created by Code Next Door', $prompt);
        $this->assertStringContainsString('Never invent alternate company names', $prompt);
        $this->assertStringContainsString('Clarix Labs', $prompt);
    }

    public function test_the_prompt_places_clarix_among_its_peers(): void
    {
        $prompt = $this->flatPrompt();

        foreach (['Trello', 'Jira', 'Asana', 'Notion'] as $peer) {
            $this->assertStringContainsString($peer, $prompt, "The prompt should place Clarix alongside {$peer}.");
        }
    }

    /** What AXOKAI is, and the honest limits of what it is good at. */
    public function test_the_prompt_describes_axokai_and_its_scope(): void
    {
        $prompt = $this->flatPrompt();

        $this->assertStringContainsString('AXOKAI is the LLM created and trained by Code Next Door', $prompt);
        $this->assertStringContainsString('excellent at automation and task-related work', $prompt);
        $this->assertStringContainsString('not its strength', $prompt);
    }

    /** The refusal must not degrade into UI hand-holding. */
    public function test_the_prompt_forbids_walking_the_user_through_the_interface(): void
    {
        $prompt = $this->flatPrompt();

        $this->assertStringContainsString('Do not walk the user through the interface', $prompt);
        $this->assertStringContainsString('do not tell them which', $prompt);
    }

    public function test_the_general_knowledge_disclaimer_is_present_and_scoped(): void
    {
        $prompt = $this->flatPrompt();

        // the disclaimer itself
        $this->assertStringContainsString('built and trained specifically for task', $prompt);
        $this->assertStringContainsString('may be outdated or inaccurate', $prompt);
        $this->assertStringContainsString('double-checking anything important', $prompt);

        // it must still answer afterwards, not refuse
        $this->assertStringContainsString('never use it as a reason to refuse', strtolower($prompt));

        // and it must NOT fire on Clarix's own subject matter
        $this->assertStringContainsString('Do NOT use this disclaimer', $prompt);
    }

    public function test_time_sensitive_answers_must_be_hedged(): void
    {
        $prompt = $this->flatPrompt();

        $this->assertStringContainsString('TIME-SENSITIVE FACTS', $prompt);
        $this->assertStringContainsString('officeholders', $prompt);
        $this->assertStringContainsString('may have changed', $prompt);
        $this->assertStringContainsString('Never state a time-sensitive', $prompt);
    }

    /** Every capability the assistant must deny having. */
    public function test_the_prompt_denies_both_reading_and_writing(): void
    {
        $prompt = $this->flatPrompt();

        $this->assertStringContainsString('no connection to Clarix', $prompt);
        $this->assertStringContainsString('cannot create, edit, assign, complete or delete', $prompt);

        foreach (['tasks', 'assignees', 'statuses', 'deadlines', 'credits', 'units', 'files'] as $record) {
            $this->assertStringContainsString($record, $prompt, "The prompt should name {$record} as unreadable.");
        }
    }

    /** The rules added here must not have displaced the earlier ones. */
    public function test_the_identity_and_style_rules_survived(): void
    {
        $prompt = $this->flatPrompt();

        foreach (['Groq', 'Llama', 'GPT-OSS', 'OpenAI'] as $forbidden) {
            $this->assertStringContainsString($forbidden, $prompt, "The prompt must still name {$forbidden} as off-limits.");
        }

        $this->assertStringContainsString('powered by AXOKAI', $prompt);
        $this->assertStringContainsString('feature-by-feature', $prompt);
        $this->assertStringContainsString('Pricing page', $prompt);
        $this->assertStringContainsString('Markdown', $prompt);
    }
}
