<?php

namespace Tests\Feature\Marketing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The marketing pages behind the nav dropdowns.
 *
 * Each page has to load, carry its own title, and deliver on the promise its
 * dropdown entry makes — the headline is checked against that promise so a
 * page cannot quietly drift into generic copy.
 */
class MarketingPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * route name => [page title, a phrase the hero must carry]
     */
    public static function pages(): array
    {
        return [
            'task boards'     => ['marketing.product.boards',  'Task Boards',      'Plan and track work in one place.'],
            'client portal'   => ['marketing.product.portal',  'Client Portal',    'Give clients a live view of delivery.'],
            'ai automation'   => ['marketing.product.ai',      'AI Automation',    'Let AI handle the busywork.'],
            'file management' => ['marketing.product.files',   'File Management',  'Keep every file attached to its task.'],
            'credits'         => ['marketing.product.credits', 'Credits &amp; Billing', 'Track spend as work moves.'],

            'agencies'        => ['marketing.solutions.agencies',    'For Agencies',    'Manage multiple clients and teams.'],
            'freelancers'     => ['marketing.solutions.freelancers', 'For Freelancers', 'Simplify solo project tracking.'],
            'enterprises'     => ['marketing.solutions.enterprises', 'For Enterprises', 'Scale delivery across departments.'],

            // Resources carries no dropdown description, so the phrase each
            // page is held to is its own hero headline.
            'pricing'         => ['marketing.pricing',   'Pricing',          'Priced by the agency, not by the head.'],
            'blog'            => ['marketing.blog',      'Tech News',        'What the industry is reading today.'],
            'docs'            => ['marketing.docs',      'Documentation',    'Everything Clarix does, written down.'],
            'help'            => ['marketing.help',      'Help Center',      'Answers, and a way to reach a person.'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_the_page_loads(string $route, string $title, string $promise): void
    {
        $this->get(route($route))->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_the_page_carries_its_own_title(string $route, string $title, string $promise): void
    {
        $this->get(route($route))
            ->assertSee('<title>', false)
            ->assertSee($title, false)
            ->assertSee('— Clarix</title>', false);
    }

    /**
     * The hero has to say what the dropdown said it would. This is the check
     * that stops a page becoming interchangeable filler.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_the_hero_delivers_the_dropdown_promise(string $route, string $title, string $promise): void
    {
        $this->get(route($route))->assertSee($promise, false);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_the_page_renders_the_shared_chrome(string $route, string $title, string $promise): void
    {
        $this->get(route($route))
            ->assertSee('aria-label="Main"', false)      // header
            ->assertSee('site-footer', false)            // footer + CTA band
            ->assertSee('Code Next Door', false);
    }

    /**
     * Every page ends on the same conversion block.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_the_page_ends_with_a_call_to_action(string $route, string $title, string $promise): void
    {
        $this->get(route($route))
            ->assertSee('Get started', false)
            ->assertSee('/home#schedule-demo', false);
    }

    public function test_every_page_has_a_distinct_title(): void
    {
        $titles = [];

        foreach (self::pages() as [$route, $title, $promise]) {
            preg_match('#<title>(.*?)</title>#', $this->get(route($route))->getContent(), $m);
            $titles[] = $m[1] ?? '';
        }

        $this->assertSame(
            $titles,
            array_unique($titles),
            'Two marketing pages share a title: ' . implode(' | ', $titles)
        );
    }

    /**
     * The type roles are the shell's, and every page uses them. A page that
     * stops doing so has drifted away from the homepage's look.
     */
    public function test_the_pages_use_the_shared_type_roles(): void
    {
        foreach (self::pages() as [$route, $title, $promise]) {
            $html = $this->get(route($route))->getContent();

            $this->assertStringContainsString('font-display', $html, "{$route} lost the display face");
            $this->assertStringContainsString('font-mono-ui', $html, "{$route} lost the mono detail type");
        }
    }

    /**
     * The window chrome is the site's way of showing a product screen, so
     * every page that describes one draws it inside a window rather than as a
     * bare card.
     *
     * The marker is the frame's red traffic light, not the .win-shadow class:
     * the shadow is declared in the layout's stylesheet, so it is present in
     * the source of every page whether or not that page draws a window.
     *
     * The blog is the single exception, and deliberately: it has no product
     * screen to show, and framing a list of unwritten posts as an application
     * mockup would be dressing up an empty page.
     */
    public function test_the_pages_that_show_a_screen_use_the_window_chrome(): void
    {
        $trafficLight = 'bg-[#FF5F57]';

        foreach (self::pages() as [$route, $title, $promise]) {
            $html = $this->get(route($route))->getContent();

            if ($route === 'marketing.blog') {
                $this->assertStringNotContainsString(
                    $trafficLight,
                    $html,
                    'the blog gained a mockup — is it showing a real screen now?'
                );

                continue;
            }

            $this->assertStringContainsString(
                $trafficLight,
                $html,
                "{$route} has no window-chrome mockup"
            );
        }
    }
}
