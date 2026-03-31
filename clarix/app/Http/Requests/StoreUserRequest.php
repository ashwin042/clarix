<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'max:255'],
            'email'   => ['required', 'email', 'unique:users,email'],
            'password'=> ['required', 'string', 'min:8'],
            'role'    => ['required', Rule::in(['pm', 'writer'])],
            'unit_id' => [
                Rule::requiredIf(fn () => $this->input('role') === 'pm'),
                'nullable',
                'exists:units,id',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($this->input('role') === 'writer' && $this->input('unit_id')) {
                $v->errors()->add('unit_id', 'Writers must not be assigned to a unit.');
            }
        });
    }
}
