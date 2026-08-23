<?php

namespace App\Http\Requests\Api;

use App\Rules\TenantExists;
use App\Services\N8nDirectory;
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
 * Three things are about this transport. assigned_admin_id is dropped, because
 * the bot's submission is the six fields a PM types into Telegram — name, code,
 * deadline, priority, credit, file — and choosing an admin is not among them.
 * Dropping it from the rules rather than ignoring it in the controller is what
 * makes the payload unable to set it: a field with no rule never reaches
 * validated(), and create() reads the key with a null default. chat_id is
 * excused from the rules entirely, having already been validated and consumed
 * by ResolveN8nActor before this class exists. And target_unit_id /
 * assigned_pm_id are added, but only for an admin — see below.
 *
 * Everything else is shared on purpose. When a field is added to the create
 * screen it arrives here too, which is the whole reason TaskCreationService
 * owns the rules — the codebase has a worked example of what happens otherwise
 * in the unrouted TaskController, still validating a column renamed in March.
 *
 * ── The admin branch ────────────────────────────────────────────────────────
 *
 * A PM carries their unit on their user row, so there is nothing for the bot to
 * ask and nothing for the payload to say. An admin belongs to no unit, which is
 * why this endpoint refused them outright before: authorize() required a
 * unit_id and they have none. So an admin, and only an admin, names the unit
 * and optionally the person.
 *
 * The two fields are *ignored* for a PM rather than rejected, and that is the
 * stronger of the two options rather than the lazier one. Rejection is a rule
 * that has to keep being right; ignoring is structural — the fields have no
 * rule when the actor is not an admin, so they never reach validated(), and
 * create() reads the target from a separate argument this class builds. There
 * is no path by which a PM's payload reaches the unit_id column, in the same
 * way there is none by which it reaches assigned_admin_id. It also means a
 * workflow that sends the fields unconditionally still works for both branches,
 * which is the shape n8n makes easiest to build.
 */
class StoreN8nTaskRequest extends FormRequest
{
    /**
     * Same two conditions the screen and the token API enforce: the agency must
     * have granted the permission, and the actor must have a unit to file the
     * work under — or, being an admin, the standing to name one.
     *
     * The permission is asked of the *person behind the chat*, not of the
     * pipeline's key. A writer who links their Telegram cannot thereby file
     * tasks, and revoking tasks.create in the Authorization panel stops them
     * immediately without anyone having to touch the bot.
     *
     * isAdmin() rather than reachesEveryUnit(): a supervisor is equally
     * unitless, but nothing offers them the unit picker (see
     * ListN8nUnitsRequest), and letting the write in without the directory in
     * front of it would be an endpoint reachable only by guessing unit ids.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->hasPermission('tasks.create')
            && ($user->isAdmin() || $user->unit_id !== null);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $unitId = $this->effectiveUnitId();

        $rules = Arr::except(TaskCreationService::rules($unitId), ['assigned_admin_id']);

        if (! $this->user()?->isAdmin()) {
            return $rules;
        }

        return $rules + [
            /*
             * TenantExists rather than a plain exists: the validator builds its
             * queries on the query builder and never sees OrganizationScope, so
             * `exists:units,id` would answer "does this id exist anywhere on the
             * platform" and let an admin file into another agency's unit. This
             * is the single check standing between the payload and a
             * cross-tenant write, because by the time the id reaches
             * TaskCreationService it is already trusted.
             */
            'target_unit_id' => ['required', 'integer', TenantExists::in('units')],

            /*
             * Optional, because unassigned work is a real state: the unit has
             * the task, nobody in it owns it yet. When given, it has to be
             * somebody the directory endpoint would have offered for *this*
             * unit — the pairing matters as much as the membership, or a PM
             * ends up holding a task their own unit filter hides.
             *
             * Asked of N8nDirectory rather than spelled out here, so the list
             * the bot showed and the answer it accepts cannot disagree.
             */
            'assigned_pm_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, callable $fail) use ($unitId): void {
                    if ($unitId === null || ! app(N8nDirectory::class)->isAssignableTo((int) $value, $unitId)) {
                        $fail('The selected person is not a PM in the chosen unit.');
                    }
                },
            ],
        ];
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
            'task_code.unique'        => 'A task with that code already exists in that unit. Pick a different code.',
            'deadline.date'           => 'The deadline must be a date, for example 2026-09-30.',
            'priority.in'             => 'Priority must be low, medium or high.',
            'target_unit_id.required' => 'Choose which unit this task is for.',
            'target_unit_id.exists'   => 'That unit does not belong to your organization.',
        ];
    }

    /**
     * Whose the task is, when that is not simply the actor's own.
     *
     * Null for a PM — the fields have no rule for them, so validated() has
     * never heard of them — which is what makes TaskCreationService fall back
     * to the actor's unit and the actor themselves.
     *
     * @return array{unit_id: int, pm_id: ?int}|null
     */
    public function target(): ?array
    {
        if (! $this->user()?->isAdmin()) {
            return null;
        }

        $validated = $this->validated();
        $pmId      = $validated['assigned_pm_id'] ?? null;

        return [
            'unit_id' => (int) $validated['target_unit_id'],
            'pm_id'   => $pmId === null ? null : (int) $pmId,
        ];
    }

    /**
     * The unit task_code has to be unique within.
     *
     * task_code is unique per unit, not globally — see the composite index on
     * the tasks table — so this has to be the unit the task is actually going
     * to land in. For an admin that is the one they named, not their own, which
     * is null and would make every code in the agency look free.
     *
     * A missing or non-numeric target yields null, and the request is refused
     * on target_unit_id a moment later; the uniqueness rule's answer in that
     * window is never read.
     */
    protected function effectiveUnitId(): ?int
    {
        $user = $this->user();

        if ($user === null) {
            return null;
        }

        if (! $user->isAdmin()) {
            return $user->unit_id === null ? null : (int) $user->unit_id;
        }

        $target = $this->input('target_unit_id');

        return is_numeric($target) ? (int) $target : null;
    }
}
