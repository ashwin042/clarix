<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\Notifications\NewTaskCreatedNotification;
use App\Rules\TenantExists;

/**
 * Filing a task on behalf of a PM, in one place.
 *
 * Extracted from CreateTask because a second caller arrived — the API endpoint
 * an external service posts to — and the codebase already had a worked example
 * of what happens when a creation path is copied instead of shared:
 * TaskController@store still validates a `description` column that was renamed
 * to `important_notes` in March, and knows nothing about credit_amount, pm_id
 * or task_type. It went stale precisely because nothing forced it to move when
 * the real flow moved. Both live callers now go through here, so there is one
 * definition to keep current.
 *
 * Covers the PM-shaped create: the actor files work under their own unit, as
 * themselves, always at status 'pending'. The admin modals in ManageTasks and
 * AssignedTasks take unit_id, pm_id *and status* as input and are a genuinely
 * different operation; folding them in here would mean a parameter that
 * switches between two authority models, which is how this kind of helper turns
 * into the thing it replaced.
 *
 * The one concession is $target on create(), which lets a caller say which unit
 * and whose the task is while everything else — status, created_by, the
 * organization stamp — stays exactly as above. It exists because an admin
 * filing from Telegram has no unit of their own to fall back on, and it is
 * narrow enough not to be the switch described in the previous paragraph:
 * there is still one authority model here, and 'pending' is still not an input.
 */
class TaskCreationService
{
    public function __construct(protected TaskFileUploader $files)
    {
    }

    /**
     * The task_type values the column accepts.
     *
     * Named here because the same list was written out inline in three
     * separate validate() calls, and a fourth caller had no way to discover
     * it other than copying one of them.
     *
     * @var list<string>
     */
    public const TASK_TYPES = [
        'tech', 'content', 'accounts', 'maths', 'nursing', 'science', 'civil', 'others',
    ];

    /**
     * Validation for the task's own fields, shared by every PM-shaped caller.
     *
     * Attachments are not covered. They are a property of how the request
     * arrived — Livewire holds TemporaryUploadedFile instances on a public
     * property, an HTTP request would carry multipart files — so each caller
     * validates its own, and passes the results to create() as plain files.
     *
     * @param  int|null  $unitId  the unit the code must be unique within
     * @return array<string, mixed>
     */
    public static function rules(?int $unitId): array
    {
        // Kept in the string form the component used, rather than rebuilt with
        // Rule::unique(), so the composite ['unit_id', 'task_code'] index is
        // consulted exactly as before. task_code is unique per unit, never
        // globally — see the index on the tasks table.
        //
        // The null case is spelled out rather than interpolated, and it matters
        // now that a caller can genuinely reach here without a unit: the task
        // bot asks an admin which unit to file into, and renders these rules
        // before the answer has been validated. Interpolating null produced a
        // trailing empty parameter — `unit_id,` — which reaches the driver as
        // `where unit_id = ''`. sqlite quietly matches nothing and MySQL
        // coerces to 0 with a truncation warning, so the two agree by accident
        // rather than because the rule means anything. 'NULL' is the token
        // Laravel turns into whereNull, and since tasks.unit_id is NOT NULL it
        // is also the honest answer: a code filed against no unit collides with
        // nothing. The request is refused on target_unit_id a moment later
        // either way — this is about the rule being defined, not about the
        // verdict.
        $uniqueRule = $unitId === null
            ? 'unique:tasks,task_code,NULL,id,unit_id,NULL'
            : "unique:tasks,task_code,NULL,id,unit_id,{$unitId}";

        return [
            'title'           => 'required|string|max:255',
            'task_code'       => ['required', 'string', 'max:50', $uniqueRule],
            'priority'        => 'required|in:low,medium,high',
            'deadline'        => 'required|date',
            'credit_amount'   => 'required|numeric|min:0',
            'task_type'       => ['nullable', 'in:'.implode(',', self::TASK_TYPES)],
            'important_notes' => 'nullable|string|max:5000',
            'assigned_admin_id' => [
                'nullable',
                TenantExists::in('users'),
                function ($attribute, $value, $fail) {
                    if ($value && User::find($value)?->role !== 'admin') {
                        $fail('The selected user must be an admin.');
                    }
                },
            ],
        ];
    }

    /**
     * File a validated task for $actor, with any attachments, and tell the
     * admins about it.
     *
     * unit_id and pm_id are taken from $actor, never from $data, and
     * created_by always is. That is the security property this method exists
     * to hold: the caller decides what the task says, the actor decides whose
     * it is. status is likewise fixed at 'pending' — moving a task along is a
     * separate, separately-authorized action.
     *
     * $target is the one exception, and it is deliberately a separate argument
     * rather than two more keys in $data. $data is the request's own validated
     * payload; a unit id read out of it would be a field the caller can set,
     * which is exactly what the paragraph above says it must not be. Passed
     * separately, the ownership decision stays with whoever constructs the
     * target — today only StoreN8nTaskRequest, and only for an admin, who
     * belongs to no unit and so has nothing else to file against. A PM-shaped
     * caller passes nothing and gets the old behaviour unchanged.
     *
     * created_by is outside the exception on purpose: the admin filed the task
     * even when it is somebody else's, and the audit trail has to say so.
     *
     * organization_id is absent on purpose. It is stamped by the
     * BelongsToOrganization creating hook from the acting user, and is kept
     * out of Task::$fillable so that no caller can set it at all. Note this is
     * also why $target cannot cross agencies whatever it holds — the stamp
     * comes from the actor, so a foreign unit id would produce a row whose unit
     * and organization disagree, which is why the id is validated against the
     * tenant scope before it ever reaches here.
     *
     * @param  array<string, mixed>              $data    validated attributes
     * @param  iterable<mixed>                   $files   anything with a store()/getSize()
     * @param  array{unit_id: int, pm_id: ?int}|null  $target  whose it is, when not the actor's
     */
    public function create(array $data, User $actor, iterable $files = [], ?array $target = null): Task
    {
        $task = Task::create([
            'title'             => $data['title'],
            'task_code'         => $data['task_code'],
            'task_type'         => ($data['task_type'] ?? null) ?: null,
            'important_notes'   => ($data['important_notes'] ?? null) ?: null,
            'unit_id'           => $target === null ? $actor->unit_id : $target['unit_id'],
            'pm_id'             => $target === null ? $actor->getKey() : ($target['pm_id'] ?? null),
            'assigned_admin_id' => ($data['assigned_admin_id'] ?? null) ?: null,
            'priority'          => $data['priority'],
            'status'            => 'pending',
            'deadline'          => $data['deadline'],
            'credit_amount'     => $data['credit_amount'],
            'created_by'        => $actor->getKey(),
        ]);

        $this->files->upload($task, $files, $actor);

        $this->notifyAdmins($task);

        return $task;
    }

    /**
     * Every admin in the actor's organization hears about a new task.
     *
     * The query carries no organization filter of its own because it does not
     * need one: User is tenant-scoped, so this resolves to the admins of
     * whichever agency the actor belongs to and cannot reach another's.
     */
    protected function notifyAdmins(Task $task): void
    {
        foreach (User::where('role', 'admin')->get() as $admin) {
            $admin->notify(new NewTaskCreatedNotification($task));
        }
    }
}
