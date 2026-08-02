<nav class="pc-sidebar">
    <div class="navbar-wrapper">
        <div class="m-header">
            <a href="{{ route('dashboard') }}" class="b-brand text-primary">
                <img src="{{ asset('assets/images/logo.png') }}" class="img-fluid logo-lg p-2" alt="logo">
            </a>
        </div>

        <div class="navbar-content">
            <ul class="pc-navbar">
                <!-- Dashboard -->
                <li class="pc-item {{ Request::routeIs('dashboard') ? 'active' : '' }}">
                    <a href="{{ route('dashboard') }}" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-dashboard"></i></span>
                        <span class="pc-mtext">Dashboard</span>
                    </a>
                </li>

                @php
                    $canReviewSubmissions = Auth::user()->hasPermissionTo('approve documents');
                    $canWeeklyBudgetReview = Auth::user()->can('view weekly budgets');
                    $canSeeManagement = $canReviewSubmissions
                        || Auth::user()->can('manage anonymous feedback')
                        || Auth::user()->can('view appreciation nominations')
                        || Auth::user()->can('manage work periods')
                        || Auth::user()->can('view reports');

                    $reviewActivitiesSubmittedCount = 0;
                    $reviewActivitiesPendingBudgetCount = 0;
                    if ($canReviewSubmissions || $canWeeklyBudgetReview) {
                        $currentPeriod = \App\Models\WorkPeriod::getCurrent();
                        $nextPeriod = $currentPeriod?->getNextPeriod();

                        if ($canReviewSubmissions) {
                            if ($currentPeriod) {
                                $reviewActivitiesSubmittedCount += \App\Models\WeeklyReport::query()
                                    ->where('state', 'submitted')
                                    ->where('period_id', (string) $currentPeriod->id)
                                    ->count();
                            }

                            if ($nextPeriod) {
                                $reviewActivitiesSubmittedCount += \App\Models\WeeklyPlan::query()
                                    ->where('state', \App\Models\WeeklyPlan::STATE_SUBMITTED)
                                    ->where('period_id', (string) $nextPeriod->id)
                                    ->count();
                            }
                        }

                        if ($canWeeklyBudgetReview) {
                            $reviewActivitiesPendingBudgetCount = \App\Models\Document::countPendingWeeklyBudgetReviewForPeriod(
                                null
                            );
                        }
                    }
                @endphp

                @if($canSeeManagement)
                    <li class="pc-item pc-hasmenu {{ Request::routeIs('management.*', 'anonymous-feedback.*', 'work-periods.*', 'documents.reviewer-dashboard', 'documents.reviewer-show', 'executive-pulse.*') ? 'active pc-trigger' : '' }}">
                        <a href="javascript:void(0);" class="pc-link">
                            <span class="pc-micon"><i class="ti ti-settings"></i></span>
                            <span class="pc-mtext">Management</span>
                            <span class="pc-arrow"><i class="ti ti-chevron-down"></i></span>
                        </a>
                        <ul class="pc-submenu">

                            @can('view reports')
                                <li class="{{ Request::routeIs('executive-pulse.*') ? 'active' : '' }}">
                                    <a href="{{ route('executive-pulse.index') }}" class="pc-link">
                                        <span class="pc-mtext">Executive Pulse</span>
                                    </a>
                                </li>
                            @endcan

                            @if($canReviewSubmissions)
                                <li class="{{ Request::routeIs('documents.reviewer-dashboard', 'documents.reviewer-show') ? 'active' : '' }}">
                                    <a href="{{ route('documents.reviewer-dashboard') }}" class="pc-link">
                                        <span class="pc-mtext">Submissions</span>
                                        @if($reviewActivitiesSubmittedCount > 0)
                                            <span class="badge bg-light-primary border border-primary ms-2">{{ $reviewActivitiesSubmittedCount }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endif

                         
                            @can('manage anonymous feedback')
                                <li class="{{ Request::routeIs('anonymous-feedback.*') ? 'active' : '' }}">
                                    <a href="{{ route('anonymous-feedback.index') }}" class="pc-link">
                                        <span class="pc-mtext">Anonymous Feedback</span>
                                    </a>
                                </li>
                            @endcan

                            @can('view appreciation nominations')
                                <li class="{{ Request::routeIs('management.appreciation.*') ? 'active' : '' }}">
                                    <a href="{{ route('management.appreciation.index') }}" class="pc-link">
                                        <span class="pc-mtext">Staff Appreciation</span>
                                    </a>
                                </li>
                            @endcan

                            @can('manage work periods')
                                <li class="{{ Request::routeIs('work-periods.*') ? 'active' : '' }}">
                                    <a href="{{ route('work-periods.index') }}" class="pc-link">
                                        <span class="pc-mtext">Work Periods</span>
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endif

                    <li class="pc-item {{ Request::routeIs('year-plan.*') ? 'active' : '' }}">
                        <a href="{{ route('year-plan.index') }}" class="pc-link">
                            <span class="pc-micon"><i class="ti ti-chart-line"></i></span>
                            <span class="pc-mtext">Year Plan</span>
                        </a>
                    </li>

                    <li class="pc-item {{ Request::routeIs('weekly-matrix.*') ? 'active' : '' }}">
                        <a href="{{ route('weekly-matrix.index') }}" class="pc-link">
                            <span class="pc-micon"><i class="ti ti-layout-grid"></i></span>
                            <span class="pc-mtext">Weekly Matrix</span>
                        </a>
                    </li>

                <!-- Weekly Plans & Reports -->
                <li class="pc-item pc-caption">
                    <label>Activities</label>
                    <i class="ti ti-calendar"></i>
                </li>

                <li class="pc-item pc-hasmenu {{ Request::routeIs('documents.weekly-plans', 'documents.weekly-reports', 'documents.monthly-report', 'documents.weekly-budget-review', 'action-logs.*', 'meeting-minutes.*') ? 'active pc-trigger' : '' }}">
                    <a href="javascript:void(0);" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-calendar"></i></span>
                        <span class="pc-mtext">Activities</span>
                        <span class="pc-arrow"><i class="ti ti-chevron-down"></i></span>
                    </a>
                    <ul class="pc-submenu">
                        <li class="{{ Request::routeIs('documents.weekly-plans') ? 'active' : '' }}">
                            <a href="{{ route('documents.weekly-plans') }}" class="pc-link">
                                <span class="pc-mtext">Weekly Plans</span>
                            </a>
                        </li>
                        <li class="{{ Request::routeIs('documents.weekly-reports') ? 'active' : '' }}">
                            <a href="{{ route('documents.weekly-reports') }}" class="pc-link">
                                <span class="pc-mtext">Weekly Reports</span>
                            </a>
                        </li>
                        @if($canWeeklyBudgetReview)
                            <li class="{{ Request::routeIs('documents.weekly-budget-review') ? 'active' : '' }}">
                                <a href="{{ route('documents.weekly-budget-review') }}" class="pc-link">
                                    <span class="pc-mtext">Weekly Budget</span>
                                    @if($reviewActivitiesPendingBudgetCount > 0)
                                        <span class="badge bg-light-warning border border-warning ms-2">{{ $reviewActivitiesPendingBudgetCount }}</span>
                                    @endif
                                </a>
                            </li>
                        @endif
                        <li class="{{ Request::routeIs('action-logs.index', 'action-logs.show', 'action-logs.edit', 'action-logs.create') ? 'active' : '' }}">
                            <a href="{{ route('action-logs.index') }}" class="pc-link">
                                <span class="pc-mtext">Action Log</span>
                            </a>
                        </li>
                        <li class="{{ Request::routeIs('action-logs.board') ? 'active' : '' }}">
                            <a href="{{ route('action-logs.board') }}" class="pc-link">
                                <span class="pc-mtext">Action Board</span>
                            </a>
                        </li>
                        <li class="{{ Request::routeIs('meeting-minutes.*') ? 'active' : '' }}">
                            <a href="{{ route('meeting-minutes.index') }}" class="pc-link">
                                <span class="pc-mtext">Minutes</span>
                            </a>
                        </li>
                        <li class="{{ Request::routeIs('documents.monthly-report') ? 'active' : '' }}">
                            <a href="{{ route('documents.monthly-report') }}" class="pc-link">
                                <span class="pc-mtext">Monthly Reports </span>
                            </a>
                        </li>
                   
                    </ul>
                </li>

                @php
                    $isOfficialLettersNav = Request::routeIs('official-letters.*');
                    $isAllDocumentsNav = Request::routeIs('documents.index');
                @endphp
                <li class="pc-item {{ $isOfficialLettersNav ? 'active' : '' }}">
                    <a href="{{ route('official-letters.index') }}" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-mail"></i></span>
                        <span class="pc-mtext">Official Letters</span>
                    </a>
                </li>
                <li class="pc-item {{ $isAllDocumentsNav ? 'active' : '' }}">
                    <a href="{{ route('documents.index') }}" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-stack"></i></span>
                        <span class="pc-mtext">All Documents</span>
                    </a>
                </li>

                <!-- Finance & Accounting -->
                <li class="pc-item pc-caption">
                    <label>Finance & Accounting</label>
                    <i class="ti ti-file-text"></i>
                </li>

                @php
                    $isStaffRequisitionRequestRoute = Request::routeIs(
                        'requisition.index',
                        'requisition.create',
                        'requisition.show',
                        'requisition.edit'
                    );
                    $isPettyCashRequestRoute = Request::routeIs('petty.index', 'petty.create', 'petty.show');
                    $isRequestsMenuOpen = $isStaffRequisitionRequestRoute || $isPettyCashRequestRoute;
                    $isFinanceMenuOpen = Request::routeIs(
                        'petty.cashier',
                        'deposit.*',
                        'cashflow.*',
                        'requisition.records',
                        'requisition.records.download'
                    );
                    $isSettingsMenuOpen = Request::routeIs(
                        'admin.*',
                        'roles.*',
                        'departments',
                        'activity-log.*',
                        'settings.*',
                        'anonymous-feedback.*'
                    );
                @endphp

                <li class="pc-item pc-hasmenu {{ $isRequestsMenuOpen ? 'active pc-trigger' : '' }}">
                    <a href="javascript:void(0);" class="pc-link">
                        <span class="pc-micon"><i class="ti ti-file"></i></span>
                        <span class="pc-mtext">Requests</span>
                        <span class="pc-arrow"><i class="ti ti-chevron-down"></i></span>
                    </a>
                    <ul class="pc-submenu">
                        <li class="{{ $isStaffRequisitionRequestRoute ? 'active' : '' }}">
                            <a href="{{ route('requisition.index') }}" class="pc-link">
                                <span class="pc-mtext">Staff Requisitions</span>
                            </a>
                        </li>
                        <li class="{{ $isPettyCashRequestRoute ? 'active' : '' }}">
                            <a href="{{ route('petty.index') }}" class="pc-link">
                                <span class="pc-mtext">Petty Cash</span>
                            </a>
                        </li>
                    </ul>
                </li>
           

                @canany(['review pettycash', 'approve pettycash', 'authorize pettycash', 'review staff requisition', 'approve staff requisition', 'authorize staff requisition'])
                    <li class="pc-item pc-hasmenu {{ Request::routeIs('petty.list') ? 'active pc-trigger' : '' }}">
                        <a href="javascript:void(0);" class="pc-link">
                            <span class="pc-micon"><i class="ti ti-users"></i></span>
                            <span class="pc-mtext">Approvals</span>
                            @if (($approvalsTotalCount ?? 0) > 0)
                                <span class="badge bg-light-danger border border-danger">{{ $approvalsTotalCount > 99 ? '99+' : $approvalsTotalCount }}</span>
                            @endif
                            <span class="pc-arrow"><i class="ti ti-chevron-down"></i></span>
                        </a>
                        <ul class="pc-submenu">
                            @can('review pettycash')
                                @php $pettyReviewCount = app(\App\Services\RequestApprovalNavService::class)->countForStageAndTypes(auth()->user(), 'review', ['Petty Cash', 'Reimbursement']); @endphp
                                <li class="{{ Request::routeIs('petty.list') && request('stage') === 'review' && request('type') === 'petty' ? 'active' : '' }}">
                                    <a href="{{ route('petty.list', ['stage' => 'review', 'type' => 'petty']) }}" class="pc-link">
                                        <span class="pc-mtext">Review Petty Cash</span>
                                        @if ($pettyReviewCount > 0)
                                            <span class="badge bg-light-warning border border-warning ms-2">{{ $pettyReviewCount }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endcan
                            @can('review staff requisition')
                                @php $requisitionReviewCount = app(\App\Services\RequestApprovalNavService::class)->countForStageAndTypes(auth()->user(), 'review', ['Staff Requisition']); @endphp
                                <li class="{{ Request::routeIs('petty.list') && request('stage') === 'review' && request('type') === 'requisition' ? 'active' : '' }}">
                                    <a href="{{ route('petty.list', ['stage' => 'review', 'type' => 'requisition']) }}" class="pc-link">
                                        <span class="pc-mtext">Review Requisitions</span>
                                        @if ($requisitionReviewCount > 0)
                                            <span class="badge bg-light-warning border border-warning ms-2">{{ $requisitionReviewCount }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endcan
                            @can('approve pettycash')
                                @php $pettyApproveCount = app(\App\Services\RequestApprovalNavService::class)->countForStageAndTypes(auth()->user(), 'approve', ['Petty Cash', 'Reimbursement']); @endphp
                                <li class="{{ Request::routeIs('petty.list') && request('stage') === 'approve' && request('type') === 'petty' ? 'active' : '' }}">
                                    <a href="{{ route('petty.list', ['stage' => 'approve', 'type' => 'petty']) }}" class="pc-link">
                                        <span class="pc-mtext">Approve Petty Cash</span>
                                        @if ($pettyApproveCount > 0)
                                            <span class="badge bg-light-primary border border-primary ms-2">{{ $pettyApproveCount }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endcan
                            @can('approve staff requisition')
                                @php $requisitionApproveCount = app(\App\Services\RequestApprovalNavService::class)->countForStageAndTypes(auth()->user(), 'approve', ['Staff Requisition']); @endphp
                                <li class="{{ Request::routeIs('petty.list') && request('stage') === 'approve' && request('type') === 'requisition' ? 'active' : '' }}">
                                    <a href="{{ route('petty.list', ['stage' => 'approve', 'type' => 'requisition']) }}" class="pc-link">
                                        <span class="pc-mtext">Approve Requisitions</span>
                                        @if ($requisitionApproveCount > 0)
                                            <span class="badge bg-light-primary border border-primary ms-2">{{ $requisitionApproveCount }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endcan
                            @can('authorize pettycash')
                                @php $pettyAuthorizeCount = app(\App\Services\RequestApprovalNavService::class)->countForStageAndTypes(auth()->user(), 'authorize', ['Petty Cash', 'Reimbursement']); @endphp
                                <li class="{{ Request::routeIs('petty.list') && request('stage') === 'authorize' && request('type') === 'petty' ? 'active' : '' }}">
                                    <a href="{{ route('petty.list', ['stage' => 'authorize', 'type' => 'petty']) }}" class="pc-link">
                                        <span class="pc-mtext">Authorize Petty Cash</span>
                                        @if ($pettyAuthorizeCount > 0)
                                            <span class="badge bg-light-secondary border border-secondary ms-2">{{ $pettyAuthorizeCount }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endcan
                            @can('authorize staff requisition')
                                @php $requisitionAuthorizeCount = app(\App\Services\RequestApprovalNavService::class)->countForStageAndTypes(auth()->user(), 'authorize', ['Staff Requisition']); @endphp
                                <li class="{{ Request::routeIs('petty.list') && request('stage') === 'authorize' && request('type') === 'requisition' ? 'active' : '' }}">
                                    <a href="{{ route('petty.list', ['stage' => 'authorize', 'type' => 'requisition']) }}" class="pc-link">
                                        <span class="pc-mtext">Authorize Requisitions</span>
                                        @if ($requisitionAuthorizeCount > 0)
                                            <span class="badge bg-light-secondary border border-secondary ms-2">{{ $requisitionAuthorizeCount }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcanany

                <!-- Finance & Transactions -->
                @canany(['view cashflow movements', 'review staff requisition', 'approve staff requisition', 'authorize staff requisition', 'download staff requisition'])
                    <li class="pc-item pc-hasmenu {{ $isFinanceMenuOpen ? 'active pc-trigger' : '' }}">
                        <a href="javascript:void(0);" class="pc-link">
                            <span class="pc-micon"><i class="ti ti-credit-card"></i></span>
                            <span class="pc-mtext">Finance & Transactions</span>
                            <span class="pc-arrow"><i class="ti ti-chevron-down"></i></span>
                        </a>
                        <ul class="pc-submenu">
                            @can('view cashflow movements')
                                <li class="{{ Request::routeIs('petty.cashier') ? 'active' : '' }}">
                                    <a href="{{ route('petty.cashier') }}" class="pc-link">
                                        <span class="pc-mtext">Petty Cash Payments</span>
                                        @if ($approvedPettiesCount > 0)
                                            <span class="badge bg-light-success border border-success">{{ $approvedPettiesCount }}</span>
                                        @endif
                                    </a>
                                </li>
                                <li class="{{ Request::routeIs('deposit.*') ? 'active' : '' }}">
                                    <a href="{{ route('deposit.index') }}" class="pc-link">
                                        <span class="pc-mtext">Cash Management</span>
                                    </a>
                                </li>
                                <li class="{{ Request::routeIs('cashflow.*') ? 'active' : '' }}">
                                    <a href="{{ route('cashflow.index') }}" class="pc-link">
                                        <span class="pc-mtext">Petty Cash Flow</span>
                                    </a>
                                </li>
                            @endcan
                            @canany(['view cashflow movements', 'review staff requisition', 'approve staff requisition', 'authorize staff requisition', 'download staff requisition'])
                                <li class="{{ Request::routeIs('requisition.records', 'requisition.records.download') ? 'active' : '' }}">
                                    <a href="{{ route('requisition.records') }}" class="pc-link">
                                        <span class="pc-mtext">Requisition Records</span>
                                    </a>
                                </li>
                            @endcanany
                        </ul>
                    </li>
                @endcanany

                <!-- Reports and Settings -->
                <li class="pc-item pc-caption">
                    <label>Reports and Settings</label>
                    <i class="ti ti-settings"></i>
                </li>

                    <li class="pc-item {{ Request::routeIs('reports') ? 'active' : '' }}">
                        <a href="{{ route('reports') }}" class="pc-link">
                            <span class="pc-micon"><i class="ti ti-file-text"></i></span>
                            <span class="pc-mtext">Org Reports & Documents</span>
                        </a>
                    </li>
             
                    @php
                        $canSeeSettings = Auth::user()->can('users management settings')
                            || Auth::user()->can('manage roles and permissions')
                            || Auth::user()->can('manage departments')
                            || Auth::user()->can('manage system settings')
                            || Auth::user()->can('view activity log');
                    @endphp

                    @if($canSeeSettings)
                        <li class="pc-item pc-hasmenu {{ $isSettingsMenuOpen ? 'active pc-trigger' : '' }}">
                            <a href="javascript:void(0);" class="pc-link">
                                <span class="pc-micon"><i class="ti ti-settings"></i></span>
                                <span class="pc-mtext">Settings</span>
                                <span class="pc-arrow"><i class="ti ti-chevron-down"></i></span>
                            </a>
                            <ul class="pc-submenu">
                                @can('users management settings')
                                    <li class="{{ Request::routeIs('admin.index') ? 'active' : '' }}">
                                        <a href="{{ route('admin.index') }}" class="pc-link">
                                            <span class="pc-mtext">Users</span>
                                        </a>
                                    </li>
                                @endcan

                                @can('manage roles and permissions')
                                    <li class="{{ Request::routeIs('roles.*') ? 'active' : '' }}">
                                        <a href="{{ route('roles.index') }}" class="pc-link">
                                            <span class="pc-mtext">Roles</span>
                                        </a>
                                    </li>
                                @endcan

                                @can('manage departments')
                                    <li class="{{ Request::routeIs('departments') ? 'active' : '' }}">
                                        <a href="{{ route('departments') }}" class="pc-link">
                                            <span class="pc-mtext">Departments</span>
                                        </a>
                                    </li>
                                @endcan

                                @can('manage system settings')
                                    <li class="{{ Request::routeIs('settings.system.*') ? 'active' : '' }}">
                                        <a href="{{ route('settings.system.edit') }}" class="pc-link">
                                            <span class="pc-mtext">System</span>
                                        </a>
                                    </li>
                                @endcan

                                @can('view activity log')
                                    <li class="{{ Request::routeIs('activity-log.*') ? 'active' : '' }}">
                                        <a href="{{ route('activity-log.index') }}" class="pc-link">
                                            <span class="pc-mtext">Activity log</span>
                                        </a>
                                    </li>
                                @endcan
                            </ul>
                        </li>
                    @endif
               
            </ul>
            <div class="px-3 py-3 border-top border-secondary border-opacity-10 mt-2">
                <x-deskora-credit compact class="flex-wrap" />
            </div>
        </div>
    </div>
</nav>
