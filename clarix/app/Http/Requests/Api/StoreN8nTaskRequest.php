<?php

namespace App\Http\Requests\Api;

use App\Services\TaskCreationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

/**
 * Validation for a task filed through the task bot.
 *
 * The rules are not written here — they are TaskCreationService::rules(), the
 * same set the create-task screen and the token API validate against. Only what
 * is genuinely about *this* transport lives in this class.
 *
 * Two things are about this transport. assigned_admin_id is dropped, because
 * the bot's submission is the six fields a PM types into Telegram — name, code,
 * deadline, priority, credit, file — and choosing an admin is not among them.
 * Dropping it from the rules rather than ignoring it in the controller is what
 * makes the payload unable to set it: a field with no rule never reaches
 * validated(), and create() reads the key with a null default. And chat_id is
 * excused from the rules entirely, having already been validated and consumed
 * by ResolveN8nActor before this class exists.
 *
 * Everything else is shared on purpose. When a field is added to the create
 * screen it arrives here too, which is the whole reason TaskCreationService
 * owns the rules — the codebase has a worked example of what happens otherwise
 * in the unrouted TaskController, still validating a column renamed in March.
 */
class StoreN8nTaskRequest extends FormRequest
{
    /**
     * Same two conditions the screen and the token API enforce: the agency must
     * have granted the permission, and the actor must have a unit to file the
     * work under.
     *
     * The permission is asked of the *person behind the chat*, not of the
     * pipeline's key. A writer who links their Telegram cannot thereby file
     * tasks, and revoking tasks.create in the Authorization panel stops them
     * immediately without anyone having to touch the bot.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->hasPermission('tasks.create')
            && $user->unit_id !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $unitId = $this->user()?->unit_id === null ? null : (int) $this->user()->unit_id;

        return Arr::except(TaskCreationService::rules($unitId), ['assigned_admin_id']);
    }

    /**
     * Messages worth writing out, because these are read in a Telegram reply by
     * somebody who cannot see the API.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'task_code.unique' => 'A task with that code already exists in your unit. Pick a different code.',
            'deadline.date'    => 'The deadline must be a date, for example 2026-09-30.',
            'priority.in'      => 'Priority must be low, medium or high.',
        ];
    }
}
