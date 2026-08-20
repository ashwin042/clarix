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
        'units'      => 'Units',
        'users'      => 'Users',
        'tasks'      => 'Tasks',
        'credits'    => 'Credit List',
        'attendance' => 'Attendance',
        'leave'      => 'Leave',
        'payroll'    => 'Payroll',
    ];

    // All actions that could appear as columns (not all modules have all actions)
    public array $actionLabels = [
        'view'         => 'View',
        'create'       => 'Create',
        'update'       => 'Update',
        'assign'       => 'Assign',
        'upload_files' => 'Upload Files',
        'view_own'     => 'View Own',
        'view_all'     => 'View Team',
        'manage'       => 'Manage',
    ];

    /**
     * Actions this screen refuses to offer, whatever is in the permissions
     * table.
     *
     * Deleting a task, a unit or a person is admin-only by structure, and the
     * seeder no longer creates a permission for it. Filtering here as well
     * means a row left behind by an older database, or inserted by hand, still
     * cannot appear as a toggle or be flipped through this component — the
     * panel can only ever offer what is genuinely grantable.
     *
     * @var list<string>
     */
    public const UNGRANTABLE_ACTIONS = ['delete'];

    // Permissions grouped by module: ['tasks' => [Permission, ...], ...]
    public array $modulePermissions = [];

    /**
     * Every role an agency configures, in seniority order.
     *
     * Admin is absent and stays absent: hasPermission() answers true for them
     * unconditionally, so a row of toggles would claim to control something it
     * does not. Superadmin is absent for the opposite reason — they belong to
     * no agency and have no map here to edit.
     */
    public array $roles = ['supervisor', 'pm', 'hr', 'writer'];

    public array $roleLabels = [
        'supervisor' => 'Supervisor',
        'pm'         => 'Project Manager',
        'hr'         => 'HR',
        'writer'     => 'Writer',
    ];

    public function mount(): void
    {
        $this->authorizeAdmin();
        $this->loadMatrix();
    }

    /**
     * An admin, and one who belongs to an agency.
     *
     * Every row this screen reads and writes is owned by the acting user's
     * organization. Somebody with no organization — a platform superadmin —
     * has no map to edit and must not reach a screen that would otherwise
     * write rows belonging to nobody.
     */
    protected function authorizeAdmin(): void
    {
        $user = auth()->user();

        abort_unless($user?->isAdmin() && $user->organization_id !== null, 403);
    }

    /**
     * The agency whose map this screen is editing.
     */
    protected function organizationId(): int
    {
        return (int) auth()->user()->organization_id;
    }

    /**
     * The permissions this panel is allowed to show and change.
     */
    private function grantablePermissions()
    {
        return Permission::whereNotIn('action', self::UNGRANTABLE_ACTIONS)
            ->orderBy('module')
            ->orderBy('action')
            ->get();
    }

    private function loadMatrix(): void
    {
        $permissions = $this->grantablePermissions();

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

        /*
         * Confined to this agency by OrganizationScope, which is what makes
         * the screen safe: an admin reads their own map and could not see
         * another organization's rows if they tried. The writes below are
         * stamped with the same organization by BelongsToOrganization.
         */
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
        $this->authorizeAdmin();

        $permission = Permission::where('name', $permissionName)->firstOrFail();

        // The screen never draws a delete toggle, but the action is reachable
        // by anyone willing to post to Livewire's endpoint directly.
        abort_if(in_array($permission->action, self::UNGRANTABLE_ACTIONS, true), 403);

        $current = $this->matrix[$role][$permissionName] ?? false;
        $newValue = ! $current;

        RolePermission::updateOrCreate(
            ['role' => $role, 'permission_id' => $permission->id],
            ['allowed' => $newValue]
        );

        $this->matrix[$role][$permissionName] = $newValue;

        PermissionService::flushFor($role, $this->organizationId());

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
        $this->authorizeAdmin();

        // Grantable only: "all" must never reach a delete permission left
        // behind in an older database.
        foreach ($this->grantablePermissions() as $perm) {
            RolePermission::updateOrCreate(
                ['role' => $role, 'permission_id' => $perm->id],
                ['allowed' => true]
            );
            $this->matrix[$role][$perm->name] = true;
        }

        PermissionService::flushFor($role, $this->organizationId());
        $this->dispatch('notify', message: 'All permissions granted to ' . $this->roleLabels[$role], type: 'success');
    }

    /**
     * Revoke all permissions for a role.
     */
    public function revokeAll(string $role): void
    {
        $this->authorizeAdmin();

        foreach ($this->grantablePermissions() as $perm) {
            RolePermission::updateOrCreate(
                ['role' => $role, 'permission_id' => $perm->id],
                ['allowed' => false]
            );
            $this->matrix[$role][$perm->name] = false;
        }

        PermissionService::flushFor($role, $this->organizationId());
        $this->dispatch('notify', message: 'All permissions revoked from ' . $this->roleLabels[$role], type: 'info');
    }

    public function render()
    {
        // mount() runs only on the first render; every later Livewire request
        // arrives on a hydrated component and passes through here instead.
        $this->authorizeAdmin();

        return view('livewire.admin.authorization-panel')
            ->layout('layouts.app', ['pageTitle' => 'Authorization']);
    }
}
