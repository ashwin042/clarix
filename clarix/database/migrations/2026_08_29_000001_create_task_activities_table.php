<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The per-task activity log.
 *
 * organization_id is carried directly rather than reached through the task,
 * matching every other tenant-owned table here: the global scope filters on
 * the column, and a join to get there would defeat it.
 *
 * Two columns exist for the actor. user_id is the account, nullable because
 * console commands, queued jobs and seeders act with nobody signed in and a
 * row from one of those is still worth keeping. actor_role is that account's
 * role *at the time*, recorded rather than resolved: writer identity masking
 * decides on it, and reading it live would unmask a writer the moment they
 * were promoted, or crash on one who had since been deleted.
 *
 * details holds the shape of the event — which field, what it went from and
 * to, a filename — rather than a rendered sentence. The sentence is built at
 * display time so masking can be applied to it there, and so wording can be
 * changed without rewriting history.
 *
 * The user foreign key nulls rather than cascades: deleting somebody should
 * anonymise what they did, not erase that it happened.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_activities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('actor_role', 32)->nullable();
            $table->string('event', 40);
            $table->json('details')->nullable();

            $table->timestamps();

            // The only read this table has: one task's entries, newest first.
            $table->index(['task_id', 'id']);
            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_activities');
    }
};
