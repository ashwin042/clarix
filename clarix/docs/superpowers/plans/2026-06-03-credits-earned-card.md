# Credits Earned Card Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an interactive "Credits Earned" stat card with a Today / This Month / Total toggle to both the writer and admin dashboards, backed by a dedicated `CreditsCard` Livewire component.

**Architecture:** A new `CreditsCard` Livewire component owns its own `$period` state and runs three lightweight `SUM` queries in isolation, so toggling the period only re-renders that one card — not the full dashboard. Credits are tracked via a new `completed_at` nullable timestamp on `tasks`, set whenever a task transitions to status `completed`.

**Tech Stack:** Laravel 11, Livewire 3, PHPUnit (RefreshDatabase), Tailwind CSS.

---

## File Map

| File | Action | Purpose |
|---|---|---|
| `database/migrations/YYYYMMDD_add_completed_at_to_tasks_table.php` | Create | Add `completed_at` nullable timestamp to tasks |
| `app/Models/Task.php` | Modify | Add `completed_at` to `$fillable` and casts |
| `app/Livewire/Tasks/TaskDetail.php` | Modify | Set `completed_at = now()` when `updateTaskStatus('completed')` |
| `app/Livewire/Tasks/ManageTasks.php` | Modify | Set/clear `completed_at` when status changes via edit form |
| `app/Services/CreditQueryBuilder.php` | Modify | Switch date filter from `updated_at` to `completed_at` |
| `tests/Unit/CreditQueryBuilderTest.php` | Modify | Update date-filter tests to use `completed_at` |
| `app/Livewire/Dashboard/CreditsCard.php` | Create | Livewire component: period state + credit sum query |
| `resources/views/livewire/dashboard/credits-card.blade.php` | Create | Card UI with amber number + 3-segment toggle |
| `tests/Feature/Dashboard/CreditsCardTest.php` | Create | Livewire tests for role scoping and period filtering |
| `resources/views/livewire/dashboard/writer-stats.blade.php` | Modify | Expand grid 4→5 cols, embed `<livewire:dashboard.credits-card role="writer">` |
| `resources/views/livewire/dashboard/admin-stats.blade.php` | Modify | Replace static credits `<div>` card with `<livewire:dashboard.credits-card role="admin">` |

---

## Task 1: Migration and Task Model

**Files:**
- Create: `database/migrations/` (use `php artisan make:migration`)
- Modify: `app/Models/Task.php`

- [ ] **Step 1: Generate the migration**

```bash
cd "D:/Desktop Files/Project Manage/clarix"
php artisan make:migration add_completed_at_to_tasks_table
```

Expected output: `Created Migration: database/migrations/YYYY_MM_DD_HHMMSS_add_completed_at_to_tasks_table.php`

- [ ] **Step 2: Write the migration**

Open the newly generated file and replace its contents:

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
            $table->timestamp('completed_at')->nullable()->after('deadline');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
```

- [ ] **Step 3: Run the migration**

```bash
php artisan migrate
```

Expected: `Running migrations... DONE`

- [ ] **Step 4: Update Task model**

In `app/Models/Task.php`, add `'completed_at'` to `$fillable` and add it to the `casts()` method:

In `$fillable`, after `'credit_amount'`:
```php
'credit_amount',
'completed_at',
```

In the `casts()` method, after `'deadline' => 'date'`:
```php
'deadline'     => 'date',
'completed_at' => 'datetime',
```

- [ ] **Step 5: Commit**

```bash
git add database/migrations/$(ls database/migrations | grep add_completed_at) app/Models/Task.php
git commit -m "feat: add completed_at timestamp to tasks table"
```

---

## Task 2: Set completed_at in TaskDetail

**Files:**
- Modify: `app/Livewire/Tasks/TaskDetail.php` — `updateTaskStatus()` method (~line 146)

- [ ] **Step 1: Update `updateTaskStatus` to set `completed_at`**

Find the `updateTaskStatus` method. Change:
```php
$this->task->update(['status' => $status]);
```
To:
```php
$updateData = ['status' => $status];
if ($status === 'completed') {
    $updateData['completed_at'] = now();
} elseif ($this->task->completed_at !== null) {
    $updateData['completed_at'] = null;
}
$this->task->update($updateData);
```

- [ ] **Step 2: Run existing tests to confirm nothing broken**

```bash
php artisan test tests/Feature/Tasks/TaskDetailRoleRestrictionsTest.php --ansi
```

Expected: all tests PASS

- [ ] **Step 3: Commit**

```bash
git add app/Livewire/Tasks/TaskDetail.php
git commit -m "feat: set completed_at when task status transitions to completed"
```

---

## Task 3: Set/clear completed_at in ManageTasks

**Files:**
- Modify: `app/Livewire/Tasks/ManageTasks.php` — `saveTask()` method (~line 151)

- [ ] **Step 1: Update `saveTask` to handle `completed_at`**

Find the edit block (the `if ($this->editingId)` branch, ~line 151). Change:

```php
if ($this->editingId) {
    Task::findOrFail($this->editingId)->update($data);
    $this->dispatch('notify', message: 'Task updated.', type: 'success');
```

To:

```php
if ($this->editingId) {
    $existing = Task::findOrFail($this->editingId);
    if ($this->status === 'completed' && $existing->status !== 'completed') {
        $data['completed_at'] = now();
    } elseif ($this->status !== 'completed' && $existing->completed_at !== null) {
        $data['completed_at'] = null;
    }
    $existing->update($data);
    $this->dispatch('notify', message: 'Task updated.', type: 'success');
```

- [ ] **Step 2: Run existing tests**

```bash
php artisan test tests/Feature/Tasks/ --ansi
```

Expected: all tests PASS

- [ ] **Step 3: Commit**

```bash
git add app/Livewire/Tasks/ManageTasks.php
git commit -m "feat: sync completed_at when task status edited via ManageTasks"
```

---

## Task 4: Update CreditQueryBuilder + its tests

**Files:**
- Modify: `app/Services/CreditQueryBuilder.php`
- Modify: `tests/Unit/CreditQueryBuilderTest.php`

- [ ] **Step 1: Update `CreditQueryBuilder` to use `completed_at`**

In `app/Services/CreditQueryBuilder.php`, change the two date filter blocks from `updated_at` to `completed_at`:

```php
if ($dateFrom) {
    $query->whereDate('completed_at', '>=', $dateFrom);
}

if ($dateTo) {
    $query->whereDate('completed_at', '<=', $dateTo);
}
```

- [ ] **Step 2: Update the existing date-filter tests**

In `tests/Unit/CreditQueryBuilderTest.php`, find `test_admin_filter_by_date_from` and `test_admin_filter_by_date_to`. Both currently manipulate `updated_at` via raw DB query. Update them to use `completed_at` instead.

Replace:
```php
public function test_admin_filter_by_date_from(): void
{
    $admin = $this->makeAdmin();
    $task  = $this->makeTask();
    $old   = $this->makeTask();
    DB::table('tasks')->where('id', $old->id)->update(['updated_at' => '2024-01-15 00:00:00']);

    $results = (new CreditQueryBuilder())
        ->build($admin, dateFrom: '2026-01-01')
        ->get();

    $this->assertCount(1, $results);
    $this->assertEquals($task->id, $results->first()->id);
}

public function test_admin_filter_by_date_to(): void
{
    $admin = $this->makeAdmin();
    $task  = $this->makeTask();
    DB::table('tasks')->where('id', $task->id)->update(['updated_at' => '2024-06-01 00:00:00']);
    $this->makeTask(); // recent, excluded

    $results = (new CreditQueryBuilder())
        ->build($admin, dateTo: '2024-12-31')
        ->get();

    $this->assertCount(1, $results);
    $this->assertEquals($task->id, $results->first()->id);
}
```

With:
```php
public function test_admin_filter_by_date_from(): void
{
    $admin = $this->makeAdmin();
    $task  = $this->makeTask(['completed_at' => now()]);
    $old   = $this->makeTask(['completed_at' => '2024-01-15 00:00:00']);

    $results = (new CreditQueryBuilder())
        ->build($admin, dateFrom: '2026-01-01')
        ->get();

    $this->assertCount(1, $results);
    $this->assertEquals($task->id, $results->first()->id);
}

public function test_admin_filter_by_date_to(): void
{
    $admin = $this->makeAdmin();
    $task  = $this->makeTask(['completed_at' => '2024-06-01 00:00:00']);
    $this->makeTask(['completed_at' => now()]); // recent, excluded

    $results = (new CreditQueryBuilder())
        ->build($admin, dateTo: '2024-12-31')
        ->get();

    $this->assertCount(1, $results);
    $this->assertEquals($task->id, $results->first()->id);
}
```

Also remove the `use Illuminate\Support\Facades\DB;` import if it's no longer used elsewhere in the file. Check by searching for other `DB::` usages in that file first.

- [ ] **Step 3: Run the CreditQueryBuilder tests**

```bash
php artisan test tests/Unit/CreditQueryBuilderTest.php --ansi
```

Expected: all tests PASS

- [ ] **Step 4: Commit**

```bash
git add app/Services/CreditQueryBuilder.php tests/Unit/CreditQueryBuilderTest.php
git commit -m "fix: CreditQueryBuilder date filters now use completed_at"
```

---

## Task 5: CreditsCard Livewire Component

**Files:**
- Create: `app/Livewire/Dashboard/CreditsCard.php`
- Create: `resources/views/livewire/dashboard/credits-card.blade.php`
- Create: `tests/Feature/Dashboard/CreditsCardTest.php`

- [ ] **Step 1: Write the failing tests first**

Create `tests/Feature/Dashboard/CreditsCardTest.php`:

```php
<?php

namespace Tests\Feature\Dashboard;

use App\Livewire\Dashboard\CreditsCard;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreditsCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makeWriter(): User
    {
        return User::factory()->create(['role' => 'writer']);
    }

    private function makeCompletedTask(array $overrides = []): Task
    {
        $unit  = Unit::create(['name' => 'Unit ' . uniqid()]);
        $admin = $this->makeAdmin();

        return Task::create(array_merge([
            'title'         => 'Test Task',
            'task_code'     => 'TC_' . uniqid(),
            'unit_id'       => $unit->id,
            'created_by'    => $admin->id,
            'priority'      => 'medium',
            'status'        => 'completed',
            'deadline'      => now()->addDays(7),
            'credit_amount' => 100.00,
            'completed_at'  => now(),
        ], $overrides));
    }

    public function test_defaults_to_total_period(): void
    {
        $admin = $this->makeAdmin();

        Livewire::actingAs($admin)
            ->test(CreditsCard::class, ['role' => 'admin'])
            ->assertSet('period', 'total');
    }

    public function test_set_period_updates_state(): void
    {
        $admin = $this->makeAdmin();

        Livewire::actingAs($admin)
            ->test(CreditsCard::class, ['role' => 'admin'])
            ->call('setPeriod', 'today')
            ->assertSet('period', 'today')
            ->call('setPeriod', 'month')
            ->assertSet('period', 'month')
            ->call('setPeriod', 'total')
            ->assertSet('period', 'total');
    }

    public function test_invalid_period_is_ignored(): void
    {
        $admin = $this->makeAdmin();

        Livewire::actingAs($admin)
            ->test(CreditsCard::class, ['role' => 'admin'])
            ->call('setPeriod', 'hacked')
            ->assertSet('period', 'total');
    }

    public function test_admin_total_sums_all_completed_credits(): void
    {
        $admin = $this->makeAdmin();
        $this->makeCompletedTask(['credit_amount' => 100.00]);
        $this->makeCompletedTask(['credit_amount' => 50.00]);
        $this->makeCompletedTask(['status' => 'pending', 'completed_at' => null]); // excluded

        Livewire::actingAs($admin)
            ->test(CreditsCard::class, ['role' => 'admin'])
            ->call('setPeriod', 'total')
            ->assertSee('150');
    }

    public function test_admin_today_only_counts_today_credits(): void
    {
        $admin = $this->makeAdmin();
        $this->makeCompletedTask(['credit_amount' => 80.00, 'completed_at' => now()]);
        $this->makeCompletedTask(['credit_amount' => 200.00, 'completed_at' => now()->subMonth()]);

        Livewire::actingAs($admin)
            ->test(CreditsCard::class, ['role' => 'admin'])
            ->call('setPeriod', 'today')
            ->assertSee('80');
    }

    public function test_admin_month_only_counts_this_month_credits(): void
    {
        $admin = $this->makeAdmin();
        $this->makeCompletedTask(['credit_amount' => 60.00, 'completed_at' => now()]);
        $this->makeCompletedTask(['credit_amount' => 40.00, 'completed_at' => now()->subMonths(2)]);

        Livewire::actingAs($admin)
            ->test(CreditsCard::class, ['role' => 'admin'])
            ->call('setPeriod', 'month')
            ->assertSee('60');
    }

    public function test_writer_only_sees_own_assigned_task_credits(): void
    {
        $writer = $this->makeWriter();
        $admin  = $this->makeAdmin();

        $myTask    = $this->makeCompletedTask(['credit_amount' => 100.00]);
        $otherTask = $this->makeCompletedTask(['credit_amount' => 999.00]);

        TaskAssignment::create([
            'task_id'     => $myTask->id,
            'writer_id'   => $writer->id,
            'assigned_by' => $admin->id,
            'status'      => 'completed',
        ]);

        Livewire::actingAs($writer)
            ->test(CreditsCard::class, ['role' => 'writer'])
            ->call('setPeriod', 'total')
            ->assertSee('100')
            ->assertDontSee('999');
    }

    public function test_writer_today_scoped_to_own_tasks(): void
    {
        $writer = $this->makeWriter();
        $admin  = $this->makeAdmin();

        $todayTask = $this->makeCompletedTask(['credit_amount' => 75.00, 'completed_at' => now()]);
        $oldTask   = $this->makeCompletedTask(['credit_amount' => 300.00, 'completed_at' => now()->subMonth()]);

        TaskAssignment::create([
            'task_id'     => $todayTask->id,
            'writer_id'   => $writer->id,
            'assigned_by' => $admin->id,
            'status'      => 'completed',
        ]);
        TaskAssignment::create([
            'task_id'     => $oldTask->id,
            'writer_id'   => $writer->id,
            'assigned_by' => $admin->id,
            'status'      => 'completed',
        ]);

        Livewire::actingAs($writer)
            ->test(CreditsCard::class, ['role' => 'writer'])
            ->call('setPeriod', 'today')
            ->assertSee('75')
            ->assertDontSee('300');
    }
}
```

- [ ] **Step 2: Run tests to confirm they fail (component doesn't exist yet)**

```bash
php artisan test tests/Feature/Dashboard/CreditsCardTest.php --ansi
```

Expected: FAIL — `Class "App\Livewire\Dashboard\CreditsCard" not found`

- [ ] **Step 3: Create the CreditsCard component**

Create `app/Livewire/Dashboard/CreditsCard.php`:

```php
<?php

namespace App\Livewire\Dashboard;

use App\Models\Task;
use Livewire\Component;

class CreditsCard extends Component
{
    public string $role;
    public string $period = 'total';

    public function setPeriod(string $period): void
    {
        if (!in_array($period, ['today', 'month', 'total'], true)) {
            return;
        }
        $this->period = $period;
    }

    public function render()
    {
        $query = Task::where('status', 'completed');

        if ($this->role === 'writer') {
            $query->whereHas('assignments', fn($q) => $q->where('writer_id', auth()->id()));
        }

        if ($this->period === 'today') {
            $query->whereDate('completed_at', today());
        } elseif ($this->period === 'month') {
            $query->whereMonth('completed_at', now()->month)
                  ->whereYear('completed_at', now()->year);
        }

        $credits = $query->sum('credit_amount');

        return view('livewire.dashboard.credits-card', [
            'credits' => $credits,
        ]);
    }
}
```

- [ ] **Step 4: Create the credits-card blade view**

Create `resources/views/livewire/dashboard/credits-card.blade.php`:

```blade
<div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm dark:shadow-none p-5">
    <div class="flex items-start justify-between mb-4">
        <div>
            <p class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Credits Earned</p>
            <p class="text-3xl font-bold text-amber-600 mt-1">{{ number_format($credits, 2) }}</p>
            <p class="text-xs text-gray-400 dark:text-slate-500 mt-1">
                @if($period === 'today') Today
                @elseif($period === 'month') This month
                @else All time
                @endif
            </p>
        </div>
        <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
            </svg>
        </div>
    </div>

    <div class="bg-gray-100 dark:bg-slate-800 rounded-lg p-0.5 flex gap-0.5">
        <button wire:click="setPeriod('today')"
            class="flex-1 text-xs font-medium py-1.5 rounded-md transition-colors {{ $period === 'today' ? 'bg-amber-500 text-white shadow-sm' : 'text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200' }}">
            Today
        </button>
        <button wire:click="setPeriod('month')"
            class="flex-1 text-xs font-medium py-1.5 rounded-md transition-colors {{ $period === 'month' ? 'bg-amber-500 text-white shadow-sm' : 'text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200' }}">
            This Month
        </button>
        <button wire:click="setPeriod('total')"
            class="flex-1 text-xs font-medium py-1.5 rounded-md transition-colors {{ $period === 'total' ? 'bg-amber-500 text-white shadow-sm' : 'text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200' }}">
            Total
        </button>
    </div>
</div>
```

- [ ] **Step 5: Run the tests again**

```bash
php artisan test tests/Feature/Dashboard/CreditsCardTest.php --ansi
```

Expected: all tests PASS

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Dashboard/CreditsCard.php \
        resources/views/livewire/dashboard/credits-card.blade.php \
        tests/Feature/Dashboard/CreditsCardTest.php
git commit -m "feat: add CreditsCard Livewire component with period toggle"
```

---

## Task 6: Embed CreditsCard in Writer Dashboard

**Files:**
- Modify: `resources/views/livewire/dashboard/writer-stats.blade.php`

- [ ] **Step 1: Expand the grid and add the credits card**

In `writer-stats.blade.php`, find the KPI grid opening tag (line 4):

```blade
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
```

Change to:
```blade
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
```

Then, directly after the closing `</div>` of the "Completed Tasks" card (after line 48, before the closing `</div>` of the grid), add:

```blade
        <livewire:dashboard.credits-card role="writer" />
```

The grid block should now contain 5 children: Assigned Tasks, Pending, Ready for Review, Completed Tasks, Credits Earned (the Livewire component).

- [ ] **Step 2: Verify the page loads without error**

```bash
php artisan route:list | grep dashboard
```

Open the writer dashboard in a browser (or run `php artisan test` for any dashboard smoke tests) and confirm no Blade/Livewire errors appear.

- [ ] **Step 3: Commit**

```bash
git add resources/views/livewire/dashboard/writer-stats.blade.php
git commit -m "feat: add Credits Earned card to writer dashboard"
```

---

## Task 7: Replace Static Credits Card in Admin Dashboard

**Files:**
- Modify: `resources/views/livewire/dashboard/admin-stats.blade.php`

- [ ] **Step 1: Replace the static credits card**

In `admin-stats.blade.php`, find the static "Credits Earned" card block (lines 38–47):

```blade
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm dark:shadow-none p-5 flex items-start justify-between">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-slate-400 uppercase tracking-wider">Credits Earned</p>
                <p class="text-3xl font-bold text-amber-600 mt-1">{{ number_format($stats['totalCredits']) }}</p>
                <p class="text-xs text-gray-400 dark:text-slate-500 mt-1">From completed tasks</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            </div>
        </div>
```

Replace the entire block with:

```blade
        <livewire:dashboard.credits-card role="admin" />
```

The grid stays at `lg:grid-cols-5` (no column count change needed).

- [ ] **Step 2: Remove the now-unused `$totalCredits` from AdminStats**

In `app/Livewire/Dashboard/AdminStats.php`, remove line 22:

```php
$totalCredits    = Task::where('status', 'completed')->sum('credit_amount');
```

And remove `'totalCredits'` from the `$stats` compact (line 27):

```php
$stats = compact('totalUsers', 'totalTasks', 'completedTasks', 'pendingTasks', 'totalUnits', 'completionRate');
```

- [ ] **Step 3: Run the full test suite**

```bash
php artisan test --ansi
```

Expected: all tests PASS

- [ ] **Step 4: Commit**

```bash
git add resources/views/livewire/dashboard/admin-stats.blade.php \
        app/Livewire/Dashboard/AdminStats.php
git commit -m "feat: replace static credits card with interactive CreditsCard on admin dashboard"
```

---

## Self-Review

**Spec coverage:**
- ✅ `completed_at` migration (Task 1)
- ✅ Set `completed_at` in TaskDetail (Task 2)
- ✅ Set/clear `completed_at` in ManageTasks (Task 3)
- ✅ CreditQueryBuilder updated to use `completed_at` (Task 4)
- ✅ CreditsCard component with period toggle (Task 5)
- ✅ Writer dashboard: 5-col grid + embedded card (Task 6)
- ✅ Admin dashboard: static card replaced (Task 7)
- ✅ Writer sees only own assigned task credits (Task 5 tests)
- ✅ Admin sees all credits (Task 5 tests)
- ✅ Historical tasks (null `completed_at`) appear in Total only (by design — Today/Month filters exclude NULLs automatically)

**Type consistency:**
- `$period` property: `string` — used consistently across component, blade, and tests
- `$role` property: `string` — passed as `role="writer"` / `role="admin"` in both dashboard embeds
- `setPeriod(string $period)` — called as `->call('setPeriod', 'today')` in tests ✅
- `credits-card` view name matches `livewire.dashboard.credits-card` ✅
