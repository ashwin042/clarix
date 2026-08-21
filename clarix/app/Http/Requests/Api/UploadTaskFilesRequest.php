<?php

namespace App\Http\Requests\Api;

use App\Rules\WithinStorageQuota;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for attachments arriving over the API.
 *
 * Three separate gates stand between a token and a stored object, and they ask
 * three different questions:
 *
 *   ability:files:write   what this credential was minted to do (on the route)
 *   uploadFiles policy    what this person may do to this task (here)
 *   OrganizationScope     whether the task is even visible (route binding)
 *
 * The middle one is the reason this class defers to the policy rather than
 * checking a permission inline. TaskPolicy::uploadFiles() asks both whether the
 * agency granted tasks.upload_files and whether the actor owns the task, and
 * the ownership half is what stops a PM in one unit attaching files to another
 * unit's work. The web upload route checks only the permission and so does not
 * enforce that half; this endpoint deliberately does.
 */
class UploadTaskFilesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('uploadFiles', $this->route('task')) === true;
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * Messages worth writing out, because the defaults describe the rule
     * rather than the situation.
     *
     * @return array<string, string>
     */
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
     * The organization whose allowance applies — the one that owns the task,
     * which under the global scope is necessarily the actor's own.
     */
    protected function quotaOrganizationId(): ?int
    {
        $organizationId = $this->route('task')?->organization_id;

        return $organizationId === null ? null : (int) $organizationId;
    }
}
