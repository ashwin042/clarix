<?php

use App\Http\Controllers\Api\N8nDirectoryController;
use App\Http\Controllers\Api\N8nTaskController;
use App\Http\Controllers\Api\N8nTelegramLinkController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskFileController;
use App\Http\Controllers\Api\TelegramLinkController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
|
| Token-authenticated endpoints for an agency's own integrations. Everything
| here authenticates as a real user row through Sanctum — see the service
| account command — because that is what gives TenantContext an organization
| to scope by. An endpoint that authenticated a bare API key against no user
| would leave organization_id null, and null means "do not filter": the write
| would escape tenancy and TenantExists would fall back to an unscoped check.
|
| 'subscription' is applied for the same reason it is applied to the web
| routes: a suspended agency's integrations must stop filing work too. It
| answers with JSON here rather than the suspension page — see
| EnsureSubscriptionActive.
|
*/

Route::middleware(['auth:sanctum', 'subscription'])->prefix('v1')->name('api.v1.')->group(function () {
    Route::post('/tasks', [TaskController::class, 'store'])
        ->middleware('ability:tasks:create')
        ->name('tasks.store');

    /*
     * Attachments are their own call, not multipart on the create above.
     * Creating a task and putting bytes in a bucket cannot be one transaction,
     * so keeping them separate is what makes each half retryable — see
     * Api\TaskFileController. It also gives every file its own post_max_size
     * budget instead of sharing one with the task fields.
     *
     * A distinct ability, so a token minted to file tasks cannot also write
     * objects to the agency's storage.
     */
    Route::post('/tasks/{task}/files', [TaskFileController::class, 'store'])
        ->middleware('ability:files:write')
        ->name('tasks.files.store');
});

/*
|--------------------------------------------------------------------------
| Hermes (Telegram) routes
|--------------------------------------------------------------------------
|
| A separate group because it authenticates a different kind of caller. The
| group above authenticates *as a user*, through Sanctum, which is what gives
| TenantContext an organization to scope by. This one deliberately does not:
| the whole point of the link endpoint is to find a user across every agency,
| and a token that resolved to one agency's service account would silently
| confine that search to it — see EnsureHermesRequest and TelegramLinkService.
|
| 'subscription' is absent for the same reason: it reads $request->user(),
| which is null here, so it would pass everything through while looking like a
| guard. The controller asks the question itself, of the person the code
| resolves to, and asks the plan question in the same place.
|
| The throttles are not optional. Nothing else in this file has one — the task
| endpoints are guarded by needing a bearer token — but verify answers "is this
| code real", and an eight-character code needs a limit in front of it.
|
*/
Route::middleware('hermes')->prefix('v1/telegram')->name('api.v1.telegram.')->group(function () {
    Route::post('/verify', [TelegramLinkController::class, 'verify'])
        ->middleware('throttle:hermes-verify')
        ->name('verify');

    Route::post('/resolve', [TelegramLinkController::class, 'resolve'])
        ->middleware('throttle:hermes-resolve')
        ->name('resolve');
});

/*
|--------------------------------------------------------------------------
| Task bot (n8n Telegram pipeline) routes
|--------------------------------------------------------------------------
|
| The second Telegram bot, and a third kind of caller. It shares the group
| above's reason for skipping Sanctum — the link lookup has to reach across
| every agency, and a token that resolved to one agency's service account
| would silently confine it — but not its handshake: this one presents a
| static shared key instead of signing each request, because the caller is an
| n8n workflow. See EnsureN8nRequest for the trade.
|
| Nothing here is shared with the Hermes group on purpose. Its own prefix, its
| own middleware, its own throttles and its own controller, so that changing
| one bot cannot alter the other — they answer to different pipelines and
| different people.
|
| 'subscription' is absent for the same reason it is absent above: it reads
| $request->user(), which is null here, so it would pass everything through
| while looking like a guard. The controller asks the question itself, of the
| person the chat resolves to, on both endpoints.
|
| Two kinds of route live here, and they differ by one middleware. The link
| pair carries 'n8n' alone: verify() is how a chat becomes known, so it cannot
| require the chat to already be known. The intake pair adds 'n8n.actor',
| which turns the chat_id in the body into an acting person and runs the rest
| of the request inside that person's organization — without which a filed
| task would be stamped with no agency at all. See ResolveN8nActor.
|
*/
Route::middleware('n8n')->prefix('v1/n8n/telegram')->name('api.v1.n8n.telegram.')->group(function () {
    Route::post('/verify', [N8nTelegramLinkController::class, 'verify'])
        ->middleware('throttle:n8n-verify')
        ->name('verify');

    Route::post('/resolve', [N8nTelegramLinkController::class, 'resolve'])
        ->middleware('throttle:n8n-resolve')
        ->name('resolve');

    /*
     * Intake. Two calls rather than one multipart create, matching the token
     * API: the R2 put cannot join a database transaction, so a combined call
     * that fails partway leaves a task the caller cannot retry — task_code is
     * already taken. Split, each half retries on its own.
     *
     * {task} is deliberately not model-bound. Implicit binding resolves before
     * anything has established who is acting, so the lookup would run with no
     * tenant context and another agency's id would resolve happily; the form
     * request loads it under the acting scope instead.
     */
    /*
     * The throttle is listed before the actor middleware, and the order is
     * load-bearing: behind it, a caller probing chat ids that are not linked
     * would be answered 404 without ever touching the limiter. Counting the
     * refusals is the point of having one.
     */
    /*
     * Directory. The two lookups an admin's conversation makes before it can
     * file anything — which unit, then whose — and that a PM's never makes,
     * their unit being on their own user row already. Both are shut to anybody
     * but an admin in the form requests.
     *
     * Its own throttle rather than the intake one. These are reads, and a
     * conversation makes two of them before the single write that follows;
     * sharing intake's bucket would mean picking a unit spent part of the
     * allowance for filing the task it was picked for.
     *
     * {unit} is deliberately not model-bound, for the same reason {task} below
     * is not: implicit binding resolves before ResolveN8nActor has established
     * who is acting, so the lookup would run with no tenant context and another
     * agency's id would resolve happily. The form request loads it under the
     * acting scope instead.
     */
    Route::middleware(['throttle:n8n-directory', 'n8n.actor'])->group(function () {
        Route::get('/units', [N8nDirectoryController::class, 'units'])->name('units.index');

        Route::get('/units/{unit}/pms', [N8nDirectoryController::class, 'unitPeople'])
            ->whereNumber('unit')
            ->name('units.pms.index');
    });
    Route::middleware(['throttle:n8n-intake', 'n8n.actor'])->group(function () {
        Route::post('/tasks', [N8nTaskController::class, 'store'])->name('tasks.store');

        /*
         * The one endpoint carrying idempotency, and the only one that needs
         * it. A replayed create is refused by the schema — task_code is unique
         * per unit — but the same file posted twice is two perfectly valid
         * attachments, and a static shared key leaves a captured request
         * replayable. The caller supplies a key per submission instead of
         * signing, which is a function an n8n workflow cannot perform without
         * a code node. See EnsureN8nIdempotency.
         */
        Route::post('/tasks/{task}/files', [N8nTaskController::class, 'attachFiles'])
            ->middleware('n8n.idempotent:tasks.files')
            ->whereNumber('task')
            ->name('tasks.files.store');
    });
});
