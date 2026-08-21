<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UploadTaskFilesRequest;
use App\Http\Resources\TaskFileResource;
use App\Models\Task;
use App\Services\TaskFileUploader;
use Illuminate\Http\JsonResponse;

/**
 * Attaching files to a task that already exists.
 *
 * A separate endpoint rather than multipart on the create call, for reasons
 * that are about failure rather than tidiness. The R2 put cannot join a
 * database transaction, so a create-with-files request that fails partway
 * leaves a task behind — and the caller cannot simply retry it, because
 * task_code is now taken. Split in two, each half is retryable on its own: the
 * task exists, so the attach can be repeated until it succeeds.
 *
 * It also sidesteps PHP's post_max_size, which caps the whole multipart body.
 * Combined, the task fields and every attachment share one 55MB ceiling; apart,
 * each file gets its own budget.
 */
class TaskFileController extends Controller
{
    public function __construct(protected TaskFileUploader $uploader)
    {
    }

    /**
     * The task is resolved by route binding under the tenant scope, so a task
     * belonging to another organization is a 404 here and never reaches the
     * policy.
     */
    public function store(UploadTaskFilesRequest $request, Task $task): JsonResponse
    {
        $files = $this->uploader->upload($task, $request->file('files'), $request->user());

        return TaskFileResource::collection($files)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
