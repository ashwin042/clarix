# Completed File Section Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a separate "Completed Files" section to the task detail page where writers/admins upload deliverables, PMs are blocked from seeing files until the task is marked completed, and uploading auto-flips all assignments to `ready_for_review`.

**Architecture:** Single boolean column `is_completed_file` on the existing `task_files` table; two Eloquent scopes split regular vs completed files. A new `storeCompleted` controller action handles the upload side-effects (assignment flip + notification). Visibility gating lives in the blade view and the existing `download` action.

**Tech Stack:** Laravel 11, Livewire 3, Alpine.js, Tailwind CSS, Cloudflare R2 (Storage disk `r2`), PHPUnit/Pest feature tests.

---

## File Map

| Action | File |
|--------|------|
| Create | `database/migrations/2026_06_02_000001_add_is_completed_file_to_task_files_table.php` |
| Modify | `app/Models/TaskFile.php` |
| Modify | `app/Models/Task.php` |
| Modify | `app/Policies/TaskPolicy.php` |
| Create | `app/Http/Requests/UploadCompletedFilesRequest.php` |
| Create | `app/Notifications/CompletedFileUploadedNotification.php` |
| Modify | `app/Http/Controllers/TaskFileController.php` |
| Modify | `app/Livewire/Tasks/TaskDetail.php` |
| Modify | `resources/views/livewire/tasks/task-detail.blade.php` |
| Modify | `routes/web.php` |
| Create | `tests/Feature/Tasks/CompletedFileUploadTest.php` |
| Create | `tests/Feature/Tasks/CompletedFileDownloadTest.php` |

---

## Task 1: Migration + Model scopes

**Files:**
- Create: `database/migrations/2026_06_02_000001_add_is_completed_file_to_task_files_table.php`
- Modify: `app/Models/TaskFile.php`
- Modify: `app/Models/Task.php`

- [ ] **Step 1: Create the migration**

Create `database/migrations/2026_06_02_000001_add_is_completed_file_to_task_files_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_files', function (Blueprint $table) {
            $table->boolean('is_completed_file')->default(false)->after('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::table('task_files', function (Blueprint $table) {
            $table->dropColumn('is_completed_file');
        });
    }
};
```

- [ ] **Step 2: Run the migration**

```bash
php artisan migrate
```

Expected: `Migrating: 2026_06_02_000001_add_is_completed_file_to_task_files_table` then `Migrated`.

- [ ] **Step 3: Update TaskFile model**

Replace the full contents of `app/Models/TaskFile.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskFile extends Model
{
    protected $fillable = [
        'task_id',
        'file_path',
        'original_name',
        'file_size',
        'mime_type',
        'uploaded_by',
        'is_completed_file',
    ];

    protected function casts(): array
    {
        return [
            'is_completed_file' => 'boolean',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeRegular(Builder $query): Builder
    {
        return $query->where('is_completed_file', false);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('is_completed_file', true);
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
}
```

- [ ] **Step 4: Update Task model — add two focused relations**

In `app/Models/Task.php`, add two new relations after the existing `files()` method:

```php
public function regularFiles(): HasMany
{
    return $this->hasMany(TaskFile::class)->regular();
}

public function completedFiles(): HasMany
{
    return $this->hasMany(TaskFile::class)->completed();
}
```

The existing `files()` relation is left unchanged (still used by the task list popover).

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_06_02_000001_add_is_completed_file_to_task_files_table.php
git add app/Models/TaskFile.php app/Models/Task.php
git commit -m "feat: add is_completed_file flag to task_files with model scopes"
```

---

## Task 2: Policy + Upload Request

**Files:**
- Modify: `app/Policies/TaskPolicy.php`
- Create: `app/Http/Requests/UploadCompletedFilesRequest.php`

- [ ] **Step 1: Add uploadCompletedFile to TaskPolicy**

In `app/Policies/TaskPolicy.php`, add this method after `uploadFiles()`:

```php
public function uploadCompletedFile(User $user, Task $task): bool
{
    return match ($user->role) {
        'admin'  => true,
        'writer' => $task->assignments()->where('writer_id', $user->id)->exists(),
        default  => false,
    };
}
```

- [ ] **Step 2: Create UploadCompletedFilesRequest**

Create `app/Http/Requests/UploadCompletedFilesRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadCompletedFilesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(auth()->user()->role, ['admin', 'writer']);
    }

    public function rules(): array
    {
        return [
            'files'   => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:10240'],
        ];
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Policies/TaskPolicy.php app/Http/Requests/UploadCompletedFilesRequest.php
git commit -m "feat: add uploadCompletedFile policy and upload request"
```

---

## Task 3: Notification

**Files:**
- Create: `app/Notifications/CompletedFileUploadedNotification.php`

- [ ] **Step 1: Create the notification**

Create `app/Notifications/CompletedFileUploadedNotification.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CompletedFileUploadedNotification extends Notification
{
    use Queueable;

    public function __construct(public Task $task) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title'      => 'Completed File Uploaded',
            'message'    => "A completed file has been uploaded for {$this->task->task_code} and is awaiting your review.",
            'related_id' => $this->task->id,
            'type'       => 'completed_file_uploaded',
        ];
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Notifications/CompletedFileUploadedNotification.php
git commit -m "feat: add CompletedFileUploadedNotification"
```

---

## Task 4: Controller — storeCompleted + route

**Files:**
- Modify: `app/Http/Controllers/TaskFileController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Tasks/CompletedFileUploadTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Tasks/CompletedFileUploadTest.php`:

```php
<?php

namespace Tests\Feature\Tasks;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\CompletedFileUploadedNotification;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompletedFileUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        Storage::fake('r2');
        Notification::fake();
    }

    private function makeUnit(): Unit
    {
        return Unit::create(['name' => 'Test Unit']);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makePm(Unit $unit): User
    {
        return User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
    }

    private function makeWriter(): User
    {
        return User::factory()->create(['role' => 'writer']);
    }

    private function makeTask(Unit $unit, User $pm, ?User $assignedAdmin = null): Task
    {
        return Task::create([
            'title'             => 'Test Task',
            'task_code'         => 'TT_001',
            'unit_id'           => $unit->id,
            'created_by'        => $pm->id,
            'pm_id'             => $pm->id,
            'priority'          => 'medium',
            'status'            => 'pending',
            'deadline'          => now()->addDays(7),
            'credit_amount'     => 1.00,
            'assigned_admin_id' => $assignedAdmin?->id,
        ]);
    }

    private function assignWriter(Task $task, User $writer): TaskAssignment
    {
        return TaskAssignment::create([
            'task_id'     => $task->id,
            'writer_id'   => $writer->id,
            'assigned_by' => $task->created_by,
            'status'      => 'in_progress',
        ]);
    }

    public function test_assigned_writer_can_upload_completed_file(): void
    {
        $unit   = $this->makeUnit();
        $pm     = $this->makePm($unit);
        $writer = $this->makeWriter();
        $task   = $this->makeTask($unit, $pm);
        $this->assignWriter($task, $writer);

        $this->actingAs($writer)
            ->post(route('tasks.completed-files.store', $task), [
                'files' => [UploadedFile::fake()->create('deliverable.pdf', 100)],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('task_files', [
            'task_id'           => $task->id,
            'original_name'     => 'deliverable.pdf',
            'is_completed_file' => true,
        ]);
    }

    public function test_unassigned_writer_cannot_upload_completed_file(): void
    {
        $unit   = $this->makeUnit();
        $pm     = $this->makePm($unit);
        $writer = $this->makeWriter();
        $task   = $this->makeTask($unit, $pm);

        $this->actingAs($writer)
            ->post(route('tasks.completed-files.store', $task), [
                'files' => [UploadedFile::fake()->create('deliverable.pdf', 100)],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('task_files', ['task_id' => $task->id]);
    }

    public function test_pm_cannot_upload_completed_file(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);
        $task = $this->makeTask($unit, $pm);

        $this->actingAs($pm)
            ->post(route('tasks.completed-files.store', $task), [
                'files' => [UploadedFile::fake()->create('deliverable.pdf', 100)],
            ])
            ->assertForbidden();
    }

    public function test_admin_can_upload_completed_file(): void
    {
        $unit  = $this->makeUnit();
        $pm    = $this->makePm($unit);
        $admin = $this->makeAdmin();
        $task  = $this->makeTask($unit, $pm);

        $this->actingAs($admin)
            ->post(route('tasks.completed-files.store', $task), [
                'files' => [UploadedFile::fake()->create('deliverable.pdf', 100)],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('task_files', [
            'task_id'           => $task->id,
            'is_completed_file' => true,
        ]);
    }

    public function test_upload_flips_all_assignments_to_ready_for_review(): void
    {
        $unit    = $this->makeUnit();
        $pm      = $this->makePm($unit);
        $writer1 = $this->makeWriter();
        $writer2 = $this->makeWriter();
        $task    = $this->makeTask($unit, $pm);
        $a1 = $this->assignWriter($task, $writer1);
        $a2 = $this->assignWriter($task, $writer2);

        $this->actingAs($writer1)
            ->post(route('tasks.completed-files.store', $task), [
                'files' => [UploadedFile::fake()->create('deliverable.pdf', 100)],
            ]);

        $this->assertDatabaseHas('task_assignments', ['id' => $a1->id, 'status' => 'ready_for_review']);
        $this->assertDatabaseHas('task_assignments', ['id' => $a2->id, 'status' => 'ready_for_review']);
    }

    public function test_upload_notifies_pm_and_assigned_admin(): void
    {
        $unit          = $this->makeUnit();
        $pm            = $this->makePm($unit);
        $assignedAdmin = $this->makeAdmin();
        $writer        = $this->makeWriter();
        $task          = $this->makeTask($unit, $pm, $assignedAdmin);
        $this->assignWriter($task, $writer);

        $this->actingAs($writer)
            ->post(route('tasks.completed-files.store', $task), [
                'files' => [UploadedFile::fake()->create('deliverable.pdf', 100)],
            ]);

        Notification::assertSentTo($pm, CompletedFileUploadedNotification::class);
        Notification::assertSentTo($assignedAdmin, CompletedFileUploadedNotification::class);
    }

    public function test_upload_does_not_notify_when_no_pm_or_admin_assigned(): void
    {
        $unit   = $this->makeUnit();
        $pm     = $this->makePm($unit);
        $writer = $this->makeWriter();
        $task   = Task::create([
            'title'         => 'No PM Task',
            'task_code'     => 'TT_002',
            'unit_id'       => $unit->id,
            'created_by'    => $pm->id,
            'pm_id'         => null,
            'priority'      => 'medium',
            'status'        => 'pending',
            'deadline'      => now()->addDays(7),
            'credit_amount' => 1.00,
        ]);
        $this->assignWriter($task, $writer);

        $this->actingAs($writer)
            ->post(route('tasks.completed-files.store', $task), [
                'files' => [UploadedFile::fake()->create('deliverable.pdf', 100)],
            ]);

        Notification::assertNothingSent();
    }
}
```

- [ ] **Step 2: Run the tests — confirm they fail**

```bash
php artisan test tests/Feature/Tasks/CompletedFileUploadTest.php
```

Expected: All tests fail with `Route [tasks.completed-files.store] not defined` or 404.

- [ ] **Step 3: Add the route**

In `routes/web.php`, add this line immediately after the existing `tasks.files.store` route (line 62):

```php
Route::post('tasks/{task}/completed-files', [TaskFileController::class, 'storeCompleted'])->name('tasks.completed-files.store');
```

- [ ] **Step 4: Add storeCompleted to the controller**

In `app/Http/Controllers/TaskFileController.php`, add the import at the top:

```php
use App\Http\Requests\UploadCompletedFilesRequest;
use App\Notifications\CompletedFileUploadedNotification;
```

Then add the `storeCompleted` method after `store()`:

```php
public function storeCompleted(UploadCompletedFilesRequest $request, Task $task)
{
    $this->authorize('uploadCompletedFile', $task);

    foreach ($request->file('files') as $file) {
        $path = $file->store('task-files/' . $task->task_code . '/completed', 'r2');

        $task->files()->create([
            'file_path'         => $path,
            'original_name'     => $file->getClientOriginalName(),
            'file_size'         => $file->getSize(),
            'mime_type'         => $file->getMimeType(),
            'uploaded_by'       => auth()->id(),
            'is_completed_file' => true,
        ]);
    }

    $task->assignments()->update(['status' => 'ready_for_review']);

    if ($task->pm) {
        $task->pm->notify(new CompletedFileUploadedNotification($task));
    }

    if ($task->assignedAdmin) {
        $task->assignedAdmin->notify(new CompletedFileUploadedNotification($task));
    }

    return redirect()->back(fallback: route('tasks.index'))->with('success', 'Completed file uploaded.');
}
```

- [ ] **Step 5: Run tests — confirm they pass**

```bash
php artisan test tests/Feature/Tasks/CompletedFileUploadTest.php
```

Expected: All 7 tests pass.

- [ ] **Step 6: Commit**

```bash
git add routes/web.php app/Http/Controllers/TaskFileController.php
git add tests/Feature/Tasks/CompletedFileUploadTest.php
git commit -m "feat: add storeCompleted action with assignment flip and notifications"
```

---

## Task 5: Download authorization for completed files

**Files:**
- Modify: `app/Http/Controllers/TaskFileController.php`
- Test: `tests/Feature/Tasks/CompletedFileDownloadTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Tasks/CompletedFileDownloadTest.php`:

```php
<?php

namespace Tests\Feature\Tasks;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskFile;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompletedFileDownloadTest extends TestCase
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

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makePm(Unit $unit): User
    {
        return User::factory()->create(['role' => 'pm', 'unit_id' => $unit->id]);
    }

    private function makeWriter(): User
    {
        return User::factory()->create(['role' => 'writer']);
    }

    private function makeTask(Unit $unit, User $pm, string $status = 'pending'): Task
    {
        return Task::create([
            'title'         => 'Test Task',
            'task_code'     => 'TT_001',
            'unit_id'       => $unit->id,
            'created_by'    => $pm->id,
            'pm_id'         => $pm->id,
            'priority'      => 'medium',
            'status'        => $status,
            'deadline'      => now()->addDays(7),
            'credit_amount' => 1.00,
        ]);
    }

    private function makeCompletedFile(Task $task, User $uploader): TaskFile
    {
        $fakePath = 'task-files/TT_001/completed/test.pdf';
        Storage::disk('r2')->put($fakePath, 'content');

        return TaskFile::create([
            'task_id'           => $task->id,
            'file_path'         => $fakePath,
            'original_name'     => 'deliverable.pdf',
            'file_size'         => 1024,
            'mime_type'         => 'application/pdf',
            'uploaded_by'       => $uploader->id,
            'is_completed_file' => true,
        ]);
    }

    private function makeRegularFile(Task $task, User $uploader): TaskFile
    {
        $fakePath = 'task-files/TT_001/test.pdf';
        Storage::disk('r2')->put($fakePath, 'content');

        return TaskFile::create([
            'task_id'           => $task->id,
            'file_path'         => $fakePath,
            'original_name'     => 'brief.pdf',
            'file_size'         => 1024,
            'mime_type'         => 'application/pdf',
            'uploaded_by'       => $uploader->id,
            'is_completed_file' => false,
        ]);
    }

    public function test_admin_can_download_completed_file_when_task_pending(): void
    {
        $unit  = $this->makeUnit();
        $pm    = $this->makePm($unit);
        $admin = $this->makeAdmin();
        $task  = $this->makeTask($unit, $pm, 'pending');
        $file  = $this->makeCompletedFile($task, $admin);

        $this->actingAs($admin)
            ->get(route('tasks.files.download', [$task, $file]))
            ->assertOk();
    }

    public function test_assigned_writer_can_download_completed_file_when_task_pending(): void
    {
        $unit   = $this->makeUnit();
        $pm     = $this->makePm($unit);
        $writer = $this->makeWriter();
        $task   = $this->makeTask($unit, $pm, 'pending');
        TaskAssignment::create([
            'task_id'     => $task->id,
            'writer_id'   => $writer->id,
            'assigned_by' => $pm->id,
            'status'      => 'in_progress',
        ]);
        $file = $this->makeCompletedFile($task, $writer);

        $this->actingAs($writer)
            ->get(route('tasks.files.download', [$task, $file]))
            ->assertOk();
    }

    public function test_pm_cannot_download_completed_file_when_task_not_completed(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);
        $task = $this->makeTask($unit, $pm, 'in_progress');
        $file = $this->makeCompletedFile($task, $pm);

        $this->actingAs($pm)
            ->get(route('tasks.files.download', [$task, $file]))
            ->assertForbidden();
    }

    public function test_pm_can_download_completed_file_when_task_is_completed(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);
        $task = $this->makeTask($unit, $pm, 'completed');
        $file = $this->makeCompletedFile($task, $pm);

        $this->actingAs($pm)
            ->get(route('tasks.files.download', [$task, $file]))
            ->assertOk();
    }

    public function test_pm_can_always_download_regular_file(): void
    {
        $unit = $this->makeUnit();
        $pm   = $this->makePm($unit);
        $task = $this->makeTask($unit, $pm, 'pending');
        $file = $this->makeRegularFile($task, $pm);

        $this->actingAs($pm)
            ->get(route('tasks.files.download', [$task, $file]))
            ->assertOk();
    }
}
```

- [ ] **Step 2: Run the tests — confirm they fail**

```bash
php artisan test tests/Feature/Tasks/CompletedFileDownloadTest.php
```

Expected: `test_pm_cannot_download_completed_file_when_task_not_completed` passes (PM already blocked by existing logic... actually it passes for wrong reasons). `test_pm_can_download_completed_file_when_task_is_completed` fails with 403 because existing code doesn't check `is_completed_file`.

- [ ] **Step 3: Update download() in TaskFileController**

Replace the entire `download()` method in `app/Http/Controllers/TaskFileController.php`:

```php
public function download(Task $task, TaskFile $file)
{
    abort_if($file->task_id !== $task->id, 404);

    $user = auth()->user();

    if ($file->is_completed_file) {
        if ($user->role === 'admin') {
            // always allowed
        } elseif ($user->role === 'writer' && $task->assignments()->where('writer_id', $user->id)->exists()) {
            // allowed
        } elseif ($user->role === 'pm' && $task->unit_id === $user->unit_id && $task->status === 'completed') {
            // allowed only when task is completed
        } else {
            abort(403);
        }
    } else {
        if ($user->role === 'admin') {
            // allow
        } elseif ($user->role === 'pm' && $task->unit_id === $user->unit_id) {
            // allow
        } elseif ($user->role === 'writer' && $task->assignments()->where('writer_id', $user->id)->exists()) {
            // allow
        } else {
            abort(403);
        }
    }

    return Storage::disk('r2')->download($file->file_path, $file->original_name);
}
```

- [ ] **Step 4: Run tests — confirm they pass**

```bash
php artisan test tests/Feature/Tasks/CompletedFileDownloadTest.php
```

Expected: All 5 tests pass.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/TaskFileController.php
git add tests/Feature/Tasks/CompletedFileDownloadTest.php
git commit -m "feat: gate completed file downloads by role and task status"
```

---

## Task 6: Livewire component updates

**Files:**
- Modify: `app/Livewire/Tasks/TaskDetail.php`

- [ ] **Step 1: Add modal state and open method**

In `app/Livewire/Tasks/TaskDetail.php`, add a new public property after `$showUploadModal`:

```php
public bool $showCompletedUploadModal = false;
```

Add this method after `openUploadModal()`:

```php
public function openCompletedUploadModal(): void
{
    $this->resetErrorBag();
    $this->showCompletedUploadModal = true;
}
```

- [ ] **Step 2: Update mount() to load the new relations**

Replace the `mount()` load call:

```php
public function mount(Task $task): void
{
    $this->task = $task->load([
        'unit', 'creator', 'pm', 'assignedAdmin',
        'assignments.writer',
        'regularFiles.uploader',
        'completedFiles.uploader',
        'notes.author',
    ]);

    if (session()->has('success')) {
        $this->dispatch('notify', message: session('success'), type: 'success');
    }
}
```

- [ ] **Step 3: Update render() to reload the new relations**

Replace the `$this->task->load(...)` call inside `render()`:

```php
$this->task->load([
    'unit', 'creator', 'assignedAdmin',
    'assignments.writer',
    'regularFiles.uploader',
    'completedFiles.uploader',
    'notes' => fn ($q) => $q->with('author')->latest(),
]);
```

- [ ] **Step 4: Scope deleteFile() to regular files only**

Replace the `deleteFile()` method body so it cannot accidentally target a completed file:

```php
public function deleteFile(int $fileId): void
{
    $file = $this->task->regularFiles()->findOrFail($fileId);
    Storage::disk('r2')->delete($file->file_path);
    $file->delete();
    $this->task->refresh();
    $this->dispatch('notify', message: 'File deleted.', type: 'success');
}
```

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/Tasks/TaskDetail.php
git commit -m "feat: add completed upload modal state and update task detail relations"
```

---

## Task 7: Blade view — Completed Files section + modal

**Files:**
- Modify: `resources/views/livewire/tasks/task-detail.blade.php`

- [ ] **Step 1: Update the Files section to use regularFiles**

In the Files card (around line 153), replace all three occurrences of `$task->files` with `$task->regularFiles`:

1. The count badge: `{{ $task->regularFiles->count() }}`
2. The `@if` check: `@if($task->regularFiles->count())`
3. The `@foreach`: `@foreach($task->regularFiles as $file)`

- [ ] **Step 2: Add the Completed Files section**

Add this entire block directly after the closing `</div>` of the Files card (after line ~204), still inside the `col-span-2` main column `<div class="col-span-2 space-y-5">`:

```blade
{{-- Completed Files --}}
@php
    $isPm = auth()->user()->isPm();
    $taskCompleted = $task->status === 'completed';
    $hasCompletedFiles = $task->completedFiles->count() > 0;
    $showCompletedSection = !$isPm || $taskCompleted || $hasCompletedFiles;
@endphp

@if($showCompletedSection)
<div class="bg-white dark:bg-slate-900 rounded-xl border border-gray-200 dark:border-slate-800 p-5">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-slate-100">
            Completed Files
            @if(!$isPm || $taskCompleted)
            <span class="ml-1.5 inline-flex items-center justify-center w-5 h-5 text-xs bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 rounded-full">{{ $task->completedFiles->count() }}</span>
            @endif
        </h2>
        @can('uploadCompletedFile', $task)
        <button wire:click="openCompletedUploadModal"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-green-700 hover:bg-green-50 border border-green-200 rounded-lg transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            Upload Completed
        </button>
        @endcan
    </div>

    @if($isPm && !$taskCompleted)
        {{-- PM sees review banner (section only rendered when hasCompletedFiles = true, enforced by $showCompletedSection) --}}
        <div class="flex items-center gap-2 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-800/40 rounded-xl">
            <svg class="w-4 h-4 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p class="text-sm text-amber-700 dark:text-amber-400">File is currently under review.</p>
        </div>
    @elseif($hasCompletedFiles)
        <div class="space-y-2">
            @foreach($task->completedFiles as $file)
            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-slate-950/50 rounded-xl">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-lg bg-green-50 dark:bg-green-900/20 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm text-gray-800 dark:text-slate-200 font-medium truncate">{{ $file->original_name }}</p>
                        <p class="text-xs text-gray-400 dark:text-slate-500">
                            {{ $file->file_size_formatted }}
                            @if(!auth()->user()->isWriter())
                                · {{ $file->uploader->name }}
                            @endif
                            · {{ $file->created_at->diffForHumans() }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <a href="{{ route('tasks.files.download', [$task, $file]) }}"
                        class="px-3 py-1.5 text-xs font-medium text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">Download</a>
                </div>
            </div>
            @endforeach
        </div>
    @else
        <p class="text-sm text-gray-400 dark:text-slate-500">No completed files uploaded yet.</p>
    @endif
</div>
@endif
```

- [ ] **Step 3: Add the Completed Files upload modal**

Add this block after the existing Upload Files modal closing `</div>` (after line ~418), before the final `</div>` that closes the component:

```blade
{{-- Upload Completed Files Modal --}}
<div
    x-data="{
        show: @entangle('showCompletedUploadModal').live,
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
            this.$refs.completedFileInput.files = dT.files;
        },
        reset() {
            this.fileObjects = [];
            if (this.$refs.completedFileInput) this.$refs.completedFileInput.value = '';
        },
        handleDrop(e) {
            this.dragging = false;
            if (e.dataTransfer?.files.length) this.addFiles(e.dataTransfer.files);
        }
    }"
    x-show="show"
    x-on:keydown.escape.window="show = false; reset()"
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    style="display: none;"
>
    <div x-show="show" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" @click="show = false; reset()"></div>
    <div x-show="show" x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
        class="relative bg-white dark:bg-slate-900 rounded-2xl shadow-2xl w-full max-w-md z-10 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-slate-800/60">
            <h3 class="text-base font-semibold text-gray-900 dark:text-slate-100">Upload Completed Files</h3>
            <button @click="show = false; reset()" class="text-gray-400 dark:text-slate-500 hover:text-gray-600 p-1 hover:bg-gray-100 dark:hover:bg-slate-800/50 rounded-lg transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form action="{{ route('tasks.completed-files.store', $task) }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Files <span class="text-gray-400 dark:text-slate-500 font-normal">(max 10 MB each)</span></label>
                <div
                    class="relative border-2 border-dashed rounded-xl p-6 text-center transition-colors cursor-pointer"
                    :class="dragging ? 'border-green-400 bg-green-50' : 'border-gray-300 hover:border-green-300 hover:bg-gray-50 dark:bg-slate-950/50'"
                    @dragover.prevent="dragging = true"
                    @dragleave.prevent="dragging = false"
                    @drop.prevent="handleDrop($event)"
                    @click="$refs.completedFileInput.click()"
                >
                    <svg class="mx-auto w-8 h-8 text-gray-400 dark:text-slate-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    <p class="text-sm text-gray-600 dark:text-slate-400">Drag & drop files here, or <span class="text-green-600 font-medium">browse</span></p>
                    <p class="text-xs text-gray-400 dark:text-slate-500 mt-1">Multiple files supported</p>
                    <input
                        x-ref="completedFileInput"
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
                                    <svg class="w-4 h-4 text-green-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
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
                    :disabled="fileObjects.length === 0"
                    class="flex-1 py-2.5 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
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

- [ ] **Step 4: Run the full test suite**

```bash
php artisan test
```

Expected: All tests pass. Watch specifically for the existing `TaskDetailRoleRestrictionsTest` and `TaskFileControllerRedirectTest` suites — they must still pass.

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/tasks/task-detail.blade.php
git commit -m "feat: add completed files section and upload modal to task detail"
```

---

## Self-Review

**Spec coverage check:**
- ✅ Migration adds `is_completed_file` boolean default false — Task 1
- ✅ Only writers (assigned) and admins can upload — Task 2 policy + Task 4 controller
- ✅ PMs cannot see upload option — Task 7 blade `@can('uploadCompletedFile', $task)`
- ✅ Upload auto-flips all assignments to `ready_for_review` — Task 4 storeCompleted
- ✅ PMs notified on upload — Task 4 storeCompleted + notification
- ✅ Admins notified on upload — Task 4 storeCompleted
- ✅ PM sees "under review" message when task not completed — Task 7 blade
- ✅ PM sees nothing when no completed files and task not completed — Task 7 `$showCompletedSection` flag
- ✅ PM can see + download when task is completed — Task 5 download + Task 7 blade
- ✅ Admins always see + download — Task 5 download + Task 7 blade
- ✅ Writers always see + download — Task 5 download + Task 7 blade
- ✅ deleteFile() scoped to regular files only — Task 6
- ✅ Existing Files section uses regularFiles — Task 7
