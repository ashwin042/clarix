<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role'         => \App\Http\Middleware\EnsureUserHasRole::class,
            'permission'   => \App\Http\Middleware\EnsureUserHasPermission::class,
            'subscription' => \App\Http\Middleware\EnsureSubscriptionActive::class,

            // The plan layer. Separate from 'permission' on purpose: that one
            // asks what a role may do, this one asks what the agency bought.
            'plan'         => \App\Http\Middleware\EnsurePlanIncludes::class,

            // Sanctum's token-ability gate, so a token minted for one
            // integration cannot be replayed against a different endpoint.
            'ability'      => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,

            // The Telegram bot's own handshake. Deliberately not Sanctum: a
            // token would resolve to a user in one agency and silently confine
            // the link-code lookup to it — see EnsureHermesRequest.
            'hermes'       => \App\Http\Middleware\EnsureHermesRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
