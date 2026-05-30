# Task Detail PM Restrictions & Cleanup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restrict task status changes to admins only, add an "Assigned To" field in the task detail view, and remove the Priority column from the task list table.

**Architecture:** Pure view/component changes across three blade files and one Livewire component. No migrations needed. Tests cover the backend status-guard change and the Assigned To render. Priority column removal is blade-only with no test needed (no logic involved).

**Tech Stack:** Laravel 11, Livewire 3, Blade, PHPUnit (SQLite in-memory)

---

## File Map

| File | Action |
|------|--------|
| `tests/Feature/Tasks/TaskDetailRoleRestrictionsTest.php` | **Create** — failing tests first |
| `app/Livewire/Tasks/TaskDetail.php` | **Modify** — tighten status guard; add `assignedAdmin` to eager loads |
| `resources/views/livewire/tasks/task-detail.blade.php` | **Modify** — status dropdown admin-only; add Assigned To field |
| `resources/views/livewire/tasks/manage-tasks.blade.php` | **Modify** — remove Priority column header and cell |

---

## Task 1: Write failing tests

**Files:**
- Create: `tests/Feature/Tasks/TaskDetailRoleRestrictionsTest.php`

- [ ] **Step 1: Create the test file**

```php
<?php

namespace Tests\Feature\Tasks;

use App\Livewire\Tasks\TaskDetail;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskDetailRoleRestrictionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUnit(): Unit
    {
        return Unit::create(['name' => 'Test Unit']);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makePm(Unit $unit): User
    {
        return User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
    }

    private function makeTask(Unit $unit, User $creator, User $pm, ?int $assignedAdminId = null): Task
    {
        return Task::create([
            'title'             => 'Test Task',
            'task_code'         => 'TT_001',
            'unit_id'           => $unit->id,
            'created_by'        => $creator->id,
            'pm_id'             => $pm->id,
            'priority'          => 'medium',
            'status'            => 'pending',
            'deadline'          => now()->addDays(7),
            'credit_amount'     => 1.00,
            'assigned_admin_id' => $assignedAdminId,
        ]);
    }

    public function test_admin_can_update_task_status(): void
    {
        $unit  = $this->makeUnit();
        $admin = $this->makeAdmin();
        $pm    = $this->makePm($unit);
        $task  = $this->makeTask($unit, $admin, $pm);

        $this->actingAs($admin);

        Livewire::test(TaskDetail::class, ['task' => $task])
            ->call('updateTaskStatus', 'completed')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'completed']);
    }

    public function test_pm_cannot_update_task_status(): void
    {
        $unit  = $this->makeUnit();
        $admin = $this->makeAdmin();
        $pm    = $this->makePm($unit);
        $task  = $this->makeTask($unit, $admin, $pm);

        $this->actingAs($pm);

        Livewire::test(TaskDetail::class, ['task' => $task])
            ->call('updateTaskStatus', 'completed')
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'pending']);
    }

    public function test_assigned_admin_name_shown_in_details(): void
    {
        $unit          = $this->makeUnit();
        $admin         = $this->makeAdmin();
        $pm            = $this->makePm($unit);
        $assignedAdmin = $this->makeAdmin();
        $task          = $this->makeTask($unit, $admin, $pm, $assignedAdmin->id);

        $this->actingAs($admin);

        Livewire::test(TaskDetail::class, ['task' => $task])
            ->assertSee($assignedAdmin->name)
            ->assertSee('Assigned To');
    }

    public function test_unassigned_task_shows_dash_for_assigned_to(): void
    {
        $unit  = $this->makeUnit();
        $admin = $this->makeAdmin();
        $pm    = $this->makePm($unit);
        $task  = $this->makeTask($unit, $admin, $pm);

        $this->actingAs($admin);

        Livewire::test(TaskDetail::class, ['task' => $task])
            ->assertSee('Assigned To');
    }
}
```

- [ ] **Step 2: Run to confirm they fail**

```bash
php artisan test tests/Feature/Tasks/TaskDetailRoleRestrictionsTest.php
```

Expected: `test_pm_cannot_update_task_status` passes (PM currently allowed — wait, it currently ISN'T forbidden, so the assertForbidden will fail). `test_assigned_admin_name_shown_in_details` fails because "Assigned To" isn't in the view yet.

- [ ] **Step 3: Commit failing tests**

```bash
git add tests/Feature/Tasks/TaskDetailRoleRestrictionsTest.php
git commit -m "test: add failing tests for TaskDetail role restrictions and assigned-to field"
```

---

## Task 2: Update the TaskDetail component

**Files:**
- Modify: `app/Livewire/Tasks/TaskDetail.php`

- [ ] **Step 1: Tighten the status guard to admin-only**

In `updateTaskStatus()` (around line 117), change:

```php
// Before
if (!auth()->user()->isAdmin() && !auth()->user()->isPm()) {
    abort(403);
}
```

To:

```php
// After
if (!auth()->user()->isAdmin()) {
    abort(403);
}
```

- [ ] **Step 2: Add `assignedAdmin` to the `mount()` eager load**

In `mount()` (line 29), update the load call:

```php
$this->task = $task->load(['unit', 'creator', 'pm', 'assignedAdmin', 'assignments.writer', 'files.uploader', 'notes.author']);
```

- [ ] **Step 3: Add `assignedAdmin` to the `render()` eager load**

In `render()` (line 146), update the load call:

```php
$this->task->load(['unit', 'creator', 'assignedAdmin', 'assignments.writer', 'files.uploader', 'notes' => fn ($q) => $q->with('author')->latest()]);
```

- [ ] **Step 4: Run the tests**

```bash
php artisan test tests/Feature/Tasks/TaskDetailRoleRestrictionsTest.php
```

Expected: `test_admin_can_update_task_status` passes, `test_pm_cannot_update_task_status` passes. The two "Assigned To" tests still fail because the blade hasn't been updated yet.

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/Tasks/TaskDetail.php
git commit -m "feat: restrict task status updates to admin only"
```

---

## Task 3: Update task-detail blade

**Files:**
- Modify: `resources/views/livewire/tasks/task-detail.blade.php`

- [ ] **Step 1: Restrict the status dropdown to admin only**

Find the status quick-change block (around line 27):

```blade
{{-- Status quick-change (admin/pm) --}}
@if(!auth()->user()->isWriter())
```

Change to:

```blade
{{-- Status quick-change (admin only) --}}
@if(auth()->user()->isAdmin())
```

- [ ] **Step 2: Add the "Assigned To" field in the Details section**

In the Details `<dl>` grid (around line 49–70), after the "Responsible PM" `<div>` block, add:

```blade
<div>
    <dt class="text-xs text-gray-400 dark:text-slate-500 mb-1">Assigned To</dt>
    <dd class="text-sm font-medium text-gray-800 dark:text-slate-200">{{ $task->assignedAdmin?->name ?? '—' }}</dd>
</div>
```

The full updated `<dl>` block should read:

```blade
<dl class="grid grid-cols-3 gap-4">
    <div>
        <dt class="text-xs text-gray-400 dark:text-slate-500 mb-1">Responsible PM</dt>
        <dd class="text-sm font-medium text-gray-800 dark:text-slate-200">{{ $task->pm?->name ?? '—' }}</dd>
    </div>
    <div>
        <dt class="text-xs text-gray-400 dark:text-slate-500 mb-1">Assigned To</dt>
        <dd class="text-sm font-medium text-gray-800 dark:text-slate-200">{{ $task->assignedAdmin?->name ?? '—' }}</dd>
    </div>
    <div>
        <dt class="text-xs text-gray-400 dark:text-slate-500 mb-1">Created by</dt>
        <dd class="text-sm text-gray-800 dark:text-slate-200">{{ $task->creator->name }}</dd>
    </div>
    <div>
        <dt class="text-xs text-gray-400 dark:text-slate-500 mb-1">Credit Amount</dt>
        <dd class="text-sm font-semibold text-gray-800 dark:text-slate-200">{{ number_format($task->credit_amount) }}</dd>
    </div>
    <div>
        <dt class="text-xs text-gray-400 dark:text-slate-500 mb-1">Code</dt>
        <dd class="text-sm font-mono text-gray-700 dark:text-slate-300">{{ $task->task_code }}</dd>
    </div>
    <div>
        <dt class="text-xs text-gray-400 dark:text-slate-500 mb-1">Unit</dt>
        <dd class="text-sm text-gray-700 dark:text-slate-300">{{ $task->unit->name }}</dd>
    </div>
</dl>
```

- [ ] **Step 3: Run the tests**

```bash
php artisan test tests/Feature/Tasks/TaskDetailRoleRestrictionsTest.php
```

Expected: all 4 tests pass.

- [ ] **Step 4: Commit**

```bash
git add resources/views/livewire/tasks/task-detail.blade.php
git commit -m "feat: restrict status dropdown to admins, add Assigned To field in task detail"
```

---

## Task 4: Remove Priority column from task list

**Files:**
- Modify: `resources/views/livewire/tasks/manage-tasks.blade.php`

- [ ] **Step 1: Remove the Priority column header**

In `<thead>`, remove this block entirely:

```blade
<th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Priority</th>
```

- [ ] **Step 2: Remove the Priority cell from each table row**

In `<tbody>`, remove this block entirely from inside the `@foreach`:

```blade
<td class="px-5 py-3">
    @php
        $pc = match($task->priority) { 'high' => 'bg-red-100 text-red-700', 'medium' => 'bg-yellow-100 text-yellow-700', 'low' => 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-400' };
    @endphp
    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $pc }}">{{ ucfirst($task->priority) }}</span>
</td>
```

- [ ] **Step 3: Run the full test suite**

```bash
php artisan test
```

Expected: all 4 new tests pass, 6 previously written assigned-admin tests pass, no regressions in the rest of the suite (pre-existing auth/profile failures are unrelated).

- [ ] **Step 4: Commit**

```bash
git add resources/views/livewire/tasks/manage-tasks.blade.php
git commit -m "feat: remove Priority column from task list table"
```
