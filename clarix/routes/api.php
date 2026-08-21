<?php

use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskFileController;
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
