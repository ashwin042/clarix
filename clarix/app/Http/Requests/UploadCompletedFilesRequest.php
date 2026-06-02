<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadCompletedFilesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(auth()->user()->role, ['admin', 'writer']);
    }

    public function rules(): array
    {
        return [
            'files'   => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:10240'],
        ];
    }
}
