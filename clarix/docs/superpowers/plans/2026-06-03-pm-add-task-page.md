# PM Add Task Page — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a dedicated `/tasks/create` page for PMs, plus a green "Add Task" button on the PM dashboard that links to it.

**Architecture:** New `CreateTask` Livewire full-page component registered at `tasks.create`. PM-only (403 for all other roles). On save, creates the task and redirects to `tasks.show`. The existing `ManageTasks` modal flow is untouched.

**Tech Stack:** Laravel 11, Livewire 3, Blade, Tailwind CSS, PHPUnit / `Livewire::test()`

---

## File Map

| Action | Path |
|--------|------|
| Modify | `routes/web.php` |
| Create | `app/Livewire/Tasks/CreateTask.php` |
| Create | `resources/views/livewire/tasks/create-task.blade.php` |
| Modify | `resources/views/dashboard/pm.blade.php` |
| Create | `tests/Feature/Tasks/CreateTaskTest.php` |

---

## Task 1 — Route + Component Skeleton + Access Tests

**Files:**
- Modify: `routes/web.php`
- Create: `app/Livewire/Tasks/CreateTask.php`
- Create: `resources/views/livewire/tasks/create-task.blade.php` (stub)
- Create: `tests/Feature/Tasks/CreateTaskTest.php`

- [ ] **Step 1: Write the failing access tests**

Create `tests/Feature/Tasks/CreateTaskTest.php`:

```php
<?php

namespace Tests\Feature\Tasks;

use App\Livewire\Tasks\CreateTask;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreateTaskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function makeUnit(): Unit
    {
        return Unit::create(['name' => 'Test Unit']);
    }

    private function makePm(Unit $unit): User
    {
        return User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makeWriter(Unit $unit): User
    {
        return User::factory()->create(['role' => 'writer', 'unit_id' => $unit->id]);
    }

    public function test_pm_can_access_create_task_page(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);

        $this->actingAs($pm)
            ->get(route('tasks.create'))
            ->assertOk();
    }

    public function test_admin_cannot_access_create_task_page(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('tasks.create'))
            ->assertForbidden();
    }

    public function test_writer_cannot_access_create_task_page(): void
    {
        $unit   = $this->makeUnit();
        $writer = $this->makeWriter($unit);

        $this->actingAs($writer)
            ->get(route('tasks.create'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('tasks.create'))
            ->assertRedirect(route('login'));
    }
}
```

- [ ] **Step 2: Run tests — confirm they fail**

```
php artisan test tests/Feature/Tasks/CreateTaskTest.php
```

Expected: all 4 tests fail (route not found / class not found).

- [ ] **Step 3: Register the route**

In `routes/web.php`, add the `use` import at the top with the other Task imports:

```php
use App\Livewire\Tasks\CreateTask;
```

Then in the `auth` middleware group, add the new route **before** `tasks.show` (it must come first so `create` isn't matched as a `{task}` slug):

```php
// Livewire full-page components
Route::get('/tasks', ManageTasks::class)->name('tasks.index');
Route::get('/tasks/create', CreateTask::class)->name('tasks.create');   // ← add this line
Route::get('/tasks/{task}', TaskDetail::class)->name('tasks.show');
```

- [ ] **Step 4: Create the `CreateTask` component**

Create `app/Livewire/Tasks/CreateTask.php`:

```php
<?php

namespace App\Livewire\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Notifications\NewTaskCreatedNotification;
use Livewire\Component;

class CreateTask extends Component
{
    public string $title = '';
    public string $task_code = '';
    public string $important_notes = '';
    public string $unit_id = '';
    public string $pm_id = '';
    public string $priority = 'medium';
    public string $deadline = '';
    public string $credit_amount = '0';
    public string $assigned_admin_id = '';

    public function mount(): void
    {
        if (!auth()->user()->isPm()) {
            abort(403);
        }
        $this->unit_id = (string) auth()->user()->unit_id;
        $this->pm_id   = (string) auth()->id();
    }

    public function save(): void
    {
        $unitId     = (string) auth()->user()->unit_id;
        $uniqueRule = "unique:tasks,task_code,NULL,id,unit_id,{$unitId}";

        $this->validate([
            'title'            => 'required|string|max:255',
            'task_code'        => ['required', 'string', 'max:50', $uniqueRule],
            'priority'         => 'required|in:low,medium,high',
            'deadline'         => 'required|date',
            'credit_amount'    => 'required|numeric|min:0',
            'important_notes'  => 'nullable|string|max:5000',
            'assigned_admin_id' => [
                'nullable',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    if ($value && User::find($value)?->role !== 'admin') {
                        $fail('The selected user must be an admin.');
                    }
                },
            ],
        ]);

        $task = Task::create([
            'title'             => $this->title,
            'task_code'         => $this->task_code,
            'important_notes'   => $this->important_notes ?: null,
            'unit_id'           => auth()->user()->unit_id,
            'pm_id'             => auth()->id(),
            'assigned_admin_id' => $this->assigned_admin_id ?: null,
            'priority'          => $this->priority,
            'status'            => 'pending',
            'deadline'          => $this->deadline,
            'credit_amount'     => $this->credit_amount,
            'created_by'        => auth()->id(),
        ]);

        foreach (User::where('role', 'admin')->get() as $admin) {
            $admin->notify(new NewTaskCreatedNotification($task));
        }

        $this->redirectRoute('tasks.show', $task);
    }

    public function render()
    {
        $adminUsers = User::where('role', 'admin')->orderBy('name')->get();

        return view('livewire.tasks.create-task', compact('adminUsers'))
            ->layout('layouts.app', ['pageTitle' => 'New Task']);
    }
}
```

- [ ] **Step 5: Create a minimal stub view**

Create `resources/views/livewire/tasks/create-task.blade.php`:

```blade
<div>
    <p>Create Task (stub)</p>
</div>
```

- [ ] **Step 6: Run the access tests — they should pass**

```
php artisan test tests/Feature/Tasks/CreateTaskTest.php
```

Expected output:
```
PASS  Tests\Feature\Tasks\CreateTaskTest
✓ pm can access create task page
✓ admin cannot access create task page
✓ writer cannot access create task page
✓ guest is redirected to login
```

- [ ] **Step 7: Commit**

```
git add routes/web.php app/Livewire/Tasks/CreateTask.php resources/views/livewire/tasks/create-task.blade.php tests/Feature/Tasks/CreateTaskTest.php
git commit -m "feat: add CreateTask component skeleton with PM-only access guard"
```

---

## Task 2 — Save Logic + Form Tests

**Files:**
- Modify: `tests/Feature/Tasks/CreateTaskTest.php`
- Modify: `resources/views/livewire/tasks/create-task.blade.php` (full form)

- [ ] **Step 1: Add the failing form tests**

Append these test methods to `CreateTaskTest`:

```php
    public function test_pm_can_create_task_and_is_redirected_to_task_detail(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);

        Livewire::actingAs($pm)
            ->test(CreateTask::class)
            ->set('title', 'My New Task')
            ->set('task_code', 'MNT_001')
            ->set('priority', 'medium')
            ->set('deadline', now()->addDays(7)->format('Y-m-d'))
            ->set('credit_amount', '2.50')
            ->call('save')
            ->assertRedirect(route('tasks.show', Task::first()));
    }

    public function test_task_is_created_with_correct_pm_unit_and_status(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);

        Livewire::actingAs($pm)
            ->test(CreateTask::class)
            ->set('title', 'My New Task')
            ->set('task_code', 'MNT_001')
            ->set('priority', 'medium')
            ->set('deadline', now()->addDays(7)->format('Y-m-d'))
            ->set('credit_amount', '2.50')
            ->call('save');

        $task = Task::first();
        $this->assertNotNull($task);
        $this->assertSame($pm->id, $task->pm_id);
        $this->assertSame($unit->id, $task->unit_id);
        $this->assertSame('pending', $task->status);
        $this->assertSame($pm->id, $task->created_by);
    }

    public function test_save_requires_title_task_code_deadline_and_credit_amount(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);

        Livewire::actingAs($pm)
            ->test(CreateTask::class)
            ->set('title', '')
            ->set('task_code', '')
            ->set('deadline', '')
            ->call('save')
            ->assertHasErrors(['title', 'task_code', 'deadline']);
    }

    public function test_task_code_must_be_unique_within_unit(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);

        Task::create([
            'title'         => 'Existing Task',
            'task_code'     => 'DUP_001',
            'unit_id'       => $unit->id,
            'pm_id'         => $pm->id,
            'created_by'    => $pm->id,
            'priority'      => 'medium',
            'status'        => 'pending',
            'deadline'      => now()->addDays(7),
            'credit_amount' => 1.00,
        ]);

        Livewire::actingAs($pm)
            ->test(CreateTask::class)
            ->set('title', 'Another Task')
            ->set('task_code', 'DUP_001')
            ->set('priority', 'medium')
            ->set('deadline', now()->addDays(7)->format('Y-m-d'))
            ->set('credit_amount', '1.00')
            ->call('save')
            ->assertHasErrors(['task_code']);
    }
```

- [ ] **Step 2: Run tests — confirm the new ones fail**

```
php artisan test tests/Feature/Tasks/CreateTaskTest.php
```

Expected: 4 pass (access tests), 4 fail (form tests — save not yet wired to view).

The `save()` method is already complete in the component from Task 1; the failures are because Livewire can't render the stub view properly when `save()` calls `assertRedirect`. The redirect tests should already pass from the component — confirm.

- [ ] **Step 3: Replace the stub view with the full form**

Replace the entire contents of `resources/views/livewire/tasks/create-task.blade.php`:

```blade
<div>
    <div class="max-w-2xl mx-auto py-8 px-4">

        {{-- Header --}}
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-slate-100">New Task</h1>
                <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">Create a new task for your unit.</p>
            </div>
            <a href="{{ route('dashboard') }}"
               class="text-sm text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200 transition-colors">
                ← Back to dashboard
            </a>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm">
            <form wire:submit="save" class="p-6 space-y-5">

                {{-- Title --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Title</label>
                    <input wire:model="title" type="text"
                        class="w-full border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-100 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('title') border-red-400 @enderror"
                        placeholder="Task title">
                    @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Task Code + Unit (read-only for PM) --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Task Code</label>
                        <input wire:model="task_code" type="text"
                            class="w-full border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-100 rounded-lg px-3 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('task_code') border-red-400 @enderror"
                            placeholder="e.g. CA_001">
                        @error('task_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Unit</label>
                        <input type="text" value="{{ auth()->user()->unit?->name ?? '—' }}" disabled
                            class="w-full border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-950/50 rounded-lg px-3 py-2.5 text-sm text-gray-500 dark:text-slate-400 cursor-not-allowed">
                    </div>
                </div>

                {{-- Responsible PM (read-only for PM) --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Responsible PM</label>
                    <div class="relative">
                        <input type="text" value="{{ auth()->user()->name }}" disabled
                            class="w-full border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-950/50 rounded-lg px-3 py-2.5 text-sm text-gray-500 dark:text-slate-400 cursor-not-allowed">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2">
                            <svg class="w-4 h-4 text-gray-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </span>
                    </div>
                    <p class="mt-1 text-[11px] text-gray-400 dark:text-slate-500">Automatically assigned to you.</p>
                </div>

                {{-- Assigned Supervisor --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Assigned Supervisor</label>
                    <select wire:model="assigned_admin_id"
                        class="w-full border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-100 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('assigned_admin_id') border-red-400 @enderror">
                        <option value="">Unassigned</option>
                        @foreach($adminUsers as $adminUser)
                            <option value="{{ $adminUser->id }}">{{ $adminUser->name }}</option>
                        @endforeach
                    </select>
                    @error('assigned_admin_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Important Notes --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Important Notes</label>
                    <textarea wire:model="important_notes" rows="3"
                        class="w-full border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-100 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                        placeholder="Optional instructions..."></textarea>
                </div>

                {{-- Priority + Deadline + Credits --}}
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Priority</label>
                        <select wire:model="priority"
                            class="w-full border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-100 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Deadline</label>
                        <input wire:model="deadline" type="date"
                            class="w-full border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-100 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('deadline') border-red-400 @enderror">
                        @error('deadline') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Credits</label>
                        <input wire:model="credit_amount" type="number" step="0.01" min="0"
                            class="w-full border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-100 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('credit_amount') border-red-400 @enderror"
                            placeholder="0.00">
                        @error('credit_amount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-100 dark:border-slate-800">
                    <a href="{{ route('dashboard') }}"
                       class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 rounded-lg transition-colors">
                        Cancel
                    </a>
                    <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 transition-colors shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Create Task
                    </button>
                </div>

            </form>
        </div>

    </div>
</div>
```

- [ ] **Step 4: Run all form tests — they should all pass**

```
php artisan test tests/Feature/Tasks/CreateTaskTest.php
```

Expected output:
```
PASS  Tests\Feature\Tasks\CreateTaskTest
✓ pm can access create task page
✓ admin cannot access create task page
✓ writer cannot access create task page
✓ guest is redirected to login
✓ pm can create task and is redirected to task detail
✓ task is created with correct pm unit and status
✓ save requires title task code deadline and credit amount
✓ task code must be unique within unit
```

- [ ] **Step 5: Commit**

```
git add tests/Feature/Tasks/CreateTaskTest.php resources/views/livewire/tasks/create-task.blade.php
git commit -m "feat: implement CreateTask form with validation and redirect"
```

---

## Task 3 — PM Dashboard Button

**Files:**
- Modify: `tests/Feature/Tasks/CreateTaskTest.php`
- Modify: `resources/views/dashboard/pm.blade.php`

- [ ] **Step 1: Write the failing dashboard button test**

Append to `CreateTaskTest`:

```php
    public function test_pm_dashboard_has_add_task_button_linking_to_create_page(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);

        $this->actingAs($pm)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('tasks.create'))
            ->assertSee('Add Task');
    }
```

- [ ] **Step 2: Run it — confirm it fails**

```
php artisan test tests/Feature/Tasks/CreateTaskTest.php::test_pm_dashboard_has_add_task_button_linking_to_create_page
```

Expected: FAIL — "Add Task" not found in PM dashboard.

- [ ] **Step 3: Add the green "Add Task" button to the PM dashboard**

In `resources/views/dashboard/pm.blade.php`, replace the `<div class="flex gap-3">` button group:

```blade
            <div class="flex gap-3">
                <a href="{{ route('tasks.create') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Task
                </a>
                <a href="{{ route('tasks.index') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    View Tasks
                </a>
                <a href="{{ route('credits.index') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-slate-300 bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                    Credits
                </a>
            </div>
```

- [ ] **Step 4: Run the full test suite**

```
php artisan test tests/Feature/Tasks/CreateTaskTest.php
```

Expected: all 9 tests pass.

- [ ] **Step 5: Run the existing task tests to catch regressions**

```
php artisan test tests/Feature/Tasks/
```

Expected: all tests pass, no regressions.

- [ ] **Step 6: Commit**

```
git add resources/views/dashboard/pm.blade.php tests/Feature/Tasks/CreateTaskTest.php
git commit -m "feat: add green Add Task button to PM dashboard"
```
