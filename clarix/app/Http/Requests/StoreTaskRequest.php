<?php

namespace App\Http\Requests;

use App\Rules\TenantExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasPermission('tasks.create');
    }

    public function rules(): array
    {
        $unitId = $this->input('unit_id');

        return [
            'title'       => ['required', 'string', 'max:255'],
            'task_code'   => [
                'required',
                'string',
                'max:50',
                Rule::unique('tasks')->where(fn ($q) => $q->where('unit_id', $unitId)),
            ],
            'description' => ['nullable', 'string'],
            'unit_id'     => ['required', TenantExists::in('units')],
            'priority'    => ['required', Rule::in(['low', 'medium', 'high'])],
            'deadline'    => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    public function prepareForValidation(): void
    {
        if (auth()->user()->isPm()) {
            $this->merge(['unit_id' => auth()->user()->unit_id]);
        }
    }
}
