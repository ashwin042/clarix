<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Support\Facades\DB;

/**
 * Answers one question: is this organization empty enough to remove?
 *
 * Every organization_id foreign key is ON DELETE RESTRICT, so the database
 * already refuses to drop an agency that still owns anything. That protection
 * is the one that matters, but on its own it surfaces as an integrity-
 * constraint error naming a constraint rather than the data. Counting first
 * turns the refusal into a sentence a person can act on.
 *
 * There is deliberately no cascade here. Removing an agency that holds real
 * work is not a button; it is a decision someone should make explicitly, with
 * the data exported or moved first.
 */
class OrganizationTeardown
{
    /**
     * Tables a superadmin is allowed to be told the size of, because they may
     * read them anyway. role_permissions is excluded: it is configuration the
     * platform seeds for every agency, not work anyone would lose.
     *
     * @var array<string, string>
     */
    protected const REPORTABLE_TABLES = [
        'users'                              => 'user',
        'organization_subscriptions'         => 'subscription',
        'organization_subscription_payments' => 'subscription payment',
    ];

    /**
     * Tables that equally block deletion but whose contents a superadmin has
     * no business reading. Their presence is reported; their size is not.
     *
     * Answering "is this organization empty" needs nothing more than a yes or
     * no, so that is all this half yields. Counting them out by name would
     * hand the platform a census of every agency's workload through a screen
     * that exists to prevent an accident.
     *
     * @var list<string>
     */
    protected const OPERATIONAL_TABLES = [
        'units',
        'tasks',
        'task_files',
        'task_notes',
        'task_assignments',
        'issues',
        'issue_replies',
        'payments',
        'notifications',
        'daily_chat_requests',
        'account_deletion_requests',
        'unit_storage_usage',
        'attendances',
        // Real requests are the agency's records. The leave *types* are not —
        // those are provisioned defaults and are cleared below instead.
        'leave_requests',
        // Payroll provisions nothing per organization — there are no default
        // records to seed — so it belongs here and not in the provisioned list
        // below. An agency holding salary history is emphatically not empty.
        'payroll_records',
    ];

    /**
     * Rows the platform provisions for an agency rather than the agency
     * creating them, cleared on the way out instead of blocking the door.
     *
     * role_permissions is configuration: every organization is given a map the
     * moment it is created, so an agency that has never been used still holds
     * rows here. Those rows are ON DELETE RESTRICT like everything else, which
     * would make a genuinely empty organization undeletable for the sake of
     * defaults nobody chose and nobody would miss.
     *
     * @var list<string>
     */
    protected const PROVISIONED_TABLES = [
        'role_permissions',
        // Every organization is given three leave categories on creation, so
        // one that has never been used still holds rows here. Ordered after
        // role_permissions but before the organization row itself; anything
        // actually booked against a type lives in leave_requests, which blocks
        // deletion above and so is never reached with rows present.
        'leave_types',
    ];

    /**
     * Everything this organization still owns, as label => count, empty when
     * it holds nothing and can safely be removed.
     *
     * Counted with the query builder rather than through the models: this runs
     * for a superadmin, whose queries are unscoped anyway, and the answer must
     * not depend on who happens to be asking.
     *
     * @return array<string, int>
     */
    public function blockers(Organization $organization): array
    {
        $blockers = [];

        foreach (self::REPORTABLE_TABLES as $table => $label) {
            $count = DB::table($table)->where('organization_id', $organization->id)->count();

            if ($count > 0) {
                $blockers[$label] = $count;
            }
        }

        if ($this->hasOperationalData($organization)) {
            // Deliberately not a count. Enough to explain the refusal, not
            // enough to describe the agency's work.
            $blockers['operational record'] = 0;
        }

        return $blockers;
    }

    /**
     * Whether the organization still holds any work content at all.
     *
     * Stops at the first table that has anything, since the answer is a
     * boolean and there is no reason to tally the rest.
     */
    public function hasOperationalData(Organization $organization): bool
    {
        foreach (self::OPERATIONAL_TABLES as $table) {
            if (DB::table($table)->where('organization_id', $organization->id)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Drop the configuration the platform provisioned for this organization.
     *
     * Called immediately before the delete, and only once blockers() has come
     * back empty — so this never removes anything from an organization that is
     * staying. It is deliberately not part of blockers(): a default permission
     * map is not a reason to refuse, it is something to tidy up.
     */
    public function purgeProvisioned(Organization $organization): void
    {
        foreach (self::PROVISIONED_TABLES as $table) {
            DB::table($table)->where('organization_id', $organization->id)->delete();
        }
    }

    /**
     * Render the blockers as a readable phrase: "3 units, 1 task and 2 files".
     *
     * @param  array<string, int>  $blockers
     */
    public function describe(array $blockers): string
    {
        $parts = [];

        foreach ($blockers as $label => $count) {
            // A zero count is the marker for "present but not enumerated".
            $parts[] = $count === 0
                ? 'operational data'
                : $count.' '.($count === 1 ? $label : \Illuminate\Support\Str::plural($label));
        }

        if (count($parts) === 1) {
            return $parts[0];
        }

        $last = array_pop($parts);

        return implode(', ', $parts).' and '.$last;
    }
}
