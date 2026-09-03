<?php

namespace Tests\Feature\Marketing;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The marketing homepage after its move onto <x-marketing.layout>.
 *
 * The page used to be one standalone 2,744-line document carrying its own
 * head, header, footer and every rule of CSS. Splitting it means a whole
 * class of breakage — a lost section, an unclosed component, CSS that went
 * to the wrong half — renders as a 500 or as a silently missing block, so
 * these assertions walk the page top to bottom rather than just checking 200.
 */
class MarketingHomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_homepage_renders(): void
    {
        $this->get(route('home'))->assertOk();
    }

    public function test_it_still_carries_its_hero_copy(): void
    {
        $this->get(route('home'))
            ->assertSee('comes clean delivery.', false)
            ->assertSee('Clarix is an AI-powered platform for agency work', false);
    }

    public function test_it_renders_the_shared_header_and_footer(): void
    {
        $response = $this->get(route('home'));

        // Header: the three dropdown triggers.
        $response->assertSee('Product', false)
            ->assertSee('Solutions', false)
            ->assertSee('Resources', false);

        // Footer: the gradient hook and the sign-off, which is a link
        // out to Code Next Door rather than plain text.
        $response->assertSee('site-footer', false)
            ->assertSee('Code Next Door', false)
            ->assertSee('https://codesnextdoor.com/', false);
    }

    /**
     * The shared half of the CSS moved into the layout; the homepage's own
     * animation loops stayed behind on the styles stack. Both have to arrive.
     */
    public function test_it_carries_both_halves_of_the_split_css(): void
    {
        $response = $this->get(route('home'));

        // Shared, from the layout.
        $response->assertSee('.font-display', false)
            ->assertSee('.win-shadow', false)
            ->assertSee('.site-footer', false);

        // Page-local, from @push('styles').
        $response->assertSee('.t-type', false)          // terminal typing
            ->assertSee('select.demo-field', false);    // demo form select
    }

    /**
     * Every section that survived the migration, in document order. A range
     * dropped by an off-by-one in the rebuild would take one of these with it.
     */
    public function test_it_still_renders_every_section(): void
    {
        $response = $this->get(route('home'));

        foreach (['product', 'pricing', 'compare', 'why', 'schedule-demo'] as $id) {
            $response->assertSee('id="' . $id . '"', false);
        }
    }

    /**
     * The demo anchor is the homepage's own; the pricing anchor only ever came
     * from the nav, and now arrives through the shared header as an absolute
     * path so the same markup works on pages that are not this one.
     */
    public function test_the_in_page_anchors_still_exist(): void
    {
        // The pricing section keeps its id so anyone who scrolls to it, or
        // follows an older /home#pricing link, still lands in the right place
        // — but the nav's Pricing link is a page of its own now, so the
        // homepage no longer has to carry a '/home#pricing' href anywhere.
        $this->get(route('home'))
            ->assertSee('href="#schedule-demo"', false)
            ->assertSee('id="pricing"', false)
            ->assertSee('id="schedule-demo"', false);
    }

    /**
     * The header branches on auth: a guest is sold to, a signed-in visitor is
     * shown the way back into the app instead.
     */
    public function test_a_guest_sees_the_sales_calls_to_action(): void
    {
        $this->get(route('home'))
            ->assertSee('Log in', false)
            ->assertDontSee('>Dashboard<', false);
    }

    public function test_a_signed_in_visitor_is_offered_their_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Dashboard', false);
    }
}
