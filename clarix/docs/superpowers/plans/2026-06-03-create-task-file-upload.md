# Create Task File Upload — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an optional file upload section to the `CreateTask` Livewire component so PMs can attach files while creating a task; files are stored on R2 and linked to the new task.

**Architecture:** Add Livewire's `WithFileUploads` trait to `CreateTask`. A `$uploads` array property holds temporary files; `save()` is extended to store each to R2 and create `TaskFile` records after the task is created. A `removeUpload(int $index)` method lets the PM remove a queued file. The view adds a server-rendered file list below the Important Notes field. No routes, controllers, or models change.

**Tech Stack:** Laravel 11, Livewire 3 (`WithFileUploads`), R2 (Flysystem), PHPUnit / `Livewire::test()`

---

## File Map

| Action  | Path |
|---------|------|
| Modify  | `app/Livewire/Tasks/CreateTask.php` |
| Modify  | `resources/views/livewire/tasks/create-task.blade.php` |
| Modify  | `tests/Feature/Tasks/CreateTaskTest.php` |

---

## Task 1 — Component: uploads property, validation, save(), removeUpload()

**Files:**
- Modify: `app/Livewire/Tasks/CreateTask.php`
- Modify: `tests/Feature/Tasks/CreateTaskTest.php`

- [ ] **Step 1: Write the failing upload tests**

Append these four test methods to `tests/Feature/Tasks/CreateTaskTest.php`.

Add these imports at the top of the file (after the existing `use` statements):

```php
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
```

Then append inside the class:

```php
    public function test_pm_can_create_task_with_files_stored_on_r2(): void
    {
        Storage::fake('r2');

        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);

        Livewire::actingAs($pm)
            ->test(CreateTask::class)
            ->set('title', 'Task With Files')
            ->set('task_code', 'TWF_001')
            ->set('priority', 'medium')
            ->set('deadline', now()->addDays(7)->format('Y-m-d'))
            ->set('credit_amount', '1.00')
            ->set('uploads', [
                UploadedFile::fake()->create('brief.pdf', 100),
                UploadedFile::fake()->create('notes.docx', 50),
            ])
            ->call('save');

        $task = Task::first();
        $this->assertNotNull($task);
        $this->assertCount(2, $task->files);
        $this->assertDatabaseHas('task_files', [
            'task_id'           => $task->id,
            'original_name'     => 'brief.pdf',
            'is_completed_file' => false,
            'uploaded_by'       => $pm->id,
        ]);
        $this->assertDatabaseHas('task_files', [
            'task_id'       => $task->id,
            'original_name' => 'notes.docx',
        ]);
    }

    public function test_files_are_stored_under_task_code_directory_on_r2(): void
    {
        Storage::fake('r2');

        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);

        Livewire::actingAs($pm)
            ->test(CreateTask::class)
            ->set('title', 'Path Test')
            ->set('task_code', 'PT_001')
            ->set('priority', 'medium')
            ->set('deadline', now()->addDays(7)->format('Y-m-d'))
            ->set('credit_amount', '1.00')
            ->set('uploads', [UploadedFile::fake()->create('doc.pdf', 10)])
            ->call('save');

        $file = \App\Models\TaskFile::first();
        $this->assertStringStartsWith('task-files/PT_001/', $file->file_path);
        Storage::disk('r2')->assertExists($file->file_path);
    }

    public function test_pm_can_create_task_without_files(): void
    {
        Storage::fake('r2');

        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);

        Livewire::actingAs($pm)
            ->test(CreateTask::class)
            ->set('title', 'No Files Task')
            ->set('task_code', 'NFT_001')
            ->set('priority', 'medium')
            ->set('deadline', now()->addDays(7)->format('Y-m-d'))
            ->set('credit_amount', '1.00')
            ->call('save');

        $this->assertCount(0, Task::first()->files);
        $this->assertEmpty(Storage::disk('r2')->allFiles('task-files'));
    }

    public function test_remove_upload_splices_file_from_array(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);

        $component = Livewire::actingAs($pm)
            ->test(CreateTask::class)
            ->set('uploads', [
                UploadedFile::fake()->create('a.pdf', 10),
                UploadedFile::fake()->create('b.pdf', 20),
                UploadedFile::fake()->create('c.pdf', 30),
            ])
            ->call('removeUpload', 1);

        $this->assertCount(2, $component->get('uploads'));
    }
```

- [ ] **Step 2: Run — confirm the four new tests fail**

```
php artisan test tests/Feature/Tasks/CreateTaskTest.php --filter="with_files|r2|without_files|remove_upload"
```

Expected: 4 fail (method/property not found).

- [ ] **Step 3: Update `CreateTask.php` with trait, property, validation, save(), and removeUpload()**

Replace the entire file at `app/Livewire/Tasks/CreateTask.php`:

```php
<?php

namespace App\Livewire\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Notifications\NewTaskCreatedNotification;
use Livewire\Component;
use Livewire\WithFileUploads;

class CreateTask extends Component
{
    use WithFileUploads;

    public string $title = '';
    public string $task_code = '';
    public string $important_notes = '';
    public string $unit_id = '';
    public string $pm_id = '';
    public string $priority = 'medium';
    public string $deadline = '';
    public string $credit_amount = '0';
    public string $assigned_admin_id = '';
    public array  $uploads = [];

    public function mount(): void
    {
        if (!auth()->user()->isPm()) {
            abort(403);
        }
        $this->unit_id = (string) auth()->user()->unit_id;
        $this->pm_id   = (string) auth()->id();
    }

    public function removeUpload(int $index): void
    {
        array_splice($this->uploads, $index, 1);
    }

    public function save(): void
    {
        $unitId     = (string) auth()->user()->unit_id;
        $uniqueRule = "unique:tasks,task_code,NULL,id,unit_id,{$unitId}";

        $this->validate([
            'title'             => 'required|string|max:255',
            'task_code'         => ['required', 'string', 'max:50', $uniqueRule],
            'priority'          => 'required|in:low,medium,high',
            'deadline'          => 'required|date',
            'credit_amount'     => 'required|numeric|min:0',
            'important_notes'   => 'nullable|string|max:5000',
            'assigned_admin_id' => [
                'nullable',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    if ($value && User::find($value)?->role !== 'admin') {
                        $fail('The selected user must be an admin.');
                    }
                },
            ],
            'uploads'   => ['nullable', 'array'],
            'uploads.*' => ['file', 'max:10240'],
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

        foreach ($this->uploads as $file) {
            $path = $file->store('task-files/' . $this->task_code, 'r2');
            $task->files()->create([
                'file_path'     => $path,
                'original_name' => $file->getClientOriginalName(),
                'file_size'     => $file->getSize(),
                'mime_type'     => $file->getMimeType(),
                'uploaded_by'   => auth()->id(),
            ]);
        }

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

- [ ] **Step 4: Run the upload tests — all four should pass**

```
php artisan test tests/Feature/Tasks/CreateTaskTest.php --filter="with_files|r2|without_files|remove_upload"
```

Expected: 4 pass.

- [ ] **Step 5: Run the full CreateTask suite — confirm no regressions**

```
php artisan test tests/Feature/Tasks/CreateTaskTest.php
```

Expected: all 13 tests pass.

- [ ] **Step 6: Commit**

```
git add app/Livewire/Tasks/CreateTask.php tests/Feature/Tasks/CreateTaskTest.php
git commit -m "feat: add WithFileUploads to CreateTask with R2 storage and validation"
```

---

## Task 2 — View: file upload section in create-task.blade.php

**Files:**
- Modify: `resources/views/livewire/tasks/create-task.blade.php`

- [ ] **Step 1: Add the Files section to the view**

In `resources/views/livewire/tasks/create-task.blade.php`, insert the following block **between** the Important Notes `</div>` and the `{{-- Priority + Deadline + Credits --}}` comment:

```blade
                {{-- Files (optional) --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">
                        Files <span class="text-gray-400 dark:text-slate-500 font-normal">(optional)</span>
                    </label>

                    {{-- Drop zone / file input --}}
                    <label class="flex flex-col items-center justify-center w-full border-2 border-dashed border-gray-300 dark:border-slate-600 rounded-lg py-6 cursor-pointer hover:border-indigo-400 dark:hover:border-indigo-500 transition-colors">
                        <svg class="w-8 h-8 text-gray-400 dark:text-slate-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        <p class="text-sm text-gray-500 dark:text-slate-400">Click to browse or drag & drop</p>
                        <p class="text-xs text-gray-400 dark:text-slate-500 mt-1">Any file type · max 10 MB each</p>
                        <input type="file" wire:model="uploads" multiple class="hidden">
                    </label>

                    {{-- Upload progress --}}
                    <div wire:loading wire:target="uploads" class="mt-2 flex items-center gap-2 text-xs text-indigo-600 dark:text-indigo-400">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>
                        Uploading…
                    </div>

                    {{-- Queued file list --}}
                    @if(count($uploads))
                        <ul class="mt-3 space-y-1.5">
                            @foreach($uploads as $i => $upload)
                                <li class="flex items-center justify-between px-3 py-2 bg-gray-50 dark:bg-slate-800/60 rounded-lg text-sm">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <svg class="w-4 h-4 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <span class="truncate text-gray-800 dark:text-slate-200 font-medium">{{ $upload->getClientOriginalName() }}</span>
                                        <span class="text-xs text-gray-400 dark:text-slate-500 shrink-0">
                                            @php
                                                $bytes = $upload->getSize();
                                                echo $bytes < 1048576
                                                    ? round($bytes / 1024, 1) . ' KB'
                                                    : round($bytes / 1048576, 1) . ' MB';
                                            @endphp
                                        </span>
                                    </div>
                                    <button type="button" wire:click="removeUpload({{ $i }})"
                                        class="ml-3 p-1 rounded text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @error('uploads.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
```

The exact insertion point in the file is after this closing `</div>` tag (end of Important Notes block):

```blade
                    placeholder="Optional instructions..."></textarea>
                </div>

                {{-- Priority + Deadline + Credits --}}
```

Insert the Files block between those two elements.

- [ ] **Step 2: Run the full CreateTask suite — confirm still all pass**

```
php artisan test tests/Feature/Tasks/CreateTaskTest.php
```

Expected: all 13 tests pass (view change doesn't break component tests).

- [ ] **Step 3: Run the full Tasks test folder for regressions**

```
php artisan test tests/Feature/Tasks/
```

Expected: all tests pass.

- [ ] **Step 4: Commit**

```
git add resources/views/livewire/tasks/create-task.blade.php
git commit -m "feat: add file upload section to create task form"
```
