<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            // Null means "no unit-specific cap", in which case the plan default
            // from config/storage.php applies.
            $table->unsignedInteger('storage_cap_gb')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('storage_cap_gb');
        });
    }
};
