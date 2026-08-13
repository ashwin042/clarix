<?php

namespace Tests\Feature\AI;

use App\Livewire\AI\Chatbot;
use App\Livewire\AI\Overview;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_it_renders_for_a_writer(): void
    {
        Livewire::actingAs(User::factory()->create(['role' => 'writer']))
            ->test(Overview::class)
            ->assertOk()
            ->assertSee('Clarix, powered by AXOKAI')
            ->assertSee('Messages Remaining')
            ->assertSee('Task Automation')
            ->assertSee('Olympus Max');
    }

    /**
     * The grouped list is written by hand, so it can fall out of step with the
     * picker. Every model the Chatbot offers has to be shown exactly once.
     */
    public function test_every_chatbot_model_appears_in_exactly_one_group(): void
    {
        $grouped = array_merge(...array_values(Overview::MODEL_GROUPS));

        $this->assertSame(
            Chatbot::MODELS,
            $grouped,
            'The Overview model groups no longer match Chatbot::MODELS.'
        );
        $this->assertSame($grouped, array_unique($grouped));
    }

    public function test_every_group_has_a_badge(): void
    {
        $this->assertSame(
            array_keys(Overview::MODEL_GROUPS),
            array_keys(Overview::GROUP_STYLE)
        );

        foreach (Overview::GROUP_STYLE as $group => $style) {
            $this->assertArrayHasKey('tint', $style, "{$group} has no tint.");
            $this->assertArrayHasKey('icon', $style, "{$group} has no icon.");
        }
    }
}
