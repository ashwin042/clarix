# Completed File Section — Design Spec

**Date:** 2026-06-02
**Status:** Approved

---

## Overview

Add a "Completed Files" section to the task detail page, separate from the existing general Files section. Writers and admins can upload completed files there; uploading automatically marks all writer assignments as `ready_for_review` and notifies the PM and assigned admin. PMs cannot upload and cannot see the files until an admin marks the task as `completed`.

---

## 1. Data Layer

### Migration

New migration adds one column to the existing `task_files` table:

```
is_completed_file  boolean  default false  not null
```

No new table. All file records (regular and completed) live in `task_files`; the flag distinguishes them.

### `TaskFile` model

- Add `is_completed_file` to `$fillable`.
- Add two local scopes:
  - `scopeRegular(Builder $query)` → `where('is_completed_file', false)`
  - `scopeCompleted(Builder $query)` → `where('is_completed_file', true)`

### `Task` model

Add two focused relations that replace raw `files()` calls in both views:

- `regularFiles()` → `hasMany(TaskFile::class)->regular()`
- `completedFiles()` → `hasMany(TaskFile::class)->completed()`

The existing `files()` relation is kept unchanged for any other callers (e.g. the task list popover).

---

## 2. Authorization

### `TaskPolicy` — new method

```php
public function uploadCompletedFile(User $user, Task $task): bool
{
    return match ($user->role) {
        'admin'  => true,
        'writer' => $task->assignments()->where('writer_id', $user->id)->exists(),
        default  => false,   // pm and any other role: denied
    };
}
```

### Download authorization

The existing `TaskFileController@download` gains an early branch for completed files:

```
if $file->is_completed_file:
    admin        → always allowed
    writer       → allowed if has assignment on task
    pm           → allowed only if $task->status === 'completed'
    else         → 403
```

Regular file download logic is unchanged.

---

## 3. Upload Flow & Side Effects

### New route

```
POST  tasks/{task}/completed-files   → TaskFileController@storeCompleted
name: tasks.completed-files.store
```

Sits alongside the existing `tasks.files.store` route in the same auth middleware group.

### `TaskFileController@storeCompleted`

1. Authorize with `uploadCompletedFile` policy.
2. Reuse `UploadTaskFilesRequest` for validation (max 10 MB per file, etc.).
3. For each file: store on R2 under `task-files/{task_code}/completed/`, create `TaskFile` with `is_completed_file = true`.
4. **Flip all writer assignments** to `ready_for_review`:
   ```php
   $task->assignments()->update(['status' => 'ready_for_review']);
   ```
5. **Notify** `$task->pm` and `$task->assignedAdmin` (if set) via new `CompletedFileUploadedNotification`.
6. Redirect back with `success` flash.

No `upload_note` field — the notification provides context.

### `CompletedFileUploadedNotification`

New notification class. Message: *"A completed file has been uploaded for [task code] and is awaiting your review."* Uses the existing notification infrastructure (stored in `notifications` table, rendered by the notification bell).

### `TaskDetail` Livewire component

- Add `public bool $showCompletedUploadModal = false`.
- Add `openCompletedUploadModal(): void` — resets error bag, sets flag true.
- `mount()` and `render()` load `completedFiles().uploader` relation alongside existing relations.

---

## 4. Task Detail UI

### "Completed Files" card

Placed directly below the existing Files card inside the `col-span-2` main column.

**Header:** "Completed Files" with a count badge (count of completed files). Upload button (`@can('uploadCompletedFile', $task)`) — writers and admins see it, PMs never do.

**File list rendering — three branches:**

| Viewer | Condition | Shows |
|--------|-----------|-------|
| Admin or Writer | always | Full list: filename, size, uploader, timestamp, Download link |
| PM | `$task->status === 'completed'` | Full list with Download links |
| PM | `$task->status !== 'completed'` AND files exist | Single banner: *"File is currently under review."* — no filenames, no count, no download |
| PM | `$task->status !== 'completed'` AND no files | Section hidden entirely |

**Empty state (non-PM):** "No completed files uploaded yet."

### Upload modal

Second Alpine modal wired to `$showCompletedUploadModal`, structurally identical to the existing Upload Files modal. Differences:
- Title: "Upload Completed Files"
- POSTs to `tasks.completed-files.store`
- No note field

---

## 5. Visibility Summary

| Role | Upload | See files | Download |
|------|--------|-----------|----------|
| Admin | Yes | Always | Always |
| Writer (assigned) | Yes | Always | Always |
| PM | No | Only when task = `completed` | Only when task = `completed` |

---

## 6. What Does NOT Change

- The existing Files section and its upload flow are untouched.
- The `files()` relation on `Task` is kept for the task list popover.
- The `uploadFiles` policy method is unchanged (controls the regular Files section).
- The existing `TaskStatusUpdatedNotification` is unchanged.
- Writers can still manually set their own assignment status via the existing dropdown — the auto-flip to `ready_for_review` on upload does not prevent them from changing it afterward.
