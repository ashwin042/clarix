<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Services\PermissionService;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

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

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isPm(): bool
    {
        return $this->role === 'pm';
    }

    public function isWriter(): bool
    {
        return $this->role === 'writer';
    }

    public function canUploadFiles(): bool
    {
        return in_array($this->role, ['admin', 'pm']);
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

        return in_array($permission, PermissionService::allowedFor($this->role));
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

