<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\RolePermission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Define ALL permissions
        $definitions = [
            // Units
            ['name' => 'units.view',   'module' => 'units',   'action' => 'view',         'label' => 'View Units'],
            ['name' => 'units.create', 'module' => 'units',   'action' => 'create',       'label' => 'Create Units'],
            ['name' => 'units.update', 'module' => 'units',   'action' => 'update',       'label' => 'Update Units'],
            ['name' => 'units.delete', 'module' => 'units',   'action' => 'delete',       'label' => 'Delete Units'],

            // Users
            ['name' => 'users.view',   'module' => 'users',   'action' => 'view',         'label' => 'View Users'],
            ['name' => 'users.create', 'module' => 'users',   'action' => 'create',       'label' => 'Create Users'],
            ['name' => 'users.update', 'module' => 'users',   'action' => 'update',       'label' => 'Update Users'],
            ['name' => 'users.delete', 'module' => 'users',   'action' => 'delete',       'label' => 'Delete Users'],

            // Tasks
            ['name' => 'tasks.view',         'module' => 'tasks', 'action' => 'view',         'label' => 'View Tasks'],
            ['name' => 'tasks.create',       'module' => 'tasks', 'action' => 'create',       'label' => 'Create Tasks'],
            ['name' => 'tasks.update',       'module' => 'tasks', 'action' => 'update',       'label' => 'Update Tasks'],
            ['name' => 'tasks.delete',       'module' => 'tasks', 'action' => 'delete',       'label' => 'Delete Tasks'],
            ['name' => 'tasks.assign',       'module' => 'tasks', 'action' => 'assign',       'label' => 'Assign Writers'],
            ['name' => 'tasks.upload_files', 'module' => 'tasks', 'action' => 'upload_files', 'label' => 'Upload Files'],

            // Credits
            ['name' => 'credits.view', 'module' => 'credits', 'action' => 'view',         'label' => 'View Credit List'],
        ];

        foreach ($definitions as $def) {
            Permission::firstOrCreate(['name' => $def['name']], $def);
        }

        // -----------------------------------------------------------------
        // DEFAULT ROLE PERMISSIONS
        // pm: can view+create+update tasks, view credits, view units/users
        // writer: can only view their own tasks
        // -----------------------------------------------------------------

        $pmDefaults = [
            'units.view'         => true,
            'units.create'       => false,
            'units.update'       => false,
            'units.delete'       => false,
            'users.view'         => true,
            'users.create'       => true,
            'users.update'       => false,
            'users.delete'       => false,
            'tasks.view'         => true,
            'tasks.create'       => true,
            'tasks.update'       => true,
            'tasks.delete'       => false,
            'tasks.assign'       => true,
            'tasks.upload_files' => true,
            'credits.view'       => true,
        ];

        $writerDefaults = [
            'units.view'         => false,
            'units.create'       => false,
            'units.update'       => false,
            'units.delete'       => false,
            'users.view'         => false,
            'users.create'       => false,
            'users.update'       => false,
            'users.delete'       => false,
            'tasks.view'         => true,
            'tasks.create'       => false,
            'tasks.update'       => false,
            'tasks.delete'       => false,
            'tasks.assign'       => false,
            'tasks.upload_files' => false,
            'credits.view'       => false,
        ];

        $permissions = Permission::all()->keyBy('name');

        foreach (['pm' => $pmDefaults, 'writer' => $writerDefaults] as $role => $defaults) {
            foreach ($defaults as $permName => $allowed) {
                if ($perm = $permissions->get($permName)) {
                    RolePermission::updateOrCreate(
                        ['role' => $role, 'permission_id' => $perm->id],
                        ['allowed' => $allowed]
                    );
                }
            }
        }
    }
}
