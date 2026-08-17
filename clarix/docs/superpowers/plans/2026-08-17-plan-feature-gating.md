# Plan-Based Feature Gating Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enforce Base/Standard/Pro plan restrictions on ERP, AI chatbot and MCP/automation features, and correct storage caps to 5/50/100 GB with a per-organization override.

**Architecture:** A second authorization layer, independent of and additive to the existing role-permission layer. `config/plans.php` declares each feature's minimum plan against an ordered plan list; `PlanFeatures` resolves an organization's current plan from `organization_subscriptions.plan`; `User::planAllows()` is the single call site used by route middleware, Livewire component guards and Blade. Every refusal is `abort(402)`, rendered by one error view.

**Tech Stack:** Laravel 11, Livewire 3, Blade, Tailwind, PHPUnit. Tests run on in-memory SQLite (`phpunit.xml`).

**Spec:** `docs/superpowers/specs/2026-08-17-plan-feature-gating-design.md`

## Global Constraints

- **Baseline test state is `14 failed, 1 skipped, 556 passed`.** Compare against this list at the end, never against zero. The 14 are pre-existing: 6 × `CreditListExportTest`, 1 × `CreateTaskTest > remove upload splices file from array`, and 7 page-render tests failing because `UserFactory` sets no `role`/`organization_id`.
- **Never write to the `clarix` MySQL database.** It is a production copy. The suite uses SQLite in memory and never reaches MySQL. Manual checks use a clone.
- **Do not use a bare `User::factory()->create()` in a test that renders a page** — it has no role and crashes the layout. Build users via `Tests\Feature\Tenancy\BuildsOrganizations::populate()`.
- **Fail closed.** An unknown feature name, an unrecognised plan, or a missing subscription all resolve to the least-permissive answer (`base` / denied).
- **Plan resolution is memoized per request only** — never cached across requests. A superadmin's plan change must take effect on the next page load with no flush step.
- **A superadmin is never plan-gated.** `User::planAllows()` returns `true` for them unconditionally.
- **Refusal copy** reads: `"{Feature label} {isn't/aren't} included in your {Plan} plan. Upgrade to {Required} to unlock {feature label}."` Built from `config('plans.labels')` and the feature's minimum plan.
- **No policy, no permission check and no part of `PermissionService` may be modified.** This layer is purely additive.
- **Nothing is deleted on downgrade.** Gating is read-path only.
- Plan names are always lowercase (`base`, `standard`, `pro`); display uses `ucfirst()`.

---

## File Structure

**Create:**
- `config/plans.php` — the feature matrix.
- `app/Services/PlanFeatures.php` — plan resolution and feature questions.
- `app/Http/Middleware/EnsurePlanIncludes.php` — route guard.
- `resources/views/errors/402.blade.php` — the single refusal page.
- `database/migrations/2026_08_20_000001_add_storage_cap_override_to_organizations.php`
- `database/migrations/2026_08_20_000002_sync_subscription_type_from_subscriptions.php`
- `tests/Feature/Plans/PlanFeaturesTest.php`
- `tests/Feature/Plans/PlanGatingTest.php`
- `tests/Feature/Plans/PlanLayeringTest.php`
- `tests/Feature/Plans/PlanDowngradeTest.php`
- `tests/Feature/Plans/PlanSidebarTest.php`
- `tests/Feature/Plans/PlanLivewireGuardTest.php`
- `tests/Feature/Plans/StorageCapTest.php`

**Modify:**
- `app/Models/User.php` — add `planAllows()`.
- `bootstrap/app.php` — register the `plan` alias.
- `routes/web.php` — attach `plan:` middleware.
- `config/storage.php` — caps 5/50/100, default 5.
- `app/Services/OrganizationStorage.php` — override, and fold two inline plan queries into `PlanFeatures`.
- Nine Livewire components — self-guards.
- `resources/views/layouts/app.blade.php` — sidebar HR + AI gating.
- `resources/views/dashboard/{admin,pm,writer}.blade.php` — ClockWidget gating.
- `app/Livewire/Profile/ProfileOverview.php` + its view + `resources/views/components/profile-withheld.blade.php` — plan-aware ERP sections.
- `app/Livewire/Superadmin/OrganizationDetail.php` + view — mirror on save, override field.
- `app/Livewire/Superadmin/ManageOrganizations.php` + view — subscription_type read-only.

---

## Task 1: The feature matrix and `PlanFeatures`

**Files:**
- Create: `config/plans.php`
- Create: `app/Services/PlanFeatures.php`
- Test: `tests/Feature/Plans/PlanFeaturesTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `PlanFeatures::planFor(?int $organizationId): string`
  - `PlanFeatures::allows(string $feature, ?int $organizationId): bool`
  - `PlanFeatures::minimumPlanFor(string $feature): ?string`
  - `PlanFeatures::labelFor(string $feature): string`
  - `PlanFeatures::flush(): void` — clears the per-request memo; tests need it after changing a plan mid-test.
  - Feature keys: `tasks`, `files`, `erp`, `ai_chat`, `calendar`, `automation`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Plans/PlanFeaturesTest.php`:

```php
<?php

namespace Tests\Feature\Plans;

use App\Models\OrganizationSubscription;
use App\Services\PlanFeatures;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

class PlanFeaturesTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    protected function planFeatures(): PlanFeatures
    {
        PlanFeatures::flush();

        return app(PlanFeatures::class);
    }

    protected function subscribe(int $organizationId, string $plan, string $startedAt = '2026-01-01'): void
    {
        TenantContext::actingAsOrganization($organizationId, function () use ($plan, $startedAt) {
            $subscription = new OrganizationSubscription([
                'plan'          => $plan,
                'price'         => 1000,
                'billing_cycle' => 'monthly',
                'started_at'    => $startedAt,
                'status'        => 'active',
            ]);
            $subscription->next_renewal_at = $subscription->renewalDateFrom($startedAt);
            $subscription->save();
        });

        PlanFeatures::flush();
    }

    public function test_an_organization_with_no_subscription_is_treated_as_base(): void
    {
        $org = $this->makeOrganization('pf-none', 'No Plan');

        $this->assertSame('base', $this->planFeatures()->planFor($org->id));
    }

    public function test_the_plan_comes_from_the_subscription(): void
    {
        $org = $this->makeOrganization('pf-std', 'Standard');
        $this->subscribe($org->id, 'standard');

        $this->assertSame('standard', $this->planFeatures()->planFor($org->id));
    }

    public function test_the_newest_subscription_wins(): void
    {
        $org = $this->makeOrganization('pf-up', 'Upgrader');
        $this->subscribe($org->id, 'base', '2026-01-01');
        $this->subscribe($org->id, 'pro', '2026-06-01');

        $this->assertSame('pro', $this->planFeatures()->planFor($org->id));
    }

    public function test_an_unrecognised_plan_name_falls_back_to_base(): void
    {
        $org = $this->makeOrganization('pf-junk', 'Junk');
        $this->subscribe($org->id, 'enterprise-deluxe');

        $this->assertSame('base', $this->planFeatures()->planFor($org->id));
    }

    public function test_each_plan_includes_the_plans_below_it(): void
    {
        $features = $this->planFeatures();

        $expected = [
            'base'     => ['tasks' => true,  'files' => true, 'erp' => false, 'ai_chat' => false, 'calendar' => false, 'automation' => false],
            'standard' => ['tasks' => true,  'files' => true, 'erp' => true,  'ai_chat' => true,  'calendar' => true,  'automation' => false],
            'pro'      => ['tasks' => true,  'files' => true, 'erp' => true,  'ai_chat' => true,  'calendar' => true,  'automation' => true],
        ];

        foreach ($expected as $plan => $matrix) {
            $org = $this->makeOrganization('pf-'.$plan.'-m', ucfirst($plan).' Matrix');
            $this->subscribe($org->id, $plan);

            foreach ($matrix as $feature => $allowed) {
                $this->assertSame(
                    $allowed,
                    $features->allows($feature, $org->id),
                    "{$plan} should ".($allowed ? 'include' : 'exclude')." {$feature}"
                );
            }
        }
    }

    public function test_an_unknown_feature_is_denied_even_on_pro(): void
    {
        $org = $this->makeOrganization('pf-unknown', 'Pro');
        $this->subscribe($org->id, 'pro');

        $this->assertFalse($this->planFeatures()->allows('teleportation', $org->id));
    }

    public function test_a_null_organization_is_treated_as_base(): void
    {
        $this->assertSame('base', $this->planFeatures()->planFor(null));
        $this->assertFalse($this->planFeatures()->allows('erp', null));
    }

    public function test_the_minimum_plan_drives_the_upgrade_message(): void
    {
        $features = $this->planFeatures();

        $this->assertSame('standard', $features->minimumPlanFor('erp'));
        $this->assertSame('pro', $features->minimumPlanFor('automation'));
        $this->assertNull($features->minimumPlanFor('teleportation'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Plans/PlanFeaturesTest.php`
Expected: FAIL — `Class "App\Services\PlanFeatures" does not exist`.

- [ ] **Step 3: Write `config/plans.php`**

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Plan Order
    |--------------------------------------------------------------------------
    |
    | Cheapest first. Everything else here is a comparison against this list,
    | so a plan's position is its meaning: anything at or above a feature's
    | minimum includes that feature.
    |
    */

    'order' => ['base', 'standard', 'pro'],

    /*
    |--------------------------------------------------------------------------
    | Fallback Plan
    |--------------------------------------------------------------------------
    |
    | Used for an organization with no subscription row, an unrecognised plan
    | name, and a null organization. Deliberately the cheapest tier: an agency
    | whose plan cannot be determined is given the least, never the most.
    |
    */

    'default' => 'base',

    /*
    |--------------------------------------------------------------------------
    | Minimum Plan Per Feature
    |--------------------------------------------------------------------------
    |
    | Expressed as minimums rather than as three lists of included features.
    | "Standard is everything in Base plus ERP" is then structurally true,
    | instead of true only while somebody remembers to copy the Base entries
    | into the Standard list.
    |
    | A feature that is not named here is denied on every plan. Adding a gated
    | feature means adding a line; forgetting leaves the feature unreachable
    | rather than silently free, which is the safer way to be wrong.
    |
    */

    'minimum' => [
        'tasks'      => 'base',
        'files'      => 'base',
        'erp'        => 'standard',
        'ai_chat'    => 'standard',
        'calendar'   => 'standard',
        'automation' => 'pro',
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Labels
    |--------------------------------------------------------------------------
    |
    | How each feature is named to somebody who has just been refused it.
    | Written as a plain noun phrase so it reads in the refusal sentence
    | without further shaping.
    |
    */

    'labels' => [
        'tasks'      => 'task boards',
        'files'      => 'file uploads',
        'erp'        => 'ERP tools (attendance, leave and payroll)',
        'ai_chat'    => 'the AI chatbot',
        'calendar'   => 'the calendar',
        'automation' => 'MCP, plugins and automation',
    ],

];
```

- [ ] **Step 4: Write `app/Services/PlanFeatures.php`**

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * What an organization's plan entitles it to.
 *
 * The second of Clarix's two authorization layers. This one answers "did this
 * agency buy this?"; the role-permission layer answers "may this person do
 * this?". They are deliberately separate and both must pass — a Pro plan does
 * not grant a writer payroll, and an admin holding every permission in the
 * panel cannot reach ERP on Base.
 *
 * The plan is read from organization_subscriptions, never from
 * organizations.subscription_type. The subscription row is what carries price,
 * cycle and status, so it is the record that describes what was actually
 * bought; the column beside it is a legacy label kept in step by a mirror on
 * save. They disagreed in production before this class existed, which is why
 * only one of them is allowed to be the answer.
 */
class PlanFeatures
{
    /**
     * Resolved plans for this request, keyed by organization id.
     *
     * A request-lifetime memo and deliberately nothing more. PermissionService
     * keeps a five-minute cache; doing the same here would mean a superadmin
     * upgrading an agency watched nothing happen for five minutes, and "the
     * change takes effect immediately" is the whole point of resolving the
     * plan live. The cost is one indexed lookup per request.
     *
     * @var array<int|string, string>
     */
    protected static array $memo = [];

    public static function flush(): void
    {
        self::$memo = [];
    }

    /**
     * The plan an organization is on right now.
     */
    public function planFor(?int $organizationId): string
    {
        $fallback = (string) config('plans.default');

        if ($organizationId === null) {
            return $fallback;
        }

        if (isset(self::$memo[$organizationId])) {
            return self::$memo[$organizationId];
        }

        $plan = DB::table('organization_subscriptions')
            ->where('organization_id', $organizationId)
            // Newest start wins, and the later-recorded row breaks a tie. Two
            // subscriptions starting the same day is ordinary — an upgrade
            // taking effect immediately — and without the second key which one
            // counted was left to the database to decide. This matches the
            // ordering OrganizationStorage used before it delegated here.
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->value('plan');

        // An unrecognised name is not trusted just because it is stored.
        if (! in_array($plan, (array) config('plans.order'), true)) {
            $plan = $fallback;
        }

        return self::$memo[$organizationId] = $plan;
    }

    /**
     * Whether an organization's plan reaches a feature.
     */
    public function allows(string $feature, ?int $organizationId): bool
    {
        $minimum = $this->minimumPlanFor($feature);

        // An unnamed feature is denied outright rather than waved through on
        // the top tier. A typo in a gate should close it, not open it.
        if ($minimum === null) {
            return false;
        }

        return $this->rank($this->planFor($organizationId)) >= $this->rank($minimum);
    }

    /**
     * The cheapest plan that includes a feature, or null if unknown.
     */
    public function minimumPlanFor(string $feature): ?string
    {
        $minimum = config('plans.minimum.'.$feature);

        return in_array($minimum, (array) config('plans.order'), true) ? $minimum : null;
    }

    /**
     * How a feature is named to somebody who has just been refused it.
     */
    public function labelFor(string $feature): string
    {
        return (string) (config('plans.labels.'.$feature) ?? 'this feature');
    }

    /**
     * A plan's position in the order. Unknown names sort below everything.
     */
    protected function rank(string $plan): int
    {
        $position = array_search($plan, (array) config('plans.order'), true);

        return $position === false ? -1 : $position;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/Plans/PlanFeaturesTest.php`
Expected: PASS, 8 tests.

- [ ] **Step 6: Commit**

```bash
git add config/plans.php app/Services/PlanFeatures.php tests/Feature/Plans/PlanFeaturesTest.php
git commit -m "feat: plan feature matrix and resolution service"
```

---

## Task 2: `User::planAllows()`

**Files:**
- Modify: `app/Models/User.php` (add beside `hasPermission()`, around line 165-180)
- Test: `tests/Feature/Plans/PlanFeaturesTest.php` (append)

**Interfaces:**
- Consumes: `PlanFeatures::allows()` from Task 1.
- Produces: `User::planAllows(string $feature): bool` — the call site used by middleware, component guards and Blade for the rest of this plan.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Plans/PlanFeaturesTest.php`:

```php
    public function test_a_member_of_a_base_organization_is_refused_erp(): void
    {
        $org = $this->populate($this->makeOrganization('pf-user-base', 'Base Co'), 'B');
        $this->subscribe($org['organization']->id, 'base');

        $this->assertTrue($org['admin']->planAllows('tasks'));
        $this->assertFalse($org['admin']->planAllows('erp'));
    }

    public function test_a_superadmin_is_not_plan_gated_at_all(): void
    {
        $superadmin = \App\Models\User::factory()->create([
            'name'            => 'Platform Super',
            'email'           => 'super@example.test',
            'role'            => 'superadmin',
            'organization_id' => null,
        ]);

        // No organization, therefore no plan to consult. Every feature.
        foreach (['tasks', 'files', 'erp', 'ai_chat', 'calendar', 'automation'] as $feature) {
            $this->assertTrue($superadmin->planAllows($feature), "superadmin should reach {$feature}");
        }
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Plans/PlanFeaturesTest.php`
Expected: FAIL — `Call to undefined method App\Models\User::planAllows()`.

- [ ] **Step 3: Add the method to `app/Models/User.php`**

Add immediately after `hasPermission()`:

```php
    /**
     * Whether this person's organization has bought a feature.
     *
     * The plan layer, deliberately shaped like hasPermission() so the two read
     * alike at call sites while staying entirely separate underneath. Both
     * must pass: this one asks what the agency purchased, the other asks what
     * the role may do.
     *
     * A superadmin is never plan-gated. They belong to no organization, so
     * there is no plan to consult, and the platform portal must not be
     * restricted by the commercial state of the agencies it administers — the
     * same exemption EnsureSubscriptionActive already grants them.
     */
    public function planAllows(string $feature): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        return app(PlanFeatures::class)->allows(
            $feature,
            $this->organization_id === null ? null : (int) $this->organization_id
        );
    }
```

Add `use App\Services\PlanFeatures;` to the imports at the top of the file.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Plans/PlanFeaturesTest.php`
Expected: PASS, 10 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Models/User.php tests/Feature/Plans/PlanFeaturesTest.php
git commit -m "feat: User::planAllows as the plan layer call site"
```

---

## Task 3: The refusal — middleware and the 402 page

**Files:**
- Create: `app/Http/Middleware/EnsurePlanIncludes.php`
- Create: `resources/views/errors/402.blade.php`
- Modify: `bootstrap/app.php:13-19`
- Test: `tests/Feature/Plans/PlanGatingTest.php`

**Interfaces:**
- Consumes: `User::planAllows()` from Task 2; `PlanFeatures::labelFor()`, `minimumPlanFor()`, `planFor()` from Task 1.
- Produces: middleware alias `plan`, used as `plan:erp` / `plan:ai_chat` / `plan:calendar` / `plan:automation`. Refusals are HTTP 402.

At this point no route carries the middleware yet, so this task tests it against a route registered inside the test.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Plans/PlanGatingTest.php`:

```php
<?php

namespace Tests\Feature\Plans;

use App\Models\OrganizationSubscription;
use App\Services\PlanFeatures;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

class PlanGatingTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    protected function subscribe(int $organizationId, string $plan): void
    {
        TenantContext::actingAsOrganization($organizationId, function () use ($plan) {
            $subscription = new OrganizationSubscription([
                'plan'          => $plan,
                'price'         => 1000,
                'billing_cycle' => 'monthly',
                'started_at'    => now()->subMonth()->toDateString(),
                'status'        => 'active',
            ]);
            $subscription->next_renewal_at = $subscription->renewalDateFrom(now()->subMonth());
            $subscription->save();
        });

        PlanFeatures::flush();
    }

    public function test_the_middleware_refuses_a_plan_that_lacks_the_feature(): void
    {
        Route::middleware(['web', 'auth', 'plan:erp'])->get('/_test/erp', fn () => 'reached');

        $org = $this->populate($this->makeOrganization('gate-base', 'Base Co'), 'B');
        $this->subscribe($org['organization']->id, 'base');

        $response = $this->actingAs($org['admin'])->get('/_test/erp');

        $response->assertStatus(402);
        $response->assertSee('Upgrade to Standard', escape: false);
    }

    public function test_the_middleware_lets_a_sufficient_plan_through(): void
    {
        Route::middleware(['web', 'auth', 'plan:erp'])->get('/_test/erp', fn () => 'reached');

        $org = $this->populate($this->makeOrganization('gate-std', 'Standard Co'), 'S');
        $this->subscribe($org['organization']->id, 'standard');

        $this->actingAs($org['admin'])->get('/_test/erp')->assertOk()->assertSee('reached');
    }

    public function test_the_refusal_names_the_feature_and_the_current_plan(): void
    {
        Route::middleware(['web', 'auth', 'plan:automation'])->get('/_test/auto', fn () => 'reached');

        $org = $this->populate($this->makeOrganization('gate-std2', 'Standard Co'), 'S');
        $this->subscribe($org['organization']->id, 'standard');

        $this->actingAs($org['admin'])->get('/_test/auto')
            ->assertStatus(402)
            ->assertSee('MCP, plugins and automation')
            ->assertSee('Standard plan')
            ->assertSee('Upgrade to Pro', escape: false);
    }

    public function test_the_refusal_page_offers_a_way_back(): void
    {
        Route::middleware(['web', 'auth', 'plan:erp'])->get('/_test/erp', fn () => 'reached');

        $org = $this->populate($this->makeOrganization('gate-back', 'Base Co'), 'B');
        $this->subscribe($org['organization']->id, 'base');

        // A dead end would be wrong here: only this feature is unavailable.
        $this->actingAs($org['admin'])->get('/_test/erp')
            ->assertStatus(402)
            ->assertSee(route('dashboard'), escape: false);
    }

    public function test_a_superadmin_passes_the_middleware_on_any_feature(): void
    {
        Route::middleware(['web', 'auth', 'plan:automation'])->get('/_test/auto', fn () => 'reached');

        $superadmin = \App\Models\User::factory()->create([
            'role'            => 'superadmin',
            'organization_id' => null,
        ]);

        $this->actingAs($superadmin)->get('/_test/auto')->assertOk();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Plans/PlanGatingTest.php`
Expected: FAIL — `Target class [plan] does not exist.`

- [ ] **Step 3: Write `app/Http/Middleware/EnsurePlanIncludes.php`**

```php
<?php

namespace App\Http\Middleware;

use App\Services\PlanFeatures;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route on the organization's plan rather than on the viewer's role.
 *
 * The commercial layer, and the coarser of the two: it asks what the agency
 * bought, before the permission layer inside the component asks what this
 * person may do. Both have to pass, and neither can stand in for the other —
 * an admin with every permission in the panel is still on whatever plan their
 * agency pays for.
 *
 * Refuses with 402 rather than 403 because the answer is "not purchased", not
 * "not permitted", and the two want different pages. abort() is used rather
 * than a bespoke response so that this middleware and the component-level
 * guards behind it cannot drift into answering the same question two ways.
 */
class EnsurePlanIncludes
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        if ($user->planAllows($feature)) {
            return $next($request);
        }

        abort(402, self::refusalFor($feature, $user->organization_id));
    }

    /**
     * The sentence shown to somebody who has just been refused.
     *
     * Built here rather than written out per feature so that adding a gated
     * area cannot introduce a fifth way of phrasing the same refusal.
     */
    public static function refusalFor(string $feature, ?int $organizationId): string
    {
        $plans   = app(PlanFeatures::class);
        $label   = $plans->labelFor($feature);
        $current = ucfirst($plans->planFor($organizationId === null ? null : (int) $organizationId));
        $needed  = $plans->minimumPlanFor($feature);

        if ($needed === null) {
            return "This feature isn't included in your {$current} plan.";
        }

        return "{$label} isn't included in your {$current} plan. "
            ."Upgrade to ".ucfirst($needed)." to unlock {$label}.";
    }
}
```

- [ ] **Step 4: Register the alias in `bootstrap/app.php`**

Replace the `$middleware->alias([...])` block with:

```php
        $middleware->alias([
            'role'         => \App\Http\Middleware\EnsureUserHasRole::class,
            'permission'   => \App\Http\Middleware\EnsureUserHasPermission::class,
            'subscription' => \App\Http\Middleware\EnsureSubscriptionActive::class,
            'plan'         => \App\Http\Middleware\EnsurePlanIncludes::class,
        ]);
```

- [ ] **Step 5: Write `resources/views/errors/402.blade.php`**

```blade
{{--
    Shown wherever a plan does not reach a feature.

    One page for every plan refusal, whether it came from the plan: middleware
    or from a component guarding itself against a direct Livewire call. Routing
    them all through the framework's own error view is what keeps a dozen call
    sites from inventing a dozen wordings.

    Standalone rather than rendered inside layouts.app: the sidebar calls
    auth()->user()->hasPermission() and friends unguarded, and running that
    inside the exception handler would risk turning a clean 402 into a 500.
    Unlike the suspended page this is not a dead end — only one feature is
    unavailable, so there is a way back to the rest of the app.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Not included in your plan - {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css'])
    <script>
        (function(){
            var t = localStorage.getItem('theme');
            if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
</head>
<body class="font-sans antialiased bg-gray-50 dark:bg-slate-950 text-gray-900 dark:text-slate-200">

<div class="min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-md">
        <div class="rounded-2xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-8 text-center">

            <div class="w-12 h-12 rounded-full bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center mx-auto mb-5">
                <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3l1.9 5.4 5.6.2-4.4 3.5 1.6 5.4L12 14.4l-4.7 3.1 1.6-5.4L4.5 8.6l5.6-.2L12 3z"/>
                </svg>
            </div>

            <h1 class="text-xl font-bold text-gray-900 dark:text-slate-100">Not included in your plan</h1>

            <p class="text-sm text-gray-600 dark:text-slate-400 mt-3 leading-relaxed">
                {{-- The specific sentence comes from the refusal; the fallback
                     covers a 402 raised from anywhere that did not set one. --}}
                {{ $exception?->getMessage() ?: "This feature isn't included in your current plan." }}
            </p>

            <p class="text-xs text-gray-500 dark:text-slate-500 mt-3">
                Contact your administrator to upgrade.
            </p>

            <div class="mt-6 pt-6 border-t border-gray-100 dark:border-slate-800">
                <a href="{{ route('dashboard') }}"
                    class="text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300">
                    Back to dashboard
                </a>
            </div>
        </div>
    </div>
</div>

</body>
</html>
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test tests/Feature/Plans/PlanGatingTest.php`
Expected: PASS, 5 tests.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Middleware/EnsurePlanIncludes.php resources/views/errors/402.blade.php bootstrap/app.php tests/Feature/Plans/PlanGatingTest.php
git commit -m "feat: plan middleware and the 402 upgrade page"
```

---

## Task 4: Gate the real routes

**Files:**
- Modify: `routes/web.php` — attendance, leave, payroll and AI route definitions
- Test: `tests/Feature/Plans/PlanGatingTest.php` (append)

**Interfaces:**
- Consumes: the `plan` alias from Task 3.
- Produces: the gated route table. Later tasks assume `/attendance`, `/leave`, `/leave/types`, `/payroll`, `/payroll/manage` are `plan:erp`; `/ai/chatbot` is `plan:ai_chat`; `/ai/calendar` is `plan:calendar`; `/ai/mcp` and `/ai/scheduled-tasks` are `plan:automation`; `/ai/overview` is ungated.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Plans/PlanGatingTest.php`:

```php
    /**
     * The full route table, asserted per plan.
     *
     * @return array<string, array{0: string, 1: array<string, int>}>
     */
    public static function planRouteMatrix(): array
    {
        $erp = ['/attendance', '/leave', '/leave/types', '/payroll', '/payroll/manage'];
        $ai  = ['/ai/chatbot', '/ai/calendar', '/ai/mcp', '/ai/scheduled-tasks'];

        return [
            'base' => ['base', array_merge(
                array_fill_keys($erp, 402),
                array_fill_keys($ai, 402),
                ['/ai/overview' => 200, '/tasks' => 200],
            )],
            'standard' => ['standard', array_merge(
                array_fill_keys($erp, 200),
                ['/ai/chatbot' => 200, '/ai/calendar' => 200],
                ['/ai/mcp' => 402, '/ai/scheduled-tasks' => 402],
                ['/ai/overview' => 200, '/tasks' => 200],
            )],
            'pro' => ['pro', array_merge(
                array_fill_keys($erp, 200),
                array_fill_keys($ai, 200),
                ['/ai/overview' => 200, '/tasks' => 200],
            )],
        ];
    }

    /**
     * @dataProvider planRouteMatrix
     *
     * @param  array<string, int>  $expected
     */
    public function test_a_plan_reaches_exactly_its_own_routes(string $plan, array $expected): void
    {
        $org = $this->populate($this->makeOrganization('matrix-'.$plan, ucfirst($plan)), 'M');
        $this->subscribe($org['organization']->id, $plan);

        // An admin holds every permission unconditionally, so anything refused
        // below can only be the plan layer talking — never the role layer.
        $admin = $org['admin'];

        foreach ($expected as $url => $status) {
            $this->actingAs($admin)->get($url)->assertStatus($status);
        }
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Plans/PlanGatingTest.php --filter=reaches_exactly`
Expected: FAIL — the base case returns 200 for `/attendance` where 402 was expected.

- [ ] **Step 3: Attach the middleware in `routes/web.php`**

Replace the attendance route:

```php
    /*
     * Attendance. No permission middleware: clocking yourself in and out is
     * structural, so the page has to open for everyone whose agency has ERP.
     * What it then shows — your own record, your unit's, or the whole agency —
     * is decided inside by AttendancePolicy.
     *
     * The plan gate is a separate question from all of that: 'structural'
     * means no role may be denied it, not that an agency which has not bought
     * ERP receives it anyway.
     */
    Route::middleware(['plan:erp'])->get('/attendance', AttendancePage::class)->name('attendance.index');
```

Replace the leave routes:

```php
    /*
     * Leave. Same reasoning as attendance: no permission middleware, because
     * asking for time off has to be open to everyone — within an agency that
     * has ERP. What the page then shows is decided inside by
     * LeaveRequestPolicy. The leave-type screen guards itself as admin-only in
     * its own mount().
     */
    Route::middleware(['plan:erp'])->group(function () {
        Route::get('/leave', LeavePage::class)->name('leave.index');
        Route::get('/leave/types', ManageLeaveTypes::class)->name('leave.types');
    });
```

Replace the payroll routes:

```php
    /*
     * Payroll. Unlike attendance and leave there is nothing structural here —
     * nobody has an unconditional right to enter payroll, and reading even
     * your own is a permission. Both components guard themselves, so the
     * routes carry no permission middleware and that refusal comes from the
     * policy; the plan gate above it is the separate question of whether the
     * agency bought ERP at all.
     */
    Route::middleware(['plan:erp'])->group(function () {
        Route::get('/payroll', MyPayroll::class)->name('payroll.index');
        Route::get('/payroll/manage', ManagePayroll::class)->name('payroll.manage');
    });
```

Replace the AI group body (keep the surrounding `role:admin,pm,writer` and `prefix('ai')`):

```php
    Route::middleware(['role:admin,pm,writer'])->prefix('ai')->name('ai.')->group(function () {
        // Ungated on purpose. An informational page is the one place in the
        // product where a Base or Standard agency can read what upgrading
        // would give them, so putting it behind the upgrade wall would hide
        // the pitch from exactly the people it is for.
        Route::get('/overview', Overview::class)->name('overview');

        Route::middleware(['plan:ai_chat'])->get('/chatbot', Chatbot::class)->name('chatbot');

        Route::middleware(['plan:calendar'])->get('/calendar', Calendar::class)->name('calendar');

        Route::middleware(['plan:automation'])->group(function () {
            Route::get('/mcp', McpPlugins::class)->name('mcp');
            Route::get('/scheduled-tasks', ScheduledTasks::class)->name('scheduled-tasks');
        });
    });
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Plans/PlanGatingTest.php`
Expected: PASS, 8 tests.

- [ ] **Step 5: Run the full suite to catch collateral damage**

Run: `php artisan test 2>&1 | grep -aE "FAILED|Tests:"`
Expected: existing Attendance/Leave/Payroll/AI tests may now fail, because organizations built by `BuildsOrganizations` have **no subscription row** and therefore resolve to `base`. Read the failures. If they are of that shape, fix them in Step 6; anything else is a real regression.

- [ ] **Step 6: Give the test fixture a plan**

The cleanest fix is at the fixture, not in each test. In `tests/Feature/Tenancy/BuildsOrganizations.php`, add a helper and call it from `makeOrganization()`:

```php
    /**
     * Put an organization on a plan.
     *
     * Fixtures default to 'pro' so that a test which is not about plans is not
     * silently gated by one. Tests that *are* about plans call this explicitly
     * with the tier they mean.
     */
    protected function subscribeOrganization(Organization $organization, string $plan = 'pro'): void
    {
        TenantContext::actingAsOrganization($organization->id, function () use ($plan) {
            $subscription = new OrganizationSubscription([
                'plan'          => $plan,
                'price'         => 1000,
                'billing_cycle' => 'monthly',
                'started_at'    => now()->subMonth()->toDateString(),
                'status'        => 'active',
            ]);
            $subscription->next_renewal_at = $subscription->renewalDateFrom(now()->subMonth());
            $subscription->save();
        });

        PlanFeatures::flush();
    }
```

and at the end of `makeOrganization()`, before the return, subscribe it to `pro`. Add the imports `App\Models\OrganizationSubscription` and `App\Services\PlanFeatures`.

Then in `PlanGatingTest::subscribe()` and `PlanFeaturesTest::subscribe()`, the newly created subscription must win over the fixture's `pro` one — give it a later `started_at` (`now()` rather than `now()->subMonth()`), so the "newest wins" rule selects it.

- [ ] **Step 7: Run the full suite again**

Run: `php artisan test 2>&1 | grep -aE "FAILED|Tests:"`
Expected: the 14 baseline failures and no others.

- [ ] **Step 8: Commit**

```bash
git add routes/web.php tests/Feature/Plans/PlanGatingTest.php tests/Feature/Tenancy/BuildsOrganizations.php tests/Feature/Plans/PlanFeaturesTest.php
git commit -m "feat: gate ERP and AI routes on the organization plan"
```

---

## Task 5: Component-level guards

**Files:**
- Modify: `app/Livewire/Attendance/AttendancePage.php` (`render()`), `app/Livewire/Attendance/ClockWidget.php` (`mount()`), `app/Livewire/Leave/LeavePage.php` (`render()`), `app/Livewire/Leave/ManageLeaveTypes.php` (`mount()`), `app/Livewire/Payroll/MyPayroll.php` (`render()`), `app/Livewire/Payroll/ManagePayroll.php` (`mount()`), `app/Livewire/AI/Chatbot.php` (`render()`), `app/Livewire/AI/Calendar.php` (`render()`), `app/Livewire/AI/McpPlugins.php` (`render()`), `app/Livewire/AI/ScheduledTasks.php` (`render()`)
- Test: `tests/Feature/Plans/PlanLivewireGuardTest.php`

**Interfaces:**
- Consumes: `User::planAllows()` (Task 2), `EnsurePlanIncludes::refusalFor()` (Task 3).
- Produces: nothing new. Closes the hole where Livewire actions POST to `/livewire/update` and skip route middleware.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Plans/PlanLivewireGuardTest.php`:

```php
<?php

namespace Tests\Feature\Plans;

use App\Livewire\AI\Chatbot;
use App\Livewire\AI\McpPlugins;
use App\Livewire\Attendance\AttendancePage;
use App\Livewire\Attendance\ClockWidget;
use App\Livewire\Leave\LeavePage;
use App\Livewire\Payroll\MyPayroll;
use App\Models\OrganizationSubscription;
use App\Services\PermissionService;
use App\Services\PlanFeatures;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * Route middleware is not the only lock.
 *
 * Livewire actions POST to /livewire/update, which never passes through the
 * route's middleware stack — so a crafted request could mount a gated
 * component directly and skip plan: entirely. These tests mount the components
 * the way such a request would.
 */
class PlanLivewireGuardTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        PermissionService::flushAll();

        $this->org = $this->populate($this->makeOrganization('lw-base', 'Base Co'), 'B');
        $this->downgradeToBase();
    }

    protected function downgradeToBase(): void
    {
        TenantContext::actingAsOrganization($this->org['organization']->id, function () {
            $subscription = new OrganizationSubscription([
                'plan'          => 'base',
                'price'         => 1000,
                'billing_cycle' => 'monthly',
                'started_at'    => now()->toDateString(),
                'status'        => 'active',
            ]);
            $subscription->next_renewal_at = $subscription->renewalDateFrom(now());
            $subscription->save();
        });

        PlanFeatures::flush();
    }

    /**
     * @return list<array{0: class-string}>
     */
    public static function gatedComponents(): array
    {
        return [
            [AttendancePage::class],
            [ClockWidget::class],
            [LeavePage::class],
            [MyPayroll::class],
            [Chatbot::class],
            [McpPlugins::class],
        ];
    }

    /**
     * @dataProvider gatedComponents
     *
     * @param  class-string  $component
     */
    public function test_a_base_org_cannot_mount_a_gated_component(string $component): void
    {
        $this->expectException(HttpException::class);

        try {
            Livewire::actingAs($this->org['admin'])->test($component);
        } catch (HttpException $e) {
            $this->assertSame(402, $e->getStatusCode());
            throw $e;
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Plans/PlanLivewireGuardTest.php`
Expected: FAIL — components mount successfully, no exception thrown.

- [ ] **Step 3: Add the guard to each component**

Add this private helper to each of the ten components (adjusting the feature name), or place the check inline. Use the exact feature per component: `erp` for `AttendancePage`, `ClockWidget`, `LeavePage`, `ManageLeaveTypes`, `MyPayroll`, `ManagePayroll`; `ai_chat` for `Chatbot`; `calendar` for `Calendar`; `automation` for `McpPlugins` and `ScheduledTasks`.

For components with a `render()` guard already (`AttendancePage`, `LeavePage`, `MyPayroll`, `Chatbot`, `Calendar`, `McpPlugins`, `ScheduledTasks`), add as the **first** statement of `render()`:

```php
        // The plan layer, ahead of the permission layer below. Repeated here
        // as well as on the route because a Livewire action POSTs to
        // /livewire/update and never passes through route middleware.
        $this->assertPlanIncludes('erp');
```

For components with a `mount()` (`ClockWidget`, `ManageLeaveTypes`, `ManagePayroll`), add as the first statement of `mount()` instead.

Add the helper to each modified class:

```php
    /**
     * Refuse if the organization's plan does not reach this feature.
     *
     * Uses the same abort(402) and the same sentence as the plan: middleware,
     * so a refusal reads identically whether it came from the route or from a
     * direct Livewire call.
     */
    protected function assertPlanIncludes(string $feature): void
    {
        $user = auth()->user();

        abort_unless(
            $user?->planAllows($feature),
            402,
            \App\Http\Middleware\EnsurePlanIncludes::refusalFor($feature, $user?->organization_id)
        );
    }
```

Note for `ClockWidget`: its existing `mount()` calls `refreshToday()`. The guard must come **before** that call, so a Base org never runs an attendance query.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Plans/PlanLivewireGuardTest.php`
Expected: PASS, 6 tests.

- [ ] **Step 5: Run the full suite**

Run: `php artisan test 2>&1 | grep -aE "FAILED|Tests:"`
Expected: the 14 baseline failures and no others.

- [ ] **Step 6: Commit**

```bash
git add app/Livewire tests/Feature/Plans/PlanLivewireGuardTest.php
git commit -m "feat: plan guards inside gated Livewire components"
```

---

## Task 6: Sidebar, dashboards and the profile page

**Files:**
- Modify: `resources/views/layouts/app.blade.php` — HR section (~lines 181-227) and AI section (~lines 283-312)
- Modify: `resources/views/dashboard/admin.blade.php:16`, `resources/views/dashboard/pm.blade.php:36`, `resources/views/dashboard/writer.blade.php:14`
- Modify: `app/Livewire/Profile/ProfileOverview.php`, `resources/views/livewire/profile/profile-overview.blade.php`, `resources/views/components/profile-withheld.blade.php`
- Test: `tests/Feature/Plans/PlanSidebarTest.php`

**Interfaces:**
- Consumes: `User::planAllows()` (Task 2).
- Produces: `x-profile-withheld` gains an optional `reason` attribute — `'permission'` (default) or `'plan'`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Plans/PlanSidebarTest.php`:

```php
<?php

namespace Tests\Feature\Plans;

use App\Models\OrganizationSubscription;
use App\Services\PermissionService;
use App\Services\PlanFeatures;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

class PlanSidebarTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        PermissionService::flushAll();
    }

    /**
     * @return array<string, mixed>
     */
    protected function orgOnPlan(string $slug, string $plan): array
    {
        $org = $this->populate($this->makeOrganization($slug, ucfirst($plan)), 'X');

        TenantContext::actingAsOrganization($org['organization']->id, function () use ($plan) {
            $subscription = new OrganizationSubscription([
                'plan'          => $plan,
                'price'         => 1000,
                'billing_cycle' => 'monthly',
                'started_at'    => now()->toDateString(),
                'status'        => 'active',
            ]);
            $subscription->next_renewal_at = $subscription->renewalDateFrom(now());
            $subscription->save();
        });

        PlanFeatures::flush();

        return $org;
    }

    public function test_a_base_org_is_offered_no_erp_or_paid_ai_links(): void
    {
        $org = $this->orgOnPlan('side-base', 'base');

        $response = $this->actingAs($org['admin'])->get('/dashboard')->assertOk();

        foreach ([route('attendance.index'), route('leave.index'), route('payroll.index'),
                  route('ai.chatbot'), route('ai.calendar'), route('ai.mcp'),
                  route('ai.scheduled-tasks')] as $url) {
            $response->assertDontSee($url, escape: false);
        }

        // The section header must go with its entries rather than hang over
        // nothing, and the ungated overview must remain.
        $response->assertDontSee('>HR<', escape: false);
        $response->assertSee(route('ai.overview'), escape: false);
    }

    public function test_a_standard_org_is_offered_erp_and_chat_but_not_automation(): void
    {
        $org = $this->orgOnPlan('side-std', 'standard');

        $response = $this->actingAs($org['admin'])->get('/dashboard')->assertOk();

        $response->assertSee(route('attendance.index'), escape: false);
        $response->assertSee(route('leave.index'), escape: false);
        $response->assertSee(route('ai.chatbot'), escape: false);
        $response->assertSee(route('ai.calendar'), escape: false);
        $response->assertDontSee(route('ai.mcp'), escape: false);
        $response->assertDontSee(route('ai.scheduled-tasks'), escape: false);
    }

    public function test_a_pro_org_is_offered_everything(): void
    {
        $org = $this->orgOnPlan('side-pro', 'pro');

        $response = $this->actingAs($org['admin'])->get('/dashboard')->assertOk();

        foreach ([route('attendance.index'), route('leave.index'), route('payroll.index'),
                  route('ai.chatbot'), route('ai.calendar'), route('ai.mcp'),
                  route('ai.scheduled-tasks'), route('ai.overview')] as $url) {
            $response->assertSee($url, escape: false);
        }
    }

    public function test_a_base_orgs_dashboard_does_not_render_the_clock_widget(): void
    {
        $org = $this->orgOnPlan('side-clock', 'base');

        // The widget guards itself with a 402; if the dashboard still embedded
        // it, the whole page would fail rather than quietly omit it.
        $this->actingAs($org['admin'])->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Clock in');
    }

    public function test_a_base_orgs_profile_withholds_erp_for_the_plan_not_the_role(): void
    {
        $org = $this->orgOnPlan('side-profile', 'base');

        $this->actingAs($org['admin'])->get('/profile')
            ->assertOk()
            ->assertSee('not included in your plan')
            ->assertDontSee('your role does not have this turned on');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Plans/PlanSidebarTest.php`
Expected: FAIL — the base org's sidebar still shows every link.

- [ ] **Step 3: Gate the sidebar HR section**

In `resources/views/layouts/app.blade.php`, wrap the whole HR block — the header `div`s, Attendance, Leave and the Payroll `@if` — in:

```blade
            {{-- Section: HR.
                 Gated on the plan, header included. Within an agency that has
                 ERP the entries stay unconditional, because clocking in and
                 requesting leave are structural and no role may be denied
                 them — that reasoning is about the permission layer and still
                 holds. It says nothing about an agency that has not bought ERP
                 at all, which is a separate question and the one asked here.
                 The header is inside the condition so it cannot be left
                 hanging over an empty section. --}}
            @if(auth()->user()->planAllows('erp'))
            ... existing HR block unchanged ...
            @endif
```

- [ ] **Step 4: Gate the AI section entries**

Replace the AI `@foreach` array so each row carries its feature, and skip rows the plan does not reach:

```blade
            @foreach ([
                ['ai.overview', 'Clarix AI Overview', null, 'M12 3l1.7 4.8L18.5 9.5 13.7 11.2 12 16l-1.7-4.8L5.5 9.5l4.8-1.7L12 3zM18 15l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8L18 15z'],
                ['ai.chatbot', 'Chatbot', 'ai_chat', 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.9 9.9 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z'],
                ['ai.mcp', 'MCP & Plugins', 'automation', 'M9 3v4M15 3v4M6 7h12v4a6 6 0 01-12 0V7zM12 17v4'],
                ['ai.scheduled-tasks', 'Scheduled Tasks', 'automation', 'M12 8v4l3 1.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['ai.calendar', 'Calendar', 'calendar', 'M8 7V3m8 4V3M4 11h16M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
            ] as [$aiRoute, $aiLabel, $aiFeature, $aiIcon])
                {{-- A null feature is ungated: the overview stays visible on
                     every plan so there is somewhere to read what upgrading
                     buys. --}}
                @continue($aiFeature !== null && ! auth()->user()->planAllows($aiFeature))
                ... existing <a> unchanged ...
            @endforeach
```

The AI section header stays outside the loop and unconditional, because the overview row always survives.

- [ ] **Step 5: Gate the ClockWidget embeds**

In each of `resources/views/dashboard/admin.blade.php`, `pm.blade.php` and `writer.blade.php`, wrap the `@livewire('attendance.clock-widget', ['compact' => true])` line:

```blade
            @if(auth()->user()->planAllows('erp'))
                @livewire('attendance.clock-widget', ['compact' => true])
            @endif
```

- [ ] **Step 6: Make the profile page plan-aware**

In `app/Livewire/Profile/ProfileOverview.php`, the three ERP flags must require the plan as well as the permission, and the view needs to know which layer refused. Replace the three assignments in `render()`:

```php
        // Two independent layers, both required. Which one refused decides
        // what the section says: "not in your plan" and "your role cannot see
        // this" are different facts and must not read alike.
        $hasErp = $user->planAllows('erp');

        $canSeeAttendance = $hasErp && Gate::allows('viewOwn', Attendance::class);
        $canSeeLeave      = $hasErp && Gate::allows('viewOwn', LeaveRequest::class);
        $canSeePayroll    = $hasErp && Gate::allows('viewOwn', PayrollRecord::class);
```

and add to the view data array:

```php
            'erpReason' => $hasErp ? 'permission' : 'plan',
```

In `resources/views/livewire/profile/profile-overview.blade.php`, change the three ERP `@else` branches from `<x-profile-withheld />` to `<x-profile-withheld :reason="$erpReason" />`. The Tasks section keeps the bare `<x-profile-withheld />`.

Rewrite `resources/views/components/profile-withheld.blade.php`:

```blade
@props(['reason' => 'permission'])

{{--
    What a profile section shows in place of data the viewer may not read.

    Two reasons, deliberately worded apart. "Your role does not have this
    turned on" is something an administrator can fix in the Authorization
    panel; "not included in your plan" is a commercial fact they cannot. A
    single shared sentence would send people to the wrong person.
--}}
<div class="px-6 py-8 text-center">
    @if($reason === 'plan')
        <p class="text-sm text-gray-500 dark:text-slate-400">
            Not available — this isn't included in your plan.
        </p>
        <p class="mt-1 text-xs text-gray-400 dark:text-slate-500">
            Ask an administrator about upgrading.
        </p>
    @else
        <p class="text-sm text-gray-500 dark:text-slate-400">
            Not available — your role does not have this turned on.
        </p>
        <p class="mt-1 text-xs text-gray-400 dark:text-slate-500">
            Ask an administrator if you think you should see it.
        </p>
    @endif
</div>
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test tests/Feature/Plans/PlanSidebarTest.php`
Expected: PASS, 5 tests.

- [ ] **Step 8: Run the profile tests, which assert the old copy**

Run: `php artisan test tests/Feature/Profile`
Expected: PASS, 28 tests — `BuildsOrganizations` now subscribes fixtures to `pro`, so those tests exercise the permission branch and its wording is unchanged.

- [ ] **Step 9: Commit**

```bash
git add resources/views app/Livewire/Profile/ProfileOverview.php tests/Feature/Plans/PlanSidebarTest.php
git commit -m "feat: hide plan-excluded features in sidebar, dashboards and profile"
```

---

## Task 7: Layering and downgrade

**Files:**
- Test: `tests/Feature/Plans/PlanLayeringTest.php`
- Test: `tests/Feature/Plans/PlanDowngradeTest.php`

**Interfaces:**
- Consumes: everything from Tasks 1-6. No production code should need changing; if a test here fails, the layering is wrong and the fix belongs in the layer at fault.

- [ ] **Step 1: Write `tests/Feature/Plans/PlanLayeringTest.php`**

```php
<?php

namespace Tests\Feature\Plans;

use App\Models\LeaveType;
use App\Models\OrganizationSubscription;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Services\PermissionService;
use App\Services\PlanFeatures;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * The two layers are independent and both must pass.
 *
 * These are the cases that would catch one layer having quietly absorbed the
 * other: a plan that grants a permission, or a permission that buys a plan.
 */
class PlanLayeringTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        PermissionService::flushAll();

        $this->org = $this->populate($this->makeOrganization('layer-a', 'Agency A'), 'A');
    }

    protected function setPlan(string $plan): void
    {
        TenantContext::actingAsOrganization($this->org['organization']->id, function () use ($plan) {
            $subscription = new OrganizationSubscription([
                'plan'          => $plan,
                'price'         => 1000,
                'billing_cycle' => 'monthly',
                'started_at'    => now()->toDateString(),
                'status'        => 'active',
            ]);
            $subscription->next_renewal_at = $subscription->renewalDateFrom(now());
            $subscription->save();
        });

        PlanFeatures::flush();
    }

    protected function setPermission(string $role, string $name, bool $allowed): void
    {
        TenantContext::actingAsOrganization($this->org['organization']->id, function () use ($role, $name, $allowed) {
            RolePermission::updateOrCreate(
                ['role' => $role, 'permission_id' => Permission::where('name', $name)->firstOrFail()->id],
                ['allowed' => $allowed]
            );
        });

        PermissionService::flushAll();
    }

    public function test_a_pro_plan_does_not_grant_a_writer_a_permission_they_lack(): void
    {
        $this->setPlan('pro');
        $this->setPermission('writer', 'leave.view_own', false);

        TenantContext::actingAsOrganization(
            $this->org['organization']->id,
            fn () => LeaveType::create(['name' => 'Sabbatical', 'default_annual_allowance' => 12])
        );

        // The plan opens the page; the permission still withholds the history.
        $this->actingAs($this->org['writer'])->get('/leave')
            ->assertOk()
            ->assertDontSee('Sabbatical');
    }

    public function test_every_permission_in_the_panel_does_not_buy_a_base_org_erp(): void
    {
        $this->setPlan('base');

        // An admin already holds every permission unconditionally.
        $this->actingAs($this->org['admin'])->get('/payroll')->assertStatus(402);
        $this->actingAs($this->org['admin'])->get('/attendance')->assertStatus(402);
    }

    public function test_a_pro_writer_with_the_permission_sees_their_leave(): void
    {
        $this->setPlan('pro');
        $this->setPermission('writer', 'leave.view_own', true);

        TenantContext::actingAsOrganization(
            $this->org['organization']->id,
            fn () => LeaveType::create(['name' => 'Sabbatical', 'default_annual_allowance' => 12])
        );

        $this->actingAs($this->org['writer'])->get('/leave')
            ->assertOk()
            ->assertSee('Sabbatical');
    }
}
```

- [ ] **Step 2: Run it**

Run: `php artisan test tests/Feature/Plans/PlanLayeringTest.php`
Expected: PASS, 3 tests. A failure here means a layer is leaking; fix the layer, not the test.

- [ ] **Step 3: Write `tests/Feature/Plans/PlanDowngradeTest.php`**

```php
<?php

namespace Tests\Feature\Plans;

use App\Models\Attendance;
use App\Models\OrganizationSubscription;
use App\Models\PayrollRecord;
use App\Services\PermissionService;
use App\Services\PlanFeatures;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

/**
 * Downgrading hides, it does not delete.
 *
 * The commercial consequence of the opposite would be severe and silent: an
 * agency that dropped a tier for a month would come back to find its payroll
 * history gone. Gating lives entirely on the read path so that cannot happen.
 */
class PlanDowngradeTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    /** @var array<string, mixed> */
    protected array $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        PermissionService::flushAll();

        $this->org = $this->populate($this->makeOrganization('down-a', 'Agency A'), 'A');
    }

    protected function setPlan(string $plan, string $startedAt): void
    {
        TenantContext::actingAsOrganization($this->org['organization']->id, function () use ($plan, $startedAt) {
            $subscription = new OrganizationSubscription([
                'plan'          => $plan,
                'price'         => 1000,
                'billing_cycle' => 'monthly',
                'started_at'    => $startedAt,
                'status'        => 'active',
            ]);
            $subscription->next_renewal_at = $subscription->renewalDateFrom($startedAt);
            $subscription->save();
        });

        PlanFeatures::flush();
    }

    public function test_a_downgrade_hides_erp_without_destroying_it(): void
    {
        $this->setPlan('pro', now()->subYear()->toDateString());

        $record = TenantContext::actingAsOrganization($this->org['organization']->id, function () {
            $payroll = new PayrollRecord([
                'month'       => now()->startOfMonth()->toDateString(),
                'base_amount' => 5000,
                'deductions'  => 0,
            ]);
            $payroll->user_id    = $this->org['pm']->id;
            $payroll->created_by = $this->org['admin']->id;
            $payroll->save();

            $attendance = new Attendance(['date' => now()->toDateString(), 'status' => 'present']);
            $attendance->user_id = $this->org['pm']->id;
            $attendance->save();

            return $payroll;
        });

        $this->actingAs($this->org['pm'])->get('/payroll')->assertOk();

        $this->setPlan('base', now()->toDateString());

        // Access is gone...
        $this->actingAs($this->org['pm'])->get('/payroll')->assertStatus(402);
        $this->actingAs($this->org['pm'])->get('/attendance')->assertStatus(402);

        // ...the records are not.
        $this->assertDatabaseHas('payroll_records', ['id' => $record->id, 'base_amount' => 5000]);
        $this->assertSame(1, DB::table('attendances')->where('user_id', $this->org['pm']->id)->count());
    }

    public function test_upgrading_again_restores_access_to_the_same_records(): void
    {
        $this->setPlan('base', now()->subYear()->toDateString());

        TenantContext::actingAsOrganization($this->org['organization']->id, function () {
            $payroll = new PayrollRecord([
                'month'       => now()->startOfMonth()->toDateString(),
                'base_amount' => 7777,
                'deductions'  => 0,
            ]);
            $payroll->user_id    = $this->org['pm']->id;
            $payroll->created_by = $this->org['admin']->id;
            $payroll->save();
        });

        $this->actingAs($this->org['pm'])->get('/payroll')->assertStatus(402);

        $this->setPlan('standard', now()->toDateString());

        $this->actingAs($this->org['pm'])->get('/payroll')
            ->assertOk()
            ->assertSee('7,777.00');
    }

    public function test_a_plan_change_takes_effect_on_the_next_request(): void
    {
        $this->setPlan('base', now()->subYear()->toDateString());
        $this->actingAs($this->org['admin'])->get('/attendance')->assertStatus(402);

        // No flush step, no cache to wait out: the very next request sees it.
        $this->setPlan('standard', now()->toDateString());
        $this->actingAs($this->org['admin'])->get('/attendance')->assertOk();
    }
}
```

Note: `PlanFeatures::flush()` inside `setPlan()` mirrors what a real HTTP request does — each request starts with an empty memo. The test process is one long-lived PHP process, so the memo must be cleared explicitly to simulate a fresh request.

- [ ] **Step 4: Run it**

Run: `php artisan test tests/Feature/Plans/PlanDowngradeTest.php`
Expected: PASS, 3 tests.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/Plans/PlanLayeringTest.php tests/Feature/Plans/PlanDowngradeTest.php
git commit -m "test: plan and permission layering, and downgrade safety"
```

---

## Task 8: Storage caps and the per-org override

**Files:**
- Modify: `config/storage.php:21-25` and `:40`
- Create: `database/migrations/2026_08_20_000001_add_storage_cap_override_to_organizations.php`
- Modify: `app/Models/Organization.php` — add `storage_cap_override_gb` to `$fillable` and cast to integer
- Modify: `app/Services/OrganizationStorage.php` — `capGbFor()`, `summaryFor()`, and the all-organizations listing
- Test: `tests/Feature/Plans/StorageCapTest.php`

**Interfaces:**
- Consumes: `PlanFeatures::planFor()` (Task 1).
- Produces: `OrganizationStorage::capGbFor(int $organizationId): int` — unchanged signature, new override behaviour.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Plans/StorageCapTest.php`:

```php
<?php

namespace Tests\Feature\Plans;

use App\Models\OrganizationSubscription;
use App\Services\OrganizationStorage;
use App\Services\PlanFeatures;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

class StorageCapTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    protected function storage(): OrganizationStorage
    {
        PlanFeatures::flush();

        return app(OrganizationStorage::class);
    }

    protected function setPlan(int $organizationId, string $plan): void
    {
        TenantContext::actingAsOrganization($organizationId, function () use ($plan) {
            $subscription = new OrganizationSubscription([
                'plan'          => $plan,
                'price'         => 1000,
                'billing_cycle' => 'monthly',
                'started_at'    => now()->toDateString(),
                'status'        => 'active',
            ]);
            $subscription->next_renewal_at = $subscription->renewalDateFrom(now());
            $subscription->save();
        });

        PlanFeatures::flush();
    }

    public function test_the_caps_are_five_fifty_and_one_hundred(): void
    {
        foreach (['base' => 5, 'standard' => 50, 'pro' => 100] as $plan => $expected) {
            $org = $this->makeOrganization('cap-'.$plan, ucfirst($plan));
            $this->setPlan($org->id, $plan);

            $this->assertSame($expected, $this->storage()->capGbFor($org->id));
        }
    }

    public function test_an_organization_with_no_subscription_gets_the_smallest_cap(): void
    {
        $org = $this->makeOrganization('cap-none', 'No Plan');

        // makeOrganization subscribes to pro; remove it to model an agency the
        // platform has not set up billing for yet.
        OrganizationSubscription::withoutGlobalScopes()->where('organization_id', $org->id)->delete();
        PlanFeatures::flush();

        $this->assertSame(5, $this->storage()->capGbFor($org->id));
    }

    public function test_a_per_org_override_beats_the_plan_default(): void
    {
        $org = $this->makeOrganization('cap-override', 'Extra Storage');
        $this->setPlan($org->id, 'pro');

        $this->assertSame(100, $this->storage()->capGbFor($org->id));

        // The Pro extra-storage arrangement, applied by hand: +100 GB.
        $org->forceFill(['storage_cap_override_gb' => 200])->save();

        $this->assertSame(200, $this->storage()->capGbFor($org->id));
    }

    public function test_an_override_below_the_plan_default_still_wins(): void
    {
        $org = $this->makeOrganization('cap-low', 'Reduced');
        $this->setPlan($org->id, 'pro');

        // The override is an instruction, not a maximum — a superadmin setting
        // it low means it.
        $org->forceFill(['storage_cap_override_gb' => 1])->save();

        $this->assertSame(1, $this->storage()->capGbFor($org->id));
    }

    public function test_clearing_the_override_returns_to_the_plan_default(): void
    {
        $org = $this->makeOrganization('cap-clear', 'Back To Plan');
        $this->setPlan($org->id, 'standard');

        $org->forceFill(['storage_cap_override_gb' => 500])->save();
        $this->assertSame(500, $this->storage()->capGbFor($org->id));

        $org->forceFill(['storage_cap_override_gb' => null])->save();
        $this->assertSame(50, $this->storage()->capGbFor($org->id));
    }

    public function test_the_summary_reports_the_overridden_cap(): void
    {
        $org = $this->makeOrganization('cap-summary', 'Summary');
        $this->setPlan($org->id, 'base');
        $org->forceFill(['storage_cap_override_gb' => 250])->save();

        $summary = $this->storage()->summaryFor($org->id);

        $this->assertSame(250, $summary['cap_gb']);
        $this->assertSame(250 * OrganizationStorage::BYTES_PER_GB, $summary['cap_bytes']);
        $this->assertSame('base', $summary['plan']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Plans/StorageCapTest.php`
Expected: FAIL — base returns 10, and `storage_cap_override_gb` does not exist.

- [ ] **Step 3: Update `config/storage.php`**

Change `plan_caps_gb` defaults to `5`, `50`, `100`, and change `default_cap_gb`'s default from `10` to `5`. Replace the `default_cap_gb` doc block's final paragraph with:

```
    | Note: this must never exceed the cheapest plan's cap. Its purpose is to
    | be the least generous answer for an organization whose plan cannot be
    | determined; set above Base it would quietly become the most generous,
    | and an agency with a broken subscription row would be rewarded for it.
```

- [ ] **Step 4: Write the migration**

Create `database/migrations/2026_08_20_000001_add_storage_cap_override_to_organizations.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A storage allowance set by hand for one organization.
 *
 * The Pro tier sells extra storage at Rs 1000 per additional 100 GB, arranged
 * by conversation rather than through the product. Rather than model a
 * purchase flow for something that happens a handful of times a year, the
 * superadmin types the agreed number here and it wins over the plan default.
 *
 * Nullable, and null is meaningfully different from zero: null means "use the
 * plan", zero would mean "no allowance at all".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->unsignedInteger('storage_cap_override_gb')->nullable()->after('subscription_type');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('storage_cap_override_gb');
        });
    }
};
```

- [ ] **Step 5: Update `app/Models/Organization.php`**

Add `'storage_cap_override_gb'` to `$fillable`, and add to the `casts()` array:

```php
            'storage_cap_override_gb' => 'integer',
```

- [ ] **Step 6: Update `app/Services/OrganizationStorage.php`**

Replace `capGbFor()` and the duplicated inline plan lookups:

```php
    /**
     * The allowance an organization has, in gigabytes.
     *
     * A per-organization override wins when set — that is how the Pro extra
     * storage arrangement is applied, by hand. Otherwise the plan decides, and
     * the plan comes from PlanFeatures so that this class and the feature
     * gates cannot disagree about what an agency is on.
     */
    public function capGbFor(int $organizationId): int
    {
        $override = DB::table('organizations')
            ->where('id', $organizationId)
            ->value('storage_cap_override_gb');

        // Null means "use the plan"; zero would mean "no allowance", so the
        // check is for null rather than for falsiness.
        if ($override !== null) {
            return (int) $override;
        }

        return $this->capGbForPlan($this->planFor($organizationId));
    }

    public function capGbForPlan(?string $plan): int
    {
        $caps = (array) config('storage.plan_caps_gb');

        return (int) ($caps[$plan] ?? config('storage.default_cap_gb'));
    }

    /**
     * The plan an organization is on, delegated so there is one answer.
     */
    protected function planFor(int $organizationId): string
    {
        return app(PlanFeatures::class)->planFor($organizationId);
    }
```

In `summaryFor()`, replace the inline subscription query with `$plan = $this->planFor($organizationId);` and `$capGb = $this->capGbFor($organizationId);`. Do the same wherever the all-organizations listing repeats that query — search the file for `organization_subscriptions` and leave none.

Add `use App\Services\PlanFeatures;`... it is in the same namespace, so no import is needed; reference it directly.

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test tests/Feature/Plans/StorageCapTest.php`
Expected: PASS, 6 tests.

- [ ] **Step 8: Run the existing storage tests**

Run: `php artisan test tests/Feature/Storage`
Expected: PASS. Any test asserting a 10 GB base cap must be updated to 5 — that is the intended change, not a regression.

- [ ] **Step 9: Commit**

```bash
git add config/storage.php database/migrations app/Models/Organization.php app/Services/OrganizationStorage.php tests/Feature/Plans/StorageCapTest.php tests/Feature/Storage
git commit -m "feat: storage caps 5/50/100 with a per-org override"
```

---

## Task 9: Make the subscription the only plan input

**Files:**
- Create: `database/migrations/2026_08_20_000002_sync_subscription_type_from_subscriptions.php`
- Modify: `app/Livewire/Superadmin/OrganizationDetail.php` — `saveSubscription()`, plus the override form field
- Modify: `resources/views/livewire/superadmin/organization-detail.blade.php` — override field in the subscription modal
- Modify: `app/Livewire/Superadmin/ManageOrganizations.php` — drop `subscription_type` from the form
- Modify: `resources/views/livewire/superadmin/manage-organizations.blade.php:116-122` — replace the select with the live plan
- Test: `tests/Feature/Plans/PlanSourceOfTruthTest.php`

**Interfaces:**
- Consumes: `PlanFeatures::planFor()` (Task 1).
- Produces: `organizations.subscription_type` is mirrored from the subscription on every save and is never an input.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Plans/PlanSourceOfTruthTest.php`:

```php
<?php

namespace Tests\Feature\Plans;

use App\Livewire\Superadmin\OrganizationDetail;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\User;
use App\Services\PlanFeatures;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Tenancy\BuildsOrganizations;
use Tests\TestCase;

class PlanSourceOfTruthTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrganizations;

    protected function superadmin(): User
    {
        return User::factory()->create([
            'name'            => 'Platform Super',
            'email'           => 'super@example.test',
            'role'            => 'superadmin',
            'organization_id' => null,
        ]);
    }

    public function test_saving_a_subscription_mirrors_the_plan_onto_the_organization(): void
    {
        $org = $this->makeOrganization('sot-a', 'Agency A');
        $org->forceFill(['subscription_type' => 'base'])->save();

        Livewire::actingAs($this->superadmin())
            ->test(OrganizationDetail::class, ['organization' => $org])
            ->call('openSubscription')
            ->set('plan', 'standard')
            ->set('price', '5000')
            ->set('billing_cycle', 'monthly')
            ->set('started_at', now()->toDateString())
            ->set('status', 'active')
            ->call('saveSubscription')
            ->assertHasNoErrors();

        // The legacy column can no longer drift away from the truth.
        $this->assertSame('standard', $org->fresh()->subscription_type);
    }

    public function test_the_storage_override_can_be_set_and_cleared_by_a_superadmin(): void
    {
        $org = $this->makeOrganization('sot-b', 'Agency B');

        $component = Livewire::actingAs($this->superadmin())
            ->test(OrganizationDetail::class, ['organization' => $org])
            ->call('openSubscription')
            ->set('plan', 'pro')
            ->set('price', '9000')
            ->set('billing_cycle', 'monthly')
            ->set('started_at', now()->toDateString())
            ->set('status', 'active')
            ->set('storage_cap_override_gb', '200')
            ->call('saveSubscription')
            ->assertHasNoErrors();

        $this->assertSame(200, $org->fresh()->storage_cap_override_gb);

        $component->call('openSubscription')
            ->set('storage_cap_override_gb', '')
            ->call('saveSubscription')
            ->assertHasNoErrors();

        $this->assertNull($org->fresh()->storage_cap_override_gb);
    }

    public function test_the_backfill_migration_corrects_a_stale_label(): void
    {
        // Model the production-copy state: the column says base, the
        // subscription says standard.
        $org = $this->makeOrganization('sot-stale', 'Stale Co');
        OrganizationSubscription::withoutGlobalScopes()->where('organization_id', $org->id)->delete();

        TenantContext::actingAsOrganization($org->id, function () {
            $subscription = new OrganizationSubscription([
                'plan'          => 'standard',
                'price'         => 1000,
                'billing_cycle' => 'monthly',
                'started_at'    => now()->toDateString(),
                'status'        => 'active',
            ]);
            $subscription->next_renewal_at = $subscription->renewalDateFrom(now());
            $subscription->save();
        });

        $org->forceFill(['subscription_type' => 'base'])->save();
        PlanFeatures::flush();

        // The backfill body lives on the service rather than inside the
        // migration class, because RefreshDatabase has already run every
        // migration against an empty table by the time a test starts — an
        // anonymous migration class is not addressable afterwards. The
        // migration is a one-line call to this.
        app(PlanFeatures::class)->syncLegacyPlanColumn();

        $this->assertSame('standard', $org->fresh()->subscription_type);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Plans/PlanSourceOfTruthTest.php`
Expected: FAIL — `subscription_type` stays `base`; `storage_cap_override_gb` is not a component property.

- [ ] **Step 3: Add `syncLegacyPlanColumn()` to `PlanFeatures`**

```php
    /**
     * Bring organizations.subscription_type back into line with the truth.
     *
     * The column is a legacy label that a second superadmin screen used to
     * write, which is how it came to disagree with the subscription — in the
     * production copy one agency read 'base' while paying for 'standard'.
     * Nothing consults it any more, but leaving a wrong number on screen is
     * its own bug, so it is corrected once here and mirrored on save
     * thereafter.
     */
    public function syncLegacyPlanColumn(): void
    {
        $rows = DB::table('organizations')->select('id')->get();

        foreach ($rows as $row) {
            DB::table('organizations')
                ->where('id', $row->id)
                ->update(['subscription_type' => $this->planFor((int) $row->id)]);
        }
    }
```

- [ ] **Step 4: Write the backfill migration**

Create `database/migrations/2026_08_20_000002_sync_subscription_type_from_subscriptions.php`:

```php
<?php

use App\Services\PlanFeatures;
use Illuminate\Database\Migrations\Migration;

/**
 * One-time correction of the legacy plan label.
 *
 * organizations.subscription_type and organization_subscriptions.plan were
 * edited from two different superadmin screens and had already diverged. The
 * subscription is now the only source of truth; this brings the column into
 * line so nothing on screen still reports the stale value.
 *
 * There is no down(): the previous values were wrong, and restoring them would
 * be restoring the bug.
 */
return new class extends Migration
{
    public function up(): void
    {
        PlanFeatures::flush();

        app(PlanFeatures::class)->syncLegacyPlanColumn();
    }

    public function down(): void
    {
        //
    }
};
```

- [ ] **Step 5: Mirror on save in `OrganizationDetail::saveSubscription()`**

Add the storage override to the property list and validation, and mirror the plan. Add the property:

```php
    /**
     * Blank means "use the plan default" — see the migration that adds the
     * column for why null and zero are different answers.
     */
    public string $storage_cap_override_gb = '';
```

In `openSubscription()`, populate it from the organization in both branches:

```php
        $this->storage_cap_override_gb = (string) ($this->organization->storage_cap_override_gb ?? '');
```

In `saveSubscription()`, extend the validation array with:

```php
            'storage_cap_override_gb' => ['nullable', 'integer', 'min:1', 'max:100000'],
```

and after the existing `TenantContext::actingAsOrganization(...)` block, add:

```php
        /*
         * The subscription is the only source of truth for the plan; this
         * keeps the legacy label on the organization from drifting away from
         * it again. One writer, so the two cannot disagree.
         */
        $this->organization->forceFill([
            'subscription_type'       => $data['plan'],
            'storage_cap_override_gb' => $data['storage_cap_override_gb'] === null
                || $data['storage_cap_override_gb'] === ''
                    ? null
                    : (int) $data['storage_cap_override_gb'],
        ])->save();

        PlanFeatures::flush();
```

Add `use App\Services\PlanFeatures;` to the imports.

- [ ] **Step 6: Add the override field to the subscription modal**

In `resources/views/livewire/superadmin/organization-detail.blade.php`, after the plan `<select>` block (around line 237-243), add:

```blade
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">
                                Storage override (GB)
                            </label>
                            <input type="number" min="1" wire:model="storage_cap_override_gb"
                                placeholder="Leave blank to use the plan default"
                                class="w-full rounded-lg bg-slate-800 border border-slate-700 px-3 py-2 text-sm text-slate-100">
                            <p class="mt-1 text-xs text-slate-500">
                                For the Pro extra-storage arrangement. Blank uses the plan's own cap.
                            </p>
                            @error('storage_cap_override_gb') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>
```

- [ ] **Step 7: Make `subscription_type` read-only in Manage Organizations**

In `app/Livewire/Superadmin/ManageOrganizations.php`: remove `'subscription_type' => [...]` from the validation array (line ~104), remove the `public string $subscription_type` property (line 40), the assignment in the edit opener (line 84) and the reset (line 170). Wherever the create/update payload is built, drop the key — a new organization keeps the column's database default of `base` until a subscription is set up.

In `resources/views/livewire/superadmin/manage-organizations.blade.php`, replace the `<select wire:model="subscription_type">` block (lines ~116-122) with:

```blade
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Plan</label>
                            <p class="rounded-lg bg-slate-800/60 border border-slate-700 px-3 py-2 text-sm text-slate-300">
                                {{ $editingId ? ucfirst(app(\App\Services\PlanFeatures::class)->planFor($editingId)) : 'Base' }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                Set by the subscription. Change it in Organization Detail.
                            </p>
                        </div>
```

- [ ] **Step 8: Run test to verify it passes**

Run: `php artisan test tests/Feature/Plans/PlanSourceOfTruthTest.php`
Expected: PASS, 3 tests.

- [ ] **Step 9: Run the superadmin tests**

Run: `php artisan test tests/Feature/Superadmin tests/Feature/Billing`
Expected: PASS. Any test setting `subscription_type` through `ManageOrganizations` must move to asserting the plan via the subscription — that is the intended change.

- [ ] **Step 10: Commit**

```bash
git add database/migrations app/Services/PlanFeatures.php app/Livewire/Superadmin resources/views/livewire/superadmin tests/Feature/Plans/PlanSourceOfTruthTest.php
git commit -m "feat: subscription is the only plan input; backfill the legacy column"
```

---

## Task 10: Full-suite verification and a clone check

**Files:**
- No production changes expected.

- [ ] **Step 1: Run the full suite**

Run: `php artisan test 2>&1 | grep -aE "FAILED|Tests:"`
Expected: exactly the 14 baseline failures listed in Global Constraints, and no others. Anything new must be fixed before continuing.

- [ ] **Step 2: Refresh the clone from the production copy**

The clone from the previous session is stale and does not have the new column.

```bash
SCRATCH="C:/Users/user/AppData/Local/Temp/claude/D--Desktop-Files-Project-Manage-clarix/7d6507a6-fcbb-41f5-8329-862298bcdc95/scratchpad"
/c/xampp/mysql/bin/mysqldump.exe -u root --single-transaction clarix > "$SCRATCH/clarix_snapshot.sql"
/c/xampp/mysql/bin/mysql.exe -u root -e "DROP DATABASE IF EXISTS clarix_profile_check; CREATE DATABASE clarix_profile_check CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
/c/xampp/mysql/bin/mysql.exe -u root clarix_profile_check < "$SCRATCH/clarix_snapshot.sql"
```

Never run migrations against `clarix` itself.

- [ ] **Step 3: Migrate the clone and confirm the backfill**

```bash
DB_DATABASE=clarix_profile_check php artisan migrate --force
/c/xampp/mysql/bin/mysql.exe -u root clarix_profile_check -e \
  "SELECT o.name, o.subscription_type, o.storage_cap_override_gb, s.plan FROM organizations o LEFT JOIN organization_subscriptions s ON s.organization_id=o.id;"
```

Expected: Code Next Door `pro`/`pro`; CRC now `standard`/`standard` — the stale `base` label corrected. `storage_cap_override_gb` null for both.

- [ ] **Step 4: Confirm the real data resolves to the right plan and cap**

```bash
DB_DATABASE=clarix_profile_check php artisan tinker --execute="
\$p = app(\App\Services\PlanFeatures::class);
\$s = app(\App\Services\OrganizationStorage::class);
foreach ([1, 4] as \$id) {
    echo \$id.': plan='.\$p->planFor(\$id)
        .' erp='.var_export(\$p->allows('erp', \$id), true)
        .' automation='.var_export(\$p->allows('automation', \$id), true)
        .' cap='.\$s->capGbFor(\$id).'GB'.PHP_EOL;
}"
```

Expected: org 1 (Code Next Door) `plan=pro erp=true automation=true cap=100GB` — full access retained, as required. Org 4 (CRC) `plan=standard erp=true automation=false cap=50GB`.

- [ ] **Step 5: Confirm the production copy is untouched**

```bash
/c/xampp/mysql/bin/mysql.exe -u root -e \
  "SELECT name, subscription_type FROM clarix.organizations; SHOW COLUMNS FROM clarix.organizations LIKE 'storage_cap_override_gb';"
```

Expected: CRC still reads `base` and the column does not exist — proving the migration ran only against the clone.

- [ ] **Step 6: Commit any fixes and report**

Report the final suite figures against the baseline, and state plainly that the visual browse-check was not performed if browser tooling is still unavailable.

---

## Self-Review

**Spec coverage:**

| Spec section | Task |
|---|---|
| Source of truth; backfill; mirror on save; ManageOrganizations read-only | 9 |
| New org resolves to base | 9 (Step 7) |
| Feature matrix as minimums; fail closed; labels | 1 |
| `PlanFeatures`, per-request memo only | 1 |
| `User::planAllows()`, superadmin exempt | 2 |
| `EnsurePlanIncludes`, `plan` alias | 3 |
| Single refusal path, `abort(402)`, standalone page with a way back | 3 |
| Route table incl. ungated `ai.overview` | 4 |
| Component self-guards (all ten) | 5 |
| Sidebar HR + AI, dashboards, profile page | 6 |
| Layering unchanged, both layers required | 7 |
| Downgrade hides without deleting | 7 |
| Storage caps 5/50/100, `default_cap_gb` → 5 | 8 |
| `storage_cap_override_gb` migration + resolution | 8 |
| Override editable by superadmin | 9 |
| Baseline comparison; clone-only verification | 10 |

No gaps.

**Placeholder scan:** none — every code step carries the actual content it needs.

**Type consistency:** `planAllows(string): bool`, `allows(string, ?int): bool`, `planFor(?int): string`, `minimumPlanFor(string): ?string`, `labelFor(string): string`, `flush(): void`, `syncLegacyPlanColumn(): void`, `refusalFor(string, ?int): string`, `capGbFor(int): int`. Feature keys are `tasks`, `files`, `erp`, `ai_chat`, `calendar`, `automation` throughout. `PlanFeatures::flush()` is static and used as such in every test.

**One risk flagged for the executor:** Task 4 Step 6 changes a shared fixture (`BuildsOrganizations::makeOrganization`) to subscribe every test organization to `pro`. That is deliberate — it keeps tests that are not about plans from being silently gated — but it touches every tenancy-aware test in the suite. Run the full suite at that step, not later.
