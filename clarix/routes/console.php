<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Correct the tracked per-unit R2 totals against the bucket overnight, when a
// full listing is cheapest. withoutOverlapping stops a long run from being
// started again on top of itself.
Schedule::command('storage:reconcile')
    ->dailyAt('03:00')
    ->withoutOverlapping();

// Walk subscriptions through the lifecycle: lapsed ones to past_due, and
// those out of grace to suspended. Runs early, before anyone is likely to be
// working, so an organization that loses access finds out at the start of the
// day rather than mid-task. The transitions are date-driven, so a missed run
// costs nothing — the next one lands on the same rows.
Schedule::command('subscriptions:enforce')
    ->dailyAt('02:00')
    ->withoutOverlapping();

// Clear out task bot idempotency keys past their window. Nothing depends on
// this landing — claim() drops an expired row before taking the key, so a key
// is reusable on time either way — so a missed night costs storage and nothing
// else.
Schedule::command('n8n:prune-idempotency-keys')
    ->dailyAt('04:00')
    ->withoutOverlapping();
