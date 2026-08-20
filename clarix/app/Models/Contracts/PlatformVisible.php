<?php

namespace App\Models\Contracts;

/**
 * Marks a tenant-scoped model that a platform superadmin is allowed to read
 * across every organization.
 *
 * The list is deliberately short, and the default is refusal: a model that
 * does not carry this interface is invisible to a superadmin entirely, not
 * merely unfiltered. Running Clarix does not come with a right to read what
 * the agencies using it are working on, so administering an organization is
 * separated here from reading its work.
 *
 * Implemented by exactly:
 *   Organization                    — the agency records themselves
 *   User                            — the member list of each organization
 *   OrganizationSubscription        — what an organization pays Clarix
 *   OrganizationSubscriptionPayment — and its record of having paid it
 *
 * Everything else — tasks, files, notes, assignments, issues, replies, the
 * agencies' own client payments, notifications, chat requests, units and
 * their storage rollups — stays closed.
 */
interface PlatformVisible
{
}
