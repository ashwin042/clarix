<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The per-user half of the Hermes Telegram link.
 *
 * Four columns rather than a table, because there is exactly one live code and
 * one linked account per user and no history worth keeping: consuming a code
 * *is* nulling the hash, which is what makes single use a property of the
 * schema rather than of a flag somebody has to remember to check.
 *
 * Only the sha256 of the code is stored, the way personal_access_tokens stores
 * its token. The plaintext exists for as long as it takes to render the card
 * and never afterwards, so a database leak yields nothing anybody can link
 * with. The consequence is deliberate: reopening the card mints a fresh code,
 * because the old one cannot be read back.
 *
 * telegram_chat_id is a *big* integer. Telegram documents ids of up to 52
 * significant bits, so a 32-bit column silently truncates or rejects newer
 * accounts — the standard way this integration fails long after release. Its
 * unique index is platform-wide rather than per-organization on purpose:
 * Telegram accounts are global, so one Telegram user is one Clarix user, and
 * MySQL permits the many nulls that leaves behind.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->char('telegram_link_code_hash', 64)->nullable()->unique()->after('unit_id');
            $table->timestamp('telegram_link_code_expires_at')->nullable()->after('telegram_link_code_hash');
            $table->unsignedBigInteger('telegram_chat_id')->nullable()->unique()->after('telegram_link_code_expires_at');
            $table->timestamp('telegram_linked_at')->nullable()->after('telegram_chat_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The indexes go first: MySQL refuses to drop a column that a
            // unique index still covers, so a down() that only dropped columns
            // would be a migration that cannot be undone in production.
            $table->dropUnique(['telegram_link_code_hash']);
            $table->dropUnique(['telegram_chat_id']);
            $table->dropColumn([
                'telegram_link_code_hash',
                'telegram_link_code_expires_at',
                'telegram_chat_id',
                'telegram_linked_at',
            ]);
        });
    }
};
