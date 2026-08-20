<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Permission extends Model
{
    protected $fillable = ['name', 'module', 'action', 'label'];

    public function rolePermissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    /**
     * All defined permission names grouped by module.
     *
     * Deletion is absent, and stays absent. Removing a task, a unit or a
     * person is admin-only by structure — there is no permission to grant, so
     * listing one here would advertise a control that does not exist. The
     * check lives in the policies, on User::administers().
     */
    public static function allByModule(): array
    {
        return [
            'units' => [
                'units.view'   => 'View Units',
                'units.create' => 'Create Units',
                'units.update' => 'Update Units',
            ],
            'users' => [
                'users.view'   => 'View Users',
                'users.create' => 'Create Users',
                'users.update' => 'Update Users',
            ],
            'tasks' => [
                'tasks.view'         => 'View Tasks',
                'tasks.create'       => 'Create Tasks',
                'tasks.update'       => 'Update Tasks',
                'tasks.assign'       => 'Assign Writers',
                'tasks.upload_files' => 'Upload Files',
            ],
            'credits' => [
                'credits.view' => 'View Credit List',
            ],
        ];
    }
}
