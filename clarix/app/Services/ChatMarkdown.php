<?php

namespace App\Services;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Renders assistant replies from Markdown to HTML for the chat thread.
 *
 * This is the one place in the app where model output is printed unescaped, so
 * it is deliberately narrow and hardened:
 *
 *   html_input: strip        raw HTML in the reply is removed, not passed
 *                            through — a model that emits <script> or an
 *                            onerror attribute gets it stripped, not run.
 *   allow_unsafe_links       off, so javascript: and data: hrefs are dropped.
 *
 * Only assistant messages go through here. User messages and our own error
 * bubbles stay plain escaped text: echoing a user's own input back as HTML
 * would hand them a self-XSS, and the error strings are ours already.
 */
class ChatMarkdown
{
    public function render(string $markdown): HtmlString
    {
        return new HtmlString(Str::markdown($markdown, [
            'html_input'         => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level'  => 20,
        ]));
    }
}
