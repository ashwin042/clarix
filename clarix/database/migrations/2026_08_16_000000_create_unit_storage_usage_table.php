<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_storage_usage', function (Blueprint $table) {
            $table->id();
            // One rollup row per unit, so the foreign key is unique rather than
            // a plain index — it stops a second row from ever being created
            // for a unit and doubles as the lookup index.
            $table->foreignId('unit_id')->unique()->constrained('units')->cascadeOnDelete();
            $table->unsignedBigInteger('bytes_used')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_storage_usage');
    }
};
