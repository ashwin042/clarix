<?php

namespace App\Livewire\Tasks;

use App\Rules\TenantExists;
use App\Livewire\Traits\WithDeleteConfirmation;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\NewTaskCreatedNotification;
use App\Notifications\TaskStatusUpdatedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class ManageTasks extends Component
{
    use WithPagination, WithDeleteConfirmation;

    /** The views the switcher offers. Kanban is the default. */
    public const VIEWS = ['kanban', 'table'];

    /** Session key holding the view the user last looked at. */
    public const VIEW_SESSION_KEY = 'tasks.active_view';

    /**
     * The board shows every open task but only the first slice of completed
     * ones — that column grows without bound, and the completed tasks page
     * already exists for the full history.
     */
    public const COMPLETED_COLUMN_LIMIT = 50;

    public string $activeView = 'kanban';

    public string $search = '';
    public string $filterStatus = '';
    public string $filterPriority = '';
    public string $filterUnit = '';
    public string $filterTaskType = '';
    public string $sortBy = 'created_at';
    public string $sortDir = 'desc';
    public bool $showModal = false;
    public ?int $editingId = null;

    // Form fields
    public string $title = '';
    public string $task_code = '';
    public string $task_type = '';
    public string $important_notes = '';
    public string $unit_id = '';
    public string $pm_id = '';
    public string $priority = 'medium';
    public string $status = 'pending';
    public string $deadline = '';
    public string $credit_amount = '0';
    public string $assigned_admin_id = '';

    public function mount(): void
    {
        $remembered = session(self::VIEW_SESSION_KEY);

        $this->activeView = in_array($remembered, self::VIEWS, true) ? $remembered : 'kanban';
    }

    /**
     * Switch the page between kanban, list and table. The choice is kept in
     * the session so the user lands back on the same view next visit.
     */
    public function setView(string $view): void
    {
        if (! in_array($view, self::VIEWS, true)) {
            return;
        }

        $this->activeView = $view;
        session([self::VIEW_SESSION_KEY => $view]);
        $this->resetPage();
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingFilterStatus(): void { $this->resetPage(); }
    public function updatingFilterPriority(): void { $this->resetPage(); }
    public function updatingFilterUnit(): void { $this->resetPage(); }
    public function updatingFilterTaskType(): void { $this->resetPage(); }

    public function updatedUnitId(): void
    {
        $this->pm_id = '';
    }

    public function sortBy(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'asc';
        }
    }

    public function openCreate(): void
    {
        $this->reset(['title', 'task_code', 'task_type', 'important_notes', 'unit_id', 'pm_id', 'assigned_admin_id', 'priority', 'status', 'deadline', 'credit_amount', 'editingId']);
        $this->priority = 'medium';
        $this->status = 'pending';
        $this->credit_amount = '0';

        if (auth()->user()->isPm()) {
            $this->unit_id = (string) auth()->user()->unit_id;
            $this->pm_id   = (string) auth()->id();
        }

        $this->resetErrorBag();
        $this->showModal = true;
    }

    /**
     * Whether the actor may choose the status in the create/edit modal.
     *
     * The same question save() asks, so the field is offered exactly when the
     * value would be honoured. Creating has no task to put to the policy yet,
     * so it asks the capability; editing asks the policy, which also answers
     * which rows.
     */
    public function maySetStatus(): bool
    {
        if ($this->editingId) {
            $task = Task::find($this->editingId);

            return $task !== null && Gate::allows('updateStatus', $task);
        }

        return auth()->user()->hasPermission('tasks.update_status');
    }

    public function openEdit(Task $task): void
    {
        $this->editingId     = $task->id;
        $this->title         = $task->title;
        $this->task_code     = $task->task_code;
        $this->task_type     = $task->task_type ?? '';
        $this->important_notes = $task->important_notes ?? '';
        $this->unit_id       = (string) $task->unit_id;
        $this->pm_id         = (string) ($task->pm_id ?? '');
        $this->priority      = $task->priority;
        $this->status        = $task->status;
        $this->deadline      = $task->deadline->format('Y-m-d');
        $this->credit_amount = (string) $task->credit_amount;
        $this->assigned_admin_id = (string) ($task->assigned_admin_id ?? '');
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save(): void
    {
        $authUser = auth()->user();

        // This action posts to Livewire's own endpoint, so the route never
        // guards it and render() only refuses once the write has happened.
        if ($this->editingId) {
            abort_unless(Gate::allows('update', Task::findOrFail($this->editingId)), 403);
        } else {
            abort_unless($authUser->hasPermission('tasks.create'), 403);
        }

        // Backend enforcement: PM cannot choose another PM or unit
        if ($authUser->isPm()) {
            $this->unit_id = (string) $authUser->unit_id;
            $this->pm_id   = (string) $authUser->id;
        }

        /*
         * The status field follows updateStatus, not update — the same split
         * moveTask() makes when a card crosses a column rather than moving
         * within one. It had no rule here at all: save() gated on `update` and
         * then wrote whatever arrived, so anyone holding tasks.update could
         * set any status through this form.
         *
         * The value is put back rather than refused, exactly as credit_amount
         * is below: the field is one part of a larger form, and someone
         * renaming a task should not get a 403 because a disabled input posted
         * its own value. The line this replaces forced 'pending' for a PM on
         * *every* save, which silently returned an in-progress task to the
         * first column whenever its title was corrected.
         */
        if ($this->editingId) {
            $existing = Task::findOrFail($this->editingId);

            if (! Gate::allows('updateStatus', $existing)) {
                $this->status = $existing->status;
            }
        } elseif (! $authUser->hasPermission('tasks.update_status')) {
            $this->status = 'pending';
        }

        // ...and cannot restate what the task is worth. Editing an existing
        // task, the stored figure is put back before validation so the form
        // cannot even report a number that will not be saved. Task::booted()
        // enforces the same rule for every other path into the column.
        if (! $authUser->isAdmin() && $this->editingId) {
            $this->credit_amount = (string) Task::findOrFail($this->editingId)->credit_amount;
        }

        $unitId = $this->unit_id;
        $uniqueRule = $this->editingId
            ? "unique:tasks,task_code,{$this->editingId},id,unit_id,{$unitId}"
            : "unique:tasks,task_code,NULL,id,unit_id,{$unitId}";

        $this->validate([
            'title'         => 'required|string|max:255',
            'task_code'     => ['required', 'string', 'max:50', $uniqueRule],
            'unit_id'       => ['required', TenantExists::in('units')],
            'pm_id'         => [
                'required',
                TenantExists::in('users'),
                function ($attribute, $value, $fail) use ($unitId) {
                    $pm = User::find($value);
                    if (!$pm || $pm->role !== 'pm') {
                        $fail('The selected user is not a project manager.');
                    } elseif ((int) $pm->unit_id !== (int) $unitId) {
                        $fail('The selected PM does not belong to the chosen unit.');
                    }
                },
            ],
            'priority'      => 'required|in:low,medium,high',
            // The constant, not a copy of it. This list is why on_hold
            // validated everywhere else and failed here.
            'status'        => ['required', Rule::in(Task::STATUSES)],
            'deadline'      => 'required|date',
            'credit_amount' => 'required|numeric|min:0',
            'task_type'          => 'nullable|in:tech,content,accounts,maths,nursing,science,civil,others',
            'important_notes'    => 'nullable|string|max:5000',
            'assigned_admin_id'  => [
                'nullable',
                TenantExists::in('users'),
                function ($attribute, $value, $fail) {
                    if ($value && User::find($value)?->role !== 'admin') {
                        $fail('The selected user must be an admin.');
                    }
                },
            ],
        ]);

        $data = [
            'title'         => $this->title,
            'task_code'     => $this->task_code,
            'task_type'         => $this->task_type ?: null,
            'important_notes'   => $this->important_notes ?: null,
            'unit_id'       => $this->unit_id,
            'pm_id'             => $this->pm_id,
            'assigned_admin_id' => $this->assigned_admin_id ?: null,
            'priority'          => $this->priority,
            'status'        => $this->status,
            'deadline'      => $this->deadline,
            'credit_amount' => $this->credit_amount,
        ];

        if ($this->editingId) {
            $existing = Task::findOrFail($this->editingId);
            $existing->update($data + $existing->completedAtFor($this->status));
            $this->dispatch('notify', message: 'Task updated.', type: 'success');
        } else {
            $task = Task::create($data + (new Task)->completedAtFor($this->status) + ['created_by' => auth()->id()]);
            $this->dispatch('notify', message: 'Task created.', type: 'success');

            // Notify all admins
            $admins = User::where('role', 'admin')->get();
            foreach ($admins as $admin) {
                $admin->notify(new NewTaskCreatedNotification($task));
            }
            $this->dispatch('notification-updated');
        }

        $this->showModal = false;
        $this->reset(['title', 'task_code', 'task_type', 'important_notes', 'unit_id', 'pm_id', 'assigned_admin_id', 'priority', 'status', 'deadline', 'credit_amount', 'editingId']);
    }

    // ── Kanban ────────────────────────────────────────────────────────────────

    /**
     * Move a card on the board.
     *
     * @param  string  $toStatus      the column the card was dropped into
     * @param  array   $columnOrders  [status => [task id, ...]] for every column
     *                                the drag touched, in their new visual order
     */
    public function moveTask(int $taskId, string $toStatus, array $columnOrders): void
    {
        $user = auth()->user();
        abort_unless($user->hasPermission('tasks.view'), 403);

        if (! array_key_exists($toStatus, Task::BOARD_COLUMNS)) {
            return;
        }

        // Scoped lookup, so a card outside the user's visibility cannot be
        // moved by hand-rolling the request.
        $task = $this->scopedTasks()->findOrFail($taskId);
        $fromStatus = $task->status;

        // Dragging is governed by exactly the rules that govern changing a
        // task's status today: crossing columns is a status change, reordering
        // inside one column is not and so only needs plain edit rights.
        //
        // TODO: column-level drag restrictions go here — e.g. only the assigned
        // supervisor may drop a card into Completed, or a writer may push their
        // own card as far as Sent for review but no further. Left open in this
        // pass; add a per-column check keyed on $toStatus once those rules are
        // settled.
        $ability = $fromStatus === $toStatus ? 'update' : 'updateStatus';
        abort_unless(Gate::allows($ability, $task), 403);

        DB::transaction(function () use ($task, $fromStatus, $toStatus, $columnOrders) {
            if ($fromStatus !== $toStatus) {
                $task->applyStatusChange($toStatus);
            }

            foreach ($columnOrders as $status => $orderedIds) {
                if (array_key_exists($status, Task::BOARD_COLUMNS)) {
                    $this->resequenceColumn($status, (array) $orderedIds);
                }
            }
        });

        if ($fromStatus === $toStatus) {
            return;
        }

        $task->pm?->notify(new TaskStatusUpdatedNotification($task));

        $this->dispatch('notification-updated');
        $this->dispatch(
            'notify',
            message: "Moved to " . Task::BOARD_COLUMNS[$toStatus]['label'] . ".",
            type: 'success',
        );
    }

    /**
     * Renumber a column so its tasks sit in the order the board just showed.
     * Only tasks the user can see are touched — another unit's cards keep the
     * positions their own PM gave them.
     */
    protected function resequenceColumn(string $status, array $orderedIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $orderedIds)));

        // Drop anything the payload named that is not actually in this column,
        // which is what a stale board sends after someone else moved a card.
        $inColumn = $this->scopedTasks()
            ->whereIn('id', $ids)
            ->where('status', $status)
            ->pluck('id')
            ->all();

        $position = 0;

        foreach ($ids as $id) {
            if (in_array($id, $inColumn, true)) {
                Task::whereKey($id)->update(['position' => $position++]);
            }
        }

        // Cards the board never sent — hidden by the filter bar, or past the
        // completed column's cap — keep their relative order behind the ones
        // the user just arranged.
        $remaining = $this->scopedTasks()
            ->where('status', $status)
            ->whereNotIn('id', $inColumn)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id');

        foreach ($remaining as $id) {
            Task::whereKey($id)->update(['position' => $position++]);
        }
    }

    /**
     * The four fixed columns with their cards, in board order.
     */
    protected function boardColumns(): array
    {
        $columns = [];

        foreach (Task::BOARD_COLUMNS as $status => $meta) {
            $query = $this->filteredTasks()
                ->where('status', $status)
                // Counted in the same query rather than loaded per card: the
                // board draws five columns, so a count read off the relation
                // would be three extra queries for every task on screen.
                ->withCount(['regularFiles', 'completedFiles', 'notes'])
                ->orderBy('position')
                ->orderBy('created_at');

            $total = (clone $query)->count();

            if ($status === 'completed') {
                $query->limit(self::COMPLETED_COLUMN_LIMIT);
            }

            $columns[$status] = $meta + [
                'status' => $status,
                'tasks'  => $query->get(),
                'total'  => $total,
            ];
        }

        return $columns;
    }

    // ── Queries ───────────────────────────────────────────────────────────────

    /**
     * Every task the current user is allowed to see, before any search or
     * filter is applied. This is the single place role scoping lives, so the
     * board, the table and the drag handler cannot drift apart.
     */
    protected function scopedTasks()
    {
        $user = auth()->user();

        return Task::query()
            ->when($user->isPm(), fn ($q) => $q->where('unit_id', $user->unit_id))
            ->when($user->isWriter(), fn ($q) => $q->whereHas('assignments', fn ($a) => $a->where('writer_id', $user->id)));
    }

    /**
     * Scoped tasks narrowed by the shared search and filter bar, which applies
     * identically across all three views.
     */
    protected function filteredTasks()
    {
        $user = auth()->user();

        return $this->scopedTasks()
            ->with(['unit', 'creator', 'pm', 'assignments.writer', 'assignedAdmin', 'files'])
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('title', 'like', "%{$this->search}%")
                  ->orWhere('task_code', 'like', "%{$this->search}%");
            }))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterPriority, fn ($q) => $q->where('priority', $this->filterPriority))
            // Filtering by unit is offered to whoever sees more than one: an
            // admin, and a supervisor, whose board spans the agency.
            ->when($this->filterUnit && $user->reachesEveryUnit(), fn ($q) => $q->where('unit_id', $this->filterUnit))
            ->when($this->filterTaskType, fn ($q) => $q->where('task_type', $this->filterTaskType));
    }

    public function confirmDelete(): void
    {
        $task = Task::with('files')->findOrFail($this->deletingId);

        // Deletion is its own permission and is not implied by tasks.update.
        abort_unless(Gate::allows('delete', $task), 403);

        $task->deleteWithFiles();
        $this->cancelDelete();
        $this->dispatch('notify', message: 'Task deleted.', type: 'success');
    }

    public function render()
    {
        $user = auth()->user();
        abort_unless($user->hasPermission('tasks.view'), 403);

        // The board carries the Completed column itself, so it needs its own
        // query; the list and table views keep the open-tasks-only behaviour
        // they have always had. Only the view actually on screen is queried.
        $board = $this->activeView === 'kanban' ? $this->boardColumns() : [];

        $tasks = $this->activeView === 'kanban'
            ? null
            : $this->filteredTasks()
                ->where('status', '!=', 'completed')
                ->orderBy($this->sortBy, $this->sortDir)
                ->paginate(15);

        $adminUsers = User::where('role', 'admin')->orderBy('name')->get();

        /*
         * The units this person may file work into.
         *
         * A supervisor belongs to no unit, so the narrowed branch handed them
         * an empty dropdown and made tasks.create unusable in the only screen
         * that offers it. Their reach is the agency, exactly as TaskPolicy
         * says, so the list is the agency's units.
         */
        $units = $user->reachesEveryUnit()
            ? Unit::orderBy('name')->get()
            : Unit::where('id', $user->unit_id)->get();

        // PMs for selected unit (admin sees dropdown; PM gets own record injected)
        $pmsForUnit = $this->unit_id
            ? User::where('role', 'pm')->where('unit_id', $this->unit_id)->orderBy('name')->get()
            : collect();

        return view('livewire.tasks.manage-tasks', compact('tasks', 'board', 'units', 'pmsForUnit', 'adminUsers') + [
            'maySetStatus' => $this->maySetStatus(),
            'lockedStatusLabel' => Task::BOARD_COLUMNS[$this->status]['label'] ?? ucfirst(str_replace('_', ' ', $this->status)),
        ])
            ->layout('layouts.app', ['pageTitle' => 'Tasks']);
    }
}
