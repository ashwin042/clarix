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
     */
    public static function allByModule(): array
    {
        return [
            'units' => [
                'units.view'   => 'View Units',
                'units.create' => 'Create Units',
                'units.update' => 'Update Units',
                'units.delete' => 'Delete Units',
            ],
            'users' => [
                'users.view'   => 'View Users',
                'users.create' => 'Create Users',
                'users.update' => 'Update Users',
                'users.delete' => 'Delete Users',
            ],
            'tasks' => [
                'tasks.view'         => 'View Tasks',
                'tasks.create'       => 'Create Tasks',
                'tasks.update'       => 'Update Tasks',
                'tasks.delete'       => 'Delete Tasks',
                'tasks.assign'       => 'Assign Writers',
                'tasks.upload_files' => 'Upload Files',
            ],
            'credits' => [
                'credits.view' => 'View Credit List',
            ],
        ];
    }
}
