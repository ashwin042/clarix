<?php

use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TaskAssignmentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\CreditExportController;
use App\Http\Controllers\TaskFileController;
use App\Http\Controllers\TaskNoteController;
use App\Livewire\Issues\IssueList;
use App\Livewire\Issues\IssueDetail;
use App\Livewire\Issues\AdminIssues;
use App\Livewire\Finance\ManagePayments;
use App\Livewire\Finance\FinancialDashboard;
use App\Livewire\Finance\Subscription;
use App\Livewire\NotificationPage;
use App\Livewire\Admin\AuthorizationPanel;
use App\Livewire\Admin\ManageUnits;
use App\Livewire\Admin\ManageUsers;
use App\Livewire\Admin\RoleUserManagement;
use App\Livewire\Admin\StorageUsage;
use App\Livewire\Admin\UnitAnalytics;
use App\Livewire\Attendance\AttendancePage;
use App\Livewire\Leave\LeavePage;
use App\Livewire\Leave\ManageLeaveTypes;
use App\Livewire\Payroll\ManagePayroll;
use App\Livewire\Payroll\MyPayroll;
use App\Livewire\Profile\ProfileOverview;
use App\Livewire\AI\Calendar;
use App\Livewire\AI\Chatbot;
use App\Livewire\AI\McpPlugins;
use App\Livewire\AI\Overview;
use App\Livewire\AI\ScheduledTasks;
use App\Livewire\CreditList;
use App\Livewire\PM\UserManagement as PMUserManagement;
use App\Livewire\Superadmin\CreateOrganizationAdmin;
use App\Livewire\Superadmin\ManageOrganizations;
use App\Livewire\Superadmin\PlatformStorage;
use App\Livewire\Superadmin\OrganizationDetail;
use App\Livewire\Tasks\AssignedTasks;
use App\Livewire\Tasks\CompletedTasks;
use App\Livewire\Tasks\CreateTask;
use App\Livewire\Tasks\ManageTasks;
use App\Livewire\Tasks\TaskDetail;
use Illuminate\Support\Facades\Route;

// The bare root is a signpost, never a page of its own: signed-in users land
// on their dashboard, everyone else on the marketing homepage. A 302, not a
// 301 — browsers cache a permanent redirect hard, and this destination is the
// kind of thing that gets revisited.
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('home');
});

// Public marketing homepage.
Route::view('/home', 'marketing.home')->name('home');

// The ordinary application. 'subscription' blocks every route in here while
// the organization is suspended; it is deliberately absent from the superadmin
// group below, which is where a suspension gets lifted.
Route::middleware(['auth', 'subscription'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    /*
     * Two pages that used to be one.
     *
     * 'profile' is what you are — your details, your workload, your
     * attendance, leave and pay, gathered in one place and read only.
     * 'settings' is what you can change about your account: name, email,
     * password, deletion. The account forms held the /profile URL first; they
     * moved here when the overview arrived, because a page called "profile"
     * that only offered a password field was the odd one out.
     *
     * The profile route carries no parameter and never will. Reading somebody
     * else's figures is a different feature with a different set of policies
     * behind it, and it must not be reachable by editing this URL.
     */
    Route::get('/profile', ProfileOverview::class)->name('profile');
    Route::view('/settings', 'settings')->name('settings');

    // Livewire full-page components
    Route::get('/tasks', ManageTasks::class)->name('tasks.index');
    Route::get('/tasks/completed', CompletedTasks::class)->name('tasks.completed');
    Route::middleware(['role:admin'])->get('/tasks/assigned', AssignedTasks::class)->name('tasks.assigned');
    Route::get('/tasks/create', CreateTask::class)->name('tasks.create');
    Route::get('/tasks/{task}', TaskDetail::class)->name('tasks.show');
    Route::get('/credits', CreditList::class)->name('credits.index');
    Route::get('/credits/export', CreditExportController::class)->name('credits.export');

    // Unit analytics (admin only)
    Route::middleware(['role:admin'])->get('/units/{unit}', UnitAnalytics::class)->name('units.show');

    // Issues
    Route::get('/issues', IssueList::class)->name('issues.index');
    Route::get('/issues/{issue}', IssueDetail::class)->name('issues.show');

    // Notifications
    Route::get('/notifications', NotificationPage::class)->name('notifications');

    /*
     * Attendance. No permission middleware: clocking yourself in and out is
     * structural, so the page has to open for everyone whose agency has ERP.
     * What it then shows — your own record, your unit's, or the whole agency —
     * is decided inside by AttendancePolicy.
     *
     * The plan gate is a separate question from all of that. "Structural"
     * means no role may be denied it; it does not mean an agency that has not
     * bought ERP receives it anyway.
     */
    Route::middleware(['plan:erp'])->get('/attendance', AttendancePage::class)->name('attendance.index');

    /*
     * Leave. Same reasoning as attendance: no permission middleware, because
     * asking for time off has to be open to everyone — within an agency that
     * has ERP. What the page then shows — your own requests, your unit's
     * queue, the whole agency's — is decided inside by LeaveRequestPolicy. The
     * leave-type screen guards itself as admin-only in its own mount().
     */
    Route::middleware(['plan:erp'])->group(function () {
        Route::get('/leave', LeavePage::class)->name('leave.index');
        Route::get('/leave/types', ManageLeaveTypes::class)->name('leave.types');
    });

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

    // Admin issues panel
    Route::middleware(['role:admin'])->get('/admin/issues', AdminIssues::class)->name('admin.issues.index');

    // Finance (admin only)
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/admin/finance', FinancialDashboard::class)->name('admin.finance');
        Route::get('/admin/payments', ManagePayments::class)->name('admin.payments');

        // This organization's own billing with Clarix. Distinct from
        // admin.payments above, which is the agency billing its clients.
        Route::get('/admin/subscription', Subscription::class)->name('admin.subscription');
        Route::get('/admin/deletion-requests', \App\Livewire\Admin\DeletionRequests::class)->name('admin.deletion-requests');
    });

    // Task sub-resources (still handled by traditional controllers)
    Route::post('tasks/{task}/files', [TaskFileController::class, 'store'])->name('tasks.files.store');
    Route::post('tasks/{task}/completed-files', [TaskFileController::class, 'storeCompleted'])->name('tasks.completed-files.store');
    Route::get('tasks/{task}/files/{file}/download', [TaskFileController::class, 'download'])->name('tasks.files.download');
    Route::delete('tasks/{task}/files/{file}', [TaskFileController::class, 'destroy'])->name('tasks.files.destroy');

    Route::post('tasks/{task}/notes', [TaskNoteController::class, 'store'])->name('tasks.notes.store');

    Route::post('tasks/{task}/assignments', [TaskAssignmentController::class, 'store'])->name('tasks.assignments.store');
    Route::delete('tasks/{task}/assignments/{assignment}', [TaskAssignmentController::class, 'destroy'])->name('tasks.assignments.destroy');
    Route::patch('tasks/{task}/assignments/{assignment}/status', [TaskAssignmentController::class, 'updateStatus'])->name('tasks.assignments.status');

    // AI & Automation. Open to every role that exists today; the middleware is
    // listed explicitly so a role added later is denied until it is named here,
    // and it mirrors the check guarding the sidebar section.
    Route::middleware(['role:admin,pm,writer'])->prefix('ai')->name('ai.')->group(function () {
        /*
         * Ungated on purpose. An informational page is the one place in the
         * product where a Base or Standard agency can read what upgrading
         * would give them, so putting it behind the upgrade wall would hide
         * the pitch from exactly the people it is for.
         */
        Route::get('/overview', Overview::class)->name('overview');

        Route::middleware(['plan:ai_chat'])->get('/chatbot', Chatbot::class)->name('chatbot');

        Route::middleware(['plan:calendar'])->get('/calendar', Calendar::class)->name('calendar');

        Route::middleware(['plan:automation'])->group(function () {
            Route::get('/mcp', McpPlugins::class)->name('mcp');
            Route::get('/scheduled-tasks', ScheduledTasks::class)->name('scheduled-tasks');
        });
    });

    // PM-only routes
    Route::middleware(['role:pm'])->prefix('pm')->name('pm.')->group(function () {
        Route::get('/users', PMUserManagement::class)->name('users');
    });

    /*
     * Agency administration.
     *
     * Gated per action on the permissions the Authorization panel actually
     * toggles, rather than on 'role:admin' for the whole group. A blanket role
     * gate here was the reason granting a role units.view changed nothing: the
     * sidebar drew the link off the permission, and this middleware refused
     * the request before ManageUnits could consult that same permission.
     *
     * Admins are unaffected — hasPermission() is unconditionally true for
     * them — so nothing an admin could reach before has narrowed.
     */
    Route::prefix('admin')->name('admin.')->group(function () {
        // Livewire full-page for manage pages
        Route::middleware(['permission:units.view'])
            ->get('/units', ManageUnits::class)->name('units.index');

        Route::middleware(['permission:users.view'])
            ->get('/users', ManageUsers::class)->name('users.index');

        /*
         * The per-role staff screens stay on the role gate. They are where an
         * agency's admins are created and edited, RoleUserManagement refuses a
         * non-admin in its own mount() regardless, and the sidebar only ever
         * offers them to admins — so gating them on users.view would hand a
         * granted role a link that the component itself then refuses.
         */
        Route::middleware(['role:admin'])->group(function () {
            Route::get('/admins', RoleUserManagement::class)->name('admins.index')->defaults('role', 'admin');
            Route::get('/project-managers', RoleUserManagement::class)->name('pms.index')->defaults('role', 'pm');
            Route::get('/writers', RoleUserManagement::class)->name('writers.index')->defaults('role', 'writer');
            Route::get('/supervisors', RoleUserManagement::class)->name('supervisors.index')->defaults('role', 'supervisor');
            Route::get('/hr', RoleUserManagement::class)->name('hr.index')->defaults('role', 'hr');
        });

        // Keep traditional routes for any redirects that reference them
        Route::middleware(['permission:units.create'])->group(function () {
            Route::get('/units/create', [UnitController::class, 'create'])->name('units.create');
            Route::post('/units', [UnitController::class, 'store'])->name('units.store');
        });
        Route::middleware(['permission:units.update'])->group(function () {
            Route::get('/units/{unit}/edit', [UnitController::class, 'edit'])->name('units.edit');
            Route::put('/units/{unit}', [UnitController::class, 'update'])->name('units.update');
        });
        // Deletion is admin-only by structure, not by permission — there is no
        // units.delete to grant. The controller re-checks through UnitPolicy.
        Route::middleware(['role:admin'])
            ->delete('/units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');

        Route::middleware(['permission:users.create'])->group(function () {
            Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
        });
        Route::middleware(['permission:users.update'])->group(function () {
            Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        });
        // Same: admin-only by structure. UserPolicy re-checks in the controller.
        Route::middleware(['role:admin'])
            ->delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        /*
         * Deliberately still role:admin, not a permission.
         *
         * This screen is where permissions are handed out. Gating it on a
         * grantable permission would let an admin give a role the power to
         * grant itself everything else, so the ability to edit the matrix
         * stays tied to being an admin and is not itself delegable.
         */
        Route::middleware(['role:admin'])->group(function () {
            Route::get('/authorization', AuthorizationPanel::class)->name('authorization');

            // R2 storage usage per unit. Not represented in the panel, so it
            // keeps the role gate it already had.
            Route::get('/storage', StorageUsage::class)->name('storage');
        });
    });
});

/*
 * Platform administration.
 *
 * Kept entirely apart from the /admin group above: that one is the top of an
 * agency, this one is above every agency. The role guard names 'superadmin'
 * only, so an organization's own admin is refused here exactly as any other
 * role would be — being the most senior person inside an agency grants nothing
 * at the platform level.
 */
Route::middleware(['auth', 'role:superadmin'])
    ->prefix('superadmin')
    ->name('superadmin.')
    ->group(function () {
        Route::redirect('/', '/superadmin/organizations');

        Route::get('/organizations', ManageOrganizations::class)->name('organizations.index');

        // Bound by slug rather than id — Organization::getRouteKeyName().
        Route::get('/organizations/{organization}', OrganizationDetail::class)->name('organizations.show');
        Route::get('/organizations/{organization}/admin', CreateOrganizationAdmin::class)->name('organizations.admin');

        /*
         * Storage across every agency. A narrow, deliberate exception to the
         * rule that operational data is closed to the platform: what an
         * organization stores against a quota it pays for is billing
         * information. Organization totals only — the per-unit rows behind
         * them stay with the agency.
         */
        Route::get('/storage', PlatformStorage::class)->name('storage');
    });

require __DIR__.'/auth.php';

