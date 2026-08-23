<?php

namespace App\Http\Requests\Api;

use App\Models\Task;
use App\Rules\WithinStorageQuota;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Validation for the file a task bot submission carries.
 *
 * The task is loaded here rather than by route-model binding, and that is a
 * security decision rather than a stylistic one. Implicit binding resolves the
 * model before anything has established who is acting, so a bound task would be
 * fetched with no tenant context and OrganizationScope would filter nothing —
 * another agency's task id in the path would resolve happily and only a policy
 * would stand between it and an upload. Loaded here, inside the acting-as
 * context ResolveN8nActor established, the scope does the work: another
 * agency's task is simply not found.
 *
 * The limits are the token API's, deliberately: same allowed types, same size
 * ceiling, same quota rule. A file arriving from Telegram is no more trusted
 * than one arriving from a script, and having two answers to "what may be
 * uploaded" is how one of them ends up wrong.
 */
class AttachN8nTaskFilesRequest extends FormRequest
{
    protected ?Task $resolved = null;

    /**
     * Defers to the policy for the same reason UploadTaskFilesRequest does:
     * TaskPolicy::uploadFiles() asks both whether the agency granted
     * tasks.upload_files and whether the actor owns the task, and the ownership
     * half is what stops a PM attaching files to another unit's work.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('uploadFiles', $this->task()) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'files' => [
                'required',
                'array',
                'min:1',
                'max:'.config('storage.api_max_files'),
                // On the array, not on each file: the allowance is about the
                // total arriving in one request, and five individually valid
                // files can still be collectively too much.
                new WithinStorageQuota($this->quotaOrganizationId()),
            ],
            'files.*' => [
                'file',
                'max:'.config('storage.api_max_file_kb'),
                'mimes:'.implode(',', (array) config('storage.api_allowed_mimes')),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'files.required' => 'No files were received. A body sent without a Content-Length header can also produce this, if it exceeded PHP post_max_size and was discarded before validation; an ordinary over-large upload is answered with 413 instead.',
            'files.max'      => 'At most '.config('storage.api_max_files').' files may be uploaded in one request.',
            'files.*.mimes'  => 'This file type is not accepted. Allowed types: '.implode(', ', (array) config('storage.api_allowed_mimes')).'.',
            'files.*.max'    => 'Each file must be '.round(((int) config('storage.api_max_file_kb')) / 1024).'MB or smaller.',
        ];
    }

    /**
     * The task this upload is for, under the acting organization's scope.
     *
     * A miss is a 404 rather than a 403. The distinction is not cosmetic: a
     * task in another agency and a task that never existed must be
     * indistinguishable from outside, or the endpoint reports whether an id is
     * in use across the whole platform.
     */
    public function task(): Task
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $task = Task::query()->whereKey($this->route('task'))->first();

        if ($task === null) {
            throw new NotFoundHttpException('No such task.');
        }

        return $this->resolved = $task;
    }

    /**
     * The organization whose allowance applies — the one that owns the task,
     * which under the global scope is necessarily the actor's own.
     */
    protected function quotaOrganizationId(): ?int
    {
        $organizationId = $this->task()->organization_id;

        return $organizationId === null ? null : (int) $organizationId;
    }
}
