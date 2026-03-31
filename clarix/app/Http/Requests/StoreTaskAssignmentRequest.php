<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'task_id'   => ['required', 'exists:tasks,id'],
            'writer_ids'=> ['required', 'array', 'min:1'],
            'writer_ids.*' => ['exists:users,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $writerIds = $this->input('writer_ids', []);
            foreach ($writerIds as $writerId) {
                $user = \App\Models\User::find($writerId);
                if ($user && $user->role !== 'writer') {
                    $v->errors()->add('writer_ids', "User #{$writerId} is not a writer.");
                }
            }
        });
    }
}
