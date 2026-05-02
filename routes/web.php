<?php

use App\Http\Controllers\ActionLogController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ManageAnonymousFeedbackController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PettyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicAnonymousFeedbackController;
use App\Http\Controllers\ReplenishmentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\WeeklyObjectiveMatrixController;
use App\Http\Controllers\WorkPeriodController;
use App\Http\Controllers\YearPlanController;
use Illuminate\Support\Facades\Route;

Route::prefix('feedback/{token}')
    ->middleware(['feedback.token'])
    ->group(function () {
        Route::get('/', [PublicAnonymousFeedbackController::class, 'create'])->name('anonymous-feedback.form');
        Route::post('/', [PublicAnonymousFeedbackController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('anonymous-feedback.store');
    });

Route::get('/', [DashboardController::class, 'redirect'])->middleware(['auth', 'verified']);

Route::get('/dashboard', [DashboardController::class, 'redirect'])
    ->middleware(['auth', 'verified'])->name('dashboard');

// Redirect old employee dashboard to main dashboard
Route::get('/documents/employee/dashboard', function () {
    return redirect()->route('dashboard');
})->middleware(['auth', 'verified']);

Route::middleware('auth')->group(function () {
    Route::get('/profile/settings', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile/settings', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/picture/update', [ProfileController::class, 'updateProfile'])->name('profile.image');

    // Documents routes
    Route::prefix('documents')->name('documents.')->group(function () {
        Route::get('/', [DocumentController::class, 'index'])->name('index');
        Route::get('/weekly-plans', [DocumentController::class, 'weeklyPlans'])->name('weekly-plans');
        Route::get('/weekly-reports', [DocumentController::class, 'weeklyReports'])->name('weekly-reports');
        Route::get('/weekly-budget-review', [DocumentController::class, 'weeklyBudgetReviewPanel'])->name('weekly-budget-review');
        Route::post('/weekly-budget-review/add-midweek-budget', [DocumentController::class, 'addMidWeekBudgetEntry'])->name('weekly-budget-review.add-midweek-budget');
        Route::get('/weekly-budget-review/export/pdf', [DocumentController::class, 'exportWeeklyBudgetReviewPdf'])->name('weekly-budget-review.export.pdf');
        Route::get('/weekly-budget-review/export/excel', [DocumentController::class, 'exportWeeklyBudgetReviewExcel'])->name('weekly-budget-review.export.excel');
        Route::get('/weekly-objective-matrix/download', [WeeklyObjectiveMatrixController::class, 'download'])->name('weekly-objective-matrix.download');
        Route::get('/plan-target', [DocumentController::class, 'getPlanTarget'])->name('plan-target');
        Route::get('/create', [DocumentController::class, 'create'])->name('create');
        Route::post('/', [DocumentController::class, 'store'])->name('store');
        Route::post('/{document}/weekly-budget-review', [DocumentController::class, 'processWeeklyBudgetReview'])->name('process-weekly-budget-review');

        // Reviewer routes (must be registered before /{document} so "reviewer" is not captured as a document id)
        Route::get('/reviewer/dashboard', [DocumentController::class, 'reviewerDashboard'])->name('reviewer-dashboard');
        Route::get('/reviewer/{document}', [DocumentController::class, 'reviewerShow'])->name('reviewer-show');

        Route::get('/{document}', [DocumentController::class, 'show'])->name('show');
        Route::get('/{document}/edit', [DocumentController::class, 'edit'])->name('edit');
        Route::put('/{document}', [DocumentController::class, 'update'])->name('update');
        Route::delete('/{document}', [DocumentController::class, 'destroy'])->name('destroy');
        Route::post('/{document}/submit', [DocumentController::class, 'submit'])->name('submit');
        Route::post('/{document}/reject', [DocumentController::class, 'reject'])->name('reject');
    });

    // Action Logs routes (restricted to minutes_preparer and admin - authorization handled in controller)
    Route::prefix('action-logs')->name('action-logs.')->group(function () {
        Route::get('/', [ActionLogController::class, 'index'])->name('index');
        Route::get('/create', [ActionLogController::class, 'create'])->name('create');
        Route::post('/', [ActionLogController::class, 'store'])->name('store');
        Route::get('/{document}/download', [ActionLogController::class, 'downloadPdf'])->name('download');
        Route::get('/{document}', [ActionLogController::class, 'show'])->name('show');
        Route::get('/{document}/edit', [ActionLogController::class, 'edit'])->name('edit');
        Route::put('/{document}', [ActionLogController::class, 'update'])->name('update');
        Route::delete('/{document}', [ActionLogController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('permission:manage year plans')->prefix('year-plans')->name('year-plan.')->group(function () {
        Route::get('/', [YearPlanController::class, 'index'])->name('index');
        Route::post('/', [YearPlanController::class, 'store'])->name('store');
        Route::get('/{year}', [YearPlanController::class, 'show'])->name('show');
        Route::post('/{year}/activities', [YearPlanController::class, 'storeActivity'])->name('activities.store');
        Route::put('/{year}/activities/{activity}', [YearPlanController::class, 'updateActivity'])->name('activities.update');
        Route::delete('/{year}/activities/{activity}', [YearPlanController::class, 'destroyActivity'])->name('activities.destroy');
    });

    // departments
    Route::middleware('permission:manage departments')->group(function () {
        Route::get('/departments', [DepartmentController::class, 'index'])->name('departments');          // list
        Route::post('/department', [DepartmentController::class, 'store'])->name('department.store');    // create
        Route::put('/department/{department}', [DepartmentController::class, 'update'])->name('department.update'); // update
        Route::delete('/department/{department}', [DepartmentController::class, 'destroy'])->name('department.destroy'); // delete
        Route::get('/department/{department}', [DepartmentController::class, 'show'])->name('department.show');       // show
    });

    // admin
    Route::resource('admin', AdminController::class);
    Route::post('/activate/{user}', [AdminController::class, 'activate'])->name('admin.activate');

    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

});

Route::middleware(['auth', 'permission:request pettycash'])->group(function () {
    Route::middleware(['auth'])->prefix('petty')->name('petty.')->group(function () {
        // Main index route
        Route::get('/', [PettyController::class, 'index'])->name('index');

        // Create and store routes
        Route::get('/create', [PettyController::class, 'create'])->name('create');
        Route::post('/store', [PettyController::class, 'store'])->name('store');

        // Other routes
        Route::get('/show/{petty}', [PettyController::class, 'show'])->name('show');
        Route::delete('/delete/{petty}', [PettyController::class, 'destroy'])->name('destroy');
    });

});

Route::middleware(['auth', 'permission:request pettycash'])->group(function () {
    Route::get('/requisition', [PettyController::class, 'requisitionRequests'])->name('requisition.index');
    Route::get('/requisition/create', [PettyController::class, 'createRequisition'])->name('requisition.create');
    Route::post('/requisition', [PettyController::class, 'store'])->name('requisition.store');
    Route::get('/requisition/{petty}', [PettyController::class, 'requisitionShow'])->name('requisition.show');
    Route::get('/requisition/{petty}/edit', [PettyController::class, 'requisitionEdit'])->name('requisition.edit');
    Route::put('/requisition/{petty}', [PettyController::class, 'requisitionUpdate'])->name('requisition.update');
});

Route::middleware(['auth', 'permission:view requested pettycash'])->group(function () {
    Route::get('pettycash/requests/list', [PettyController::class, 'requests_list'])->name('petty.list');
    Route::get('/pettycash/request/{petty}/details', [PettyController::class, 'request_show'])->name('petty.details');
    Route::post('/petty/reject/{petty}', [PettyController::class, 'reject'])->name('petty.reject');

    Route::resource('replenishment', ReplenishmentController::class);
    Route::post('/replenishment/initial/approve/{replenishment}', [ReplenishmentController::class, 'firstApprove'])->name('initial.approve');
    Route::post('/replenishment/last/approve/{replenishment}', [ReplenishmentController::class, 'lastApprove'])->name('last.approve');
    Route::get('/replenishment/petty/cash/list', [ReplenishmentController::class, 'pettycash'])->name('replenishment.pettycash');
    Route::get('/replenishment/{replenishment}/download', [ReplenishmentController::class, 'downloadPDF'])->name('replenishment.download');
});

Route::middleware(['auth', 'permission:review requests|review staff requisition|first pettycash approval'])->group(function () {
    Route::post('f_approve/{petty}', [PettyController::class, 'f_approve'])->name('f_approve.approve');
});

Route::middleware(['auth', 'permission:approve requests|approve staff requisition|last pettycash approval'])->group(function () {
    Route::post('l_approve/{petty}', [PettyController::class, 'l_approve'])->name('l_approve.approve');
});

Route::middleware(['auth', 'permission:authorize requests|authorize staff requisition'])->group(function () {
    Route::post('authorize/{petty}', [PettyController::class, 'authorizeRequest'])->name('requests.authorize');
});

Route::middleware(['auth', 'permission:approve pettycash payments'])->group(function () {
    Route::post('c_approve/{petty}', [PettyController::class, 'c_approve'])->name('c_approve.approve');
});

Route::middleware(['auth', 'permission:review staff requisition|approve staff requisition|authorize staff requisition'])->group(function () {
    Route::get('pettycash/requisitions/approvals', [PettyController::class, 'requisitionApprovals'])->name('petty.requisition.approvals');
});

Route::middleware(['auth', 'permission:download staff requisition|authorize staff requisition|approve pettycash payments'])->group(function () {
    Route::get('pettycash/requisitions/{petty}/download', [PettyController::class, 'downloadRequisition'])->name('petty.requisition.download');
});

Route::middleware(['auth', 'permission:last pettycash approval'])->group(function () {
    Route::get('pettycash/all/requests/list', [PettyController::class, 'all_requests'])->name('all.pettycash');
});

Route::middleware(['auth', 'permission:view cashflow movements'])->group(function () {
    Route::resource('deposit', DepositController::class);
    Route::get('/pettycash/flow', [DepositController::class, 'cashflow'])->name('cashflow.index');
    Route::get('/cashflow/download', [DepositController::class, 'download'])->name('cashflow.download');
    Route::get('pettycash/requests/payments/list', [PettyController::class, 'requestsCashier'])->name('petty.cashier');
});

Route::middleware(['auth', 'permission:view reports'])->group(function () {
    Route::get('reports/list', [ReportController::class, 'index'])->name('reports');
    Route::post('reports/organization-documents', [ReportController::class, 'storeOrganizationDocument'])
        ->name('reports.organization-documents.store');
    Route::get('reports/organization-documents/{organizationReport}/view', [ReportController::class, 'showOrganizationDocument'])
        ->name('reports.organization-documents.view');
    Route::get('reports/organization-documents/{organizationReport}/preview', [ReportController::class, 'previewOrganizationDocument'])
        ->name('reports.organization-documents.preview');
    Route::get('reports/organization-documents/{organizationReport}/download', [ReportController::class, 'downloadOrganizationDocument'])
        ->name('reports.organization-documents.download');
    Route::put('reports/organization-documents/{organizationReport}', [ReportController::class, 'updateOrganizationDocument'])
        ->name('reports.organization-documents.update');
    Route::delete('reports/organization-documents/{organizationReport}', [ReportController::class, 'destroyOrganizationDocument'])
        ->name('reports.organization-documents.destroy');
    Route::get('reports/users', [ReportController::class, 'usersReport'])->name('reports.users');
    Route::get('/reports/users/download/{type}', [ReportController::class, 'downloadUsers'])->name('reports.users.download');
    Route::get('/reports/routes/download', [ReportController::class, 'downloadRouteReport'])->name('reports.route.download');
});

Route::middleware(['auth', 'permission:view notification settings'])->group(function () {
    Route::get('/settings/notifications', [NotificationController::class, 'index'])->name('notification.index');
});

Route::middleware(['auth', 'permission:manage notifications'])->group(function () {
    Route::put('/settings/notifications/weekly-reminder', [NotificationController::class, 'updateWeeklyReminder'])
        ->name('notification.weekly-reminder.update');
    Route::post('/settings/notifications/weekly-reminder/run-now', [NotificationController::class, 'runWeeklyReminderNow'])
        ->name('notification.weekly-reminder.run-now');
});

Route::middleware(['auth', 'permission:manage anonymous feedback'])->group(function () {
    Route::get('/settings/anonymous-feedback', [ManageAnonymousFeedbackController::class, 'index'])->name('anonymous-feedback.index');
    Route::get('/settings/anonymous-feedback/qr.png', [ManageAnonymousFeedbackController::class, 'downloadQr'])->name('anonymous-feedback.qr-download');
    Route::patch('/settings/anonymous-feedback/{anonymous_feedback}', [ManageAnonymousFeedbackController::class, 'update'])->name('anonymous-feedback.update');
});

Route::middleware(['auth', 'permission:view activity log'])->group(function () {
    Route::get('/settings/activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');
    Route::get('/settings/activity-log/{activity}', [ActivityLogController::class, 'show'])->name('activity-log.show');
});

Route::middleware(['auth', 'permission:users management settings'])->group(function () {
    Route::resource('/settings/roles', RoleController::class)->names([
        'index' => 'roles.index',
        'create' => 'roles.create',
        'store' => 'roles.store',
        'edit' => 'roles.edit',
        'update' => 'roles.update',
        'destroy' => 'roles.destroy',
    ])->except(['show']);
});

// Work Periods Management
Route::middleware(['auth', 'permission:manage work periods'])->group(function () {
    Route::resource('work-periods', WorkPeriodController::class)->names([
        'index' => 'work-periods.index',
        'create' => 'work-periods.create',
        'store' => 'work-periods.store',
        'show' => 'work-periods.show',
        'edit' => 'work-periods.edit',
        'update' => 'work-periods.update',
        'destroy' => 'work-periods.destroy',
    ]);

    // Additional routes for work periods
    Route::post('/work-periods/{work_period}/close', [WorkPeriodController::class, 'close'])->name('work-periods.close');
    Route::post('/work-periods/{work_period}/archive', [WorkPeriodController::class, 'archive'])->name('work-periods.archive');
});

// Bonus Petty Cash Routes - View Permission

require __DIR__.'/auth.php';
