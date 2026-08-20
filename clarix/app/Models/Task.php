<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Observers\TaskActivityObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Services\StorageUsageService;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

#[ObservedBy(TaskActivityObserver::class)]
class Task extends Model
{
    use BelongsToOrganization;

    /**
     * What a task is worth is an admin's decision, and only on an existing
     * task — a PM sets the figure when they file the work, and from then on it
     * is fixed to them.
     *
     * Enforced on the model rather than at each screen because there are three
     * separate edit paths today (TaskController@update, ManageTasks and
     * AssignedTasks) and nothing stops a fourth being added. A check written
     * into each one is a check that gets forgotten; here, every path that ends
     * in a save is covered, including any added later.
     *
     * The revert is silent, matching how ManageTasks already pins unit_id,
     * pm_id and status for a PM: the rest of the edit is legitimate and goes
     * through, and only this field snaps back to what it was.
     *
     * Deliberately scoped to an authenticated non-admin. Console commands,
     * queued jobs and seeders have no acting user and must stay able to write
     * the column, or the backfills and the seeder would silently stop working.
     */
    protected static function booted(): void
    {
        static::updating(function (Task $task): void {
            if (! $task->isDirty('credit_amount')) {
                return;
            }

            $actor = auth()->user();

            if ($actor === null || $actor->isAdmin()) {
                return;
            }

            $task->credit_amount = $task->getOriginal('credit_amount');
        });
    }

    protected $fillable = [
        'title',
        'task_code',
        'task_type',
        'important_notes',
        'unit_id',
        'created_by',
        'pm_id',
        'assigned_admin_id',
        'priority',
        'status',
        'position',
        'deadline',
        'credit_amount',
        'completed_at',
    ];

    /**
     * The kanban board's fixed columns, keyed by the tasks.status value each
     * one maps to. Columns are not user-editable — the board is a view over
     * the status enum, so anything not listed here (currently 'cancelled')
     * simply does not appear on the board. Column colours live in the Blade
     * view alongside the other status colours; Tailwind does not scan models.
     */
    public const BOARD_COLUMNS = [
        'pending'         => ['label' => 'Pending'],
        'on_hold'         => ['label' => 'On hold'],
        'in_progress'     => ['label' => 'In progress'],
        'sent_for_review' => ['label' => 'Sent for review'],
        'completed'       => ['label' => 'Completed'],
    ];

    /**
     * Every value the status column accepts, board or not.
     *
     * BOARD_COLUMNS is a subset — 'cancelled' is a real status with no column
     * — so it cannot serve as the whitelist. Named here because the list was
     * written out by hand in each place that needed it, and applyStatusChange()
     * writes whatever string reaches it: a caller that validated against a
     * shorter list, or against none, put an unknown value straight into an
     * enum column that only MySQL would reject.
     *
     * @var list<string>
     */
    public const STATUSES = ['pending', 'on_hold', 'in_progress', 'sent_for_review', 'completed', 'cancelled'];

    protected function casts(): array
    {
        return [
            'deadline'     => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pm(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pm_id');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    public function writers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignments', 'task_id', 'writer_id')
            ->withPivot('status', 'assigned_by')
            ->withTimestamps();
    }

    public function files(): HasMany
    {
        return $this->hasMany(TaskFile::class);
    }

    public function regularFiles(): HasMany
    {
        return $this->hasMany(TaskFile::class)->regular();
    }

    public function completedFiles(): HasMany
    {
        return $this->hasMany(TaskFile::class)->completed();
    }

    /**
     * The R2 prefix new uploads for this task are written under.
     *
     * The unit id leads the path so that every object is addressable by
     * tenant. task_code is only unique per unit — see the unique index on
     * ['unit_id', 'task_code'] — so a code on its own cannot identify whose
     * file it is, and a prefix keyed on it alone would span units.
     *
     * Files uploaded before this changed still live under the old
     * task-files/{task_code}/ layout. Reads and deletes go through the stored
     * file_path, so both layouts keep working; only new writes land here.
     */
    public function storagePrefix(): string
    {
        return 'task-files/'.$this->unit_id.'/'.$this->task_code;
    }

    /**
     * The R2 prefix new completed-file uploads for this task are written
     * under.
     */
    public function completedStoragePrefix(): string
    {
        return $this->storagePrefix().'/completed';
    }

    /**
     * Delete the task along with every attached file. Each file (regular and
     * completed) is removed from R2 first, then the file records, then the
     * task itself. A failed R2 delete is logged but does not stop the rest of
     * the cleanup, so nothing gets stuck.
     */
    public function deleteWithFiles(): void
    {
        $bytes = 0;

        foreach ($this->files as $file) {
            $this->deleteFileFromR2($file->file_path);
            $bytes += (int) $file->file_size;
        }

        // files()->delete() is a mass delete, which does not fire model
        // events, so the TaskFile observer never sees these rows go. The
        // unit's storage total is adjusted here in their place.
        $this->files()->delete();

        if ($bytes > 0 && $this->unit_id) {
            app(StorageUsageService::class)->decrement((int) $this->unit_id, $bytes);
        }

        $this->delete();
    }

    /**
     * Delete a single stored object from R2, logging (but swallowing) any
     * failure so callers can continue their cleanup.
     */
    public function deleteFileFromR2(string $filePath): void
    {
        try {
            // The r2 disk is configured with 'throw' => false, so a failed
            // delete returns false rather than raising an exception.
            if (! Storage::disk('r2')->delete($filePath)) {
                Log::error('R2 storage reported failure deleting file.', [
                    'file_path' => $filePath,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to delete file from R2 storage.', [
                'file_path' => $filePath,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Everything that has happened to this task, oldest first by id.
     *
     * Ordered by id rather than created_at: several entries can share a
     * timestamp when one save changes two fields, and id is the only thing
     * that keeps them in the order they were written.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(TaskNote::class)->latest();
    }

    /**
     * The completed_at attributes to merge into an update that moves this task
     * to $status — an empty array when the timestamp must be left alone.
     *
     * completed_at is write-once. It is stamped the first time a task reaches
     * completed and never touched again, so a task that is reopened and later
     * re-completed keeps the date it was originally finished on. Nothing here
     * ever nulls the column: that is what the credit list bills against, and
     * reopening a task must not silently rewrite history.
     */
    public function completedAtFor(string $status): array
    {
        if ($status === 'completed' && is_null($this->completed_at)) {
            return ['completed_at' => now()];
        }

        return [];
    }

    /**
     * Apply a status change together with everything that hangs off it:
     * completed_at bookkeeping, and rolling ready-for-review assignments up to
     * completed once the task itself is done. The task detail page and the
     * kanban board both go through here so a status set by dragging a card is
     * indistinguishable from one set by the detail page buttons.
     */
    public function applyStatusChange(string $status): void
    {
        $from = $this->getOriginal('status');

        $this->update(['status' => $status] + $this->completedAtFor($status));

        // Logged here rather than in the observer's updated() hook because
        // this method is the single door every status change already comes
        // through — a dragged card and a chosen dropdown value both land here,
        // so both produce one identical entry. The observer skips 'status' for
        // the same reason, or every change would be recorded twice.
        if ($from !== $status) {
            app(\App\Services\TaskActivityLogger::class)
                ->record($this, 'status_changed', ['from' => $from, 'to' => $status]);
        }

        if ($status === 'completed') {
            $this->assignments()
                ->where('status', 'ready_for_review')
                ->update(['status' => 'completed']);
        }

        $this->refresh();
    }

    public function scopeForAdmin(Builder $query): Builder
    {
        return $query->with(['unit', 'creator']);
    }

    public function scopeForPm(Builder $query, int $unitId): Builder
    {
        return $query->where('unit_id', $unitId)->with(['unit']);
    }

    public function scopeForWriter(Builder $query, int $writerId): Builder
    {
        return $query->whereHas('assignments', fn ($q) => $q->where('writer_id', $writerId))
            ->with(['files']);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeByPriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    /**
     * The acting user's organization wins, so a request can never attribute a
     * record to another agency. Console commands and queued jobs have no
     * acting user, and fall back to the unit that owns this row.
     */
    protected function resolveOrganizationId(): ?int
    {
        return TenantContext::organizationId() ?? $this->unit?->organization_id;
    }
}
