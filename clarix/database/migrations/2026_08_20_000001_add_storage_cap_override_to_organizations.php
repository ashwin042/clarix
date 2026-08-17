<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A storage allowance set by hand for one organization.
 *
 * The Pro tier sells extra storage at Rs 1000 per additional 100 GB, arranged
 * by conversation rather than through the product. Rather than model a
 * purchase flow for something that happens a handful of times a year, the
 * superadmin types the agreed number here and it wins over the plan default.
 *
 * Nullable, and null is meaningfully different from zero: null means "use the
 * plan", zero would mean "no allowance at all". Anything reading this column
 * must check for null rather than for falsiness.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->unsignedInteger('storage_cap_override_gb')->nullable()->after('subscription_type');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('storage_cap_override_gb');
        });
    }
};
