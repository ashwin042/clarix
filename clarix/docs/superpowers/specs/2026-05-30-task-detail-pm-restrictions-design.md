# Task Detail PM Restrictions & Cleanup Design

**Date:** 2026-05-30
**Status:** Approved

## Summary

Three targeted changes:
1. Restrict task status changes to admins only (remove from PM).
2. Add an "Assigned To" field in the task detail Details section.
3. Remove the Priority column from the task list table.

---

## 1. PM Status Restriction

**View (`task-detail.blade.php`):**
The status quick-change `<select>` block (currently shown to `!isWriter()`) changes to `isAdmin()` only. PMs already see the status badge in the header as plain text — no new element is needed for them.

**Component (`TaskDetail.php`):**
The `updateTaskStatus()` method currently guards with `!isAdmin() && !isPm()`. Tighten to `!isAdmin()` so the 403 fires for PMs even on direct Livewire calls.

---

## 2. "Assigned To" in Details Section

**View (`task-detail.blade.php`):**
Add a new `<div>` entry inside the Details `<dl>` grid, after "Responsible PM":

```
Assigned To
<admin name or —>
```

Visible to all roles (writers, PMs, admins). No role gate — it is not sensitive metadata.

**Component (`TaskDetail.php`):**
Add `assignedAdmin` to the eager-load chain in both `mount()` and `render()`.

---

## 3. Remove Priority Column from Task List

**View (`manage-tasks.blade.php`):**
Remove the `<th>Priority</th>` column header and the corresponding `<td>` priority-badge cell from the table body. Priority is not removed from the create/edit modal — only the list display is affected.

---

## Out of Scope

- No changes to the create/edit modal (priority field stays).
- No changes to the task detail page's priority badge in the header (stays as informational display).
- No notifications or new permissions introduced.
