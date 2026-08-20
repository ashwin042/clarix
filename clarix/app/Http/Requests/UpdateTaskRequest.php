<?php

namespace App\Http\Requests;

use App\Rules\TenantExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->hasPermission('tasks.update');
    }

    public function rules(): array
    {
        $task   = $this->route('task');
        $unitId = $this->input('unit_id', $task->unit_id);

        return $this->creditAmountRule() + [
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
            'unit_id'     => ['required', TenantExists::in('units')],
            'priority'    => ['required', Rule::in(['low', 'medium', 'high'])],
            'deadline'    => ['required', 'date'],
            'status'      => ['sometimes', Rule::in(['pending', 'in_progress', 'completed', 'cancelled'])],
        ];
    }

    /**
     * credit_amount is validatable only for an admin.
     *
     * The controller updates from validated(), so leaving the field out of the
     * rules for everyone else drops it from the update entirely and the stored
     * figure stands — a PM may send whatever they like and the task keeps its
     * value. The rest of their edit is accepted; only this field is ignored.
     *
     * It used to be absent for admins too, which made the whole path safe by
     * accident rather than by decision, and meant no one could change the
     * figure here at all. Naming it explicitly settles both halves.
     *
     * @return array<string, list<string>>
     */
    protected function creditAmountRule(): array
    {
        if (! auth()->user()?->isAdmin()) {
            return [];
        }

        return ['credit_amount' => ['sometimes', 'numeric', 'min:0']];
    }
}
