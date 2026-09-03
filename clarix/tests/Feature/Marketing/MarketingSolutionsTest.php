<?php

namespace Tests\Feature\Marketing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The three Solutions pages.
 *
 * They all draw the same picture — several things resolving into one
 * organisation — from one component, so what is asserted here is that the
 * shared diagram is actually shared, that each page fills it with its own
 * subject, and that the product facts each page leans on are the ones the
 * codebase has rather than invented ones.
 */
class MarketingSolutionsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string}> */
    public static function solutions(): array
    {
        return [
            'agencies'    => ['marketing.solutions.agencies'],
            'freelancers' => ['marketing.solutions.freelancers'],
            'enterprises' => ['marketing.solutions.enterprises'],
        ];
    }

    /**
     * Both halves of the diagram ship on every page: the arcs for md and up,
     * and the squared-off nesting that replaces them on a phone. Losing
     * either one leaves a breakpoint with no diagram at all.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('solutions')]
    public function test_the_page_draws_the_shared_org_diagram(string $route): void
    {
        $html = $this->get(route($route))->assertOk()->getContent();

        // The filled bands, pale then medium then the core gradient.
        $this->assertStringContainsString('#EDEAFB', $html, 'no pale band');
        $this->assertStringContainsString('#BEB6F2', $html, 'no medium band');
        $this->assertStringContainsString('id="org-core-', $html, 'no core fill');

        // The stacked fallback, which is the same nesting squared off.
        $this->assertStringContainsString('rounded-t-[28px] bg-[#EDEAFB]', $html, 'no stacked fallback');
    }

    /**
     * Every tile is placed on the arc by the component's own trigonometry. If
     * a page's nodes stop being positioned, the diagram has silently become a
     * pile of tiles in the top-left corner.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('solutions')]
    public function test_every_node_is_placed_on_the_arc(string $route): void
    {
        $html = $this->get(route($route))->getContent();

        preg_match_all('#style="left: [\d.]+%; bottom: calc\([\d.]+% - \d+px\)"#', $html, $placed);

        $this->assertNotEmpty($placed[0], "{$route} draws a diagram with no placed nodes");
    }

    /**
     * The three pages are the same diagram at three scales, so the thing that
     * distinguishes them is what sits in the core. Two pages sharing a core
     * label would mean one of them had been copied and not rewritten.
     */
    public function test_each_page_names_its_own_core(): void
    {
        $this->get(route('marketing.solutions.agencies'))->assertSee('One organisation', false);
        $this->get(route('marketing.solutions.freelancers'))->assertSee('Your studio', false);
        $this->get(route('marketing.solutions.enterprises'))->assertSee('Group operations', false);
    }

    /**
     * The agencies diagram is units — the object the product actually has —
     * and the page explains the roles that go with them.
     */
    public function test_the_agencies_page_is_about_units_and_roles(): void
    {
        $response = $this->get(route('marketing.solutions.agencies'));

        $response->assertSee('Units', false);

        // The roles as User defines them, minus superadmin, which is ours.
        foreach (['Admin', 'Project manager', 'Supervisor', 'Writer', 'HR'] as $role) {
            $response->assertSee($role, false);
        }

        $response->assertDontSee('Superadmin', false);
    }

    /**
     * The freelancer version is the same diagram collapsed: several clients,
     * and exactly one person above them.
     */
    public function test_the_freelancer_diagram_collapses_to_one_person(): void
    {
        $html = $this->get(route('marketing.solutions.freelancers'))->getContent();

        // Three clients on the outer band and a single node above them: four
        // placed tiles in the arc layout.
        preg_match_all('#style="left: [\d.]+%; bottom: calc\([\d.]+% - \d+px\)"#', $html, $placed);

        $this->assertCount(4, $placed[0], 'the solo diagram should carry three clients and one person');
        $this->assertStringContainsString('One board, one ledger, one invoice', $html);
    }

    /**
     * The enterprise page is the one that names the ERP surfaces, and those
     * are the three the product ships — not a wishlist.
     */
    public function test_the_enterprise_page_names_the_erp_surfaces(): void
    {
        $response = $this->get(route('marketing.solutions.enterprises'));

        foreach (['Attendance', 'Leave', 'Payroll'] as $surface) {
            $response->assertSee($surface, false);
        }

        // And the Enterprise plan's own promises, which come off the pricing
        // table rather than being written fresh here.
        foreach ([
            'Dedicated account manager',
            'Custom integrations &amp; API access',
            'SLA-backed uptime guarantee',
            'Onboarding &amp; migration support',
        ] as $feature) {
            $response->assertSee($feature, false);
        }
    }

    /**
     * A Solutions page's job is to hand the visitor on to the product page
     * that answers the question it raised, rather than being a cul-de-sac.
     */
    public function test_the_pages_lead_into_the_product_pages(): void
    {
        $this->get(route('marketing.solutions.agencies'))
            ->assertSee('href="' . route('marketing.product.portal') . '"', false)
            ->assertSee('href="' . route('marketing.product.credits') . '"', false)
            ->assertSee('href="' . route('marketing.product.files') . '"', false);

        $this->get(route('marketing.solutions.freelancers'))
            ->assertSee('href="' . route('marketing.product.boards') . '"', false);
    }
}
