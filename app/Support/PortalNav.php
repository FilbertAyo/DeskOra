<?php

namespace App\Support;

use App\Models\Document;
use App\Models\InventoryBorrowRequest;
use App\Models\InventoryDispatch;
use App\Models\InventoryDisposalRequest;
use App\Models\InventoryMaintenanceRecord;
use App\Models\InventoryVerificationSession;
use App\Models\OfficialLetter;
use App\Models\User;
use App\Models\WeeklyPlan;
use App\Models\WeeklyReport;
use App\Models\WorkPeriod;
use App\Services\RequestApprovalNavService;
use Illuminate\Support\Facades\Schema;

class PortalNav
{
    /**
     * @return array{groups: array<int, array{label: ?string, items: array<int, array<string, mixed>>}>}
     */
    public static function forUser(User $user): array
    {
        $canReviewSubmissions = $user->can('approve documents');
        $canWeeklyBudget = $user->can('view weekly budgets');
        $submittedCount = 0;
        $pendingBudgetCount = 0;

        if ($canReviewSubmissions || $canWeeklyBudget) {
            $currentPeriod = WorkPeriod::getCurrent();
            $nextPeriod = $currentPeriod?->getNextPeriod();

            if ($canReviewSubmissions && Schema::hasTable('weekly_reports') && $currentPeriod) {
                $submittedCount += WeeklyReport::query()
                    ->where('state', 'submitted')
                    ->where('period_id', (string) $currentPeriod->id)
                    ->count();
            }

            if ($canReviewSubmissions && Schema::hasTable('weekly_plans') && $nextPeriod) {
                $submittedCount += WeeklyPlan::query()
                    ->where('state', WeeklyPlan::STATE_SUBMITTED)
                    ->where('period_id', (string) $nextPeriod->id)
                    ->count();
            }

            if ($canWeeklyBudget) {
                $pendingBudgetCount = Document::countPendingWeeklyBudgetReviewForPeriod(null);
            }
        }

        $groups = [];

        $home = [
            self::link('Dashboard', 'dashboard', [], 'LayoutDashboard', request()->routeIs('dashboard')),
        ];

        $canSeeManagement = $canReviewSubmissions
            || $user->can('manage anonymous feedback')
            || $user->can('view appreciation nominations')
            || $user->can('manage work periods');

        if ($canSeeManagement) {
            $management = [
                self::link('Executive Pulse', 'executive-pulse.index', [], null, request()->routeIs('executive-pulse.*')),
            ];

            if ($canReviewSubmissions) {
                $management[] = self::link(
                    'Submissions',
                    'documents.reviewer-dashboard',
                    [],
                    null,
                    request()->routeIs('documents.reviewer-dashboard', 'documents.reviewer-show'),
                    $submittedCount
                );
            }

            if ($user->can('manage anonymous feedback')) {
                $management[] = self::link('Anonymous Feedback', 'anonymous-feedback.index', [], null, request()->routeIs('anonymous-feedback.*'));
            }

            if ($user->can('view appreciation nominations')) {
                $management[] = self::link('Staff Appreciation', 'management.appreciation.index', [], null, request()->routeIs('management.appreciation.*'));
            }

            if ($user->can('manage work periods')) {
                $management[] = self::link('Work Periods', 'work-periods.index', [], null, request()->routeIs('work-periods.*'));
            }

            $home[] = self::group(
                'Management',
                'Settings',
                $management,
                request()->routeIs('management.*', 'anonymous-feedback.*', 'work-periods.*', 'documents.reviewer-dashboard', 'documents.reviewer-show', 'executive-pulse.*')
            );
        }

        $home[] = self::link('Year Plan', 'year-plan.index', [], 'TrendingUp', request()->routeIs('year-plan.*'));

        $groups[] = ['label' => null, 'items' => $home];

        $activities = [
            self::link('Weekly Matrix', 'weekly-matrix.index', [], null, request()->routeIs('weekly-matrix.*')),
            self::link('Weekly Plans', 'documents.weekly-plans', [], null, request()->routeIs('documents.weekly-plans')),
            self::link('Weekly Reports', 'documents.weekly-reports', [], null, request()->routeIs('documents.weekly-reports')),
        ];

        if ($canWeeklyBudget) {
            $activities[] = self::link(
                'Weekly Budget',
                'documents.weekly-budget-review',
                $pendingBudgetCount > 0 ? ['status' => 'pending'] : [],
                null,
                request()->routeIs('documents.weekly-budget-review'),
                $pendingBudgetCount
            );
        }

        $activities[] = self::link('Action Log', 'action-logs.index', [], null, request()->routeIs('action-logs.index', 'action-logs.show', 'action-logs.edit', 'action-logs.create'));
        $activities[] = self::link('Action Board', 'action-logs.board', [], null, request()->routeIs('action-logs.board'));
        $activities[] = self::link('Minutes', 'meeting-minutes.index', [], null, request()->routeIs('meeting-minutes.*'));
        $activities[] = self::link('Monthly Reports', 'documents.monthly-report', [], null, request()->routeIs('documents.monthly-report'));

        $letterBadge = 0;
        if ($user->can(OfficialLetter::PERMISSION_AUTHORIZE_DOWNLOADS)) {
            $letterBadge = OfficialLetter::pendingDownloadAuthorizationCount();
        }

        $groups[] = [
            'label' => 'Activities',
            'items' => [
                self::group(
                    'Activities',
                    'CalendarDays',
                    $activities,
                    request()->routeIs('documents.weekly-plans', 'documents.weekly-reports', 'documents.monthly-report', 'documents.weekly-budget-review', 'action-logs.*', 'meeting-minutes.*', 'weekly-matrix.*')
                ),
                self::link('Official Letters', 'official-letters.index', [], 'Mail', request()->routeIs('official-letters.*'), $letterBadge),
                self::link('All Documents', 'documents.index', [], 'Layers', request()->routeIs('documents.index')),
            ],
        ];

        if ($user->can('view inventory') || $user->can('view own inventory items')) {
            $groups[1]['items'][] = self::inventory($user);
        }

        $finance = [
            self::group('Requests', 'FileText', [
                self::link('Staff Requisitions', 'requisition.index', [], null, request()->routeIs('requisition.index', 'requisition.create', 'requisition.show', 'requisition.edit')),
                self::link('Petty Cash', 'petty.index', [], null, request()->routeIs('petty.index', 'petty.create', 'petty.show')),
            ], request()->routeIs('requisition.index', 'requisition.create', 'requisition.show', 'requisition.edit', 'petty.index', 'petty.create', 'petty.show')),
        ];

        $approvalService = app(RequestApprovalNavService::class);
        $canApprovals = $user->canany([
            'review pettycash',
            'approve pettycash',
            'authorize pettycash',
            'review staff requisition',
            'approve staff requisition',
            'authorize staff requisition',
        ]);

        if ($canApprovals) {
            $approvalItems = [];
            $stages = [
                ['review', 'petty', 'review pettycash', 'Review Petty Cash', ['Petty Cash', 'Reimbursement']],
                ['review', 'requisition', 'review staff requisition', 'Review Requisitions', ['Staff Requisition']],
                ['approve', 'petty', 'approve pettycash', 'Approve Petty Cash', ['Petty Cash', 'Reimbursement']],
                ['approve', 'requisition', 'approve staff requisition', 'Approve Requisitions', ['Staff Requisition']],
                ['authorize', 'petty', 'authorize pettycash', 'Authorize Petty Cash', ['Petty Cash', 'Reimbursement']],
                ['authorize', 'requisition', 'authorize staff requisition', 'Authorize Requisitions', ['Staff Requisition']],
            ];

            foreach ($stages as [$stage, $type, $permission, $label, $types]) {
                if (! $user->can($permission)) {
                    continue;
                }

                $count = Schema::hasTable('requests')
                    ? $approvalService->countForStageAndTypes($user, $stage, $types)
                    : 0;

                $approvalItems[] = self::link(
                    $label,
                    'petty.list',
                    ['stage' => $stage, 'type' => $type],
                    null,
                    request()->routeIs('petty.list') && request('stage') === $stage && request('type') === $type,
                    $count
                );
            }

            $total = Schema::hasTable('requests')
                ? (int) ($approvalService->countsFor($user)['total'] ?? 0)
                : 0;

            $finance[] = self::group('Approvals', 'Users', $approvalItems, request()->routeIs('petty.list'), $total);
        }

        $canFinance = $user->canany([
            'view cashflow movements',
            'review staff requisition',
            'approve staff requisition',
            'authorize staff requisition',
            'download staff requisition',
        ]);

        if ($canFinance) {
            $financeItems = [];

            if ($user->can('view cashflow movements')) {
                $approvedCount = 0;
                if (Schema::hasTable('requests')) {
                    $approvedCount = (int) \App\Models\Request::query()
                        ->whereIn('department_id', $user->departmentScopeIds())
                        ->whereIn('request_type', ['Petty Cash', 'Reimbursement'])
                        ->where('status', 'approved')
                        ->count();
                }

                $financeItems[] = self::link('Petty Cash Payments', 'petty.cashier', [], null, request()->routeIs('petty.cashier'), $approvedCount);
                $financeItems[] = self::link('Cash Management', 'deposit.index', [], null, request()->routeIs('deposit.*'));
                $financeItems[] = self::link('Petty Cash Flow', 'cashflow.index', [], null, request()->routeIs('cashflow.*'));
            }

            $financeItems[] = self::link(
                'Requisition Records',
                'requisition.records',
                [],
                null,
                request()->routeIs('requisition.records', 'requisition.records.download')
            );

            $finance[] = self::group(
                'Finance & Transactions',
                'CreditCard',
                $financeItems,
                request()->routeIs('petty.cashier', 'deposit.*', 'cashflow.*', 'requisition.records', 'requisition.records.download')
            );
        }

        $groups[] = ['label' => 'Finance & Accounting', 'items' => $finance];

        $reports = [
            self::link('Org Reports & Documents', 'reports', [], 'FolderKanban', request()->routeIs('reports')),
        ];

        $settings = [];
        if ($user->can('users management settings')) {
            $settings[] = self::link('Users', 'admin.index', [], null, request()->routeIs('admin.index'));
        }
        if ($user->can('manage roles and permissions')) {
            $settings[] = self::link('Roles', 'roles.index', [], null, request()->routeIs('roles.*'));
        }
        if ($user->can('manage departments')) {
            $settings[] = self::link('Departments', 'departments', [], null, request()->routeIs('departments'));
        }
        if ($user->can('manage system settings')) {
            $settings[] = self::link('System', 'settings.system.edit', [], null, request()->routeIs('settings.system.*'));
        }
        if ($user->can('view activity log')) {
            $settings[] = self::link('Activity log', 'activity-log.index', [], null, request()->routeIs('activity-log.*'));
        }
        if ($user->can('view feature usage')) {
            $settings[] = self::link('Feature usage', 'feature-usage.index', [], null, request()->routeIs('feature-usage.*'));
        }

        if ($settings !== []) {
            $reports[] = self::group(
                'Settings',
                'Settings',
                $settings,
                request()->routeIs('admin.*', 'roles.*', 'departments', 'activity-log.*', 'feature-usage.*', 'settings.*')
            );
        }

        $groups[] = ['label' => 'Reports and Settings', 'items' => $reports];

        return ['groups' => $groups];
    }

    /**
     * @return array<string, mixed>
     */
    private static function inventory(User $user): array
    {
        $opsAttention = 0;
        if ($user->can('view inventory')) {
            $opsAttention = InventoryBorrowRequest::overdueCount()
                + InventoryBorrowRequest::pendingReviewCount()
                + InventoryMaintenanceRecord::openCount()
                + InventoryDisposalRequest::pendingReviewCount()
                + InventoryVerificationSession::openCount()
                + InventoryDispatch::overdueCount()
                + InventoryDispatch::openMovementCount();
        }

        $children = [];

        if ($user->can('view own inventory items')) {
            $children[] = self::link('My items', 'inventory.my-items.index', [], null, request()->routeIs('inventory.my-items.*'));
        }

        if ($user->can('view inventory')) {
            $children[] = self::link('Overview', 'inventory.index', [], null, request()->routeIs('inventory.index'));
            $children[] = self::link('Assets', 'inventory.assets.index', [], null, request()->routeIs('inventory.assets.*'));
            $children[] = self::link('Stock', 'inventory.stock-items.index', [], null, request()->routeIs('inventory.stock-items.*'));
            $children[] = self::link('Receipts', 'inventory.receipts.index', [], null, request()->routeIs('inventory.receipts.*'));
            $children[] = self::group('Operations', null, [
                self::link('Borrowing', 'inventory.borrowing.index', [], null, request()->routeIs('inventory.borrowing.*')),
                self::link('Dispatch', 'inventory.dispatches.index', [], null, request()->routeIs('inventory.dispatches.*')),
                self::link('Maintenance', 'inventory.maintenance.index', [], null, request()->routeIs('inventory.maintenance.*')),
                self::link('Disposal', 'inventory.disposal.index', [], null, request()->routeIs('inventory.disposal.*')),
                self::link('Verification', 'inventory.verification.index', [], null, request()->routeIs('inventory.verification.*')),
            ], request()->routeIs('inventory.borrowing.*', 'inventory.dispatches.*', 'inventory.maintenance.*', 'inventory.disposal.*', 'inventory.verification.*'), $opsAttention);
            $children[] = self::link('Reports', 'inventory.reports.index', [], null, request()->routeIs('inventory.reports.*'));

            if ($user->can('manage inventory settings')) {
                $children[] = self::group('Setup', null, [
                    self::link('Categories', 'inventory.categories.index', [], null, request()->routeIs('inventory.categories.*')),
                    self::link('Locations', 'inventory.locations.index', [], null, request()->routeIs('inventory.locations.*')),
                    self::link('Suppliers', 'inventory.suppliers.index', [], null, request()->routeIs('inventory.suppliers.*')),
                    self::link('Donors', 'inventory.donors.index', [], null, request()->routeIs('inventory.donors.*')),
                    self::link('Projects', 'inventory.projects.index', [], null, request()->routeIs('inventory.projects.*')),
                ], request()->routeIs('inventory.categories.*', 'inventory.locations.*', 'inventory.suppliers.*', 'inventory.donors.*', 'inventory.projects.*'));
            }
        }

        return self::group('Inventory', 'Package', $children, request()->routeIs('inventory.*'), $user->can('view inventory') ? $opsAttention : 0);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private static function link(string $label, string $routeName, array $params, ?string $icon, bool $active, int $badge = 0): array
    {
        return [
            'label' => $label,
            'href' => route($routeName, $params),
            'icon' => $icon,
            'active' => $active,
            'badge' => $badge > 0 ? ($badge > 99 ? '99+' : (string) $badge) : null,
            'children' => null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private static function group(string $label, ?string $icon, array $children, bool $active, int $badge = 0): array
    {
        return [
            'label' => $label,
            'href' => null,
            'icon' => $icon,
            'active' => $active,
            'badge' => $badge > 0 ? ($badge > 99 ? '99+' : (string) $badge) : null,
            'children' => $children,
        ];
    }
}
