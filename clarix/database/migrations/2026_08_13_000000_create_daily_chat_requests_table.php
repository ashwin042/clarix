<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user daily counter for Clarix AI chatbot messages.
 *
 * A table rather than the cache: CACHE_STORE is database here, so a
 * `cache:clear` during a deploy would hand every user a fresh allowance. One
 * row per user per day, and the unique index is what makes the atomic
 * increment in ChatQuota safe against two tabs sending at once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_chat_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('request_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_chat_requests');
    }
};
