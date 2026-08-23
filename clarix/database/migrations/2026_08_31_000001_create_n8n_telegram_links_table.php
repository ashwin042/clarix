<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The task-submission bot's account links.
 *
 * A table of its own rather than four more columns on users, which is where
 * this parts company with the AXOKAI link. That one is one person's assistant
 * and belongs on the person; this one serves a separate pipeline with a
 * separate bot token and a separate audience, and putting it on users would
 * mean two integrations sharing a row and every future bot adding four more
 * columns to the widest table in the schema.
 *
 * No organization_id and no unit_id, deliberately. Both are derivable from
 * user_id, and a stored copy is a copy that goes stale: somebody moved between
 * units keeps filing tasks against the unit they left, silently, until an
 * accountant notices the credit landed in the wrong place. Resolving live from
 * the user costs one join the pipeline was already paying for, and cannot be
 * wrong. This is the decision flagged for confirmation — see the service.
 *
 * link_code_hash, not link_code. Only the sha256 of the code is stored, the way
 * personal_access_tokens stores its token and the way the AXOKAI link stores
 * its own. Nothing server-side ever needs to read a code back: the bot receives
 * the plaintext from the person who was shown it and posts it here to be
 * matched. Storing it readable would buy nothing and put a live credential in
 * every database dump.
 *
 * chat_id is a string. Telegram's ids for groups and channels are negative and
 * its user ids already run past 32 bits, so a string sidesteps the whole
 * question of how many bits are enough — this table never does arithmetic on
 * it. Its unique index is platform-wide, not per-organization: Telegram
 * accounts are global, so one Telegram account is one Clarix user, and that
 * index is what makes cross-agency confusion impossible rather than merely
 * unlikely. It is nullable because a row exists from the moment a code is
 * minted, which is before any chat has presented itself; MySQL permits the many
 * nulls that leaves behind.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('n8n_telegram_links', function (Blueprint $table) {
            $table->id();

            // Unique: one live code and one linked chat per person. Issuing
            // again updates this row rather than accumulating history, so a
            // stale code cannot be resurrected by deleting a newer one.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('chat_id', 64)->nullable()->unique();

            $table->char('link_code_hash', 64)->nullable()->unique();
            $table->timestamp('code_expires_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('linked_at')->nullable();

            $table->timestamps();

            // No further indexes. The pipeline's only read is
            // "chat_id = ? and is_active = 1", and a (chat_id, is_active)
            // composite would look like it helps while helping nothing: chat_id
            // is already unique, so the unique index resolves that predicate to
            // at most one row and the second column has nothing left to narrow.
            // It would cost a write on every link and buy no read.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('n8n_telegram_links');
    }
};
