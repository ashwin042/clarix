<?php

namespace Tests\Feature\Marketing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The four Resources pages.
 *
 * These are the pages with the least product to point at, which is exactly
 * why they are the easiest to fill with content that does not exist. What is
 * asserted here is mostly about honesty: no link that goes nowhere, and no
 * invented customer presented as a real one.
 */
class MarketingResourcesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string}> */
    public static function resources(): array
    {
        return [
            'blog'      => ['marketing.blog'],
            'docs'      => ['marketing.docs'],
            'help'      => ['marketing.help'],
        ];
    }

    /**
     * The page's own body, between the shared header and the shared footer.
     * The footer keeps a handful of deliberate '#' entries — Changelog,
     * Careers, Status and the social icons — and those are not these pages'
     * to answer for.
     */
    private function body(string $route): string
    {
        $html = $this->get(route($route))->assertOk()->getContent();

        $start = strpos($html, '</header>');
        $end = strpos($html, 'site-footer', $start);

        $this->assertNotFalse($start, 'no header to slice from');
        $this->assertNotFalse($end, 'no footer to slice to');

        return substr($html, $start, $end - $start);
    }

    /**
     * The whole point of building these pages was to kill the dead links in
     * the nav. A Resources page that ships its own '#' has just moved the
     * problem one level down.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('resources')]
    public function test_the_page_body_has_no_dead_links(string $route): void
    {
        $this->assertStringNotContainsString(
            'href="#"',
            $this->body($route),
            "{$route} ships a link that goes nowhere"
        );
    }

    /**
     * The accordion moved out of home.blade.php and into the layout when the
     * FAQ became a shared component. If it had stayed page-local, the Help
     * Center's panels would render but never open.
     */
    public function test_the_accordion_styles_ship_with_the_shared_layout(): void
    {
        $html = $this->get(route('marketing.help'))->getContent();

        $this->assertStringContainsString('.acc-panel {', $html, 'the accordion has no styles off the homepage');
        $this->assertStringContainsString('.acc-panel--open', $html);
        $this->assertStringContainsString('.acc-chev--open', $html);
    }

    /**
     * One panel open on arrival, from the server rather than from Alpine, so
     * the page does not pop as it hydrates — and only one, so the FAQ reads
     * as an index rather than a wall of prose.
     */
    public function test_the_faq_ships_exactly_one_open_panel(): void
    {
        $html = $this->get(route('marketing.help'))->getContent();

        $this->assertSame(
            1,
            substr_count($html, 'acc-panel acc-panel--open'),
            'the Help Center should arrive with exactly one answer showing'
        );

        // Every group renders, and the panel ids stay unique across them.
        preg_match_all('#id="(help-[a-z]+-panel-\d+)"#', $html, $ids);
        $this->assertNotEmpty($ids[1]);
        $this->assertSame($ids[1], array_unique($ids[1]), 'two FAQ panels share an id');
    }

    /**
     * The docs sidebar is an index that navigates, not a decorative list.
     * Every anchor in it has to land on a section of the page.
     */
    public function test_every_docs_sidebar_anchor_lands_on_a_section(): void
    {
        $html = $this->get(route('marketing.docs'))->getContent();

        preg_match_all('#href="\#([a-z-]+)"#', $html, $anchors);

        $this->assertNotEmpty($anchors[1], 'the docs index has no sidebar anchors');

        foreach (array_unique($anchors[1]) as $anchor) {
            $this->assertStringContainsString(
                'id="' . $anchor . '"',
                $html,
                "the docs sidebar points at #{$anchor}, which is not on the page"
            );
        }
    }

    /**
     * The blog carries other people's journalism, and never implies otherwise.
     *
     * The wording of this section has been rewritten once already, for tone.
     * What is asserted here is therefore the substance rather than the
     * sentences: that the page says the articles are curated from other
     * publishers, that each is credited to whoever wrote it, and that a card
     * opens at the source. A future rewrite is free to move the words around;
     * it is not free to drop the disclosure.
     */
    public function test_the_blog_says_the_articles_are_not_ours(): void
    {
        $html = $this->get(route('marketing.blog'))->assertOk()->getContent();

        foreach ([
            'curated' => 'the feed is not presented as original writing',
            'publishers worldwide' => 'the articles are not said to come from elsewhere',
            'credited to the publication that wrote it' => 'authorship is not attributed away from Clarix',
            'opens at the original source' => 'the reader is not told where a card goes',
        ] as $needle => $why) {
            $this->assertStringContainsStringIgnoringCase($needle, $html, $why);
        }

        // The credit line at the foot of the feed only renders when there are
        // articles to credit, so it is asserted in TechNewsFeedTest, where a
        // feed is faked. Here the page is in its no-key fallback state — and
        // the disclosure above still has to be on it.
    }

    /**
     * The feed is down in the test environment by default — no key is
     * configured — so the page has to be a page anyway.
     */
    public function test_the_blog_falls_back_when_the_feed_is_empty(): void
    {
        $this->get(route('marketing.blog'))
            ->assertOk()
            ->assertSee("We couldn't load the news right now.", false)
            ->assertSee('Feed unavailable', false);
    }

    /**
     * The docs DOM, with libxml's HTML5 grumbling silenced.
     */
    private function docsDom(): \DOMXPath
    {
        $html = $this->get(route('marketing.docs'))->assertOk()->getContent();

        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML($html);
        libxml_clear_errors();

        return new \DOMXPath($doc);
    }

    /**
     * Every topic tag on the index is a disclosure trigger, and every one of
     * them owns a panel that is actually on the page. A pill whose
     * aria-controls names nothing is a tag that looks clickable and does
     * nothing, which is what this change existed to fix.
     */
    public function test_every_docs_topic_pill_opens_a_panel(): void
    {
        $xpath = $this->docsDom();

        $pills = $xpath->query('//button[starts-with(@aria-controls, "docs-")]');

        $this->assertGreaterThanOrEqual(20, $pills->length, 'the docs index lost its topic pills');

        foreach ($pills as $pill) {
            $id = $pill->getAttribute('aria-controls');

            $panel = $xpath->query('//*[@id="' . $id . '"]');
            $this->assertSame(1, $panel->length, "the pill for '{$id}' controls no panel");

            // A disclosure, so both halves of the contract are present.
            $this->assertSame('false', $pill->getAttribute('aria-expanded'), "{$id} starts expanded");
            $this->assertStringContainsString('acc-panel', $panel->item(0)->getAttribute('class'));
        }
    }

    /**
     * Opening a pill has to reveal something worth the click. This is the
     * check that stops a topic shipping with a stub in it.
     */
    public function test_every_docs_panel_carries_real_content(): void
    {
        $xpath = $this->docsDom();

        foreach ($xpath->query('//div[starts-with(@id, "docs-") and contains(@class, "acc-panel")]') as $panel) {
            $id = $panel->getAttribute('id');
            $text = trim(preg_replace('/\s+/', ' ', $panel->textContent));

            $this->assertGreaterThan(
                120,
                strlen($text),
                "{$id} opens onto barely any content — is it still a stub?"
            );

            foreach (['lorem ipsum', 'TODO', 'TBD'] as $filler) {
                $this->assertStringNotContainsStringIgnoringCase($filler, $text, "{$id} contains filler copy");
            }
        }
    }

    /**
     * The cards are an index first. All six arrive closed, so the page still
     * reads as a contents page rather than as six essays.
     */
    public function test_the_docs_topics_all_start_closed(): void
    {
        $html = $this->get(route('marketing.docs'))->getContent();

        $this->assertStringNotContainsString(
            'acc-panel acc-panel--open',
            $html,
            'a docs topic ships open, which turns the index into a wall'
        );
    }

    /**
     * Six cards each numbering their topics from zero, so the ids only stay
     * unique because each card passes its own prefix. Getting that wrong
     * makes one card's pill open another card's panel.
     */
    public function test_docs_panel_ids_are_unique_across_cards(): void
    {
        $xpath = $this->docsDom();

        $ids = [];
        foreach ($xpath->query('//div[starts-with(@id, "docs-") and contains(@class, "acc-panel")]') as $panel) {
            $ids[] = $panel->getAttribute('id');
        }

        $this->assertNotEmpty($ids);
        $this->assertSame($ids, array_unique($ids), 'two docs panels share an id');
    }

    /**
     * The pills becoming interactive must not have cost the card its link out
     * to the full page for that area — that link is the one destination on
     * the card that leaves the page, and it still has to work.
     */
    public function test_every_docs_card_still_links_to_its_full_page(): void
    {
        $expected = [
            'marketing.help',
            'marketing.product.boards',
            'marketing.product.files',
            'marketing.product.credits',
            'marketing.solutions.agencies',
            'marketing.product.ai',
        ];

        $html = $this->get(route('marketing.docs'))->getContent();

        foreach ($expected as $route) {
            $this->assertStringContainsString(
                'href="' . route($route) . '"',
                $html,
                "the docs index no longer links to {$route}"
            );

            // And the destination is a page, not a 404.
            $this->get(route($route))->assertOk();
        }
    }

    /**
     * The pill row is the one part of a card that can overflow on a phone:
     * a long topic name in a row that cannot wrap would push the card wider
     * than the viewport. It wraps, and no pill is pinned to one line.
     */
    public function test_the_docs_pill_rows_wrap_on_narrow_screens(): void
    {
        $xpath = $this->docsDom();

        $rows = $xpath->query('//ul[contains(@class, "flex-wrap")][.//button[starts-with(@aria-controls, "docs-")]]');
        $this->assertSame(6, $rows->length, 'a docs card lost its wrapping pill row');

        foreach ($xpath->query('//button[starts-with(@aria-controls, "docs-")]') as $pill) {
            $this->assertStringNotContainsString(
                'whitespace-nowrap',
                $pill->getAttribute('class'),
                'a topic pill refuses to wrap, which overflows the card on a phone'
            );
        }
    }

    /**
     * Topics written from the shape of a feature rather than checked against
     * it say so in the panel. The flag is derived from a 'verified' key on
     * the topic, so an unmarked draft is a thing you have to do on purpose.
     */
    public function test_unverified_docs_topics_are_marked_as_drafts(): void
    {
        $xpath = $this->docsDom();

        $drafts = 0;
        foreach ($xpath->query('//div[starts-with(@id, "docs-") and contains(@class, "acc-panel")]') as $panel) {
            if (str_contains($panel->textContent, 'Draft.')) {
                $drafts++;

                $this->assertStringStartsWith(
                    'docs-automation-',
                    $panel->getAttribute('id'),
                    'a draft topic turned up outside Automation — check it and flip its flag, or leave the note'
                );
            }
        }

        $this->assertGreaterThan(0, $drafts, 'the draft marker stopped rendering');
    }

    /**
     * Each Resources page hands the reader on to somewhere real.
     */
    public function test_the_pages_cross_link_into_the_rest_of_the_site(): void
    {
        $this->get(route('marketing.blog'))
            ->assertSee('href="' . route('marketing.docs') . '"', false)
            ->assertSee('href="' . route('marketing.help') . '"', false);

        $this->get(route('marketing.docs'))
            ->assertSee('href="' . route('marketing.product.boards') . '"', false)
            ->assertSee('href="' . route('marketing.product.credits') . '"', false);

        $this->get(route('marketing.help'))
            ->assertSee('href="' . route('marketing.docs') . '"', false);
    }
}
