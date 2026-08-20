<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadCompletedFilesRequest extends FormRequest
{
    /**
     * Defers to the policy rather than keeping a second list of roles.
     *
     * The inline list here and the match in TaskPolicy::uploadCompletedFile()
     * said the same thing in two places, so adding the supervisor to one would
     * have left the other refusing them — with the request winning, since it
     * runs first.
     */
    public function authorize(): bool
    {
        return $this->user()->can('uploadCompletedFile', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'files'   => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:51200'],
        ];
    }
}
