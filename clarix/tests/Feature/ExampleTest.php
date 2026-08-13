<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic smoke test: the app boots and the root URL answers.
     *
     * The root is a redirect, not a page — guests go to the marketing
     * homepage, signed-in users to their dashboard. Where it points is
     * RootRedirectTest's business; this only checks the app responds.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertRedirect();
    }
}
