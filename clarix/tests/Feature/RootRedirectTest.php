<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The bare root URL is a signpost, not a page.
 *
 * It used to render a stub view whose only content was a meta-refresh to the
 * login screen. Guests now go to the marketing homepage instead; signed-in
 * users still go to their dashboard.
 */
class RootRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_sent_to_the_marketing_homepage(): void
    {
        $this->get('/')
            ->assertRedirect(route('home'))
            ->assertStatus(302);
    }

    public function test_a_guest_is_no_longer_sent_to_login(): void
    {
        $response = $this->get('/');

        $this->assertNotSame(route('login'), $response->headers->get('Location'));
    }

    public function test_a_signed_in_user_still_lands_on_their_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/')
            ->assertRedirect(route('dashboard'));
    }

    /** Following the redirect must actually reach the marketing page. */
    public function test_the_homepage_renders_at_the_end_of_the_redirect(): void
    {
        $this->get('/')
            ->assertRedirect(route('home'));

        $this->get('/home')
            ->assertOk()
            ->assertSee('AXOKAI', false);
    }

    /** The login screen stays reachable on its own. */
    public function test_login_is_still_directly_accessible(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign in', false)
            ->assertSee('Password', false);
    }
}
