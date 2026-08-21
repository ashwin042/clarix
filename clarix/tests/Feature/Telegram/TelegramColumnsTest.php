<?php

namespace Tests\Feature\Telegram;

use App\Models\User;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

class TelegramColumnsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->org = $this->populate($this->makeOrganization('tg-a', 'Agency A'), 'A');
    }

    public function test_the_columns_exist(): void
    {
        foreach ([
            'telegram_link_code_hash',
            'telegram_link_code_expires_at',
            'telegram_chat_id',
            'telegram_linked_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('users', $column),
                "users.{$column} is missing"
            );
        }
    }

    public function test_a_fresh_user_is_not_linked(): void
    {
        $this->assertFalse($this->org['pm']->hasLinkedTelegram());
    }

    public function test_a_user_with_a_chat_id_is_linked(): void
    {
        $user = $this->org['pm'];
        $user->forceFill(['telegram_chat_id' => 7654321012345])->save();

        $this->assertTrue($user->fresh()->hasLinkedTelegram());
    }

    /**
     * Telegram ids run past 32 bits. A signed int column would truncate or
     * reject this, which is the classic way this integration breaks months
     * after it ships.
     */
    public function test_a_large_chat_id_round_trips_intact(): void
    {
        $user   = $this->org['pm'];
        $chatId = 7654321012345;

        $user->forceFill(['telegram_chat_id' => $chatId])->save();

        $this->assertSame($chatId, $user->fresh()->telegram_chat_id);
    }

    /**
     * The link columns must not be mass-assignable, for the same reason
     * organization_id is not: a crafted form field must not be able to bind
     * somebody else's Telegram account.
     */
    public function test_the_link_columns_are_not_mass_assignable(): void
    {
        $user = $this->org['pm'];

        $user->fill([
            'telegram_chat_id'        => 999,
            'telegram_link_code_hash' => str_repeat('a', 64),
        ]);

        $this->assertNull($user->telegram_chat_id);
        $this->assertNull($user->telegram_link_code_hash);
    }

    /** The hash must never reach a JSON payload. */
    public function test_the_code_hash_is_hidden_from_serialisation(): void
    {
        $user = $this->org['pm'];
        $user->forceFill(['telegram_link_code_hash' => str_repeat('a', 64)])->save();

        $this->assertArrayNotHasKey('telegram_link_code_hash', $user->fresh()->toArray());
    }

    /** Two users cannot hold the same Telegram account. */
    public function test_chat_id_is_unique_across_the_platform(): void
    {
        $other = $this->populate($this->makeOrganization('tg-b', 'Agency B'), 'B');

        $this->org['pm']->forceFill(['telegram_chat_id' => 555])->save();

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        TenantContext::runWithoutScope(function () use ($other) {
            $other['pm']->forceFill(['telegram_chat_id' => 555])->save();
        });
    }
}
