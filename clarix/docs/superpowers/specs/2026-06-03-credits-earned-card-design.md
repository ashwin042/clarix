# Credits Earned Card — Design Spec
Date: 2026-06-03

## Overview

Add an interactive "Credits Earned" stat card to both the writer and admin dashboards. The card displays total credits earned from completed tasks with a three-way period toggle (Today / This Month / Total) that updates the displayed amount via Livewire without a page reload.

---

## Data Model Change

### Migration: `add_completed_at_to_tasks_table`
- Add `completed_at` (nullable timestamp) to the `tasks` table.
- Existing completed tasks: leave `completed_at` as `null` (they predate this feature; they still appear under "Total" but not under time-filtered views).

### Setting `completed_at`
Two places in the codebase transition a task's status to `'completed'`:

1. `app/Livewire/Tasks/TaskDetail.php` — `updateTaskStatus()` method, line ~151:
   - When `$status === 'completed'`, also set `completed_at = now()` in the same `update()` call.
2. `app/Livewire/Tasks/ManageTasks.php` — `saveTask()` method, line ~152:
   - When `$data['status'] === 'completed'` and the task isn't already completed, add `completed_at => now()` to the update payload.
   - If status is being changed *away* from completed, set `completed_at = null`.

### `CreditQueryBuilder` service
Update `app/Services/CreditQueryBuilder.php` to filter on `completed_at` instead of `updated_at` for date range filtering. This affects the Finance dashboard too — a non-breaking improvement.

---

## New Component: `CreditsCard`

**Class:** `app/Livewire/Dashboard/CreditsCard.php`
**View:** `resources/views/livewire/dashboard/credits-card.blade.php`

### Props
| Property | Type | Default | Description |
|---|---|---|---|
| `$role` | string | required | `'writer'` or `'admin'` |
| `$period` | string | `'total'` | Active period: `'today'`, `'month'`, or `'total'` |

### Methods
- `setPeriod(string $period)` — validates input is one of the three allowed values, updates `$this->period`.

### Query logic

**Writer:**
```php
Task::where('status', 'completed')
    ->whereHas('assignments', fn($q) => $q->where('writer_id', auth()->id()))
    ->{period filter}
    ->sum('credit_amount')
```

**Admin:**
```php
Task::where('status', 'completed')
    ->{period filter}
    ->sum('credit_amount')
```

**Period filter:**
- `today` → `whereDate('completed_at', today())`
- `month` → `whereMonth('completed_at', now()->month)->whereYear('completed_at', now()->year)`
- `total` → no additional filter (includes all completed tasks, even those with null `completed_at`)

---

## UI

### Card layout
Matches existing stat card style:
- `bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-sm p-5`
- Amber color scheme (matches existing admin "Credits Earned" static card)
- Top: label ("Credits Earned"), large amber number, subtitle changes with period ("Today" / "This month" / "All time")
- Bottom: three-segment pill toggle

### Toggle
```
[ Today ][ This Month ][ Total ]
```
- Active segment: `bg-amber-500 text-white`
- Inactive: `text-gray-500 hover:text-gray-700`
- Container: `bg-gray-100 dark:bg-slate-800 rounded-lg p-0.5`
- Each button: `wire:click="setPeriod('today')"` etc.

### Grid changes
- **Writer dashboard** (`writer-stats.blade.php`): expand grid from `lg:grid-cols-4` to `lg:grid-cols-5`, add `<livewire:dashboard.credits-card role="writer" />` as the 5th card.
- **Admin dashboard** (`admin-stats.blade.php`): replace the existing static "Credits Earned" `<div>` card (currently 4th of 5 in the `lg:grid-cols-5` grid) with `<livewire:dashboard.credits-card role="admin" />`. Grid stays at 5 columns.

---

## Files Changed

| File | Change |
|---|---|
| `database/migrations/YYYY_MM_DD_add_completed_at_to_tasks_table.php` | New migration |
| `app/Livewire/Tasks/TaskDetail.php` | Set `completed_at` on status → completed |
| `app/Livewire/Tasks/ManageTasks.php` | Set/clear `completed_at` on status change |
| `app/Services/CreditQueryBuilder.php` | Switch date filter from `updated_at` to `completed_at` |
| `app/Models/Task.php` | Add `completed_at` to `$fillable` and casts |
| `app/Livewire/Dashboard/CreditsCard.php` | New Livewire component |
| `resources/views/livewire/dashboard/credits-card.blade.php` | New view |
| `resources/views/livewire/dashboard/writer-stats.blade.php` | Expand grid + embed card |
| `resources/views/livewire/dashboard/admin-stats.blade.php` | Replace static card with component |

---

## Edge Cases

- Tasks completed before this migration have `completed_at = null`. They count under "Total" but not Today/This Month. This is acceptable — historical data predates the tracking column.
- If a task is reverted from `completed` to another status (via ManageTasks edit form), `completed_at` is cleared to `null`.
- `$period` is validated server-side in `setPeriod()` to prevent manipulation.
