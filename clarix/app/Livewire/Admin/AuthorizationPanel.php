<?php

namespace App\Livewire\Admin;

use App\Models\Permission;
use App\Models\RolePermission;
use App\Services\PermissionService;
use Livewire\Component;

class AuthorizationPanel extends Component
{
    // Structure: $matrix['pm']['tasks.create'] = true/false
    public array $matrix = [];

    // Module order and labels
    public array $modules = [
        'units'   => 'Units',
        'users'   => 'Users',
        'tasks'   => 'Tasks',
        'credits' => 'Credit List',
    ];

    // All actions that could appear as columns (not all modules have all actions)
    public array $actionLabels = [
        'view'         => 'View',
        'create'       => 'Create',
        'update'       => 'Update',
        'delete'       => 'Delete',
        'assign'       => 'Assign',
        'upload_files' => 'Upload Files',
    ];

    // Permissions grouped by module: ['tasks' => [Permission, ...], ...]
    public array $modulePermissions = [];

    public array $roles = ['pm', 'writer'];

    public array $roleLabels = [
        'pm'     => 'Project Manager',
        'writer' => 'Writer',
    ];

    public function mount(): void
    {
        $this->loadMatrix();
    }

    private function loadMatrix(): void
    {
        $permissions = Permission::orderBy('module')->orderBy('action')->get();

        // Build modulePermissions: module => [name => label, ...]
        $grouped = [];
        foreach ($permissions as $perm) {
            $grouped[$perm->module][$perm->name] = [
                'id'     => $perm->id,
                'label'  => $perm->label,
                'action' => $perm->action,
            ];
        }
        $this->modulePermissions = $grouped;

        // Load existing role_permissions into matrix
        $existing = RolePermission::with('permission')->get();
        $map = [];
        foreach ($existing as $rp) {
            $map[$rp->role][$rp->permission->name] = $rp->allowed;
        }

        // Build full matrix with defaults
        $this->matrix = [];
        foreach ($this->roles as $role) {
            foreach ($permissions as $perm) {
                $this->matrix[$role][$perm->name] = $map[$role][$perm->name] ?? false;
            }
        }
    }

    /**
     * Called when any checkbox is toggled.
     * Immediately persists to DB and flushes cache.
     */
    public function toggle(string $role, string $permissionName): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $permission = Permission::where('name', $permissionName)->firstOrFail();

        $current = $this->matrix[$role][$permissionName] ?? false;
        $newValue = ! $current;

        RolePermission::updateOrCreate(
            ['role' => $role, 'permission_id' => $permission->id],
            ['allowed' => $newValue]
        );

        $this->matrix[$role][$permissionName] = $newValue;

        PermissionService::flushFor($role);

        $this->dispatch('notify',
            message: $permission->label . ' ' . ($newValue ? 'enabled' : 'disabled') . ' for ' . $this->roleLabels[$role],
            type: $newValue ? 'success' : 'info'
        );
    }

    /**
     * Grant all permissions for a role.
     */
    public function grantAll(string $role): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $permissions = Permission::all();
        foreach ($permissions as $perm) {
            RolePermission::updateOrCreate(
                ['role' => $role, 'permission_id' => $perm->id],
                ['allowed' => true]
            );
            $this->matrix[$role][$perm->name] = true;
        }

        PermissionService::flushFor($role);
        $this->dispatch('notify', message: 'All permissions granted to ' . $this->roleLabels[$role], type: 'success');
    }

    /**
     * Revoke all permissions for a role.
     */
    public function revokeAll(string $role): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $permissions = Permission::all();
        foreach ($permissions as $perm) {
            RolePermission::updateOrCreate(
                ['role' => $role, 'permission_id' => $perm->id],
                ['allowed' => false]
            );
            $this->matrix[$role][$perm->name] = false;
        }

        PermissionService::flushFor($role);
        $this->dispatch('notify', message: 'All permissions revoked from ' . $this->roleLabels[$role], type: 'info');
    }

    public function render()
    {
        return view('livewire.admin.authorization-panel')
            ->layout('layouts.app', ['pageTitle' => 'Authorization']);
    }
}
