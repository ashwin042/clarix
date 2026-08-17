<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage Cap Per Plan
    |--------------------------------------------------------------------------
    |
    | The R2 allowance, in gigabytes, that each subscription tier carries. The
    | cap belongs to the organization as a whole: every unit inside it draws on
    | one shared allowance rather than each holding a separate one.
    |
    | Kept in config rather than a database column so changing what a tier is
    | worth is a deploy, not a migration, and so the commercial figures are
    | visible in one place.
    |
    */

    'plan_caps_gb' => [
        'base'     => (int) env('STORAGE_CAP_BASE_GB', 5),
        'standard' => (int) env('STORAGE_CAP_STANDARD_GB', 50),
        'pro'      => (int) env('STORAGE_CAP_PRO_GB', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Storage Cap
    |--------------------------------------------------------------------------
    |
    | Applied to an organization with no subscription record yet, and to any
    | plan name not listed above. Deliberately the smallest tier: an agency
    | whose plan cannot be determined should be treated as the least generous
    | case rather than handed the most generous one.
    |
    | This must never exceed the cheapest plan's cap. Its whole purpose is to
    | be the least generous answer for an organization whose plan cannot be
    | determined; set above Base it would quietly become the *most* generous,
    | and an agency with a broken subscription row would be rewarded for it.
    | It moved 10 -> 5 when Base did, for exactly that reason.
    |
    | Note: units.storage_cap_gb is no longer read. The per-unit override was
    | replaced by the per-plan organization cap; the column is left in place so
    | this change stays reversible.
    |
    | A single organization can be given a different allowance by hand through
    | organizations.storage_cap_override_gb, which beats both of these.
    |
    */

    'default_cap_gb' => (int) env('STORAGE_DEFAULT_CAP_GB', 5),

    /*
    |--------------------------------------------------------------------------
    | Reconciliation
    |--------------------------------------------------------------------------
    |
    | How many R2 object keys the nightly reconcile command resolves back to
    | units per database query. Keep it under the driver's bound-parameter
    | limit; SQLite caps out around 999.
    |
    */

    'reconcile_chunk' => (int) env('STORAGE_RECONCILE_CHUNK', 500),

];
