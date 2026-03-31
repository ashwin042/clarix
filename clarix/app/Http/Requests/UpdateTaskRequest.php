<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(auth()->user()->role, ['admin', 'pm']);
    }

    public function rules(): array
    {
        $task   = $this->route('task');
        $unitId = $this->input('unit_id', $task->unit_id);

        return [
            'title'       => ['required', 'string', 'max:255'],
            'task_code'   => [
                'required',
                'string',
                'max:50',
                Rule::unique('tasks')
                    ->where(fn ($q) => $q->where('unit_id', $unitId))
                    ->ignore($task->id),
            ],
            'description' => ['nullable', 'string'],
            'unit_id'     => ['required', 'exists:units,id'],
            'priority'    => ['required', Rule::in(['low', 'medium', 'high'])],
            'deadline'    => ['required', 'date'],
            'status'      => ['sometimes', Rule::in(['pending', 'in_progress', 'submitted', 'verified', 'completed'])],
        ];
    }
}
