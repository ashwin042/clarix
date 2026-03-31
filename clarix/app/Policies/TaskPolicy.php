<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Task $task): bool
    {
        return match ($user->role) {
            'admin'  => true,
            'pm'     => $task->unit_id === $user->unit_id,
            'writer' => $task->assignments()->where('writer_id', $user->id)->exists(),
            default  => false,
        };
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'pm']);
    }

    public function update(User $user, Task $task): bool
    {
        return match ($user->role) {
            'admin' => true,
            'pm'    => $task->unit_id === $user->unit_id,
            default => false,
        };
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->isAdmin();
    }

    public function updateStatus(User $user, Task $task): bool
    {
        return $user->isAdmin();
    }

    public function uploadFiles(User $user, Task $task): bool
    {
        return match ($user->role) {
            'admin' => true,
            'pm'    => $task->unit_id === $user->unit_id,
            default => false,
        };
    }

    public function assign(User $user, Task $task): bool
    {
        return $user->isAdmin();
    }
}
