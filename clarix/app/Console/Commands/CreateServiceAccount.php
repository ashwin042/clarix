<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\Unit;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Mints the account an external integration authenticates as, and prints its
 * token once.
 *
 * The account is an ordinary user row with role 'pm'. That is not a shortcut:
 * TenantContext answers "which organization is acting" by reading the
 * authenticated user, so an integration that is not a user has no organization,
 * and no organization means no tenant filtering at all — the task would be
 * written unowned and TenantExists would stop confining unit ids to the agency.
 * Being a real PM in a real unit is what makes the API safe by construction
 * rather than by a second set of checks.
 *
 * 'pm' specifically, rather than a new 'service' role, because role is a MySQL
 * enum: another value means an ALTER TABLE migration, and the sqlite test suite
 * would never exercise it. The PM-shaped endpoint wants PM authority anyway.
 */
class CreateServiceAccount extends Command
{
    protected $signature = 'clarix:service-account
                            {organization : id or slug of the owning organization}
                            {unit : id or name of the unit tasks will be filed under}
                            {--name=API Service Account : display name for the account}
                            {--email= : defaults to service+UNIT_ID at the organization slug .invalid}
                            {--abilities=tasks:create,files:write : comma separated token abilities}';

    protected $description = 'Create (or reuse) a service account for an organization and issue an API token';

    public function handle(): int
    {
        $organization = TenantContext::runWithoutScope(fn () => Organization::query()
            ->where('id', $this->argument('organization'))
            ->orWhere('slug', $this->argument('organization'))
            ->first());

        if ($organization === null) {
            $this->error('No such organization: '.$this->argument('organization'));

            return self::FAILURE;
        }

        // Everything below runs inside the agency, so the unit lookup is
        // confined to it and the new user is stamped with it automatically.
        return TenantContext::actingAsOrganization($organization->id, function () use ($organization) {
            $unit = Unit::query()
                ->where('id', $this->argument('unit'))
                ->orWhere('name', $this->argument('unit'))
                ->first();

            if ($unit === null) {
                $this->error('No such unit in '.$organization->name.': '.$this->argument('unit'));

                return self::FAILURE;
            }

            $email = $this->option('email') ?: 'service+'.$unit->id.'@'.$organization->slug.'.invalid';

            $account = User::where('email', $email)->first();

            if ($account === null) {
                $account = User::create([
                    'name'     => $this->option('name'),
                    'email'    => $email,
                    // Never signed in to interactively. A random secret means
                    // there is no password to guess and none to leak, and the
                    // token is the only way in.
                    'password' => Str::random(64),
                    'role'     => 'pm',
                    'unit_id'  => $unit->id,
                ]);

                $this->info('Created service account '.$email);
            } else {
                $this->warn('Reusing existing service account '.$email);
            }

            // Scoped to named abilities. A token that could do everything the
            // account can do is a token whose blast radius grows every time the
            // account gains a permission — so filing tasks and writing objects
            // into the agency's bucket are separate grants, and an integration
            // that only needs one can be given only that.
            $abilities = collect(explode(',', (string) $this->option('abilities')))
                ->map(fn (string $ability) => trim($ability))
                ->filter()
                ->values()
                ->all();

            if ($abilities === []) {
                $this->error('At least one ability is required.');

                return self::FAILURE;
            }

            $token = $account->createToken('task-intake', $abilities);

            $this->newLine();
            $this->line('Organization: '.$organization->name.' (#'.$organization->id.')');
            $this->line('Unit:         '.$unit->name.' (#'.$unit->id.')');
            $this->line('Abilities:    '.implode(', ', $abilities));
            $this->newLine();
            $this->info('API token (shown once, store it now):');
            $this->line($token->plainTextToken);
            $this->newLine();
            $this->comment('The account still needs the tasks.create permission for role "pm" in the Authorization panel.');

            return self::SUCCESS;
        });
    }
}
