<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client-supplied idempotency keys for the task bot's write endpoints.
 *
 * Exists because of a gap the create endpoint does not have. A replayed create
 * is answered by the schema — task_code is unique per unit, so the second
 * attempt is a 422 rather than a second task. Attaching a file has no such
 * natural key: the same bytes posted twice are two perfectly valid attachments,
 * and a static shared key leaves a captured request replayable indefinitely
 * (see EnsureN8nRequest for why that trade was taken). So the caller supplies
 * the key instead, one per submission, and this table remembers it.
 *
 * The stored response is the point, not merely the lock. An n8n retry usually
 * happens because the first call timed out, not because it failed — the work
 * may well have completed. Answering the retry with the original 201 and the
 * original body is what lets the workflow carry on as though nothing happened;
 * answering 409 would be correct and useless.
 *
 * Scoped by user, and that is a confidentiality requirement rather than
 * tidiness. The stored body names task and file ids. If keys shared one
 * namespace, agency B replaying a key that agency A happened to choose would be
 * handed agency A's response — a cross-tenant leak through a cache. The unique
 * index is (user_id, scope, key) so a collision is only ever a person colliding
 * with themselves.
 *
 * 'scope' names the operation. Only one uses this today, but a key minted for
 * an attach must never satisfy a create — the caller would receive a response
 * of the wrong shape for an operation that never ran — and a column is cheaper
 * than discovering that later.
 *
 * response_status null means in flight: the row was claimed and the work has
 * not finished. That is what makes the insert itself the lock, with no separate
 * status column to get out of step with reality.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('n8n_idempotency_keys', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('scope', 64);

            // 128 rather than 255: long enough for a uuid, a ulid or n8n's
            // execution id with room to spare, and short enough that the
            // composite unique index stays comfortably inside InnoDB's limit
            // once utf8mb4 has multiplied every column by four.
            $table->string('key', 128);

            /*
             * What the key was used for, as a sha256 of the request's shape.
             *
             * This is the half that protects against the dangerous mistake
             * rather than the merely wasteful one. A workflow that reuses a key
             * across two different submissions would otherwise be handed the
             * first submission's response for the second file, silently, and
             * the second file would never be stored — a lost attachment that
             * looks like a success in every log. Comparing fingerprints turns
             * that into a refusal the workflow can see.
             *
             * Nothing the server can check makes a bad key good, so this is the
             * safeguard rather than a length rule pretending to measure
             * entropy.
             */
            $table->char('request_fingerprint', 64);

            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_body')->nullable();

            /*
             * Read on every replay, so it is stored rather than derived from
             * created_at plus a config value that may have changed since.
             *
             * dateTime, not timestamp, and the difference is not cosmetic.
             * MySQL gives the first NOT NULL TIMESTAMP column in a table an
             * implicit "DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
             * so declaring this as a timestamp meant every complete() — an
             * UPDATE on this row — silently reset the expiry to now. The row
             * would then read as expired, holder() would ignore it, and the
             * replay it exists to catch would sail through and attach the file
             * twice.
             *
             * sqlite has no such rule, so the whole test suite passed while the
             * mechanism was inert in production. Caught by running the
             * migration against a real MySQL clone and reading the DDL back.
             */
            $table->dateTime('expires_at');

            $table->timestamps();

            $table->unique(['user_id', 'scope', 'key']);

            // The prune command's only query.
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('n8n_idempotency_keys');
    }
};
