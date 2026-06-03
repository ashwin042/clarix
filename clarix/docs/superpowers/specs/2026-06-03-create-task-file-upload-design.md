# Create Task — File Upload Section

**Date:** 2026-06-03
**Status:** Approved

## Summary

Add an optional file upload section to the PM task creation form (`/tasks/create`). Files are selected in the browser, temporarily held by Livewire, and stored to R2 + linked to the newly created task during `save()`. Nothing outside `CreateTask` changes.

## Component — `app/Livewire/Tasks/CreateTask.php`

Add the `WithFileUploads` trait from Livewire.

Add one new property:

```php
public array $uploads = [];
```

Validation is added inside the existing `$this->validate()` call in `save()`:

```php
'uploads'   => ['nullable', 'array'],
'uploads.*' => ['file', 'max:10240'],
```

Matches `UploadTaskFilesRequest` (file, max 10 MB each). `array` type supports multiple files via `wire:model`.

`save()` is extended after `Task::create(...)`:

```
foreach ($this->uploads as $file) {
    $path = $file->store('task-files/' . $task->task_code, 'r2');
    $task->files()->create([
        'file_path'     => $path,
        'original_name' => $file->getClientOriginalName(),
        'file_size'     => $file->getSize(),
        'mime_type'     => $file->getMimeType(),
        'uploaded_by'   => auth()->id(),
    ]);
}
```

No changes to `mount()`, the admin notification loop, or the redirect. Files are optional — if `$uploads` is empty the task is created normally.

## View — `resources/views/livewire/tasks/create-task.blade.php`

Add a "Files" section between Important Notes and the Priority/Deadline/Credits row.

```
[ Files (optional) ]
  <input type="file" wire:model="uploads" multiple>
  wire:loading spinner while Livewire chunks files
  Alpine-powered per-file preview: name, formatted size, × remove button
  @error('uploads.*') validation message
```

The remove-file button calls a Livewire method `removeUpload(int $index)` which splices the uploads array. This is necessary because Livewire's multi-file upload array cannot be manipulated from Alpine alone.

## What Is Not Changed

- `TaskFileController`, `UploadTaskFilesRequest`, `TaskFile` model — untouched.
- Existing upload modal on task detail — untouched.
- Routes — no new routes.
- `is_completed_file` is always `false` (default) for files uploaded at creation.
