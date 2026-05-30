# Assigned Admin Task Field Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a nullable `assigned_admin_id` FK to tasks so both admins and PMs can assign an admin to a task, with a dropdown in the create/edit modal and an admin-only column in the task list.

**Architecture:** Standard Laravel/Livewire pattern — migration → model → component → view. Mirrors the existing `pm_id` field exactly. Tests use `Livewire::test()` with SQLite in-memory DB and the existing `PermissionSeeder` to grant PM role the `tasks.view` permission.

**Tech Stack:** Laravel 11, Livewire 3, Blade, Tailwind CSS, PHPUnit (SQLite in-memory)

---

## File Map

| File | Action |
|------|--------|
| `database/migrations/YYYY_MM_DD_HHMMSS_add_assigned_admin_id_to_tasks_table.php` | **Create** — new nullable FK column |
| `app/Models/Task.php` | **Modify** — add to `$fillable`, add `assignedAdmin()` relationship |
| `tests/Feature/Tasks/ManageTasksAssignedAdminTest.php` | **Create** — feature tests (write first, make them pass after) |
| `app/Livewire/Tasks/ManageTasks.php` | **Modify** — form field, validation, `$adminUsers` in render |
| `resources/views/livewire/tasks/manage-tasks.blade.php` | **Modify** — dropdown in modal, admin-only column in table |

---

## Task 1: Create and run the migration

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_add_assigned_admin_id_to_tasks_table.php`

- [ ] **Step 1: Generate the migration**

```bash
php artisan make:migration add_assigned_admin_id_to_tasks_table
```

This produces a file like `database/migrations/2026_05_30_XXXXXX_add_assigned_admin_id_to_tasks_table.php`. Open it.

- [ ] **Step 2: Fill in the migration body**

Replace the generated contents entirely:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('assigned_admin_id')->nullable()->after('pm_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['assigned_admin_id']);
            $table->dropColumn('assigned_admin_id');
        });
    }
};
```

- [ ] **Step 3: Run the migration**

```bash
php artisan migrate
```

Expected output contains:
```
... add_assigned_admin_id_to_tasks_table .... DONE
```

- [ ] **Step 4: Commit**

```bash
git add database/migrations/
git commit -m "feat: migration — add assigned_admin_id to tasks table"
```

---

## Task 2: Update the Task model

**Files:**
- Modify: `app/Models/Task.php`

- [ ] **Step 1: Add `assigned_admin_id` to `$fillable`**

Current `$fillable` ends with `'credit_amount'`. Add the new field after `'pm_id'`:

```php
protected $fillable = [
    'title',
    'task_code',
    'important_notes',
    'unit_id',
    'created_by',
    'pm_id',
    'assigned_admin_id',
    'priority',
    'status',
    'deadline',
    'credit_amount',
];
```

- [ ] **Step 2: Add the `assignedAdmin()` relationship**

After the existing `pm()` method (currently around line 46), add:

```php
public function assignedAdmin(): BelongsTo
{
    return $this->belongsTo(User::class, 'assigned_admin_id');
}
```

The `BelongsTo` import is already present at the top of the file.

- [ ] **Step 3: Commit**

```bash
git add app/Models/Task.php
git commit -m "feat: add assignedAdmin relationship to Task model"
```

---

## Task 3: Write the failing tests

**Files:**
- Create: `tests/Feature/Tasks/ManageTasksAssignedAdminTest.php`

- [ ] **Step 1: Create the test file**

Create the directory if needed (`tests/Feature/Tasks/`) and write the file:

```php
<?php

namespace Tests\Feature\Tasks;

use App\Livewire\Tasks\ManageTasks;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageTasksAssignedAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // PM role needs tasks.view permission for the component render() guard
        $this->seed(PermissionSeeder::class);
    }

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

    /** Minimum valid form fields for an admin saving a new task. */
    private function adminTaskFields(Unit $unit, User $pm): array
    {
        return [
            'title'         => 'Test Task',
            'task_code'     => 'TT_001',
            'unit_id'       => (string) $unit->id,
            'pm_id'         => (string) $pm->id,
            'priority'      => 'medium',
            'status'        => 'pending',
            'deadline'      => now()->addDays(7)->format('Y-m-d'),
            'credit_amount' => '1.00',
        ];
    }

    public function test_admin_can_save_task_with_assigned_admin(): void
    {
        $unit          = $this->makeUnit();
        $admin         = $this->makeAdmin();
        $pm            = $this->makePm($unit);
        $assignedAdmin = $this->makeAdmin();

        $this->actingAs($admin);

        $fields = $this->adminTaskFields($unit, $pm);
        $fields['assigned_admin_id'] = (string) $assignedAdmin->id;

        Livewire::test(ManageTasks::class)
            ->set($fields)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'task_code'         => 'TT_001',
            'assigned_admin_id' => $assignedAdmin->id,
        ]);
    }

    public function test_pm_can_save_task_with_assigned_admin(): void
    {
        $unit          = $this->makeUnit();
        $pm            = $this->makePm($unit);
        $assignedAdmin = $this->makeAdmin();

        $this->actingAs($pm);

        // PM's unit_id and pm_id are force-set in save(), so only supply the
        // fields the PM actually controls.
        Livewire::test(ManageTasks::class)
            ->set('assigned_admin_id', (string) $assignedAdmin->id)
            ->set('title', 'PM Task')
            ->set('task_code', 'PM_001')
            ->set('priority', 'medium')
            ->set('deadline', now()->addDays(7)->format('Y-m-d'))
            ->set('credit_amount', '1.00')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'task_code'         => 'PM_001',
            'assigned_admin_id' => $assignedAdmin->id,
        ]);
    }

    public function test_assigned_admin_id_is_optional(): void
    {
        $unit  = $this->makeUnit();
        $admin = $this->makeAdmin();
        $pm    = $this->makePm($unit);

        $this->actingAs($admin);

        Livewire::test(ManageTasks::class)
            ->set($this->adminTaskFields($unit, $pm))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'task_code'         => 'TT_001',
            'assigned_admin_id' => null,
        ]);
    }

    public function test_non_admin_user_is_rejected_as_assigned_admin(): void
    {
        $unit      = $this->makeUnit();
        $admin     = $this->makeAdmin();
        $pm        = $this->makePm($unit);
        $anotherPm = $this->makePm($unit);

        $this->actingAs($admin);

        $fields = $this->adminTaskFields($unit, $pm);
        $fields['assigned_admin_id'] = (string) $anotherPm->id;

        Livewire::test(ManageTasks::class)
            ->set($fields)
            ->call('save')
            ->assertHasErrors(['assigned_admin_id']);
    }

    public function test_admin_users_are_passed_to_view(): void
    {
        $admin      = $this->makeAdmin();
        $otherAdmin = $this->makeAdmin();
        $unit       = $this->makeUnit();
        $pm         = $this->makePm($unit);

        $this->actingAs($admin);

        Livewire::test(ManageTasks::class)
            ->assertViewHas('adminUsers', function ($adminUsers) use ($admin, $otherAdmin, $pm) {
                return $adminUsers->contains('id', $admin->id)
                    && $adminUsers->contains('id', $otherAdmin->id)
                    && ! $adminUsers->contains('id', $pm->id);
            });
    }

    public function test_edit_populates_assigned_admin_id(): void
    {
        $unit          = $this->makeUnit();
        $admin         = $this->makeAdmin();
        $pm            = $this->makePm($unit);
        $assignedAdmin = $this->makeAdmin();

        $task = Task::create([
            'title'             => 'Existing Task',
            'task_code'         => 'EX_001',
            'unit_id'           => $unit->id,
            'created_by'        => $admin->id,
            'pm_id'             => $pm->id,
            'priority'          => 'medium',
            'status'            => 'pending',
            'deadline'          => now()->addDays(7),
            'credit_amount'     => 1.00,
            'assigned_admin_id' => $assignedAdmin->id,
        ]);

        $this->actingAs($admin);

        Livewire::test(ManageTasks::class)
            ->call('openEdit', $task)
            ->assertSet('assigned_admin_id', (string) $assignedAdmin->id);
    }
}
```

- [ ] **Step 2: Run the tests — confirm they fail**

```bash
php artisan test tests/Feature/Tasks/ManageTasksAssignedAdminTest.php
```

Expected: errors like `Property [assigned_admin_id] not found on component` or `Undefined variable $adminUsers`, confirming the component hasn't been updated yet.

- [ ] **Step 3: Commit the failing tests**

```bash
git add tests/Feature/Tasks/ManageTasksAssignedAdminTest.php
git commit -m "test: add failing tests for assigned_admin_id on ManageTasks"
```

---

## Task 4: Update the ManageTasks Livewire component

**Files:**
- Modify: `app/Livewire/Tasks/ManageTasks.php`

- [ ] **Step 1: Add the form field property**

After line 35 (`public string $credit_amount = '0';`), add:

```php
public string $assigned_admin_id = '';
```

- [ ] **Step 2: Include it in both `reset()` calls**

In `openCreate()` (the `$this->reset([...])` call), update to:

```php
$this->reset(['title', 'task_code', 'important_notes', 'unit_id', 'pm_id', 'assigned_admin_id', 'priority', 'status', 'deadline', 'credit_amount', 'editingId']);
```

At the bottom of `save()` (the second `$this->reset([...])` call after `$this->showModal = false;`), update to:

```php
$this->reset(['title', 'task_code', 'important_notes', 'unit_id', 'pm_id', 'assigned_admin_id', 'priority', 'status', 'deadline', 'credit_amount', 'editingId']);
```

- [ ] **Step 3: Populate it in `openEdit()`**

After the line `$this->credit_amount = (string) $task->credit_amount;` (currently line 83), add:

```php
$this->assigned_admin_id = (string) ($task->assigned_admin_id ?? '');
```

- [ ] **Step 4: Add the validation rule**

In the `$this->validate([...])` array in `save()`, add after the `'important_notes'` rule:

```php
'assigned_admin_id' => [
    'nullable',
    'exists:users,id',
    function ($attribute, $value, $fail) {
        if ($value && User::find($value)?->role !== 'admin') {
            $fail('The selected user must be an admin.');
        }
    },
],
```

- [ ] **Step 5: Include it in the `$data` array**

In `save()`, add to `$data` after the `'pm_id'` line:

```php
'assigned_admin_id' => $this->assigned_admin_id ?: null,
```

- [ ] **Step 6: Pass `$adminUsers` to the view and eager-load `assignedAdmin`**

In `render()`, update the `Task::with(...)` call to eager-load `assignedAdmin`:

```php
$query = Task::with(['unit', 'creator', 'pm', 'assignments.writer', 'assignedAdmin'])
```

Add `$adminUsers` just before the `return view(...)` call:

```php
$adminUsers = User::where('role', 'admin')->orderBy('name')->get();
```

Update `compact(...)` in the return statement:

```php
return view('livewire.tasks.manage-tasks', compact('tasks', 'units', 'pmsForUnit', 'adminUsers'))
    ->layout('layouts.app', ['pageTitle' => 'Tasks']);
```

- [ ] **Step 7: Run the tests**

```bash
php artisan test tests/Feature/Tasks/ManageTasksAssignedAdminTest.php
```

Expected: all 6 tests pass.

- [ ] **Step 8: Commit**

```bash
git add app/Livewire/Tasks/ManageTasks.php
git commit -m "feat: add assigned_admin_id field to ManageTasks component"
```

---

## Task 5: Update the blade view

**Files:**
- Modify: `resources/views/livewire/tasks/manage-tasks.blade.php`

- [ ] **Step 1: Add the "Assigned To" dropdown in the modal**

In the modal form, locate the closing `@endif` and `@error` block for the PM field (ends around line 215). Immediately after it, add a new form group:

```blade
{{-- Assigned To --}}
<div>
    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Assigned To</label>
    <select wire:model="assigned_admin_id"
        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('assigned_admin_id') border-red-400 @enderror">
        <option value="">Unassigned</option>
        @foreach($adminUsers as $adminUser)
            <option value="{{ $adminUser->id }}">{{ $adminUser->name }}</option>
        @endforeach
    </select>
    @error('assigned_admin_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
</div>
```

- [ ] **Step 2: Add the "Assigned To" column header**

In `<thead>`, after the `<th>` for PM (the one with text "PM", currently line 63), add:

```blade
@if(auth()->user()->isAdmin())
<th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Assigned To</th>
@endif
```

- [ ] **Step 3: Add the "Assigned To" table cell**

In `<tbody>`, after the PM cell (`<td>` with `{{ $task->pm?->name ?? '—' }}`, currently line 89), add:

```blade
@if(auth()->user()->isAdmin())
<td class="px-5 py-3 text-sm text-gray-600 dark:text-slate-400">{{ $task->assignedAdmin?->name ?? '—' }}</td>
@endif
```

- [ ] **Step 4: Run the full test suite**

```bash
php artisan test
```

Expected: all tests pass, including the 6 new ones.

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/tasks/manage-tasks.blade.php
git commit -m "feat: add Assigned To dropdown in task modal and admin-only list column"
```
