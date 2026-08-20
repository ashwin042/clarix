<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Grace period
    |--------------------------------------------------------------------------
    |
    | Days an organization keeps working after its renewal date passes before
    | access is suspended. During the grace period nothing is blocked — the
    | status is tracked so the nightly job knows when the window closes, and
    | so the organization can be warned.
    |
    */

    'grace_days' => (int) env('SUBSCRIPTION_GRACE_DAYS', 3),

];
