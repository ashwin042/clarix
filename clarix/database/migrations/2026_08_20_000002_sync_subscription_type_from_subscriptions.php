<?php

use App\Services\PlanFeatures;
use Illuminate\Database\Migrations\Migration;

/**
 * One-time correction of the legacy plan label.
 *
 * organizations.subscription_type and organization_subscriptions.plan were
 * edited from two different superadmin screens and had already diverged: in
 * the production copy one agency read 'base' while paying for 'standard'. The
 * subscription is now the only source of truth, and Organization Detail
 * mirrors it onto the column on every save, so this brings the existing rows
 * into line and nothing reintroduces the drift afterwards.
 *
 * An organization with no subscription is labelled with the fallback plan,
 * because nothing has been bought for it.
 *
 * There is deliberately no down(): the previous values were wrong, and
 * restoring them would be restoring the bug.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PlanFeatures::class)->syncLegacyPlanColumn();
    }

    public function down(): void
    {
        //
    }
};
