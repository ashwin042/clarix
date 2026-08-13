<?php

namespace Tests\Feature\AI;

use App\Services\ChatMarkdown;
use Tests\TestCase;

class ChatMarkdownTest extends TestCase
{
    private ChatMarkdown $md;

    protected function setUp(): void
    {
        parent::setUp();
        $this->md = app(ChatMarkdown::class);
    }

    private function render(string $source): string
    {
        return $this->md->render($source)->toHtml();
    }

    public function test_bold_renders_as_strong_not_asterisks(): void
    {
        $html = $this->render('Use **bold** here.');

        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringNotContainsString('**', $html);
    }

    public function test_bullet_lists_render_as_list_items(): void
    {
        $html = $this->render("Steps:\n\n- First\n- Second\n- Third");

        $this->assertStringContainsString('<ul>', $html);
        $this->assertSame(3, substr_count($html, '<li>'));
        $this->assertStringContainsString('First', $html);
    }

    public function test_numbered_lists_render_as_ordered_lists(): void
    {
        $html = $this->render("1. One\n2. Two");

        $this->assertStringContainsString('<ol>', $html);
        $this->assertSame(2, substr_count($html, '<li>'));
    }

    public function test_paragraphs_and_line_breaks_are_preserved(): void
    {
        $html = $this->render("First paragraph.\n\nSecond paragraph.");

        $this->assertSame(2, substr_count($html, '<p>'));
    }

    public function test_inline_code_and_fenced_blocks_render(): void
    {
        $this->assertStringContainsString('<code>', $this->render('Run `php artisan migrate` now.'));
        $this->assertStringContainsString('<pre>', $this->render("```\nsome code\n```"));
    }

    /**
     * This is the one place model output is printed unescaped, so a reply
     * containing a script tag must not survive into the page.
     */
    public function test_raw_html_in_a_reply_is_stripped(): void
    {
        $html = $this->render('Hello <script>alert("xss")</script> world');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('alert("xss")', $html);
    }

    public function test_html_event_attributes_are_stripped(): void
    {
        $html = $this->render('<img src=x onerror="alert(1)">');

        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_javascript_links_are_not_rendered_as_hrefs(): void
    {
        $html = $this->render('[click me](javascript:alert(1))');

        $this->assertStringNotContainsString('href="javascript:', $html);
    }

    public function test_ordinary_links_still_work(): void
    {
        $html = $this->render('[Clarix](https://example.com)');

        $this->assertStringContainsString('href="https://example.com"', $html);
    }

    public function test_plain_text_passes_through_unharmed(): void
    {
        $this->assertStringContainsString(
            'Just a normal sentence.',
            $this->render('Just a normal sentence.')
        );
    }
}
