<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AttachN8nTaskFilesRequest;
use App\Http\Requests\Api\StoreN8nTaskRequest;
use App\Http\Resources\N8nTaskFileCollection;
use App\Http\Resources\N8nTaskResource;
use App\Services\TaskCreationService;
use App\Services\TaskFileUploader;
use Illuminate\Http\JsonResponse;

/**
 * Task intake for the task bot: what a PM submits from Telegram.
 *
 * Thin on purpose. Everything that decides what a task *is* lives in
 * TaskCreationService, shared with the create-task screen and the token API, so
 * there is no third definition here to fall out of date. Everything that
 * decides *who is acting* happened in ResolveN8nActor, which is why this reads
 * $request->user() as though a person were signed in — one is, for the duration
 * of the request, without the guard being touched.
 *
 * Two endpoints rather than one multipart call, matching the token API and for
 * the same reasons — which are about failure rather than tidiness:
 *
 *   - The R2 put cannot join a database transaction. A create-with-file request
 *     that fails partway leaves a task behind, and the caller cannot retry it,
 *     because task_code is now taken. Split in two, each half is retryable on
 *     its own: the task exists, so the attach can be repeated until it works.
 *     That matters more here than anywhere else in the codebase, because the
 *     caller retrying is an n8n error branch rather than a person.
 *   - It sidesteps PHP's post_max_size, which caps the whole multipart body.
 *
 * The one thing the pipeline must get right in exchange: keep the task id from
 * the create response and use it in the attach path. A submission whose file
 * fails to attach is a real task with no brief on it, so the workflow's error
 * branch should say so in the chat rather than swallow it.
 */
class N8nTaskController extends Controller
{
    public function __construct(
        protected TaskCreationService $tasks,
        protected TaskFileUploader $uploader,
    ) {
    }

    /**
     * File a task for the person behind the chat.
     *
     * unit_id, pm_id and created_by are taken from the resolved user inside the
     * service — never from the payload. The bot can say what the task is; it
     * cannot say whose it is. status is fixed at 'pending' for the same reason.
     *
     * Replaying a captured create is answered by the schema rather than by the
     * transport: task_code is unique per unit, so the second attempt is a 422
     * rather than a duplicate task. That is the closest thing this endpoint has
     * to an idempotency key, and it is worth knowing it is doing that job —
     * see EnsureN8nRequest on what a static shared key does and does not buy.
     */
    public function store(StoreN8nTaskRequest $request): JsonResponse
    {
        $task = $this->tasks->create($request->validated(), $request->user());

        return (new N8nTaskResource($task))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    /**
     * Attach the submission's file to a task already filed.
     *
     * The task is resolved by the form request under the acting organization's
     * scope, so another agency's task is a 404 and never reaches the policy.
     */
    public function attachFiles(AttachN8nTaskFilesRequest $request): JsonResponse
    {
        $files = $this->uploader->upload(
            $request->task(),
            $request->file('files'),
            $request->user()
        );

        return (new N8nTaskFileCollection($files))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
