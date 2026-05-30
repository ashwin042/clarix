# Assigned Admin Field on Tasks

**Date:** 2026-05-30
**Status:** Approved

## Summary

Add an "Assigned To" field to tasks that references an admin user. Both admins and PMs can set this field when creating or editing a task. The assigned admin name is shown as a read-only column in the task list, visible only to admins.

## Database

**Migration:** `2026_05_30_000000_add_assigned_admin_id_to_tasks_table`

```php
Schema::table('tasks', function (Blueprint $table) {
    $table->foreignId('assigned_admin_id')->nullable()->after('pm_id')->constrained('users')->nullOnDelete();
});
```

- Nullable — tasks may have no assigned admin.
- `nullOnDelete()` — if the admin user is deleted, the column is set to null rather than cascading.

## Model

**`Task`:**
- Add `assigned_admin_id` to `$fillable`
- Add `assignedAdmin(): BelongsTo` → `User`, foreign key `assigned_admin_id`

## Livewire Component (`ManageTasks`)

**New form field:** `public string $assigned_admin_id = ''`

**Included in:**
- `reset()` calls in `openCreate()` and after `save()`
- `openEdit()` population from `$task->assigned_admin_id`

**Validation:**
```php
'assigned_admin_id' => ['nullable', 'exists:users,id', function ($attr, $value, $fail) {
    if ($value && User::find($value)?->role !== 'admin') {
        $fail('The selected user must be an admin.');
    }
}],
```

**Data passed to view:** `$adminUsers` — `User::where('role', 'admin')->orderBy('name')->get()`

**`render()` query:** eager-load `assignedAdmin` alongside existing relations.

## View — Modal Form

Placed after the "Responsible PM" field. Visible to admins and PMs (not writers — consistent with rest of the form).

```blade
<select wire:model="assigned_admin_id">
    <option value="">Unassigned</option>
    @foreach($adminUsers as $admin)
        <option value="{{ $admin->id }}">{{ $admin->name }}</option>
    @endforeach
</select>
```

## View — Task List Table

New column header and cell, both wrapped in `@if(auth()->user()->isAdmin())`:

- Header: `Assigned To` — between the PM column and Priority column.
- Cell: `{{ $task->assignedAdmin?->name ?? '—' }}`

## Out of Scope

- No change to the task detail page (`task-detail.blade.php`). The assigned admin is management metadata; it does not need prominent display on the detail view for this iteration.
- No notifications triggered by this assignment.
- No filter by assigned admin in the list view.
