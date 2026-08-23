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

            // The task-submission bot's pipeline. A separate alias rather than
            // a second config on 'hermes': the two bots authenticate
            // differently — one signs, one presents a static key — and sharing
            // an alias would make it impossible to tell from a route which
            // guarantee that route actually has. See EnsureN8nRequest.
            'n8n'          => \App\Http\Middleware\EnsureN8nRequest::class,

            // The second half of the task bot's stack: 'n8n' proves that the
            // pipeline is calling, this proves who it is calling for. Separate,
            // because the link endpoints need the first without the second —
            // verify() is how a chat becomes known, so it cannot require the
            // chat to already be known.
            'n8n.actor'    => \App\Http\Middleware\ResolveN8nActor::class,

            // Makes a task bot write safe to retry, on a key the caller
            // supplies. Takes the operation as a parameter so one operation's
            // key can never satisfy another's. Depends on 'n8n.actor' having
            // run: keys are scoped per user.
            'n8n.idempotent' => \App\Http\Middleware\EnsureN8nIdempotency::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
