<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Contracts\PlatformVisible;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Services\PermissionService;
use App\Services\PlanFeatures;

/**
 * PlatformVisible: a superadmin may read the member list of every
 * organization. It is one of only three models they may — see the interface.
 */
class User extends Authenticatable implements PlatformVisible
{
    use HasFactory, Notifiable;
    use BelongsToOrganization;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'unit_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Overrides Notifiable's relation so notifications are read and written
     * through the tenant-scoped model rather than Laravel's own. Both the
     * database channel and unreadNotifications() route through here, so this
     * one override covers every path.
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable')->latest();
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    public function ownedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'pm_id');
    }

    public function taskAssignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class, 'writer_id');
    }

    public function assignedTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_assignments', 'writer_id', 'task_id')
            ->withPivot('status', 'assigned_by')
            ->withTimestamps();
    }

    public function uploadedTaskFiles(): HasMany
    {
        return $this->hasMany(TaskFile::class, 'uploaded_by');
    }

    public function taskNotes(): HasMany
    {
        return $this->hasMany(TaskNote::class, 'created_by');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class, 'created_by');
    }

    /**
     * Platform-level administrator, above any single agency.
     *
     * Nothing grants this role behaviour yet; the superadmin portal and the
     * permission wiring arrive in a later phase.
     */
    public function isSuperadmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isPm(): bool
    {
        return $this->role === 'pm';
    }

    /**
     * Runs the agency's work across every unit, without running the agency.
     *
     * The role's whole character is in that gap, so the two things worth
     * remembering are both negatives: a supervisor is not a PM, and so is not
     * confined to one unit; and a supervisor is not an admin, and so may
     * destroy nothing — administers() answers false for them like anyone else.
     */
    public function isSupervisor(): bool
    {
        return $this->role === 'supervisor';
    }

    /**
     * Runs attendance, leave and pay for the whole agency, and nothing else.
     *
     * Named here because AttendancePolicy and LeaveRequestPolicy have to ask:
     * their reach is structural, and "everyone in the agency" was previously a
     * thing only an admin could be.
     */
    public function isHr(): bool
    {
        return $this->role === 'hr';
    }

    /**
     * Works across every unit of the agency rather than inside one.
     *
     * Admin and supervisor differ in what they may do; they do not differ in
     * how far they reach, and neither carries a unit_id. So anything keyed on
     * "which unit is this person's" — the board's unit filter, the unit and PM
     * pickers in the create modal — has to ask this rather than isAdmin(), and
     * hand them the agency's units to choose from.
     *
     * Named rather than spelled out inline because the question is asked in
     * four places across ManageTasks and its view. Inline, the view's copies
     * were simply missed when the supervisor role arrived: a supervisor was
     * given admin's data and a PM's markup — two disabled boxes over a form
     * that could then only fail validation.
     */
    public function reachesEveryUnit(): bool
    {
        return $this->isAdmin() || $this->isSupervisor();
    }

    public function isWriter(): bool
    {
        return $this->role === 'writer';
    }

    /**
     * Kept as a named shorthand, but answered by the Authorization panel
     * rather than by a literal role list, so it cannot drift back out of step
     * with the toggle that claims to control it.
     */
    public function canUploadFiles(): bool
    {
        return $this->hasPermission('tasks.upload_files');
    }

    /**
     * Whether this user is an administrator of the agency that owns a record.
     *
     * The single test behind every destructive action. Deletion is not a
     * grantable capability in Clarix: there is no permission to switch on, so
     * no configuration an agency's admin can get wrong hands anyone else the
     * ability to remove a task, a unit or a person. That is why this asks the
     * role directly instead of going through hasPermission().
     *
     * The organization half is checked here as well as by OrganizationScope.
     * The scope stops another agency's row from ever being loaded, which is
     * the protection that matters; repeating it means a record that reached a
     * policy some other way — an unscoped query, a relation loaded in console
     * context — still cannot be deleted by an outsider.
     *
     * A platform superadmin fails this deliberately. They administer the
     * platform, not the agencies' work, and hold no organization of their own.
     */
    public function administers(\Illuminate\Database\Eloquent\Model $record): bool
    {
        if (! $this->isAdmin() || $this->organization_id === null) {
            return false;
        }

        return (int) $record->getAttribute('organization_id') === (int) $this->organization_id;
    }

    /**
     * Check if this user has a given permission.
     * Admin always returns true. PM/Writer checks role_permissions table.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        // Resolved against this user's own agency, not whichever one happens
        // to be acting. A superadmin has no organization and so no map.
        return in_array(
            $permission,
            PermissionService::allowedFor($this->role, $this->organization_id === null ? null : (int) $this->organization_id)
        );
    }

    /**
     * Whether this person's organization has bought a feature.
     *
     * The plan layer, deliberately shaped like hasPermission() so the two read
     * alike at call sites while staying entirely separate underneath. Both
     * must pass: this one asks what the agency purchased, the other asks what
     * the role may do. Neither can stand in for the other — a Pro plan grants
     * a writer nothing, and an admin holding every permission in the panel is
     * still on whatever plan their agency pays for.
     *
     * A superadmin is never plan-gated. They belong to no organization, so
     * there is no plan to consult, and the platform portal must not be
     * restricted by the commercial state of the agencies it administers — the
     * same exemption EnsureSubscriptionActive already grants them.
     */
    public function planAllows(string $feature): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        return app(PlanFeatures::class)->allows(
            $feature,
            $this->organization_id === null ? null : (int) $this->organization_id
        );
    }

    /**
     * Check multiple permissions (all must pass).
     */
    public function hasAllPermissions(string ...$permissions): bool
    {
        foreach ($permissions as $permission) {
            if (! $this->hasPermission($permission)) {
                return false;
            }
        }
        return true;
    }
}

