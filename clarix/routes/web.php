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
use App\Livewire\NotificationPage;
use App\Livewire\Admin\AuthorizationPanel;
use App\Livewire\Admin\ManageUnits;
use App\Livewire\Admin\ManageUsers;
use App\Livewire\Admin\RoleUserManagement;
use App\Livewire\Admin\UnitAnalytics;
use App\Livewire\AI\Calendar;
use App\Livewire\AI\Chatbot;
use App\Livewire\AI\McpPlugins;
use App\Livewire\AI\Overview;
use App\Livewire\AI\ScheduledTasks;
use App\Livewire\CreditList;
use App\Livewire\PM\UserManagement as PMUserManagement;
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

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::view('/profile', 'profile')->name('profile');

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

    // Admin issues panel
    Route::middleware(['role:admin'])->get('/admin/issues', AdminIssues::class)->name('admin.issues.index');

    // Finance (admin only)
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/admin/finance', FinancialDashboard::class)->name('admin.finance');
        Route::get('/admin/payments', ManagePayments::class)->name('admin.payments');
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
        Route::get('/chatbot', Chatbot::class)->name('chatbot');

        Route::get('/mcp', McpPlugins::class)->name('mcp');

        Route::get('/calendar', Calendar::class)->name('calendar');

        Route::get('/overview', Overview::class)->name('overview');

        Route::get('/scheduled-tasks', ScheduledTasks::class)->name('scheduled-tasks');
    });

    // PM-only routes
    Route::middleware(['role:pm'])->prefix('pm')->name('pm.')->group(function () {
        Route::get('/users', PMUserManagement::class)->name('users');
    });

    // Admin-only
    Route::middleware(['role:admin'])->prefix('admin')->name('admin.')->group(function () {
        // Livewire full-page for manage pages
        Route::get('/units', ManageUnits::class)->name('units.index');
        Route::get('/users', ManageUsers::class)->name('users.index');
        Route::get('/admins', RoleUserManagement::class)->name('admins.index')->defaults('role', 'admin');
        Route::get('/project-managers', RoleUserManagement::class)->name('pms.index')->defaults('role', 'pm');
        Route::get('/writers', RoleUserManagement::class)->name('writers.index')->defaults('role', 'writer');

        // Keep traditional routes for any redirects that reference them
        Route::get('/units/create', [UnitController::class, 'create'])->name('units.create');
        Route::get('/units/{unit}/edit', [UnitController::class, 'edit'])->name('units.edit');
        Route::post('/units', [UnitController::class, 'store'])->name('units.store');
        Route::put('/units/{unit}', [UnitController::class, 'update'])->name('units.update');
        Route::delete('/units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');

        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        // Authorization panel (admin only)
        Route::get('/authorization', AuthorizationPanel::class)->name('authorization');
    });
});

require __DIR__.'/auth.php';

