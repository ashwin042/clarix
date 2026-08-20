<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Console\Command;

/**
 * Reports what changes for real users when enforcement moves off hardcoded
 * roles and onto the Authorization panel.
 *
 * Worth running against a copy of production before deploying, because the
 * toggles in that panel have never meant anything for most actions. An admin
 * who switched tasks.create off for PMs saw no effect and may well have left
 * it off; the moment the permission is actually enforced, every PM in that
 * agency loses the ability to create a task. This command names those cases
 * ahead of time instead of letting them arrive as support tickets.
 */
class AuditPermissionEnforcement extends Command
{
    protected $signature = 'permissions:audit {--organization= : Restrict to one organization id}';

    protected $description = 'Compare stored role permissions against the role rules that used to be hardcoded';

    /**
     * What each role could do before, when the gates were literal role checks.
     *
     * Read off the code being replaced: route middleware 'role:admin' on the
     * admin group, the form requests' authorize(), and the policies.
     *
     * @var array<string, list<string>>
     */
    protected array $previouslyAllowed = [
        'pm' => [
            'tasks.create', 'tasks.update', 'tasks.upload_files',
        ],
        'writer' => [
            // Writers reached none of the panel's actions by role alone.
        ],
    ];

    public function handle(): int
    {
        // Reads across every agency: this is a pre-deploy check run from the
        // console, not something an agency sees.
        return TenantContext::runWithoutScope(function () {
            $permissions = Permission::orderBy('name')->pluck('name', 'id');
            $losses      = 0;
            $gains       = 0;

            /*
             * Evaluated globally, because that is what the application
             * actually does: PermissionService reads role_permissions filtered
             * by role alone, and RolePermission carries no organization scope,
             * so every row applies to every agency regardless of the
             * organization_id sitting on it. Reporting this per organization
             * would describe the behaviour role_permissions is *going* to have
             * once it is made tenant-aware, not the behaviour being shipped.
             */
            $allowed = RolePermission::query()
                ->where('allowed', true)
                ->get()
                ->map(fn ($row) => $row->role.'|'.($permissions[$row->permission_id] ?? '?'))
                ->all();

            $this->info('Effective permission map (global — role_permissions is not yet tenant-scoped)');

            foreach ($this->previouslyAllowed as $role => $wasAllowed) {
                $affected = User::withoutGlobalScopes()->where('role', $role)->count();

                foreach ($wasAllowed as $permission) {
                    if (! in_array("{$role}|{$permission}", $allowed, true)) {
                        $losses++;
                        $this->line("  <fg=red>LOSES</>  {$role} could {$permission} by role, and the panel has it off — {$affected} affected");
                    }
                }

                foreach ($permissions as $permission) {
                    if (in_array("{$role}|{$permission}", $allowed, true)
                        && ! in_array($permission, $wasAllowed, true)
                        && ! in_array($permission, ['tasks.view', 'credits.view'], true)) {
                        $gains++;
                        $this->line("  <fg=yellow>GAINS</>  {$role} gains {$permission}, which the panel already had switched on — {$affected} affected");
                    }
                }
            }

            $this->reportRowOwnership();

            $this->line('');
            $this->line("Capabilities lost on deploy:   {$losses}");
            $this->line("Capabilities gained on deploy: {$gains}");

            if ($losses > 0) {
                $this->line('');
                $this->warn('Switch the listed permissions on in the Authorization panel before deploying, or those users will be locked out of work they can do today.');
            }

            return self::SUCCESS;
        });
    }

    /**
     * Which agency each row is stamped with, and which agencies have none.
     *
     * Reported rather than acted on. It changes nothing about what ships here,
     * since the map is global today, but it is what decides whether making
     * role_permissions tenant-scoped later would leave an agency with an empty
     * map — so it is worth having on the record before that work starts.
     */
    protected function reportRowOwnership(): void
    {
        $this->line('');
        $this->info('Row ownership (relevant only once role_permissions becomes tenant-scoped)');

        $counts = RolePermission::query()
            ->selectRaw('organization_id, count(*) as total')
            ->groupBy('organization_id')
            ->pluck('total', 'organization_id');

        foreach (Organization::orderBy('id')->get(['id', 'name']) as $organization) {
            $total    = (int) ($counts[$organization->id] ?? 0);
            $members  = User::withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->whereIn('role', ['pm', 'writer'])
                ->count();

            $note = $total === 0
                ? "<fg=yellow>no rows of its own</> — would inherit nothing; {$members} non-admin member(s)"
                : "{$total} rows";

            $this->line("  #{$organization->id} {$organization->name}: {$note}");
        }

        if (($unowned = (int) ($counts[null] ?? 0)) > 0) {
            $this->line("  <fg=yellow>unowned</>: {$unowned} rows carry no organization_id");
        }
    }
}
