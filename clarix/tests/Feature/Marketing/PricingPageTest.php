<?php

namespace Tests\Feature\Marketing;

use App\Support\Pricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dedicated pricing page.
 *
 * The point of most of this is drift. Prices used to live in a @php block
 * inside home.blade.php; a second page quoting them from a second copy would
 * be a stale number waiting to happen, on the one page where a stale number
 * actually costs something. Both pages now read App\Support\Pricing, and the
 * tests below check that what they render agrees rather than trusting that
 * they were wired to the same place.
 */
class PricingPageTest extends TestCase
{
    use RefreshDatabase;

    private function pricingPage(): string
    {
        return $this->get(route('marketing.pricing'))->assertOk()->getContent();
    }

    private function homepage(): string
    {
        return $this->get(route('home'))->assertOk()->getContent();
    }

    // ---------------------------------------------------------------- parity

    /**
     * Every plan, every price and every period appears on both pages. If one
     * gets an edit the other does not, this is what says so.
     */
    public function test_the_two_pages_quote_the_same_numbers(): void
    {
        $pricing = $this->pricingPage();
        $home = $this->homepage();

        foreach (Pricing::plans() as $plan) {
            foreach (array_filter([$plan['name'], $plan['price'], $plan['priceAnnual'] ?? null]) as $value) {
                $needle = e($value);

                $this->assertStringContainsString($needle, $pricing, "/pricing is missing '{$value}'");
                $this->assertStringContainsString($needle, $home, "the homepage is missing '{$value}'");
            }
        }
    }

    /**
     * And no number appears that the shared source does not carry — the guard
     * against a hand-typed figure creeping onto the page beside the real ones.
     */
    public function test_the_page_quotes_no_price_of_its_own(): void
    {
        preg_match_all('/Rs\s?[\d,]+/u', $this->pricingPage(), $found);

        $known = [];
        foreach (Pricing::plans() as $plan) {
            foreach ([$plan['price'], $plan['priceAnnual'] ?? null] as $price) {
                if ($price) {
                    $known[] = preg_replace('/\s+/', ' ', $price);
                }
            }
            foreach ($plan['features'] as $feature) {
                preg_match_all('/Rs\s?[\d,]+/u', $feature, $inFeature);
                $known = array_merge($known, $inFeature[0]);
            }
        }

        foreach (Pricing::comparison() as $rows) {
            foreach ($rows as $row) {
                foreach ((array) $row[1] as $value) {
                    if (is_string($value)) {
                        preg_match_all('/Rs\s?[\d,]+/u', $value, $inCell);
                        $known = array_merge($known, $inCell[0]);
                    }
                }
            }
        }

        // The mocked subscription screen quotes a plan price and its yearly
        // total; both are derived from the real figures, not invented tiers.
        $known[] = 'Rs 1,600';
        $known[] = 'Rs 19,200';

        $known = array_map(fn (string $v) => preg_replace('/\s+/', ' ', $v), $known);

        foreach (array_unique($found[0]) as $quoted) {
            $this->assertContains(
                preg_replace('/\s+/', ' ', $quoted),
                $known,
                "'{$quoted}' is on the pricing page but not in App\\Support\\Pricing",
            );
        }
    }

    public function test_every_plan_and_every_comparison_row_is_rendered(): void
    {
        $html = $this->pricingPage();

        foreach (Pricing::planNames() as $name) {
            $this->assertStringContainsString($name, $html, "the {$name} plan is missing");
        }

        foreach (Pricing::comparison() as $group => $rows) {
            $this->assertStringContainsString($group, $html, "the {$group} group is missing");

            foreach ($rows as $row) {
                $this->assertStringContainsString(e($row[0]), $html, "the '{$row[0]}' row is missing");
            }
        }
    }

    /**
     * The dedicated page is meant to be more than the homepage band. The
     * comparison there is a preview; here it is the whole table, so this page
     * should carry strictly more of it.
     */
    public function test_the_page_is_fuller_than_the_homepage_section(): void
    {
        $rows = array_sum(array_map('count', Pricing::comparison()));

        $this->assertGreaterThanOrEqual(15, $rows);

        // Things the homepage section does not have at all.
        $this->assertStringContainsString('Credits are not a thing you buy', $this->pricingPage());
        $this->assertStringContainsString('acc-panel', $this->pricingPage(), 'the billing FAQ is missing');
    }

    /**
     * The team ceiling per plan, which is a limit rather than the "unlimited"
     * the table used to claim. Checked on both pages, because a wrong number
     * here is a promise the product does not keep.
     */
    public function test_the_team_limits_are_right_on_both_pages(): void
    {
        $expected = ['Base' => '5', 'Standard' => '15', 'Pro' => '50', 'Enterprise' => 'Unlimited'];

        $row = null;
        foreach (Pricing::comparison()['Core'] as $candidate) {
            if ($candidate[0] === 'Teams/Units') {
                $row = $candidate[1];
            }
        }

        $this->assertNotNull($row, 'the Teams/Units row has gone from the comparison');
        $this->assertSame(array_values($expected), $row);

        // And the plan cards agree with the table rather than contradicting it.
        foreach (Pricing::plans() as $plan) {
            $limit = $expected[$plan['name']];

            $stated = implode(' ', $plan['features']);

            if ($limit === 'Unlimited') {
                $this->assertStringContainsStringIgnoringCase('unlimited teams', $stated);
            } else {
                $this->assertStringContainsString(
                    "Up to {$limit} teams/units",
                    $stated,
                    "the {$plan['name']} card does not state its team ceiling",
                );
            }
        }

        // Rendered, on both pages.
        foreach ([$this->pricingPage(), $this->homepage()] as $html) {
            $this->assertStringContainsString('Up to 5 teams/units', $html);
            $this->assertStringContainsString('Up to 15 teams/units', $html);
            $this->assertStringContainsString('Up to 50 teams/units', $html);
        }
    }

    /**
     * No page may still be telling a visitor that units are unlimited on every
     * plan. That was true before the ceilings were set and is now a false
     * promise wherever it survived.
     */
    public function test_no_page_still_promises_unlimited_units(): void
    {
        $routes = [
            'home', 'marketing.pricing', 'marketing.docs', 'marketing.help',
            'marketing.blog', 'marketing.solutions.freelancers',
            'marketing.solutions.agencies', 'marketing.solutions.enterprises',
        ];

        foreach ($routes as $route) {
            $html = strtolower($this->get(route($route))->assertOk()->getContent());

            foreach ([
                'unlimited teams and units are on every plan',
                'units are unlimited on every plan',
                'unlimited teams on every tier',
                'unlimited teams/units are on every',
            ] as $claim) {
                $this->assertStringNotContainsString(
                    $claim,
                    $html,
                    "{$route} still promises unlimited units",
                );
            }
        }
    }

    /**
     * The annual figure is the monthly one at 20% off — the discount the
     * billing toggle advertises. Derived rather than typed, so a price change
     * that forgets the annual column fails here instead of on the page.
     */
    public function test_every_annual_price_is_twenty_percent_off_the_monthly_one(): void
    {
        $toNumber = fn (string $price) => (float) str_replace([',', 'Rs', ' '], '', $price);

        $checked = 0;

        foreach (Pricing::plans() as $plan) {
            if (! ($plan['priceAnnual'] ?? null)) {
                continue;
            }

            $this->assertEqualsWithDelta(
                $toNumber($plan['price']) * 0.8,
                $toNumber($plan['priceAnnual']),
                0.01,
                "{$plan['name']}'s annual price is not 20% off its monthly one, which is what the toggle claims",
            );

            $checked++;
        }

        $this->assertGreaterThan(0, $checked, 'no plan carries an annual price any more');
    }

    /**
     * Pro's price, in the format the rest of the table uses: rupees, thousands
     * separated, quoted per month.
     */
    public function test_the_pro_plan_is_priced_and_formatted_like_the_others(): void
    {
        $pro = collect(Pricing::plans())->firstWhere('name', 'Pro');

        $this->assertSame('Rs 4,000', $pro['price']);
        $this->assertSame('Rs 3,200', $pro['priceAnnual']);
        $this->assertSame('per month', $pro['period']);

        // The same currency and period wording as every other listed plan.
        foreach (Pricing::plans() as $plan) {
            if ($plan['name'] === 'Enterprise') {
                continue;   // no list price
            }

            $this->assertMatchesRegularExpression('/^Rs [\d,]+$/', $plan['price']);
            $this->assertSame('per month', $plan['period']);
        }

        foreach ([$this->pricingPage(), $this->homepage()] as $html) {
            $this->assertStringContainsString('Rs 4,000', $html);
            $this->assertStringNotContainsString('Rs 3,500', $html, 'the old Pro price is still rendered');
        }
    }

    // ------------------------------------------------------------------- nav

    public function test_the_nav_pricing_link_points_at_this_page(): void
    {
        $home = $this->homepage();

        $this->assertStringContainsString('href="' . route('marketing.pricing') . '"', $home);
        $this->assertStringNotContainsString('href="/home#pricing"', $home);
    }

    /**
     * The homepage section stays as a preview and keeps its anchor, so an
     * older link into it still lands somewhere sensible — and it now signs
     * the way to the full page.
     */
    public function test_the_homepage_section_survives_and_points_here(): void
    {
        $this->get(route('home'))
            ->assertSee('id="pricing"', false)
            ->assertSee('See the full pricing page', false)
            ->assertSee('href="' . route('marketing.pricing') . '"', false);
    }

    // -------------------------------------------------------------- the page

    public function test_the_billing_faq_answers_from_the_product(): void
    {
        $html = $this->pricingPage();

        foreach ([
            'How is Clarix billed?',
            'How do I move between plans?',
            'What happens to my credits if I change plan?',
            'What happens if a payment is late?',
        ] as $question) {
            $this->assertStringContainsString(e($question), $html);
        }

        // The two answers most easily got wrong, both checked against the
        // code when they were written: past_due is a grace period rather than
        // a lockout, and credits are not a purchased balance.
        $this->assertStringContainsString('stays fully usable', $html);
        $this->assertStringContainsString('there is no balance to reset', $html);
    }

    public function test_the_billing_toggle_is_the_one_the_site_already_uses(): void
    {
        $html = $this->pricingPage();

        $this->assertStringContainsString("x-data=\"{ billing: 'monthly' }\"", $html);
        $this->assertStringContainsString('aria-label="Billing period"', $html);
        $this->assertStringContainsString('Save 20%', $html);

        // A plan with no annual figure ignores the toggle rather than showing
        // the same number under both labels.
        $this->assertSame(
            count(array_filter(Pricing::plans(), fn (array $p) => isset($p['priceAnnual']))),
            substr_count($html, "x-show=\"billing === 'annual'\" x-cloak\n                                          class=\"rounded-full bg-[#EEF0FF]"),
        );
    }

    /**
     * The same visual-consistency bar the other marketing pages are held to.
     */
    public function test_the_page_uses_the_shared_visual_language(): void
    {
        $html = $this->pricingPage();

        $this->assertStringContainsString('font-display', $html);
        $this->assertStringContainsString('font-mono-ui', $html);
        $this->assertStringContainsString('bg-[#FF5F57]', $html, 'no window-chrome mockup');
        $this->assertStringContainsString('site-footer', $html);
        $this->assertStringContainsString('aria-label="Main"', $html);

        // The paper card, not a new one invented for this page.
        $this->assertStringContainsString('border-[#E9E4D8]', $html);
    }

    public function test_the_page_body_has_no_dead_links(): void
    {
        $html = $this->pricingPage();
        $start = strpos($html, '</header>');
        $end = strpos($html, 'site-footer', $start);

        $this->assertStringNotContainsString('href="#"', substr($html, $start, $end - $start));
    }
}
