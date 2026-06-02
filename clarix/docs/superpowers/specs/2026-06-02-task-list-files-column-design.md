# Task List — Files Column & Unit Column Removal

**Date:** 2026-06-02  
**Status:** Approved

## Overview

Remove the Unit column from the PM task list view and add a Files column after the Writers column. The Files column shows uploaded file attachments inline, similar to Linear/Notion. Empty cells offer a direct upload trigger; populated cells show a count with a popover file list and download links.

---

## Column Changes

### Remove

- `Unit` `<th>` header
- All `Unit` `<td>` cells (`{{ $task->unit->name }}`)

The Unit value is already shown on the task detail page and is available via the Unit filter dropdown in the list header, so removing the column reduces visual clutter without data loss.

### Add

- `Files` `<th>` header, positioned after the `Writers` column
- `Files` `<td>` cell per row (see Cell Behavior below)

### Eager-load change

Add `files` to the `with([...])` call in `ManageTasks::render()`:

```php
Task::with(['unit', 'creator', 'pm', 'assignments.writer', 'assignedAdmin', 'files'])
```

---

## Files Cell Behavior

### No files + user can upload (`@can('uploadFiles', $task)`)

- Renders a subtle paperclip/upload SVG icon in muted gray (`text-gray-400`)
- On hover: shows a light gray background pill
- On click: dispatches an Alpine `open-upload-modal` window event with payload `{ uploadUrl: '{{route(tasks.files.store, task)}}' }`
- Policy: `uploadFiles` allows `admin` and `pm` (of the task's unit); writer cannot upload — already enforced by `TaskPolicy::uploadFiles`

### No files + user cannot upload (writer role)

- Renders `—` (em dash), same style as other empty cells

### Files exist (any role with view access)

- Renders a document SVG icon + count in indigo (e.g. `📄 3` style using inline SVG + badge)
- On click: toggles an Alpine inline popover anchored to the cell
- Popover closes on `@click.away`
- Popover lists each file:
  - Truncated filename
  - Formatted file size (`file_size_formatted` accessor)
  - Download link → `route('tasks.files.download', [$task, $file])` (existing route, existing auth enforcement)
- Writers can see the count and download files they are assigned to — the route already enforces this

---

## Shared Upload Modal

A single Alpine-driven upload modal lives at the bottom of `manage-tasks.blade.php`, inside the root `<div>`.

### State

```js
x-data="{
    show: false,
    uploadUrl: '',
    dragging: false,
    fileObjects: [],
    // ... same helpers as task-detail upload modal
}"
x-on:open-upload-modal.window="uploadUrl = $event.detail.uploadUrl; show = true"
```

### Form

```html
<form :action="uploadUrl" method="POST" enctype="multipart/form-data">
    @csrf
    <!-- drag & drop zone, file preview list — identical markup to task detail upload modal -->
    <!-- no upload_note field (lean for list context) -->
</form>
```

### Redirect behavior

`TaskFileController::store()` changes its final redirect from:

```php
return redirect()->route('tasks.show', $task)->with('success', 'Files uploaded.');
```

to:

```php
return redirect()->back()->with('success', 'Files uploaded.');
```

This is safe: `redirect()->back()` uses the HTTP `Referer` header. When submitting from the list page, it reloads the list. When submitting from the task detail page, it reloads the detail. Both contexts already work correctly.

---

## Create Task Modal

The informational note `"Files can be uploaded from the task detail page after creation."` remains unchanged — it still applies because a task must be created before files can be attached.

---

## Affected Files

| File | Change |
|---|---|
| `resources/views/livewire/tasks/manage-tasks.blade.php` | Remove Unit column; add Files column header + cells; add shared upload modal |
| `app/Livewire/Tasks/ManageTasks.php` | Add `files` to eager-load in `render()` |
| `app/Http/Controllers/TaskFileController.php` | Change `store()` redirect to `redirect()->back()` |

---

## Out of Scope

- File deletion from the list view (detail page only)
- Livewire-based no-reload upload (future consideration)
- Changing the `uploadFiles` policy rules
