# Task List Files Column Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove the Unit column from the task list table and add a Files column after Writers that shows inline file counts with a popover for downloads and a direct upload trigger for empty cells.

**Architecture:** Three files change — the controller redirect becomes `redirect()->back()` so uploads work from both list and detail pages; the Livewire component eager-loads the `files` relation; the blade template drops the Unit column and adds the Files column with an Alpine.js per-row popover and a single shared upload modal at the bottom that receives a dispatched event from row buttons.

**Tech Stack:** Laravel 11, Livewire 3, Alpine.js v3 (bundled with Livewire), Tailwind CSS, PHPUnit

---

### Task 1: Change `TaskFileController::store()` redirect to `redirect()->back()`

**Files:**
- Modify: `app/Http/Controllers/TaskFileController.php:33`
- Create: `tests/Feature/Tasks/TaskFileControllerRedirectTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Tasks/TaskFileControllerRedirectTest.php`:

```php
<?php

namespace Tests\Feature\Tasks;

use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskFileControllerRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        Storage::fake('r2');
    }

    private function makeUnit(): Unit
    {
        return Unit::create(['name' => 'Test Unit']);
    }

    private function makePm(Unit $unit): User
    {
        return User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
    }

    private function makeTask(Unit $unit, User $pm): Task
    {
        return Task::create([
            'title'         => 'Test Task',
            'task_code'     => 'TT_001',
            'unit_id'       => $unit->id,
            'created_by'    => $pm->id,
            'pm_id'         => $pm->id,
            'priority'      => 'medium',
            'status'        => 'pending',
            'deadline'      => now()->addDays(7),
            'credit_amount' => 1.00,
        ]);
    }

    public function test_store_redirects_back_to_task_list_when_uploaded_from_list(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);
        $task = $this->makeTask($unit, $pm);

        $this->actingAs($pm)
            ->from(route('tasks.index'))
            ->post(route('tasks.files.store', $task), [
                'files' => [UploadedFile::fake()->create('report.pdf', 100)],
            ])
            ->assertRedirect(route('tasks.index'));
    }

    public function test_store_redirects_back_to_task_detail_when_uploaded_from_detail(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);
        $task = $this->makeTask($unit, $pm);

        $this->actingAs($pm)
            ->from(route('tasks.show', $task))
            ->post(route('tasks.files.store', $task), [
                'files' => [UploadedFile::fake()->create('report.pdf', 100)],
            ])
            ->assertRedirect(route('tasks.show', $task));
    }
}
```

- [ ] **Step 2: Run tests to confirm they fail**

```
php artisan test tests/Feature/Tasks/TaskFileControllerRedirectTest.php
```

Expected: FAIL — both tests assert redirect to referrer, but current code always redirects to `tasks.show`.

- [ ] **Step 3: Change the redirect in `TaskFileController::store()`**

In `app/Http/Controllers/TaskFileController.php`, replace line 33:

```php
return redirect()->route('tasks.show', $task)->with('success', 'Files uploaded.');
```

with:

```php
return redirect()->back()->with('success', 'Files uploaded.');
```

- [ ] **Step 4: Run tests to confirm they pass**

```
php artisan test tests/Feature/Tasks/TaskFileControllerRedirectTest.php
```

Expected: PASS — 2 tests, 0 failures.

- [ ] **Step 5: Run the full suite to check for regressions**

```
php artisan test
```

Expected: all tests pass.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/TaskFileController.php tests/Feature/Tasks/TaskFileControllerRedirectTest.php
git commit -m "fix: redirect back after file upload so list and detail pages both work"
```

---

### Task 2: Add `files` to eager-load in `ManageTasks::render()`

**Files:**
- Modify: `app/Livewire/Tasks/ManageTasks.php:183`
- Create: `tests/Feature/Tasks/ManageTasksFilesColumnTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Tasks/ManageTasksFilesColumnTest.php`:

```php
<?php

namespace Tests\Feature\Tasks;

use App\Livewire\Tasks\ManageTasks;
use App\Models\Task;
use App\Models\TaskFile;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageTasksFilesColumnTest extends TestCase
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

    private function makeTask(Unit $unit, User $pm): Task
    {
        return Task::create([
            'title'         => 'Test Task',
            'task_code'     => 'TT_001',
            'unit_id'       => $unit->id,
            'created_by'    => $pm->id,
            'pm_id'         => $pm->id,
            'priority'      => 'medium',
            'status'        => 'pending',
            'deadline'      => now()->addDays(7),
            'credit_amount' => 1.00,
        ]);
    }

    private function makeFile(Task $task, User $uploader): TaskFile
    {
        return TaskFile::create([
            'task_id'       => $task->id,
            'file_path'     => 'task-files/TT_001/report.pdf',
            'original_name' => 'report.pdf',
            'file_size'     => 102400,
            'mime_type'     => 'application/pdf',
            'uploaded_by'   => $uploader->id,
        ]);
    }

    public function test_files_relation_is_eager_loaded_on_tasks(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);
        $task = $this->makeTask($unit, $pm);
        $this->makeFile($task, $pm);

        $this->actingAs($pm);

        Livewire::test(ManageTasks::class)
            ->assertViewHas('tasks', function ($tasks) {
                return $tasks->first()->relationLoaded('files');
            });
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```
php artisan test tests/Feature/Tasks/ManageTasksFilesColumnTest.php::test_files_relation_is_eager_loaded_on_tasks
```

Expected: FAIL — `files` relation not loaded.

- [ ] **Step 3: Add `files` to the eager-load in `ManageTasks::render()`**

In `app/Livewire/Tasks/ManageTasks.php`, find line 183:

```php
$query = Task::with(['unit', 'creator', 'pm', 'assignments.writer', 'assignedAdmin'])
```

Replace with:

```php
$query = Task::with(['unit', 'creator', 'pm', 'assignments.writer', 'assignedAdmin', 'files'])
```

- [ ] **Step 4: Run test to confirm it passes**

```
php artisan test tests/Feature/Tasks/ManageTasksFilesColumnTest.php::test_files_relation_is_eager_loaded_on_tasks
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/Tasks/ManageTasks.php tests/Feature/Tasks/ManageTasksFilesColumnTest.php
git commit -m "feat: eager-load files relation in task list query"
```

---

### Task 3: Remove Unit column and add Files column header

**Files:**
- Modify: `resources/views/livewire/tasks/manage-tasks.blade.php:62,77,90`
- Modify: `tests/Feature/Tasks/ManageTasksFilesColumnTest.php` (add tests)

- [ ] **Step 1: Add column header tests to `ManageTasksFilesColumnTest.php`**

Append two new test methods to the existing class in `tests/Feature/Tasks/ManageTasksFilesColumnTest.php`:

```php
public function test_unit_column_header_is_absent(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);

    Livewire::test(ManageTasks::class)
        ->assertDontSeeHtml('tracking-wider">Unit</th>');
}

public function test_files_column_header_is_present(): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);

    Livewire::test(ManageTasks::class)
        ->assertSeeHtml('tracking-wider">Files</th>');
}
```

- [ ] **Step 2: Run new tests to confirm they fail**

```
php artisan test tests/Feature/Tasks/ManageTasksFilesColumnTest.php
```

Expected: `test_unit_column_header_is_absent` FAILS (Unit header still there), `test_files_column_header_is_present` FAILS (Files header missing).

- [ ] **Step 3: Remove the Unit `<th>` from `manage-tasks.blade.php`**

In `resources/views/livewire/tasks/manage-tasks.blade.php`, remove line 62:

```blade
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Unit</th>
```

- [ ] **Step 4: Add the Files `<th>` after the Writers `<th>`**

In `resources/views/livewire/tasks/manage-tasks.blade.php`, find the Writers `<th>` (the line containing `>Writers<`):

```blade
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Writers</th>
```

Replace it with (Writers header followed immediately by Files header):

```blade
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Writers</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Files</th>
```

- [ ] **Step 5: Remove the Unit `<td>` from the row loop**

Find and remove this line (the Unit cell, around line 90 after the previous deletion shifts line numbers):

```blade
                            <td class="px-5 py-3 text-sm text-gray-600 dark:text-slate-400">{{ $task->unit->name }}</td>
```

- [ ] **Step 6: Run tests to confirm they pass**

```
php artisan test tests/Feature/Tasks/ManageTasksFilesColumnTest.php
```

Expected: PASS — 3 tests, 0 failures.

- [ ] **Step 7: Commit**

```bash
git add resources/views/livewire/tasks/manage-tasks.blade.php tests/Feature/Tasks/ManageTasksFilesColumnTest.php
git commit -m "feat: remove Unit column and add Files column header to task list"
```

---

### Task 4: Add Files cell (file count popover + upload trigger)

**Files:**
- Modify: `resources/views/livewire/tasks/manage-tasks.blade.php`
- Modify: `tests/Feature/Tasks/ManageTasksFilesColumnTest.php` (add tests)

- [ ] **Step 1: Add Files cell behavior tests**

Append two new test methods to `tests/Feature/Tasks/ManageTasksFilesColumnTest.php`:

```php
public function test_file_name_appears_in_html_when_task_has_files(): void
{
    $unit = $this->makeUnit();
    $pm   = $this->makePm($unit);
    $task = $this->makeTask($unit, $pm);
    $this->makeFile($task, $pm);

    $this->actingAs($pm);

    Livewire::test(ManageTasks::class)
        ->assertSee('report.pdf');
}

public function test_upload_dispatch_present_for_pm_with_no_files(): void
{
    $unit = $this->makeUnit();
    $pm   = $this->makePm($unit);
    $this->makeTask($unit, $pm);

    $this->actingAs($pm);

    Livewire::test(ManageTasks::class)
        ->assertSeeHtml('open-upload-modal');
}
```

- [ ] **Step 2: Run new tests to confirm they fail**

```
php artisan test tests/Feature/Tasks/ManageTasksFilesColumnTest.php
```

Expected: `test_file_name_appears_in_html_when_task_has_files` FAILS, `test_upload_dispatch_present_for_pm_with_no_files` FAILS.

- [ ] **Step 3: Add the Files `<td>` cell after the Writers `<td>` in `manage-tasks.blade.php`**

Find the closing tag of the Writers `<td>` block. It ends around the line containing `@endif` after the writers loop. The full Writers cell looks like this in the current blade:

```blade
                            <td class="px-5 py-3 text-sm text-gray-500 dark:text-slate-400">
                                @if(auth()->user()->isAdmin() && $task->assignments->count())
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($task->assignments as $assignment)
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700">{{ $assignment->writer->name }}</span>
                                        @endforeach
                                    </div>
                                @elseif(auth()->user()->isAdmin())
                                    <span class="text-gray-400 dark:text-slate-500">—</span>
                                @else
                                    {{ $task->assignments->count() }}
                                @endif
                            </td>
```

Insert the following Files `<td>` immediately after that closing `</td>`:

```blade
                            <td class="px-5 py-3">
                                @if($task->files->count())
                                    <div class="relative" x-data="{ open: false }">
                                        <button
                                            type="button"
                                            @click="open = !open"
                                            @click.outside="open = false"
                                            class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/30 transition-colors text-xs font-medium"
                                            title="{{ $task->files->count() }} {{ $task->files->count() === 1 ? 'file' : 'files' }}"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            {{ $task->files->count() }}
                                        </button>
                                        <div
                                            x-show="open"
                                            x-cloak
                                            x-transition:enter="ease-out duration-150"
                                            x-transition:enter-start="opacity-0 scale-95"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            class="absolute left-0 top-8 z-20 w-64 bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 shadow-lg overflow-hidden"
                                        >
                                            <div class="px-3 py-2 border-b border-gray-100 dark:border-slate-800/60">
                                                <p class="text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Files</p>
                                            </div>
                                            <ul class="divide-y divide-gray-100 dark:divide-slate-800/60 max-h-48 overflow-y-auto">
                                                @foreach($task->files as $file)
                                                <li class="flex items-center justify-between px-3 py-2.5 hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors">
                                                    <div class="flex items-center gap-2 min-w-0">
                                                        <svg class="w-3.5 h-3.5 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                        </svg>
                                                        <div class="min-w-0">
                                                            <p class="text-xs font-medium text-gray-800 dark:text-slate-200 truncate">{{ $file->original_name }}</p>
                                                            <p class="text-[10px] text-gray-400 dark:text-slate-500">{{ $file->file_size_formatted }}</p>
                                                        </div>
                                                    </div>
                                                    <a href="{{ route('tasks.files.download', [$task, $file]) }}"
                                                       class="ml-2 px-2 py-1 text-[10px] font-medium text-indigo-600 hover:bg-indigo-50 rounded-md transition-colors shrink-0">
                                                        Download
                                                    </a>
                                                </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                @else
                                    @can('uploadFiles', $task)
                                    <button
                                        type="button"
                                        @click="$dispatch('open-upload-modal', { uploadUrl: '{{ route('tasks.files.store', $task) }}' })"
                                        title="Upload files"
                                        class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800/50 transition-colors"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                    </button>
                                    @else
                                    <span class="text-gray-400 dark:text-slate-500 text-sm">—</span>
                                    @endcan
                                @endif
                            </td>
```

- [ ] **Step 4: Run tests to confirm they pass**

```
php artisan test tests/Feature/Tasks/ManageTasksFilesColumnTest.php
```

Expected: PASS — 5 tests, 0 failures.

- [ ] **Step 5: Run full suite**

```
php artisan test
```

Expected: all tests pass.

- [ ] **Step 6: Commit**

```bash
git add resources/views/livewire/tasks/manage-tasks.blade.php tests/Feature/Tasks/ManageTasksFilesColumnTest.php
git commit -m "feat: add Files cell with popover and upload trigger to task list"
```

---

### Task 5: Add shared upload modal

**Files:**
- Modify: `resources/views/livewire/tasks/manage-tasks.blade.php`

Note: This modal is pure Alpine.js — its behavior (opening on event, dynamic form action, drag-and-drop) is not testable via Livewire unit tests. Manual browser verification is the test for this task.

- [ ] **Step 1: Add the shared upload modal to `manage-tasks.blade.php`**

Find the existing `<x-delete-confirm-modal .../>` near the bottom of the file (before the final `</div>`). Insert the following block immediately before it:

```blade
    {{-- Shared inline upload modal (triggered from Files column) --}}
    <div
        x-data="{
            show: false,
            uploadUrl: '',
            dragging: false,
            fileObjects: [],
            formatSize(bytes) {
                if (bytes < 1024) return bytes + ' B';
                if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
                return (bytes / 1048576).toFixed(1) + ' MB';
            },
            addFiles(fileList) {
                Array.from(fileList).forEach(f => {
                    const exists = this.fileObjects.find(e => e.name === f.name && e.size === f.size);
                    if (!exists) this.fileObjects.push(f);
                });
                this.syncInput();
            },
            removeFile(index) {
                this.fileObjects.splice(index, 1);
                this.syncInput();
            },
            syncInput() {
                const dT = new DataTransfer();
                this.fileObjects.forEach(f => dT.items.add(f));
                this.$refs.fileInput.files = dT.files;
            },
            reset() {
                this.fileObjects = [];
                if (this.$refs.fileInput) this.$refs.fileInput.value = '';
            },
            handleDrop(e) {
                this.dragging = false;
                if (e.dataTransfer?.files.length) this.addFiles(e.dataTransfer.files);
            }
        }"
        x-on:open-upload-modal.window="uploadUrl = $event.detail.uploadUrl; fileObjects = []; show = true"
        x-on:keydown.escape.window="show = false; reset()"
        x-show="show"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        style="display: none;"
    >
        <div x-show="show" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" @click="show = false; reset()"></div>
        <div x-show="show" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
            class="relative bg-white dark:bg-slate-900 rounded-2xl shadow-2xl w-full max-w-md z-10 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-slate-800/60">
                <h3 class="text-base font-semibold text-gray-900 dark:text-slate-100">Upload Files</h3>
                <button @click="show = false; reset()" class="text-gray-400 dark:text-slate-500 hover:text-gray-600 p-1 hover:bg-gray-100 dark:hover:bg-slate-800/50 rounded-lg transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form :action="uploadUrl" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Files <span class="text-gray-400 dark:text-slate-500 font-normal">(max 10 MB each)</span></label>
                    <div
                        class="relative border-2 border-dashed rounded-xl p-6 text-center transition-colors cursor-pointer"
                        :class="dragging ? 'border-indigo-400 bg-indigo-50' : 'border-gray-300 hover:border-indigo-300 hover:bg-gray-50 dark:bg-slate-950/50'"
                        @dragover.prevent="dragging = true"
                        @dragleave.prevent="dragging = false"
                        @drop.prevent="handleDrop($event)"
                        @click="$refs.fileInput.click()"
                    >
                        <svg class="mx-auto w-8 h-8 text-gray-400 dark:text-slate-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        <p class="text-sm text-gray-600 dark:text-slate-400">Drag & drop files here, or <span class="text-indigo-600 font-medium">browse</span></p>
                        <p class="text-xs text-gray-400 dark:text-slate-500 mt-1">Multiple files supported</p>
                        <input
                            x-ref="fileInput"
                            type="file"
                            name="files[]"
                            multiple
                            class="sr-only"
                            @change="addFiles($event.target.files)"
                        >
                    </div>
                    <template x-if="fileObjects.length > 0">
                        <ul class="mt-2 space-y-1">
                            <template x-for="(f, i) in fileObjects" :key="i">
                                <li class="flex items-center justify-between text-xs bg-gray-50 dark:bg-slate-950/50 border border-gray-200 dark:border-slate-800/60 rounded-lg px-3 py-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <svg class="w-4 h-4 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        <span class="truncate text-gray-700 dark:text-slate-300" x-text="f.name"></span>
                                    </div>
                                    <div class="flex items-center gap-2 ml-2 shrink-0">
                                        <span class="text-gray-400 dark:text-slate-500" x-text="formatSize(f.size)"></span>
                                        <button type="button" @click.stop="removeFile(i)"
                                            class="text-gray-400 dark:text-slate-500 hover:text-red-500 transition-colors p-0.5 rounded hover:bg-red-50">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </li>
                            </template>
                        </ul>
                    </template>
                </div>
                <div class="flex gap-3">
                    <button type="submit"
                        class="flex-1 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors">
                        Upload
                    </button>
                    <button type="button" @click="show = false; reset()"
                        class="flex-1 py-2.5 border border-gray-300 text-gray-700 dark:text-slate-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
```

- [ ] **Step 2: Run the full suite to confirm no regressions**

```
php artisan test
```

Expected: all tests pass.

- [ ] **Step 3: Manual browser verification**

Start the dev server:

```
php artisan serve
```

Verify the following:

1. Task list loads — Unit column is gone, Files column is present
2. A task with no files (PM user): Files cell shows a `+` icon; clicking opens the upload modal
3. Upload a file from the modal — page reloads to the task list; file count now shows `📄 1`
4. Click the `📄 1` badge — a popover appears listing the file with filename, size, and Download link
5. Download link works
6. Click outside the popover — it closes
7. Press Escape while upload modal is open — modal closes and file list resets
8. Writer user: Files cell shows `—` when no files; shows count badge (not upload button) when files exist

- [ ] **Step 4: Commit**

```bash
git add resources/views/livewire/tasks/manage-tasks.blade.php
git commit -m "feat: add shared upload modal to task list for inline file upload"
```
