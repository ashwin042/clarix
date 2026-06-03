# PM Add Task — Dedicated Creation Page

**Date:** 2026-06-03
**Status:** Approved

## Summary

Add a green "Add Task" button to the PM dashboard that navigates to a new dedicated task creation page. On successful submission the PM is redirected to the newly created task's detail page.

## Route

| Method | URI | Name | Component |
|--------|-----|------|-----------|
| GET | `/tasks/create` | `tasks.create` | `App\Livewire\Tasks\CreateTask` |

The route is added to `routes/web.php` alongside the existing `tasks.*` routes and protected by the same `auth` middleware. Role enforcement (PM only) is handled inside the component — admins and writers are both rejected with 403, as admins already have the full `ManageTasks` modal flow.

## CreateTask Component

**Location:** `app/Livewire/Tasks/CreateTask.php`
**View:** `resources/views/livewire/tasks/create-task.blade.php`

### Form fields

| Field | Type | PM behaviour | Admin behaviour |
|-------|------|-------------|----------------|
| title | text | editable | editable |
| task_code | text | editable | editable |
| important_notes | textarea | editable | editable |
| unit_id | hidden | locked to own unit (not rendered) |
| pm_id | hidden | locked to own id (not rendered) |
| priority | select (low/medium/high) | editable, default medium |
| deadline | date | editable |
| credit_amount | number | editable, default 0 |
| assigned_admin_id | select | editable |

`unit_id` and `pm_id` are set in `mount()` to the authenticated PM's values and are not rendered — they are enforced server-side in `save()` (same pattern as `ManageTasks`).

### Lifecycle

```
mount()  → enforce unit_id/pm_id for PM role
save()   → validate → Task::create() → redirect(route('tasks.show', $task))
cancel() → redirect(route('dashboard'))  // back button / cancel link
```

Validation rules are the same subset used by `ManageTasks::save()` for the create path.

### Authorization

```php
if (!auth()->user()->isPm()) {
    abort(403);
}
```

## PM Dashboard Button

File: `resources/views/dashboard/pm.blade.php`

Add a green anchor button **before** "View Tasks" in the existing `flex gap-3` button group:

```html
<a href="{{ route('tasks.create') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors">
    <!-- plus icon -->
    Add Task
</a>
```

Button order (left → right): **Add Task** (green) · View Tasks (indigo) · Credits (white/grey).

## What Is Not Changed

- `ManageTasks` and its modal flow are untouched.
- No changes to existing routes, middleware groups, or policies.
- The "New Task" button on `tasks.index` continues to work as before.
