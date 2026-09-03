<?php

namespace Tests\Feature\Marketing;

use App\Support\CustomerStories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The rule the Customer Stories page rests on.
 *
 * An invented studio presented without a marker is a fabricated testimonial,
 * so the interesting cases here are the ones where a story tries to shed the
 * marker: no flag, a flag on an unapproved company, a company approved but
 * not flagged. All three stay illustrative. Only a story that is both flagged
 * and approved is exempt.
 *
 * The page-level render is checked both ways — with the real content, and
 * with an injected real story — so the exemption is proven to work before a
 * real story ships rather than after.
 */
class CustomerStoriesTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------ the rule

    public function test_a_story_with_no_flag_is_illustrative(): void
    {
        $this->assertTrue(CustomerStories::isIllustrative(['studio' => 'Some Studio']));
        $this->assertFalse(CustomerStories::isReal(['studio' => 'Some Studio']));
    }

    /**
     * The flag alone is not enough. Copying a real story's array and changing
     * the name must not carry the exemption across with it.
     */
    public function test_the_flag_alone_does_not_make_a_story_real(): void
    {
        $this->assertTrue(CustomerStories::isIllustrative([
            'studio' => 'Definitely Not Approved Ltd',
            'real' => true,
        ]));
    }

    /**
     * And the name alone is not enough either, so an approved company's story
     * is still marked until someone deliberately says it is true.
     */
    public function test_an_approved_name_alone_does_not_make_a_story_real(): void
    {
        $this->assertTrue(CustomerStories::isIllustrative([
            'studio' => 'Code Next Door',
            'real' => false,
        ]));

        $this->assertTrue(CustomerStories::isIllustrative(['studio' => 'Code Next Door']));
    }

    public function test_both_together_make_a_story_real(): void
    {
        $this->assertTrue(CustomerStories::isReal([
            'studio' => 'Code Next Door',
            'real' => true,
        ]));
    }

    /**
     * Truthy is not true. A string, a 1, or a stray array must not slip past
     * a check whose whole job is to be strict.
     */
    public function test_only_a_literal_true_counts_as_a_claim(): void
    {
        foreach (['yes', 1, '1', [], 'true'] as $notTrue) {
            $this->assertTrue(
                CustomerStories::isIllustrative(['studio' => 'Code Next Door', 'real' => $notTrue]),
                'a non-boolean value was accepted as a claim that a story is real',
            );
        }
    }

    public function test_a_malformed_story_fails_toward_the_disclosure(): void
    {
        $this->assertTrue(CustomerStories::isIllustrative([]));
        $this->assertTrue(CustomerStories::isIllustrative(['real' => true]));
    }

    // -------------------------------------------------------- the content

    /**
     * Every story shipping today is invented, including the featured one —
     * the featured slot is held for Code Next Door and is not filled in with
     * guesses about a real company in the meantime.
     */
    public function test_nothing_currently_on_the_page_claims_to_be_real(): void
    {
        foreach (CustomerStories::all() as $story) {
            $this->assertTrue(
                CustomerStories::isIllustrative($story),
                "'{$story['studio']}' claims to be a real customer — is its content confirmed?",
            );
        }
    }

    /**
     * Role-only bylines, on every story, real or not. A quote attributed to a
     * named individual is a different kind of claim from one attributed to a
     * role, and this page does not make it.
     */
    public function test_no_story_names_an_individual(): void
    {
        foreach (CustomerStories::all() as $story) {
            $byline = $story['byline'] ?? '';

            $this->assertNotSame('', $byline, "'{$story['studio']}' has no byline");

            $this->assertDoesNotMatchRegularExpression(
                '/\b[A-Z][a-z]+ (?:[A-Z]\.|[A-Z][a-z]+)\b/',
                $byline,
                "the byline '{$byline}' looks like a person's name rather than a role",
            );
        }
    }

    // ---------------------------------------------------------- the render

    /**
     * The page rendered directly. It has no route while it is unpublished, so
     * these checks go through the view rather than over HTTP — the markup and
     * the rule it encodes are what they are testing either way.
     */
    private function page(array $data = []): string
    {
        return view('marketing.resources.customers', $data)->render();
    }

    /**
     * Each illustrative story's own byline carries the marker — not a single
     * disclaimer somewhere on the page, so the mark survives a screenshot of
     * one card.
     */
    public function test_every_illustrative_story_is_marked_beside_its_byline(): void
    {
        $html = $this->page();

        $marked = substr_count($html, '&middot; illustrative');
        $illustrative = count(array_filter(CustomerStories::all(), CustomerStories::isIllustrative(...)));

        $this->assertSame(
            $illustrative,
            $marked,
            'the number of illustrative markers on the page does not match the number of invented stories',
        );

        foreach (CustomerStories::all() as $story) {
            if (CustomerStories::isIllustrative($story)) {
                $this->assertStringContainsString($story['byline'], $html);
            }
        }
    }

    /**
     * The exemption, proven before it is used: a story that is both flagged
     * and approved renders without the marker, and the notice under the hero
     * switches to the wording that explains a mixed page.
     */
    public function test_a_real_story_renders_without_the_marker(): void
    {
        $real = array_merge(CustomerStories::featured(), [
            'studio' => 'Code Next Door',
            'real' => true,
        ]);

        $html = view('marketing.resources.customers', [
            'featured' => $real,
            'stories' => CustomerStories::others(),
        ])->render();

        // Three invented stories are still marked; the real one is not.
        $this->assertSame(3, substr_count($html, '&middot; illustrative'));
        $this->assertStringContainsString('Operations lead, Code Next Door', $html);

        // And the notice now describes a mixed page rather than claiming
        // everything on it is invented.
        $this->assertStringContainsString('is a real customer, named and', $html);
        $this->assertStringNotContainsString('The studios on this page are invented', $html);
    }

    /**
     * The mirror of the above: with everything invented, the notice says so
     * plainly rather than hinting that some of it might be real.
     */
    public function test_an_all_invented_page_says_so_plainly(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('The studios on this page are invented', $html);
        $this->assertStringNotContainsString('is a real customer, named and', $html);
    }

    /**
     * The page is built but not published: nothing on the site links to it and
     * there is no route to reach it by. Publishing fictional stories as a
     * stand-in for real ones was the thing we decided against, so this is the
     * check that it stays decided — restoring the page means restoring the
     * route and its two config entries, deliberately.
     */
    public function test_the_page_is_not_published(): void
    {
        $this->assertFalse(
            \Illuminate\Support\Facades\Route::has('marketing.customers'),
            'the Customer Stories route is registered again — are the stories real now?',
        );

        foreach (\App\Support\MarketingNav::allItems() as $entry) {
            $this->assertStringNotContainsStringIgnoringCase(
                'customer stories',
                $entry['item']['label'],
                "{$entry['source']} links to Customer Stories",
            );
        }

        // And no other marketing page points at it either.
        foreach (['home', 'marketing.blog', 'marketing.docs', 'marketing.help', 'marketing.pricing'] as $route) {
            $this->assertStringNotContainsString(
                'Customer Stories',
                $this->get(route($route))->getContent(),
                "{$route} still mentions Customer Stories",
            );
        }
    }

    /**
     * A story flagged real but not approved must not lose its marker on the
     * page either — the view asks the same question the rule answers.
     */
    public function test_an_unapproved_claim_is_still_marked_when_rendered(): void
    {
        $imposter = array_merge(CustomerStories::featured(), [
            'studio' => 'Imposter Studio',
            'real' => true,
        ]);

        $html = view('marketing.resources.customers', [
            'featured' => $imposter,
            'stories' => [],
        ])->render();

        $this->assertStringContainsString('Operations lead, Imposter Studio &middot; illustrative', $html);
    }
}
