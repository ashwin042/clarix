<?php

namespace App\Http\Requests;

use App\Rules\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

class StoreTaskAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasPermission('tasks.assign');
    }

    public function rules(): array
    {
        return [
            'task_id'   => ['required', TenantExists::in('tasks')],
            'writer_ids'=> ['required', 'array', 'min:1'],
            'writer_ids.*' => [TenantExists::in('users')],
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
