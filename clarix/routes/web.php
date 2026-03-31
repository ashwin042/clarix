<?php

use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TaskAssignmentController;
use App\Http\Controllers\TaskController;
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
use App\Livewire\CreditList;
use App\Livewire\PM\UserManagement as PMUserManagement;
use App\Livewire\Tasks\ManageTasks;
use App\Livewire\Tasks\TaskDetail;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::view('/profile', 'profile')->name('profile');

    // Livewire full-page components
    Route::get('/tasks', ManageTasks::class)->name('tasks.index');
    Route::get('/tasks/{task}', TaskDetail::class)->name('tasks.show');
    Route::get('/credits', CreditList::class)->name('credits.index');

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
    });

    // Task sub-resources (still handled by traditional controllers)
    Route::post('tasks/{task}/files', [TaskFileController::class, 'store'])->name('tasks.files.store');
    Route::get('tasks/{task}/files/{file}/download', [TaskFileController::class, 'download'])->name('tasks.files.download');
    Route::delete('tasks/{task}/files/{file}', [TaskFileController::class, 'destroy'])->name('tasks.files.destroy');

    Route::post('tasks/{task}/notes', [TaskNoteController::class, 'store'])->name('tasks.notes.store');

    Route::post('tasks/{task}/assignments', [TaskAssignmentController::class, 'store'])->name('tasks.assignments.store');
    Route::delete('tasks/{task}/assignments/{assignment}', [TaskAssignmentController::class, 'destroy'])->name('tasks.assignments.destroy');
    Route::patch('tasks/{task}/assignments/{assignment}/status', [TaskAssignmentController::class, 'updateStatus'])->name('tasks.assignments.status');

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

