<?php

namespace App\Http\Requests\Api;

use App\Models\Task;
use App\Services\N8nTaskQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Who may read tasks through the bot, and what they are allowed to ask for.
 *
 * Every filter is optional and every one of them only narrows — the ceiling is
 * N8nTaskQuery's business, decided from the acting person rather than from the
 * query string. So there is very little to validate here beyond shape, and that
 * is the point: nothing in the query string can widen what comes back, so
 * nothing in the query string needs to be trusted.
 *
 * The one exception is unit_id, which *looks* like it could widen, and is
 * checked after validation rather than in a rule — see below.
 */
class ListN8nTasksRequest extends FormRequest
{
    /**
     * The permission is asked of the person behind the chat, not of the
     * pipeline's key.
     *
     * Same reasoning as StoreN8nTaskRequest asking tasks.create: turning
     * tasks.view off for a role in the Authorization panel has to stop the bot
     * for that role immediately, without anyone editing a workflow. HR is the
     * live example — the role holds no task permissions by default, so it is
     * refused here despite having a perfectly valid Telegram link.
     *
     * No role check beyond the permission. Which *rows* a role reaches is
     * N8nTaskQuery's ceiling, and a role with no ceiling gets an empty list
     * rather than a refusal — the two questions are kept apart here exactly as
     * TaskPolicy keeps them apart.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('tasks.view') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'unit_id'   => ['sometimes', 'nullable', 'integer', 'min:1'],
            'pm_id'     => ['sometimes', 'nullable', 'integer', 'min:1'],
            'task_code' => ['sometimes', 'nullable', 'string', 'max:255'],

            /*
             * Validated against the model's own list rather than left to the
             * column. tasks.status is a MySQL enum, and sqlite — which the test
             * suite runs on — accepts any string in an enum column without
             * complaint, so an unvalidated status would pass every test and
             * fail only in production. Rejecting it here also means a typo in a
             * workflow reads as "that is not a status" instead of silently
             * matching nothing, which is a different and much more confusing
             * bug to chase from a Telegram reply.
             */
            'status'    => ['sometimes', 'nullable', 'string', Rule::in(Task::STATUSES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'That is not a task status. Use one of: '.implode(', ', Task::STATUSES).'.',
        ];
    }

    /**
     * The one filter that has to be authorized rather than merely validated.
     *
     * Here rather than in rules() because the answer is 403, not 422. A PM
     * naming another unit has sent a well-formed request asking for something
     * they may not have, which is a refusal; a 422 would describe it as a typo.
     *
     * And after validation rather than in authorize(), because authorize() runs
     * first and has no validated integer to work with — reading a raw query
     * parameter there would mean parsing it twice, in two places, with two
     * chances to disagree about what "7abc" means.
     */
    protected function passedValidation(): void
    {
        $unitId = $this->validated()['unit_id'] ?? null;

        if ($unitId === null) {
            return;
        }

        if (! app(N8nTaskQuery::class)->mayQueryUnit($this->user(), (int) $unitId)) {
            throw new AuthorizationException(
                'You cannot read tasks for that unit.'
            );
        }
    }

    /**
     * The narrowing filters, with the blanks dropped.
     *
     * n8n sends an empty string for a field the conversation did not fill in,
     * and ConvertEmptyStringsToNull turns those into nulls before this runs, so
     * filtering nulls out here is what makes a half-filled query mean "no
     * filter" rather than "match nothing".
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return array_filter(
            $this->safe()->only(['unit_id', 'pm_id', 'task_code', 'status']),
            fn ($value) => $value !== null && $value !== ''
        );
    }
}
