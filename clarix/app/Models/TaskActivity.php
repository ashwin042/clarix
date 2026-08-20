<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing that happened to a task.
 *
 * Rows are written by observers rather than by the screens, so every route
 * into an action is covered by one piece of bookkeeping — the same argument
 * TaskFileObserver makes for storage accounting.
 *
 * Nothing here is user-editable and nothing updates: the log is append-only in
 * practice, which is what makes it worth reading.
 */
class TaskActivity extends Model
{
    use BelongsToOrganization;

    /** How a writer is referred to, always. See describeFor(). */
    public const MASKED_ACTOR = 'Assigned writer';

    protected $fillable = [
        'task_id',
        'user_id',
        'actor_role',
        'event',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
        ];
    }

    /**
     * An entry belongs to the organization of the task it describes.
     *
     * Read from the task rather than from the ambient context, the way
     * Notification reads from its recipient. Activity is written by observers,
     * which fire wherever a model is saved — a console command, a queued job
     * or a seeder has no acting tenant, and the default resolution would hand
     * back null against a NOT NULL column. The task always knows.
     */
    protected function resolveOrganizationId(): ?int
    {
        // TenantContext directly, not parent:: — BelongsToOrganization is a
        // trait, so the default lives on this class rather than above it.
        if ($fromContext = TenantContext::organizationId()) {
            return $fromContext;
        }

        if ($this->task_id === null) {
            return null;
        }

        return Task::withoutGlobalScopes()
            ->whereKey($this->task_id)
            ->value('organization_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Who did this, as it should be shown.
     *
     * Masking is unconditional: a writer is never named, whoever is looking.
     * That is stricter than the PM-only rule the Assigned Writers list uses,
     * and deliberately so — a log is read long after the fact and by more
     * people than the section above it.
     *
     * The decision is made on actor_role, the role recorded when the row was
     * written, so it holds even if the account has since been promoted,
     * renamed or deleted.
     */
    public function actorName(): string
    {
        if ($this->actor_role === 'writer') {
            return self::MASKED_ACTOR;
        }

        if ($this->user_id === null) {
            return 'System';
        }

        return $this->user?->name ?? 'A removed account';
    }

    /**
     * The entry as a sentence.
     *
     * Built here rather than stored, so masking is applied on the way out and
     * the wording can change without rewriting what happened. $viewer is
     * accepted for symmetry with the rest of the page and because a
     * viewer-dependent rule was a live option; it is unused while masking is
     * unconditional, and having the seam already cut is cheaper than adding it
     * later.
     */
    public function describeFor(?User $viewer = null): string
    {
        $actor = $this->actorName();
        $d = $this->details ?? [];

        return match ($this->event) {
            'created'         => "{$actor} created this task",
            'status_changed'  => sprintf(
                '%s changed status from %s to %s',
                $actor,
                self::humanise($d['from'] ?? '—'),
                self::humanise($d['to'] ?? '—'),
            ),
            'file_uploaded'   => sprintf(
                '%s uploaded %s%s',
                $actor,
                $d['filename'] ?? 'a file',
                ($d['completed'] ?? false) ? ' as a completed file' : '',
            ),
            'file_deleted'    => sprintf('%s deleted %s', $actor, $d['filename'] ?? 'a file'),
            // The writer is the subject here, not the actor, and is masked for
            // the same reason — naming them in "X assigned Y" would leak the
            // identity the upload lines are careful to withhold.
            'writer_assigned' => "{$actor} assigned a writer",
            'writer_removed'  => "{$actor} removed a writer",
            'note_added'      => "{$actor} added a note",
            'updated'         => sprintf(
                '%s changed %s from %s to %s',
                $actor,
                self::humanise($d['field'] ?? 'a field'),
                self::valueFor($d['from'] ?? null),
                self::valueFor($d['to'] ?? null),
            ),
            default           => "{$actor} updated this task",
        };
    }

    /** 'sent_for_review' -> 'Sent for review', 'pm_id' -> 'PM'. */
    protected static function humanise(string $value): string
    {
        return match ($value) {
            'pm_id'             => 'PM',
            'assigned_admin_id' => 'assigned supervisor',
            'credit_amount'     => 'credit amount',
            'task_type'         => 'task type',
            default             => ucfirst(str_replace('_', ' ', $value)),
        };
    }

    /**
     * A stored value as it should read.
     *
     * Values arrive already presentable — the logger resolves a pm_id to a
     * name before writing, so an entry says what it meant at the time rather
     * than an integer that needs a lookup to make sense of. A person renamed
     * afterwards keeps their old name in old entries, which is the honest
     * thing for a log to say.
     */
    protected static function valueFor(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'nothing';
        }

        return (string) $value;
    }
}
