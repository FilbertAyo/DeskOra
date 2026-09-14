<?php

use App\Http\Controllers\ActionLogController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AiAssistActionController;
use App\Http\Controllers\CommandPaletteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\DocumentAiSummaryController;
use App\Http\Controllers\DocumentBudgetReviewController;
use App\Http\Controllers\DocumentCrudController;
use App\Http\Controllers\DocumentDashboardController;
use App\Http\Controllers\DocumentWorkflowController;
use App\Http\Controllers\ExecutivePulseController;
use App\Http\Controllers\FeatureUsageController;
use App\Http\Controllers\FeatureUsageEventController;
use App\Http\Controllers\InventoryAssetController;
use App\Http\Controllers\InventoryBorrowRequestController;
use App\Http\Controllers\InventoryCategoryController;
use App\Http\Controllers\InventoryDashboardController;
use App\Http\Controllers\InventoryDisposalRequestController;
use App\Http\Controllers\InventoryDonorController;
use App\Http\Controllers\InventoryLocationController;
use App\Http\Controllers\InventoryMaintenanceController;
use App\Http\Controllers\InventoryMyItemsController;
use App\Http\Controllers\InventoryProjectController;
use App\Http\Controllers\InventoryReceiptController;
use App\Http\Controllers\InventoryReportController;
use App\Http\Controllers\InventoryStockItemController;
use App\Http\Controllers\InventorySupplierController;
use App\Http\Controllers\InventoryVerificationController;
use App\Http\Controllers\ManageAnonymousFeedbackController;
use App\Http\Controllers\ManageStaffAppreciationController;
use App\Http\Controllers\MeetingMinuteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OfficialLetterController;
use App\Http\Controllers\PendingApprovalGateController;
use App\Http\Controllers\PettyController;
use App\Http\Controllers\PortalAiAssistantController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicAnonymousFeedbackController;
use App\Http\Controllers\ReplenishmentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\StaffAppreciationNominationController;
use App\Http\Controllers\SystemSettingController;
use App\Http\Controllers\WeeklyMatrixController;
use App\Http\Controllers\WeeklyWorkLogController;
use App\Http\Controllers\WorkPeriodController;
use App\Http\Controllers\YearPlanController;
use Illuminate\Support\Facades\Route;

// Lightweight session liveness check for long-lived forms.
Route::get('/session/ping', function () {
    return auth()->check()
        ? response()->json(['ok' => true])
        : response()->json(['ok' => false], 401);
})->name('session.ping');

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

    Route::get('/approval-gate', [PendingApprovalGateController::class, 'snapshot'])
        ->name('approval-gate.snapshot');
    Route::post('/approval-gate/remind-later', [PendingApprovalGateController::class, 'remindLater'])
        ->name('approval-gate.remind-later');

    Route::post('/ai-assistant/ask', [PortalAiAssistantController::class, 'ask'])
        ->middleware('throttle:20,1')
        ->name('ai-assistant.ask');

    Route::get('/ai-assist/status', [AiAssistActionController::class, 'status'])
        ->middleware('throttle:60,1')
        ->name('ai-assist.status');
    Route::post('/ai-assist/draft-weekly-report', [AiAssistActionController::class, 'draftWeeklyReport'])
        ->middleware('throttle:20,1')
        ->name('ai-assist.draft-weekly-report');
    Route::post('/ai-assist/polish-performance-tips', [AiAssistActionController::class, 'polishPerformanceTips'])
        ->middleware('throttle:20,1')
        ->name('ai-assist.polish-performance-tips');
    Route::post('/ai-assist/suggest-rejection', [AiAssistActionController::class, 'suggestRejection'])
        ->middleware('throttle:20,1')
        ->name('ai-assist.suggest-rejection');
    Route::post('/ai-assist/scan-weekly-writing', [AiAssistActionController::class, 'scanWeeklyWriting'])
        ->middleware('throttle:20,1')
        ->name('ai-assist.scan-weekly-writing');
    Route::post('/ai-assist/evaluate-smart-target', [AiAssistActionController::class, 'evaluateSmartTarget'])
        ->middleware('throttle:20,1')
        ->name('ai-assist.evaluate-smart-target');

    Route::get('/commands', [CommandPaletteController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('commands.index');

    Route::post('/analytics/events', [FeatureUsageEventController::class, 'store'])
        ->middleware('throttle:120,1')
        ->name('analytics.events.store');

    // Documents routes
    Route::prefix('documents')->name('documents.')->group(function () {
        Route::get('/', [DocumentCrudController::class, 'index'])->name('index');
        Route::get('/weekly-plans', [DocumentDashboardController::class, 'weeklyPlans'])->name('weekly-plans');
        Route::get('/weekly-reports', [DocumentDashboardController::class, 'weeklyReports'])->name('weekly-reports');
        Route::get('/monthly-report', [DocumentDashboardController::class, 'monthlyReports'])->name('monthly-report');
        Route::get('/weekly-budget-review', [DocumentBudgetReviewController::class, 'weeklyBudgetReviewPanel'])
            ->middleware('permission:view weekly budgets|approve documents|review weekly budgets|approve weekly budgets|authorize weekly budgets')
            ->name('weekly-budget-review');
        Route::post('/weekly-budget-review/add-midweek-budget', [DocumentBudgetReviewController::class, 'addMidWeekBudgetEntry'])
            ->middleware('permission:process weekly budgets')
            ->name('weekly-budget-review.add-midweek-budget');
        Route::get('/weekly-budget-review/export/pdf', [DocumentBudgetReviewController::class, 'exportWeeklyBudgetReviewPdf'])
            ->middleware('permission:view weekly budgets|approve documents|review weekly budgets|approve weekly budgets|authorize weekly budgets')
            ->name('weekly-budget-review.export.pdf');
        Route::get('/weekly-budget-review/export/excel', [DocumentBudgetReviewController::class, 'exportWeeklyBudgetReviewExcel'])
            ->middleware('permission:view weekly budgets|approve documents|review weekly budgets|approve weekly budgets|authorize weekly budgets')
            ->name('weekly-budget-review.export.excel');
        Route::get('/plan-target', [DocumentDashboardController::class, 'getPlanTarget'])->name('plan-target');
        Route::get('/create', [DocumentCrudController::class, 'create'])->name('create');
        Route::post('/', [DocumentCrudController::class, 'store'])->name('store');
        Route::post('/ai-summary', [DocumentAiSummaryController::class, 'summarize'])
            ->middleware('throttle:6,1')
            ->name('ai-summary');
        Route::post('/ai-summary/ask', [DocumentAiSummaryController::class, 'ask'])
            ->middleware('throttle:10,1')
            ->name('ai-summary.ask');
        Route::post('/{document}/weekly-budget-review', [DocumentBudgetReviewController::class, 'processWeeklyBudgetReview'])
            ->middleware('permission:review weekly budgets|approve weekly budgets|authorize weekly budgets')
            ->name('process-weekly-budget-review');

        // Reviewer routes (must be registered before /{document} so "reviewer" is not captured as a document id)
        Route::get('/reviewer/dashboard', [DocumentDashboardController::class, 'reviewerDashboard'])->name('reviewer-dashboard');
        Route::get('/reviewer/{document}', [DocumentWorkflowController::class, 'reviewerShow'])->name('reviewer-show');

        Route::get('/{document}/weekly-report/action-tasks', [DocumentWorkflowController::class, 'weeklyReportActionTasks'])
            ->name('weekly-report.action-tasks');
        Route::get('/{document}/weekly-report/review', [DocumentWorkflowController::class, 'weeklyReportReview'])
            ->name('weekly-report.review');

        Route::get('/{document}/download', [DocumentCrudController::class, 'downloadPdf'])->name('download');
        Route::get('/{document}', [DocumentCrudController::class, 'show'])->name('show');
        Route::get('/{document}/edit', [DocumentCrudController::class, 'edit'])->name('edit');
        Route::put('/{document}', [DocumentCrudController::class, 'update'])->name('update');
        Route::delete('/{document}', [DocumentCrudController::class, 'destroy'])->name('destroy');
        Route::post('/{document}/submit', [DocumentWorkflowController::class, 'submit'])->name('submit');
        Route::post('/{document}/reject', [DocumentWorkflowController::class, 'reject'])->name('reject');
    });

    Route::prefix('weekly-work-logs')->name('weekly-work-logs.')->group(function () {
        Route::get('/', [WeeklyWorkLogController::class, 'index'])->name('index');
        Route::post('/', [WeeklyWorkLogController::class, 'store'])->name('store');
        Route::put('/{weeklyWorkLog}', [WeeklyWorkLogController::class, 'update'])->name('update');
        Route::post('/{weeklyWorkLog}/create-action', [WeeklyWorkLogController::class, 'createAction'])
            ->name('create-action');
    });

    // Action Logs routes (restricted to minutes_preparer and admin - authorization handled in controller)
    Route::prefix('action-logs')->name('action-logs.')->group(function () {
        Route::get('/', [ActionLogController::class, 'index'])->name('index');
        Route::get('/board', [ActionLogController::class, 'board'])->name('board');
        Route::get('/create', [ActionLogController::class, 'create'])->name('create');
        Route::post('/', [ActionLogController::class, 'store'])->name('store');
        Route::get('/{document}/download', [ActionLogController::class, 'downloadPdf'])->name('download');
        Route::put('/{document}/quick-update', [ActionLogController::class, 'quickUpdate'])->name('quick-update');
        Route::patch('/{document}/board-move', [ActionLogController::class, 'boardMove'])
            ->middleware('throttle:60,1')
            ->name('board-move');
        Route::get('/{document}', [ActionLogController::class, 'show'])->name('show');
        Route::get('/{document}/edit', [ActionLogController::class, 'edit'])->name('edit');
        Route::put('/{document}', [ActionLogController::class, 'update'])->name('update');
        Route::delete('/{document}', [ActionLogController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('meeting-minutes')->name('meeting-minutes.')->group(function () {
        Route::get('/', [MeetingMinuteController::class, 'index'])->name('index');
        Route::get('/create', [MeetingMinuteController::class, 'create'])
            ->middleware('permission:manage minutes')
            ->name('create');
        Route::post('/', [MeetingMinuteController::class, 'store'])
            ->middleware('permission:manage minutes')
            ->name('store');
        Route::get('/{meetingMinute}/view', [MeetingMinuteController::class, 'view'])->name('view');
        Route::get('/{meetingMinute}/download', [MeetingMinuteController::class, 'download'])->name('download');
        Route::get('/{meetingMinute}/edit', [MeetingMinuteController::class, 'edit'])
            ->middleware('permission:manage minutes')
            ->name('edit');
        Route::put('/{meetingMinute}', [MeetingMinuteController::class, 'update'])
            ->middleware('permission:manage minutes')
            ->name('update');
        Route::get('/{meetingMinute}', [MeetingMinuteController::class, 'show'])->name('show');
    });

    Route::prefix('official-letters')->name('official-letters.')->group(function () {
        Route::get('/', [OfficialLetterController::class, 'index'])->name('index');
        Route::get('/create', [OfficialLetterController::class, 'create'])
            ->middleware('permission:manage official letters')
            ->name('create');
        Route::post('/', [OfficialLetterController::class, 'store'])
            ->middleware('permission:manage official letters')
            ->name('store');
        Route::get('/{officialLetter}/download', [OfficialLetterController::class, 'download'])->name('download');
        Route::post('/{officialLetter}/request-download', [OfficialLetterController::class, 'requestDownload'])->name('request-download');
        Route::post('/{officialLetter}/authorize-download', [OfficialLetterController::class, 'authorizeDownload'])->name('authorize-download');
        Route::post('/{officialLetter}/reject-download', [OfficialLetterController::class, 'rejectDownload'])->name('reject-download');
        Route::get('/{officialLetter}/verify', [OfficialLetterController::class, 'verify'])->name('verify');
        Route::get('/{officialLetter}/edit', [OfficialLetterController::class, 'edit'])->name('edit');
        Route::put('/{officialLetter}', [OfficialLetterController::class, 'update'])->name('update');
        Route::post('/{officialLetter}/share', [OfficialLetterController::class, 'share'])->name('share');
        Route::delete('/{officialLetter}', [OfficialLetterController::class, 'destroy'])->name('destroy');
        Route::get('/{officialLetter}', [OfficialLetterController::class, 'show'])->name('show');
    });

    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::middleware('permission:view own inventory items')->group(function () {
            Route::get('/my-items', [InventoryMyItemsController::class, 'index'])->name('my-items.index');
            Route::get('/my-items/export/{type}/{format}', [InventoryMyItemsController::class, 'export'])
                ->whereIn('type', ['assets', 'stock'])
                ->whereIn('format', ['xlsx', 'pdf'])
                ->name('my-items.export');
        });
    });

    Route::prefix('inventory')->name('inventory.')->middleware('permission:view inventory')->group(function () {
        Route::get('/', [InventoryDashboardController::class, 'index'])->name('index');

        Route::middleware('permission:manage inventory settings')->group(function () {
            Route::get('/categories', [InventoryCategoryController::class, 'index'])->name('categories.index');
            Route::post('/categories', [InventoryCategoryController::class, 'store'])->name('categories.store');
            Route::put('/categories/{category}', [InventoryCategoryController::class, 'update'])->name('categories.update');
            Route::delete('/categories/{category}', [InventoryCategoryController::class, 'destroy'])->name('categories.destroy');

            Route::get('/locations', [InventoryLocationController::class, 'index'])->name('locations.index');
            Route::post('/locations', [InventoryLocationController::class, 'store'])->name('locations.store');
            Route::put('/locations/{location}', [InventoryLocationController::class, 'update'])->name('locations.update');
            Route::delete('/locations/{location}', [InventoryLocationController::class, 'destroy'])->name('locations.destroy');

            Route::get('/suppliers', [InventorySupplierController::class, 'index'])->name('suppliers.index');
            Route::post('/suppliers', [InventorySupplierController::class, 'store'])->name('suppliers.store');
            Route::put('/suppliers/{supplier}', [InventorySupplierController::class, 'update'])->name('suppliers.update');
            Route::delete('/suppliers/{supplier}', [InventorySupplierController::class, 'destroy'])->name('suppliers.destroy');

            Route::get('/donors', [InventoryDonorController::class, 'index'])->name('donors.index');
            Route::post('/donors', [InventoryDonorController::class, 'store'])->name('donors.store');
            Route::put('/donors/{donor}', [InventoryDonorController::class, 'update'])->name('donors.update');
            Route::delete('/donors/{donor}', [InventoryDonorController::class, 'destroy'])->name('donors.destroy');

            Route::get('/projects', [InventoryProjectController::class, 'index'])->name('projects.index');
            Route::post('/projects', [InventoryProjectController::class, 'store'])->name('projects.store');
            Route::put('/projects/{project}', [InventoryProjectController::class, 'update'])->name('projects.update');
            Route::delete('/projects/{project}', [InventoryProjectController::class, 'destroy'])->name('projects.destroy');
        });

        Route::middleware('permission:manage inventory assets|view inventory')->group(function () {
            Route::get('/assets', [InventoryAssetController::class, 'index'])->name('assets.index');
            Route::get('/assets/labels', [InventoryAssetController::class, 'labels'])->name('assets.labels');
            Route::get('/assets/export/{format}', [InventoryAssetController::class, 'export'])
                ->whereIn('format', ['xlsx', 'pdf'])
                ->name('assets.export');
        });

        Route::middleware('permission:manage inventory assets')->group(function () {
            Route::get('/assets/create', [InventoryAssetController::class, 'create'])->name('assets.create');
            Route::post('/assets', [InventoryAssetController::class, 'store'])->name('assets.store');
            Route::get('/assets/{asset}/edit', [InventoryAssetController::class, 'edit'])->name('assets.edit');
            Route::put('/assets/{asset}', [InventoryAssetController::class, 'update'])->name('assets.update');
            Route::delete('/assets/{asset}', [InventoryAssetController::class, 'destroy'])->name('assets.destroy');
            Route::get('/assets/{asset}/assign', [InventoryAssetController::class, 'assignForm'])->name('assets.assign.form');
            Route::post('/assets/{asset}/assign', [InventoryAssetController::class, 'assign'])->name('assets.assign');
        });

        Route::middleware('permission:manage inventory transfers')->group(function () {
            Route::get('/assets/{asset}/transfer', [InventoryAssetController::class, 'transferForm'])->name('assets.transfer.form');
            Route::post('/assets/{asset}/transfer', [InventoryAssetController::class, 'transfer'])->name('assets.transfer');
        });

        Route::middleware('permission:manage inventory stock|view inventory')->group(function () {
            Route::get('/stock-items', [InventoryStockItemController::class, 'index'])->name('stock-items.index');
            Route::get('/stock-items/export/{format}', [InventoryStockItemController::class, 'export'])
                ->whereIn('format', ['xlsx', 'pdf'])
                ->name('stock-items.export');
        });

        Route::middleware('permission:manage inventory stock')->group(function () {
            Route::get('/stock-items/create', [InventoryStockItemController::class, 'create'])->name('stock-items.create');
            Route::post('/stock-items', [InventoryStockItemController::class, 'store'])->name('stock-items.store');
            Route::get('/stock-items/{stockItem}/edit', [InventoryStockItemController::class, 'edit'])->name('stock-items.edit');
            Route::put('/stock-items/{stockItem}', [InventoryStockItemController::class, 'update'])->name('stock-items.update');
            Route::post('/stock-items/{stockItem}/issue', [InventoryStockItemController::class, 'issue'])->name('stock-items.issue');
            Route::post('/stock-items/{stockItem}/adjust', [InventoryStockItemController::class, 'adjust'])->name('stock-items.adjust');
        });

        Route::middleware('permission:manage inventory receipts')->group(function () {
            Route::get('/receipts/create', [InventoryReceiptController::class, 'create'])->name('receipts.create');
            Route::post('/receipts', [InventoryReceiptController::class, 'store'])->name('receipts.store');
            Route::get('/receipts/{receipt}/add-items', [InventoryReceiptController::class, 'addItems'])->name('receipts.add-items');
            Route::post('/receipts/{receipt}/add-items', [InventoryReceiptController::class, 'storeItems'])->name('receipts.store-items');
        });

        Route::middleware('permission:manage inventory receipts|view inventory')->group(function () {
            Route::get('/receipts', [InventoryReceiptController::class, 'index'])->name('receipts.index');
            Route::get('/receipts/{receipt}', [InventoryReceiptController::class, 'show'])->name('receipts.show');
        });

        Route::middleware('permission:manage inventory borrowing')->group(function () {
            Route::get('/borrowing/create', [InventoryBorrowRequestController::class, 'create'])->name('borrowing.create');
            Route::post('/borrowing', [InventoryBorrowRequestController::class, 'store'])->name('borrowing.store');
            Route::post('/borrowing/{borrowRequest}/issue', [InventoryBorrowRequestController::class, 'issue'])->name('borrowing.issue');
            Route::post('/borrowing/{borrowRequest}/return', [InventoryBorrowRequestController::class, 'returnItem'])->name('borrowing.return');
        });

        Route::middleware('permission:approve inventory requests')->group(function () {
            Route::post('/borrowing/{borrowRequest}/approve', [InventoryBorrowRequestController::class, 'approve'])->name('borrowing.approve');
            Route::post('/borrowing/{borrowRequest}/reject', [InventoryBorrowRequestController::class, 'reject'])->name('borrowing.reject');
        });

        Route::middleware('permission:manage inventory borrowing|view inventory|approve inventory requests')->group(function () {
            Route::get('/borrowing', [InventoryBorrowRequestController::class, 'index'])->name('borrowing.index');
            Route::get('/borrowing/{borrowRequest}', [InventoryBorrowRequestController::class, 'show'])->name('borrowing.show');
        });

        Route::middleware('permission:manage inventory maintenance')->group(function () {
            Route::get('/maintenance/create', [InventoryMaintenanceController::class, 'create'])->name('maintenance.create');
            Route::post('/maintenance', [InventoryMaintenanceController::class, 'store'])->name('maintenance.store');
            Route::get('/maintenance/{maintenance}/complete', [InventoryMaintenanceController::class, 'completeForm'])->name('maintenance.complete.form');
            Route::post('/maintenance/{maintenance}/complete', [InventoryMaintenanceController::class, 'complete'])->name('maintenance.complete');
        });

        Route::middleware('permission:manage inventory maintenance|view inventory')->group(function () {
            Route::get('/maintenance', [InventoryMaintenanceController::class, 'index'])->name('maintenance.index');
            Route::get('/maintenance/{maintenance}', [InventoryMaintenanceController::class, 'show'])->name('maintenance.show');
        });

        Route::middleware('permission:manage inventory assets')->group(function () {
            Route::get('/disposal/create', [InventoryDisposalRequestController::class, 'create'])->name('disposal.create');
            Route::post('/disposal', [InventoryDisposalRequestController::class, 'store'])->name('disposal.store');
        });

        Route::middleware('permission:approve inventory requests')->group(function () {
            Route::post('/disposal/{disposal}/approve', [InventoryDisposalRequestController::class, 'approve'])->name('disposal.approve');
            Route::post('/disposal/{disposal}/reject', [InventoryDisposalRequestController::class, 'reject'])->name('disposal.reject');
        });

        Route::middleware('permission:manage inventory assets|view inventory|approve inventory requests')->group(function () {
            Route::get('/disposal', [InventoryDisposalRequestController::class, 'index'])->name('disposal.index');
            Route::get('/disposal/{disposal}', [InventoryDisposalRequestController::class, 'show'])->name('disposal.show');
        });

        Route::middleware('permission:manage inventory assets')->group(function () {
            Route::get('/verification/create', [InventoryVerificationController::class, 'create'])->name('verification.create');
            Route::post('/verification', [InventoryVerificationController::class, 'store'])->name('verification.store');
            Route::put('/verification/{verification}/results', [InventoryVerificationController::class, 'updateResults'])->name('verification.results');
            Route::post('/verification/{verification}/complete', [InventoryVerificationController::class, 'complete'])->name('verification.complete');
        });

        Route::middleware('permission:manage inventory assets|view inventory')->group(function () {
            Route::get('/verification', [InventoryVerificationController::class, 'index'])->name('verification.index');
            Route::get('/verification/{verification}', [InventoryVerificationController::class, 'show'])->name('verification.show');
        });

        Route::middleware('permission:view inventory')->group(function () {
            Route::get('/reports', [InventoryReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/export/assets', [InventoryReportController::class, 'exportAssets'])->name('reports.export.assets');
            Route::get('/reports/export/stock', [InventoryReportController::class, 'exportStock'])->name('reports.export.stock');
        });

        Route::middleware('permission:manage inventory assets')->group(function () {
            Route::post('/reports/notify-assignments', [InventoryReportController::class, 'notifyAssignments'])->name('reports.notify-assignments');
        });
    });

    Route::prefix('inventory')->name('inventory.')->middleware('permission:view inventory|view own inventory items')->group(function () {
        Route::get('/q/{assetCode}', [InventoryAssetController::class, 'scan'])->name('scan');
        Route::get('/assets/{asset}', [InventoryAssetController::class, 'show'])->name('assets.show');
    });

    Route::middleware('permission:manage year plans')->prefix('year-plans')->name('year-plan.')->group(function () {
        Route::get('/', [YearPlanController::class, 'index'])->name('index');
        Route::post('/', [YearPlanController::class, 'store'])->name('store');
        Route::get('/{year}', [YearPlanController::class, 'show'])->name('show');
        Route::get('/{year}/download', [YearPlanController::class, 'download'])->name('download');
        Route::get('/{year}/departments/{department}/download', [YearPlanController::class, 'downloadDepartment'])->name('department.download');
        Route::post('/{year}/activities', [YearPlanController::class, 'storeActivity'])->name('activities.store');
        Route::put('/{year}/activities/{activity}', [YearPlanController::class, 'updateActivity'])->name('activities.update');
        Route::delete('/{year}/activities/{activity}', [YearPlanController::class, 'destroyActivity'])->name('activities.destroy');
    });

    Route::prefix('weekly-matrix')->name('weekly-matrix.')->group(function () {
        Route::get('/', [WeeklyMatrixController::class, 'index'])->name('index');
        Route::get('/periods/{period}', [WeeklyMatrixController::class, 'period'])->name('period');
        Route::get('/periods/{period}/table', [WeeklyMatrixController::class, 'periodTable'])->name('period.table');
        Route::get('/periods/{period}/download', [WeeklyMatrixController::class, 'download'])->name('download');
        Route::get('/periods/{period}/create', [WeeklyMatrixController::class, 'create'])->name('create');
        Route::post('/periods/{period}/create', [WeeklyMatrixController::class, 'storeFromCreate'])->name('create.store');
        Route::get('/periods/{period}/departments/{department}', [WeeklyMatrixController::class, 'edit'])->name('edit');
        Route::post('/periods/{period}/departments/{department}', [WeeklyMatrixController::class, 'store'])->name('store');
        Route::put('/periods/{period}/departments/{department}', [WeeklyMatrixController::class, 'update'])->name('update');
        Route::delete('/periods/{period}/departments/{department}', [WeeklyMatrixController::class, 'destroy'])->name('destroy');
        Route::get('/periods/{period}/departments/{department}/suggestions', [WeeklyMatrixController::class, 'suggestions'])->name('suggestions');
    });

    // departments
    Route::middleware('permission:manage departments')->group(function () {
        Route::get('/departments', [DepartmentController::class, 'index'])->name('departments');          // list
        Route::post('/department', [DepartmentController::class, 'store'])->name('department.store');    // create
        Route::put('/department/{department}', [DepartmentController::class, 'update'])->name('department.update'); // update
        Route::delete('/department/{department}', [DepartmentController::class, 'destroy'])->name('department.destroy'); // delete
        Route::get('/department/{department}', [DepartmentController::class, 'show'])->name('department.show');       // show
    });

    // admin — restricted to users management settings
    Route::middleware('permission:users management settings')->group(function () {
        Route::patch('/admin/{user}/notification-channel', [AdminController::class, 'updateNotificationChannel'])
            ->name('admin.notification-channel.update');
        Route::post('/admin/{admin}/signature', [AdminController::class, 'updateSignature'])
            ->name('admin.signature.update');
        Route::resource('admin', AdminController::class);
        Route::post('/activate/{user}', [AdminController::class, 'activate'])->name('admin.activate');
    });

    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/notifications', [NotificationController::class, 'inbox'])->name('notifications.inbox');
    Route::get('/notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');

    // Staff Appreciation Nomination (staff-facing, anonymous)
    Route::prefix('staff-appreciation')->name('appreciation.')->group(function () {
        Route::get('/staff-search', [StaffAppreciationNominationController::class, 'staffSearch'])
            ->middleware('throttle:30,1')
            ->name('staff-search');
        Route::post('/nominate', [StaffAppreciationNominationController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('nominate');
        Route::post('/skip', [StaffAppreciationNominationController::class, 'skip'])->name('skip');
        Route::post('/remind-later', [StaffAppreciationNominationController::class, 'remindLater'])->name('remind-later');
    });

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
    Route::delete('/requisition/{petty}', [PettyController::class, 'destroy'])->name('requisition.destroy');
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

Route::middleware(['auth', 'permission:review pettycash|review staff requisition'])->group(function () {
    Route::post('f_approve/{petty}', [PettyController::class, 'f_approve'])->name('f_approve.approve');
});

Route::middleware(['auth', 'permission:approve pettycash|approve staff requisition'])->group(function () {
    Route::post('l_approve/{petty}', [PettyController::class, 'l_approve'])->name('l_approve.approve');
});

Route::middleware(['auth', 'permission:authorize pettycash|authorize staff requisition'])->group(function () {
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

Route::middleware(['auth', 'permission:approve pettycash'])->group(function () {
    Route::get('pettycash/all/requests/list', [PettyController::class, 'all_requests'])->name('all.pettycash');
});

Route::middleware(['auth', 'permission:view cashflow movements'])->group(function () {
    Route::resource('deposit', DepositController::class);
    Route::get('/pettycash/flow', [DepositController::class, 'cashflow'])->name('cashflow.index');
    Route::get('/cashflow/download', [DepositController::class, 'download'])->name('cashflow.download');
    Route::get('pettycash/requests/payments/list', [PettyController::class, 'requestsCashier'])->name('petty.cashier');
});

Route::middleware(['auth', 'permission:view cashflow movements|review staff requisition|approve staff requisition|authorize staff requisition|download staff requisition'])->group(function () {
    Route::get('/requisitions/records', [DepositController::class, 'requisitionRecords'])->name('requisition.records');
    Route::get('/requisitions/records/download', [DepositController::class, 'downloadRequisitionRecords'])->name('requisition.records.download');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/executive-pulse', [ExecutivePulseController::class, 'index'])
        ->middleware('permission:view reports')
        ->name('executive-pulse.index');
    Route::get('/executive-pulse/pdf', [ExecutivePulseController::class, 'download'])
        ->middleware('permission:view reports')
        ->name('executive-pulse.download');
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
    Route::get('/reports/routes/download', [ReportController::class, 'downloadRouteReport'])->name('reports.route.download');
});

Route::middleware(['auth', 'permission:view notification settings'])->group(function () {
    Route::get('/settings/notifications', [NotificationController::class, 'index'])->name('notification.index');
});

Route::middleware(['auth', 'permission:manage notifications'])->group(function () {
    Route::put('/settings/notifications/grace-period', [NotificationController::class, 'updateGracePeriod'])
        ->name('notification.grace-period.update');
    Route::post('/settings/notifications/grace-period/run-now', [NotificationController::class, 'runGracePeriodNow'])
        ->name('notification.grace-period.run-now');
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

Route::middleware(['auth', 'permission:manage system settings'])->prefix('settings/system')->name('settings.system.')->group(function () {
    Route::get('/', [SystemSettingController::class, 'edit'])->name('edit');
    Route::put('/', [SystemSettingController::class, 'update'])->name('update');
});

Route::middleware(['auth', 'permission:view activity log'])->group(function () {
    Route::get('/settings/activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');
    Route::get('/settings/activity-log/{activity}', [ActivityLogController::class, 'show'])->name('activity-log.show');
});

Route::middleware(['auth', 'permission:view feature usage'])->group(function () {
    Route::get('/settings/feature-usage', [FeatureUsageController::class, 'index'])->name('feature-usage.index');
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
    Route::post('/work-periods/reopen-and-reset', [WorkPeriodController::class, 'reopenAndReset'])->name('work-periods.reopen-and-reset');
});

// Staff Appreciation Nomination (management view)
Route::middleware(['auth', 'permission:view appreciation nominations'])->group(function () {
    Route::get('/management/staff-appreciation', [ManageStaffAppreciationController::class, 'index'])
        ->name('management.appreciation.index');
    Route::get('/management/staff-appreciation/{nomination_period}', [ManageStaffAppreciationController::class, 'show'])
        ->name('management.appreciation.show');
});

// Bonus Petty Cash Routes - View Permission

require __DIR__.'/auth.php';
