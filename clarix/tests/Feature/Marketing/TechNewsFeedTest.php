<?php

namespace Tests\Feature\Marketing;

use App\Services\TechNewsFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The tech news feed behind /blog, and the page that renders it.
 *
 * Nothing here touches the network: phpunit.xml ships an empty
 * NEWSDATA_API_KEY, so a test that wants a working feed has to set a key and
 * fake the client itself. A test that forgets is a test with no key, which
 * fails closed rather than quietly billing the real allowance.
 */
class TechNewsFeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget(TechNewsFeed::CACHE_KEY);
        config()->set('services.newsdata.key', 'test-key');
    }

    /**
     * A response shaped like the live one — field names taken from an actual
     * call, including the free tier's upsell sentinel in `content`.
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'status' => 'success',
            'totalResults' => 2,
            'results' => [
                [
                    'article_id'  => 'a1',
                    'title'       => 'A chip maker posts record quarterly revenue',
                    'description' => 'The company said demand for data-centre parts drove the quarter, and raised its guidance for the year ahead.',
                    'content'     => 'ONLY AVAILABLE IN PAID PLANS',
                    'pubDate'     => '2026-09-02 18:22:00',
                    'pubDateTZ'   => 'UTC',
                    'source_id'   => 'the_verge',
                    'source_name' => 'The Verge',
                    'source_url'  => 'https://www.theverge.com',
                    'link'        => 'https://www.theverge.com/2026/9/2/chip-maker-revenue',
                    'category'    => ['technology'],
                ],
                [
                    'article_id'  => 'a2',
                    'title'       => 'Open source project ships its first stable release',
                    'description' => 'After four years the maintainers have tagged 1.0, and say the API will not change again without a major version.',
                    'content'     => 'ONLY AVAILABLE IN PAID PLANS',
                    'pubDate'     => '2026-09-02 09:10:00',
                    'pubDateTZ'   => 'UTC',
                    'source_id'   => 'ars_technica',
                    'source_name' => 'Ars Technica',
                    'source_url'  => 'https://arstechnica.com',
                    'link'        => 'https://arstechnica.com/2026/09/first-stable-release',
                    'category'    => ['technology'],
                ],
            ],
        ], $overrides);
    }

    private function fakeSuccess(array $overrides = []): void
    {
        Http::fake(['newsdata.io/*' => Http::response($this->payload($overrides), 200)]);
    }

    // ---------------------------------------------------------------- feed

    public function test_it_asks_only_for_technology_news(): void
    {
        $this->fakeSuccess();

        app(TechNewsFeed::class)->articles();

        Http::assertSent(function ($request) {
            $this->assertSame('technology', $request['category']);
            $this->assertSame('en', $request['language']);

            // full_content is never requested. The terms around reproducing
            // article bodies are the one thing we are not sure of, so the
            // request does not go near them.
            $this->assertArrayNotHasKey('full_content', $request->data());

            return true;
        });
    }

    public function test_it_normalises_the_fields_a_card_needs(): void
    {
        $this->fakeSuccess();

        $articles = app(TechNewsFeed::class)->articles();

        $this->assertCount(2, $articles);

        $first = $articles[0];
        $this->assertSame('A chip maker posts record quarterly revenue', $first['title']);
        $this->assertStringContainsString('data-centre parts', $first['description']);
        $this->assertSame('The Verge', $first['source']);
        $this->assertSame('theverge.com', $first['domain']);
        $this->assertSame('https://www.theverge.com/2026/9/2/chip-maker-revenue', $first['url']);
        $this->assertSame('2026-09-02', $first['published_at']->toDateString());
    }

    /**
     * The free tier returns a sentinel string where a paid plan would return
     * the article body. It must never reach a page as though it were a
     * snippet — and the body itself is never carried at all.
     */
    public function test_it_never_carries_the_article_body(): void
    {
        $this->fakeSuccess(['results' => [[
            'title'       => 'A headline',
            'description' => 'ONLY AVAILABLE IN PAID PLANS',
            'content'     => 'ONLY AVAILABLE IN PAID PLANS',
            'link'        => 'https://example.com/a',
            'source_name' => 'Example',
            'pubDate'     => '2026-09-02 10:00:00',
        ]]]);

        $articles = app(TechNewsFeed::class)->articles();

        $this->assertNull($articles[0]['description']);
        $this->assertArrayNotHasKey('content', $articles[0]);
    }

    /**
     * The url goes straight into an href and comes from a third party.
     */
    public function test_it_drops_articles_with_an_unusable_link(): void
    {
        $this->fakeSuccess(['results' => [
            ['title' => 'Script', 'link' => 'javascript:alert(1)', 'source_name' => 'X', 'description' => 'd'],
            ['title' => 'Data', 'link' => 'data:text/html,<script>', 'source_name' => 'X', 'description' => 'd'],
            ['title' => 'Relative', 'link' => '/local/path', 'source_name' => 'X', 'description' => 'd'],
            ['title' => 'Missing', 'source_name' => 'X', 'description' => 'd'],
            ['title' => 'Fine', 'link' => 'https://example.com/ok', 'source_name' => 'X', 'description' => 'd'],
        ]]);

        $articles = app(TechNewsFeed::class)->articles();

        $this->assertCount(1, $articles);
        $this->assertSame('https://example.com/ok', $articles[0]['url']);
    }

    public function test_it_caps_how_many_articles_it_returns(): void
    {
        $many = [];
        for ($i = 0; $i < 40; $i++) {
            $many[] = ['title' => "Headline {$i}", 'link' => "https://example.com/{$i}", 'source_name' => 'Example', 'description' => 'A snippet.'];
        }

        $this->fakeSuccess(['results' => $many]);

        $this->assertCount(TechNewsFeed::LIMIT, app(TechNewsFeed::class)->articles());
    }

    // ------------------------------------------------------------- caching

    /**
     * The whole point of the cache: a second visitor inside the window costs
     * nothing. Counted in requests, not eyeballed on the page.
     */
    public function test_repeat_page_loads_inside_the_window_do_not_call_the_api(): void
    {
        $this->fakeSuccess();

        $this->get(route('marketing.blog'))->assertOk();
        $this->get(route('marketing.blog'))->assertOk();
        $this->get(route('marketing.blog'))->assertOk();

        Http::assertSentCount(1);
    }

    public function test_the_cache_expires_and_the_api_is_called_again(): void
    {
        $this->fakeSuccess();

        app(TechNewsFeed::class)->articles();
        Http::assertSentCount(1);

        $this->travel(config('services.newsdata.cache_minutes') + 1)->minutes();

        app(TechNewsFeed::class)->articles();
        Http::assertSentCount(2);
    }

    /**
     * Negative caching. Without it an outage costs one outbound request per
     * page view, and the daily allowance is spent on failures.
     */
    public function test_a_failure_is_cached_too_so_an_outage_is_not_amplified(): void
    {
        Http::fake(['newsdata.io/*' => Http::response('', 500)]);

        $this->get(route('marketing.blog'))->assertOk();
        $this->get(route('marketing.blog'))->assertOk();
        $this->get(route('marketing.blog'))->assertOk();

        Http::assertSentCount(1);

        // ...but it clears sooner than a good result would.
        $this->travel(TechNewsFeed::FAILURE_TTL_MINUTES + 1)->minutes();
        app(TechNewsFeed::class)->articles();

        Http::assertSentCount(2);
    }

    public function test_it_makes_no_request_at_all_without_a_key(): void
    {
        config()->set('services.newsdata.key', null);
        Http::fake();

        $this->assertFalse(app(TechNewsFeed::class)->isConfigured());
        $this->assertSame([], app(TechNewsFeed::class)->articles());

        Http::assertNothingSent();
    }

    // ------------------------------------------------------- failure paths

    public static function failures(): array
    {
        return [
            'server error'      => [fn () => Http::response('', 500)],
            'rate limited'      => [fn () => Http::response(['status' => 'error', 'results' => ['message' => 'Rate limit exceeded']], 429)],
            'bad api key'       => [fn () => Http::response(['status' => 'error', 'results' => ['message' => 'Unauthorized']], 401)],
            'error body, 200'   => [fn () => Http::response(['status' => 'error', 'results' => []], 200)],
            'not json'          => [fn () => Http::response('<html>gateway</html>', 200)],
            'results not a list'=> [fn () => Http::response(['status' => 'success', 'results' => 'nope'], 200)],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('failures')]
    public function test_the_page_survives_every_failure_shape(\Closure $response): void
    {
        Http::fake(['newsdata.io/*' => $response()]);

        $this->get(route('marketing.blog'))
            ->assertOk()
            ->assertSee("We couldn't load the news right now.", false)
            ->assertDontSee('Exception', false);
    }

    public function test_a_connection_failure_does_not_take_the_page_down(): void
    {
        Http::fake(['newsdata.io/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out')]);

        $this->get(route('marketing.blog'))
            ->assertOk()
            ->assertSee("We couldn't load the news right now.", false);
    }

    // ---------------------------------------------------------------- page

    public function test_every_card_links_out_to_the_original_article(): void
    {
        $this->fakeSuccess();

        $html = $this->get(route('marketing.blog'))->assertOk()->getContent();

        foreach (['https://www.theverge.com/2026/9/2/chip-maker-revenue', 'https://arstechnica.com/2026/09/first-stable-release'] as $url) {
            $this->assertStringContainsString('href="' . $url . '"', $html);
        }

        // Opened in a new tab, and never as a Clarix permalink.
        $this->assertSame(2, substr_count($html, 'rel="noopener noreferrer nofollow"'));
        $this->assertStringNotContainsString(route('marketing.blog') . '/', $html);
    }

    /**
     * Attribution has to be legible, not tucked into a caption. The source
     * name is rendered at the card's own heading weight and size.
     */
    public function test_the_source_publication_is_shown_prominently(): void
    {
        $this->fakeSuccess();

        $html = $this->get(route('marketing.blog'))->getContent();

        foreach (['The Verge', 'Ars Technica'] as $source) {
            $this->assertStringContainsString(
                '<span class="min-w-0 truncate text-[13px] font-semibold tracking-tight text-[#17143A]">' . "\n                                " . $source,
                $html,
                "{$source} is not rendered as the card's attribution line"
            );
        }

        // And the destination is named at the foot of the card too.
        $this->assertStringContainsString('Read at theverge.com', $html);
        $this->assertStringContainsString('Read at arstechnica.com', $html);
    }

    public function test_the_cards_carry_a_snippet_and_a_date(): void
    {
        $this->fakeSuccess();

        $this->get(route('marketing.blog'))
            ->assertSee('demand for data-centre parts drove the quarter', false)
            ->assertSee('datetime="2026-09-02T18:22:00+00:00"', false);
    }

    public function test_the_page_credits_the_provider(): void
    {
        $this->fakeSuccess();

        $this->get(route('marketing.blog'))
            ->assertSee('NewsData.io', false)
            ->assertSee('the property of their respective publications', false);
    }

    // -------------------------------------------------------------- images

    public function test_it_keeps_the_article_thumbnail(): void
    {
        $this->fakeSuccess(['results' => [[
            'title'       => 'A headline',
            'description' => 'A snippet.',
            'link'        => 'https://example.com/a',
            'source_name' => 'Example News',
            'image_url'   => 'https://cdn.example.com/photo.jpg',
        ]]]);

        $article = app(TechNewsFeed::class)->articles()[0];

        $this->assertSame('https://cdn.example.com/photo.jpg', $article['image']);
        $this->assertSame('EN', $article['initials']);
    }

    /**
     * An img src is as good a place to smuggle something as an href, and both
     * arrive from the same third party.
     */
    public static function unusableImages(): array
    {
        return [
            'missing'      => [null],
            'empty'        => [''],
            'javascript'   => ['javascript:alert(1)'],
            'data uri'     => ['data:image/svg+xml;base64,PHN2Zz48L3N2Zz4='],
            'relative'     => ['/images/thumb.jpg'],
            'not a string' => [['https://example.com/a.jpg']],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unusableImages')]
    public function test_it_drops_an_unusable_thumbnail_without_dropping_the_article(mixed $image): void
    {
        $this->fakeSuccess(['results' => [[
            'title'       => 'A headline',
            'description' => 'A snippet.',
            'link'        => 'https://example.com/a',
            'source_name' => 'Example News',
            'image_url'   => $image,
        ]]]);

        $articles = app(TechNewsFeed::class)->articles();

        // The article survives — only its picture is refused.
        $this->assertCount(1, $articles);
        $this->assertNull($articles[0]['image']);
    }

    public function test_initials_cope_with_awkward_source_names(): void
    {
        $cases = [
            ['The Verge', 'TV'],
            ['Ars Technica', 'AT'],
            ['cordcuttersnews', 'C'],
            ['PC Mag / IDG', 'PM'],
            // A blank name never reaches initials(): sourceName() has already
            // substituted "Unknown source", so a card is never unattributed.
            ['   ', 'US'],
            // Only a name with no letters or digits at all falls through to
            // the monogram's own last resort.
            ['•••', '·'],
        ];

        // One stub, read by reference: Http::fake() merges rather than
        // replaces, so calling it again inside the loop would leave the first
        // case's response answering for every iteration after it.
        $source = null;

        // A full closure, not an arrow function: fn () captures by value, and
        // this one has to see the loop variable change under it.
        Http::fake(function () use (&$source) {
            return Http::response($this->payload(['results' => [[
                'title'       => 'A headline',
                'description' => 'A snippet.',
                'link'        => 'https://example.com/a',
                'source_name' => $source,
            ]]]), 200);
        });

        foreach ($cases as [$source, $expected]) {
            Cache::forget(TechNewsFeed::CACHE_KEY);

            $this->assertSame(
                $expected,
                app(TechNewsFeed::class)->articles()[0]['initials'],
                "for source '{$source}'"
            );
        }
    }

    /**
     * The image renders inside a fixed-ratio box with the monogram behind it,
     * so every card is the same height whether or not a thumbnail arrives —
     * and a thumbnail that fails at load time removes itself and reveals the
     * monogram rather than leaving a broken icon.
     */
    public function test_the_card_renders_the_image_over_a_fallback_panel(): void
    {
        $this->fakeSuccess(['results' => [[
            'title'       => 'A headline',
            'description' => 'A snippet.',
            'link'        => 'https://example.com/a',
            'source_name' => 'Example News',
            'image_url'   => 'https://cdn.example.com/photo.jpg',
        ]]]);

        $html = $this->get(route('marketing.blog'))->assertOk()->getContent();

        $this->assertStringContainsString('aspect-[16/9]', $html, 'the thumbnail box has no fixed ratio');
        $this->assertStringContainsString('src="https://cdn.example.com/photo.jpg"', $html);
        $this->assertStringContainsString('object-cover', $html, 'the thumbnail is not cropped to the box');
        $this->assertStringContainsString('onerror="this.remove()"', $html, 'a failed image would show as a broken icon');
        $this->assertStringContainsString('loading="lazy"', $html);

        // Decorative: the headline beside it already carries the meaning.
        $this->assertStringContainsString('alt=""', $html);

        // The monogram sits underneath, ready for the onerror case.
        $this->assertStringContainsString('EN', $html);
    }

    public function test_a_card_without_an_image_still_gets_the_same_box(): void
    {
        $this->fakeSuccess(['results' => [[
            'title'       => 'A headline',
            'description' => 'A snippet.',
            'link'        => 'https://example.com/a',
            'source_name' => 'Example News',
        ]]]);

        $html = $this->get(route('marketing.blog'))->assertOk()->getContent();

        // Same fixed-ratio panel, so the grid does not go ragged...
        $this->assertStringContainsString('aspect-[16/9]', $html);
        // ...with no <img> in it at all, rather than one pointing at nothing.
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('EN', $html);
    }

    /**
     * Mixed feeds are the normal case: some publishers supply a thumbnail and
     * some do not. Every card gets a box either way, which is what keeps the
     * grid even.
     */
    public function test_a_mixed_feed_gives_every_card_a_thumbnail_box(): void
    {
        $this->fakeSuccess(['results' => [
            ['title' => 'With', 'description' => 'd', 'link' => 'https://example.com/1', 'source_name' => 'Alpha News', 'image_url' => 'https://cdn.example.com/1.jpg'],
            ['title' => 'Without', 'description' => 'd', 'link' => 'https://example.com/2', 'source_name' => 'Beta Times'],
            ['title' => 'With too', 'description' => 'd', 'link' => 'https://example.com/3', 'source_name' => 'Gamma Post', 'image_url' => 'https://cdn.example.com/3.jpg'],
        ]]);

        $html = $this->get(route('marketing.blog'))->getContent();

        $this->assertSame(3, substr_count($html, 'aspect-[16/9]'), 'a card is missing its thumbnail box');
        $this->assertSame(2, substr_count($html, '<img'), 'the wrong number of images rendered');

        foreach (['AN', 'BT', 'GP'] as $monogram) {
            $this->assertStringContainsString($monogram, $html);
        }
    }

    /**
     * The key is a credential. It reaches the API and nowhere else.
     */
    public function test_the_api_key_never_reaches_the_page(): void
    {
        config()->set('services.newsdata.key', 'pub_secret_value_here');
        $this->fakeSuccess();

        $this->get(route('marketing.blog'))
            ->assertOk()
            ->assertDontSee('pub_secret_value_here', false);
    }
}
