<?php

namespace Tests\Feature\TaskBot;

use App\Models\N8nTelegramLink;
use App\Models\User;
use App\Services\N8nTelegramLinkService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

class N8nTelegramLinkSchemaTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->org = $this->populate($this->makeOrganization('tb-a', 'Agency A'), 'A');
    }

    public function test_the_table_and_its_columns_exist(): void
    {
        $this->assertTrue(Schema::hasTable('n8n_telegram_links'));

        foreach ([
            'id',
            'user_id',
            'chat_id',
            'link_code_hash',
            'code_expires_at',
            'is_active',
            'linked_at',
            'created_at',
            'updated_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('n8n_telegram_links', $column),
                "n8n_telegram_links.{$column} is missing"
            );
        }
    }

    /**
     * The flagged decision, asserted rather than described.
     *
     * organization_id and unit_id are derived from the linked user at request
     * time. Adding either column here would make them a snapshot, and a
     * snapshot goes stale the first time somebody changes unit — so the absence
     * is load-bearing and a future migration that "helpfully" adds one should
     * fail this test and have to argue with the comment in the migration.
     */
    public function test_the_table_stores_no_tenant_snapshot(): void
    {
        $this->assertFalse(
            Schema::hasColumn('n8n_telegram_links', 'organization_id'),
            'organization_id must be derived from the user, not cached here.'
        );

        $this->assertFalse(
            Schema::hasColumn('n8n_telegram_links', 'unit_id'),
            'unit_id must be derived from the user, not cached here.'
        );
    }

    /** The plaintext code must have nowhere to live. */
    public function test_the_table_stores_a_hash_rather_than_the_code(): void
    {
        $this->assertFalse(
            Schema::hasColumn('n8n_telegram_links', 'link_code'),
            'Only the hash of a code may be stored.'
        );

        $this->assertTrue(Schema::hasColumn('n8n_telegram_links', 'link_code_hash'));
    }

    public function test_a_fresh_user_has_no_link(): void
    {
        $this->assertNull(app(N8nTelegramLinkService::class)->linkFor($this->org['pm']));
    }

    /**
     * A row exists from the moment a code is minted, and is_active defaults to
     * true — so "connected" cannot be read off that column alone.
     */
    public function test_a_minted_code_alone_is_not_a_live_link(): void
    {
        $service = app(N8nTelegramLinkService::class);
        $service->issueCode($this->org['pm']);

        $link = $service->linkFor($this->org['pm']);

        $this->assertTrue($link->is_active);
        $this->assertNull($link->chat_id);
        $this->assertFalse($link->isLive());
    }

    public function test_a_verified_link_is_live(): void
    {
        $service = app(N8nTelegramLinkService::class);
        $service->verify($service->issueCode($this->org['pm']), '5150');

        $this->assertTrue($service->linkFor($this->org['pm'])->isLive());
    }

    /**
     * Telegram ids for groups and channels are negative, and user ids already
     * run past 32 bits. A string column sidesteps both; this proves it round
     * trips rather than being coerced somewhere along the way.
     */
    public function test_long_and_negative_chat_ids_round_trip_intact(): void
    {
        $orgB    = $this->populate($this->makeOrganization('tb-b', 'Agency B'), 'B');
        $service = app(N8nTelegramLinkService::class);

        $service->verify($service->issueCode($this->org['pm']), '7654321012345');
        $service->verify($service->issueCode($orgB['pm']), '-1001234567890');

        $this->assertSame('7654321012345', $service->linkFor($this->org['pm'])->chat_id);
        $this->assertSame('-1001234567890', $service->linkFor($orgB['pm'])->chat_id);
    }

    /** Two users cannot hold the same Telegram account. */
    public function test_chat_id_is_unique_across_the_platform(): void
    {
        $orgB = $this->populate($this->makeOrganization('tb-c', 'Agency C'), 'C');

        N8nTelegramLink::query()->insert([
            'user_id'    => $this->org['pm']->id,
            'chat_id'    => '555',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        N8nTelegramLink::query()->insert([
            'user_id'    => $orgB['pm']->id,
            'chat_id'    => '555',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** One live code and one linked chat per person, enforced by the schema. */
    public function test_a_user_can_hold_only_one_link_row(): void
    {
        N8nTelegramLink::query()->insert([
            'user_id'    => $this->org['pm']->id,
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        N8nTelegramLink::query()->insert([
            'user_id'    => $this->org['pm']->id,
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Deleting a person takes their link with them. */
    public function test_the_link_is_removed_with_its_user(): void
    {
        $service = app(N8nTelegramLinkService::class);
        $user    = $this->org['writer'];

        $service->verify($service->issueCode($user), '6006');
        $this->assertNotNull($service->linkFor($user));

        // Unscoped, because TestCase pins the ambient organization to the first
        // one in the database and these fixtures are in a later one — a scoped
        // delete would touch no rows and the cascade would look broken.
        $deleted = TenantContext::runWithoutScope(
            fn () => User::query()->whereKey($user->id)->delete()
        );

        $this->assertSame(1, $deleted, 'The fixture user was not deleted, so nothing was cascaded.');
        $this->assertSame(0, N8nTelegramLink::query()->where('user_id', $user->id)->count());
    }

    /** The hash must never reach a JSON payload. */
    public function test_the_code_hash_is_hidden_from_serialisation(): void
    {
        $service = app(N8nTelegramLinkService::class);
        $service->issueCode($this->org['pm']);

        $this->assertArrayNotHasKey(
            'link_code_hash',
            $service->linkFor($this->org['pm'])->toArray()
        );
    }

    /**
     * Nothing on this model may be set from request input. A fillable chat_id
     * would be a way to bind somebody else's Telegram account from a crafted
     * form field, exactly as a fillable organization_id would move a record
     * between agencies.
     */
    public function test_nothing_on_the_model_is_mass_assignable(): void
    {
        // An empty $fillable makes the model totally guarded, which Eloquent
        // treats as louder than "silently discard": fill() throws rather than
        // dropping the attribute. That is the better failure — a caller trying
        // to mass-assign a chat id finds out immediately instead of wondering
        // why their write vanished.
        $this->expectException(\Illuminate\Database\Eloquent\MassAssignmentException::class);

        (new N8nTelegramLink)->fill([
            'user_id'        => 999,
            'chat_id'        => '999',
            'is_active'      => true,
            'link_code_hash' => str_repeat('a', 64),
        ]);
    }

    /** Whichever attribute is offered, nothing lands on the model. */
    public function test_no_individual_attribute_can_be_mass_assigned(): void
    {
        foreach (['user_id', 'chat_id', 'is_active', 'link_code_hash', 'linked_at'] as $attribute) {
            try {
                (new N8nTelegramLink)->fill([$attribute => 'x']);
                $this->fail("{$attribute} is mass-assignable.");
            } catch (\Illuminate\Database\Eloquent\MassAssignmentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
