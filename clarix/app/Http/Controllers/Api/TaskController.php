<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTaskRequest;
use App\Http\Resources\TaskResource;
use App\Services\TaskCreationService;
use Illuminate\Http\JsonResponse;

/**
 * Programmatic task creation for an agency's own integrations.
 *
 * Thin on purpose. Everything that decides what a task *is* lives in
 * TaskCreationService, shared with the create-task screen, so there is no
 * second definition here to fall out of date — which is exactly what happened
 * to the unrouted App\Http\Controllers\TaskController.
 */
class TaskController extends Controller
{
    public function __construct(protected TaskCreationService $tasks)
    {
    }

    /**
     * File a task as the authenticated service account.
     *
     * The actor is the token's user, and unit_id, pm_id and created_by are
     * taken from it inside the service — never from the payload. A caller can
     * say what the task is; it cannot say whose it is.
     *
     * Attachments are not accepted in v1. The service still supports them for
     * the browser path; adding them here needs its own decisions about size
     * limits and storage-quota accounting.
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = $this->tasks->create($request->validated(), $request->user());

        return (new TaskResource($task))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
