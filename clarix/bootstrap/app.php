<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
