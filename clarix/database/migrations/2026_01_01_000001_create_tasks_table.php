<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('task_code');
            $table->longText('description')->nullable();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->enum('status', ['pending', 'in_progress', 'submitted', 'verified', 'completed'])->default('pending');
            $table->date('deadline');
            $table->timestamps();

            $table->unique(['unit_id', 'task_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
