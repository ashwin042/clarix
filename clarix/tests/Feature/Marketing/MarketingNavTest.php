<?php

namespace Tests\Feature\Marketing;

use App\Support\MarketingNav;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The marketing navigation, which now has exactly one definition in
 * config/marketing.php instead of two @php arrays inside home.blade.php.
 *
 * The point of these is that adding a menu item without building its page —
 * or naming a route that does not exist — fails here rather than shipping as
 * a dead link.
 */
class MarketingNavTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_item_declares_a_destination(): void
    {
        foreach (MarketingNav::allItems() as $entry) {
            ['source' => $source, 'item' => $item] = $entry;

            $this->assertTrue(
                isset($item['route']) || isset($item['url']),
                "{$source}: '{$item['label']}' has neither a route nor a url"
            );
        }
    }

    /**
     * Route-backed items, by source label. Empty until the first page batch
     * lands, which is why the tests below assert the shape of the set as well
     * as its contents — an empty loop must not read as a pass.
     *
     * @return array<int, array{source: string, item: array}>
     */
    private function routeBackedItems(): array
    {
        return array_values(array_filter(
            MarketingNav::allItems(),
            fn (array $entry) => isset($entry['item']['route'])
        ));
    }

    public function test_every_route_backed_item_names_a_registered_route(): void
    {
        $items = $this->routeBackedItems();

        $this->assertIsArray($items);

        foreach ($items as ['source' => $source, 'item' => $item]) {
            $this->assertTrue(
                Route::has($item['route']),
                "{$source}: '{$item['label']}' points at unregistered route '{$item['route']}'"
            );
        }
    }

    public function test_every_route_backed_item_resolves_to_a_page_that_loads(): void
    {
        $items = $this->routeBackedItems();

        $this->assertIsArray($items);

        foreach ($items as ['source' => $source, 'item' => $item]) {
            $this->get(route($item['route']))->assertOk(
                "{$source}: '{$item['label']}' does not load"
            );
        }
    }

    /**
     * The dropdown destinations were the deliverable, and all of them now have
     * a page. The assertion is "none left": adding a menu item without
     * building its page fails here rather than shipping as a dead link.
     *
     * Footer-only entries — Changelog, Alternatives, Community, Legal, Privacy
     * Policy, Security & Compliance, Careers, Status — are deliberately not
     * counted. No page was ever asked for, so they stay '#'.
     */
    public function test_only_the_unbuilt_dropdown_destinations_are_still_pending(): void
    {
        $pending = [];

        foreach (MarketingNav::menus() as $label => $menu) {
            foreach ($menu['items'] as $item) {
                if (MarketingNav::isPending($item)) {
                    $pending[] = "{$label}: {$item['label']}";
                }
            }
        }

        // Batch 3 was the last one: Product, Solutions and Resources have
        // all shipped, so nothing in any dropdown points at nothing. A new
        // menu item added without a page to go with it fails here.
        $this->assertSame(
            [],
            $pending,
            'Unbuilt dropdown destinations: ' . implode(', ', $pending)
        );
    }

    public function test_the_desktop_menu_renders_every_item(): void
    {
        $response = $this->get(route('home'));

        foreach (MarketingNav::menus() as $label => $menu) {
            $response->assertSee($label, false);

            foreach ($menu['items'] as $item) {
                $response->assertSee(e($item['label']), false);
                $response->assertSee('href="' . e(MarketingNav::href($item)) . '"', false);
            }
        }
    }

    public function test_the_mobile_panel_renders_every_item(): void
    {
        $html = $this->get(route('home'))->getContent();

        // The flattened panel is the markup after the desktop bar closes.
        $panel = substr($html, strpos($html, 'aria-label="Main, mobile"'));

        foreach (MarketingNav::menus() as $menu) {
            foreach ($menu['items'] as $item) {
                $this->assertStringContainsString(e($item['label']), $panel);
            }
        }
    }

    /**
     * The AXOKAI menu entry used to leave the site. It now goes to our own
     * page for it, and that page carries the outbound link instead — so the
     * visitor reaches AXOKAI through Clarix rather than skipping past it.
     */
    public function test_the_axokai_menu_item_leads_to_our_own_page(): void
    {
        $axokai = collect(config('marketing.menus.Product.items'))
            ->firstWhere('label', 'AI Automation (AXOKAI)');

        $this->assertFalse(MarketingNav::isExternal($axokai));
        $this->assertSame('marketing.product.ai', $axokai['route']);

        $this->get(route('marketing.product.ai'))
            ->assertOk()
            ->assertSee('axokai.codesnextdoor.com', false)
            ->assertSee('rel="noopener noreferrer"', false);
    }

    /**
     * The footer's sign-off is a link out to the company that built Clarix,
     * not plain text — and it follows the same convention as every other
     * off-site link on the site: a new tab, and rel set so the opened page
     * cannot reach back through window.opener.
     */
    public function test_the_footer_credits_code_next_door_with_a_real_link(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<a\s+href="https://codesnextdoor\.com/"[^>]*target="_blank"[^>]*rel="noopener noreferrer"[^>]*>\s*Code Next Door\s*</a>#',
            $html,
            'the Code Next Door credit is not an external link opening in a new tab',
        );
    }

    /**
     * And it is in the shared footer, so it is on every marketing page rather
     * than only the homepage.
     */
    public function test_the_code_next_door_link_is_on_every_page(): void
    {
        foreach (['home', 'marketing.pricing', 'marketing.blog', 'marketing.product.boards'] as $route) {
            $this->get(route($route))
                ->assertSee('https://codesnextdoor.com/', false)
                ->assertSee('Code Next Door', false);
        }
    }

    public function test_internal_items_are_never_marked_external(): void
    {
        $items = $this->routeBackedItems();

        $this->assertIsArray($items);

        foreach ($items as ['source' => $source, 'item' => $item]) {
            $this->assertFalse(
                MarketingNav::isExternal($item),
                "{$source}: '{$item['label']}' is route-backed but reads as external"
            );
        }
    }

    /**
     * Pricing has its own page now. The bar's link goes there rather than to
     * an anchor on the homepage — which is the whole point of building it, so
     * a regression to '#pricing' should fail loudly.
     */
    public function test_the_pricing_link_goes_to_the_pricing_page(): void
    {
        $pricing = collect(config('marketing.links'))->firstWhere('label', 'Pricing');

        $this->assertSame('marketing.pricing', $pricing['route'] ?? null);
        $this->assertArrayNotHasKey('url', $pricing, 'the Pricing link still carries a literal url');

        $this->get(route('home'))
            ->assertSee('href="' . route('marketing.pricing') . '"', false);
    }

    /**
     * "Book a demo" is still an anchor into the homepage, and still renders on
     * pages that are not the homepage — so it has to carry the path.
     */
    public function test_the_demo_anchor_is_absolute(): void
    {
        $this->get(route('home'))->assertSee('/home#schedule-demo', false);
        $this->get(route('marketing.pricing'))->assertSee('/home#schedule-demo', false);
    }
}
