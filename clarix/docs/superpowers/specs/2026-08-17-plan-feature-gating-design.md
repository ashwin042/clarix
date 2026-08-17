# Plan-based feature gating

**Date:** 2026-08-17
**Status:** approved for planning

## Problem

Clarix sells three plans — Base, Standard and Pro — and enforces none of them.
An organization on Base reaches ERP, the AI chatbot and the MCP plugin system
exactly as a Pro organization does. The only thing a plan currently affects is
the storage cap, and even those figures are wrong: Base is set to 10 GB where
the confirmed price list says 5 GB.

This adds a second authorization layer that answers "does this organization's
plan include this feature?", alongside the existing layer that answers "does
this person's role permit this action?". Both must pass.

## Source of truth

Two columns can name a plan today, and they already disagree: in the
production copy, CRC carries `organizations.subscription_type = 'base'` while
its `organization_subscriptions.plan` says `'standard'`. They are edited from
two different superadmin screens, so this was going to keep happening.

`organization_subscriptions.plan` is **authoritative**. It is what the storage
caps already read, and it is the row that carries price, cycle and status, so
it is the record that actually describes what the agency bought.

`organizations.subscription_type` stays as a column but stops being an input:

- A one-time migration corrects every row to match its subscription. CRC
  becomes `standard`, which the product owner has confirmed is correct.
- `OrganizationDetail::saveSubscription()` mirrors the plan onto the column on
  every save, so a single writer keeps it true from now on.
- `ManageOrganizations` stops offering it as an editable field and displays the
  live plan instead, pointing at Organization Detail to change it.

Nothing reads `subscription_type` for gating or for storage.

A newly created organization therefore has no subscription row and resolves to
**base** until a superadmin sets one up in Organization Detail. That is the
correct default — an agency that has not been put on a plan has not bought one
— and it matches the column's existing database default, so creating an
organization behaves the same as before even though the field has left the
form.

### Resolving the current plan

Newest `started_at` wins, with `id` breaking a tie — two subscriptions starting
the same day is an ordinary upgrade, and without the second key the database
picks. This is the ordering `OrganizationStorage` already uses inline, in two
places; it moves into one method that both callers share.

An organization with no subscription row resolves to `base`. An unrecognised
plan name also resolves to `base`. The rule is **fail closed**: an agency whose
plan cannot be determined gets the least generous answer, never the most.

Subscription *status* is deliberately not consulted here. A suspended
organization is already stopped at `EnsureSubscriptionActive` before any of
this runs, and a cancelled subscription still describes what was bought. Mixing
status into plan resolution would put two unrelated decisions in one place.

## Feature matrix

`config/plans.php` declares an ordered list of plans and, for each feature, the
minimum plan that includes it:

```php
'order'   => ['base', 'standard', 'pro'],
'minimum' => [
    'tasks'      => 'base',
    'files'      => 'base',
    'erp'        => 'standard',
    'ai_chat'    => 'standard',
    'calendar'   => 'standard',
    'automation' => 'pro',
],
```

Expressed as minimums rather than as three lists of included features. "Standard
is everything in Base plus ERP" is then structurally true instead of true only
as long as somebody remembers to copy the Base entries into the Standard list.
It also yields the upgrade message directly: the minimum plan for the feature
the user was refused is the plan they need to buy.

A feature not named in the map is denied. Adding a gated feature means adding a
line here, and forgetting to means the feature is unreachable rather than
silently free.

`labels` gives each feature a human phrase for the refusal page — `erp` reads
"ERP tools (attendance, leave and payroll)".

## Components

### `App\Services\PlanFeatures`

- `planFor(?int $organizationId): string` — resolves as described above.
- `allows(string $feature, ?int $organizationId): bool` — rank comparison.
- `minimumPlanFor(string $feature): ?string` — drives the refusal copy.

Memoized **per request only**. Deliberately not cached across requests the way
`PermissionService` caches its map: requirement 5 is that a plan change takes
effect immediately with no second step, and a five-minute cache would mean a
superadmin upgrading an agency watched nothing happen. The cost is one indexed
lookup per request.

### `User::planAllows(string $feature): bool`

Mirrors the shape of `User::hasPermission()` so call sites read the same way.

A **superadmin returns true unconditionally**. They belong to no organization,
so there is no plan to consult, and the platform portal must not be gated by
the commercial state of agencies it exists to administer. This matches the
existing rule that a superadmin passes `EnsureSubscriptionActive` untouched.

### `App\Http\Middleware\EnsurePlanIncludes`, aliased `plan`

`plan:erp` on a route. On refusal it calls `abort(402, $message)`, where the
message is built from the feature's label and its minimum plan.

### The refusal itself

One mechanism for every refusal, whether it comes from the middleware or from a
component guard: `abort(402, …)`, rendered by `resources/views/errors/402.blade.php`.

402 Payment Required is the accurate status and is already the one
`EnsureSubscriptionActive` uses for the neighbouring case. Routing everything
through the framework's own error view means the middleware and the nine
component guards cannot drift into answering the same question two ways, and a
gated feature reached by a route nobody remembered to annotate still refuses
correctly.

The view reads `$exception->getMessage()` for the specific line and falls back
to generic copy if it is empty. It is a **standalone page**, like the suspended
page, rather than one rendered inside `layouts.app`: the sidebar calls
`auth()->user()->hasPermission()` and friends unguarded, and running that inside
the exception handler risks turning a clean 402 into a 500. Unlike the suspended
page it is not a dead end — it carries a link back to the dashboard, because
only this one feature is unavailable and the rest of the app still works.

## Enforcement points

Routes:

| Route | Gate |
|---|---|
| `attendance.index` | `plan:erp` |
| `leave.index`, `leave.types` | `plan:erp` |
| `payroll.index`, `payroll.manage` | `plan:erp` |
| `ai.chatbot` | `plan:ai_chat` |
| `ai.calendar` | `plan:calendar` |
| `ai.mcp`, `ai.scheduled-tasks` | `plan:automation` |
| `ai.overview` | none |

`ai.overview` stays open on every plan. It is an informational page, and
leaving it reachable gives Base and Standard organizations somewhere in the
product that describes what upgrading would get them.

Route middleware is not sufficient on its own. Livewire actions POST to
`/livewire/update`, which does not pass through the route's middleware stack,
so a crafted request could mount a gated component directly. Every gated
component therefore repeats the check in its own `mount()` or `render()`, using
the same `abort(402, …)` as the middleware — the same belt-and-braces
`MyPayroll` and `ManagePayroll` already apply with their policy checks, for the
same reason.

Components to guard: `AttendancePage`, `ClockWidget`, `LeavePage`,
`ManageLeaveTypes`, `MyPayroll`, `ManagePayroll` (all `erp`); `Chatbot`
(`ai_chat`); `Calendar` (`calendar`); `McpPlugins`, `ScheduledTasks`
(`automation`).

Views:

- **Sidebar** — the HR section, header included, is wrapped in
  `planAllows('erp')`. The existing comment there explains the section is
  deliberately unconditional because attendance and leave are open to everyone;
  that reasoning is about the *permission* layer and still holds within it, but
  a Base organization has no HR at all, so the header must go too rather than
  hang over nothing. AI entries are filtered per feature.
- **Dashboards** — `ClockWidget` is embedded on all three; each embed is
  wrapped in the same check.
- **Profile page** — its attendance, leave and payroll sections must respect
  the plan layer too, or ERP data appears on a Base organization's profile. The
  existing `x-profile-withheld` component gains an optional reason so it can say
  "not included in your plan" rather than "your role does not have this turned
  on"; the two refusals mean different things and must not read alike.

## Storage caps

`config/storage.php`:

- `plan_caps_gb` becomes `base => 5`, `standard => 50`, `pro => 100`.
- `default_cap_gb` drops from 10 to **5**. Its documented purpose is to be the
  least generous case for an organization whose plan cannot be determined;
  leaving it at 10 once Base is 5 would quietly make it the *most* generous.

New migration adds `organizations.storage_cap_override_gb`, nullable unsigned
integer. When set it wins over the plan default. This is how the Pro extra-storage
arrangement (Rs 1000 per additional 100 GB, agreed by conversation) is applied:
the superadmin edits the number. No automated billing, by explicit decision —
the money moves outside the system, as it already does for subscriptions.

`OrganizationStorage::capGbFor()` consults the override first, then the plan.
Its two duplicated inline plan lookups collapse into `PlanFeatures::planFor()`.

The override is editable from Organization Detail, beside the subscription.

## Layering with role permissions

The two layers are independent and both must pass. Plan is the coarser
commercial gate and runs first, at the route; permissions run after, inside the
components, exactly as they do today.

**No policy, no permission check and no part of `PermissionService` is
modified.** The plan layer is purely additive. The consequences are:

- A Pro-plan writer without `leave.view_own` still reaches the leave page and
  still sees no history — unchanged behaviour.
- A Base-plan admin holding every permission in the panel gets the upgrade page
  for ERP. Being the most senior person in an agency does not buy a plan.
- A superadmin is subject to neither layer here.

## Downgrading

Gating is on the read path only. Nothing cascades, nothing is deleted, and no
migration touches operational rows. An organization that drops from Pro to Base
keeps every payroll record, leave request and attendance row it had; the data
becomes unreachable, not absent, and upgrading again restores access to it
untouched. There is a test that asserts exactly this.

## Testing

New `tests/Feature/Plans/`:

- `PlanFeaturesTest` — rank comparison; unknown plan and unknown feature both
  fail closed; missing subscription resolves to base; newest subscription wins;
  superadmin allowed everything.
- `PlanGatingTest` — for each of the three plans, the full route table above,
  asserted with an org admin holding **every** permission so that a pass or
  refusal can only be the plan layer talking.
- `PlanDowngradeTest` — create payroll on Pro, downgrade to Base, assert 402
  and assert the row is still in the database.
- `PlanSidebarTest` — a Base org's sidebar offers no Attendance, Leave, Payroll,
  Chatbot, Calendar, MCP or Scheduled Tasks link, and does offer AI Overview.
- `PlanLivewireGuardTest` — mounting a gated component directly is refused for a
  Base org, proving the middleware is not the only lock.
- `PlanLayeringTest` — a Pro writer without `leave.view_own` sees the page but
  no history; a Base admin with all permissions is refused.
- Storage: caps are 5/50/100, an unrecognised plan gets 5, and the per-org
  override beats the plan default.
- Superadmin access is unaffected by any organization's plan.

Baseline before this work is **14 failed, 1 skipped, 556 passed**. The
comparison at the end is against that list, not against zero.

## Safety

The only schema change is one nullable additive column; it drops cleanly. No
migration writes to operational tables. The subscription_type backfill writes
only to that one legacy column.

The suite runs on in-memory SQLite and never reaches MySQL. Manual verification
runs against a clone of the production copy, never against `clarix` itself.
Code Next Door is `pro`/`pro` and must retain full access throughout; CRC moves
from a stale `base` label to its real `standard` plan.
