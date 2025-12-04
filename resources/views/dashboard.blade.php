<x-app-layout>
    <div class="row align-items-center mb-3 border-bottom no-gutters">
        <div class="col">
            <ul class="nav nav-tabs" id="myTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="home-tab" data-toggle="tab" href="#home" role="tab"
                        aria-controls="home" aria-selected="true">
                        Dashboard
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-lg-8 mb-3">
            <div class="card shadow-none border h-100">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1"><i class="fe fe-calendar me-2"></i>Work Period Overview</h5>
                        @if($currentPeriod)
                            <small class="text-muted">Week {{ $currentPeriod->week_number }} • {{ $currentPeriod->date_range }}</small>
                        @else
                            <small class="text-muted">No active work period</small>
                        @endif
                    </div>
                    @if($currentPeriod)
                        <span class="badge badge-{{ $currentPeriod->status === 'open' ? 'success' : 'secondary' }}">
                            {{ ucfirst($currentPeriod->status) }}
                        </span>
                    @endif
                </div>
                <div class="card-body">
                    @if($currentPeriod)
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <p class="text-muted mb-1">
                                        <i class="fe fe-target me-1 text-info"></i>Weekly Plan Deadline
                                    </p>
                                    <h5 class="mb-1">{{ \Carbon\Carbon::parse($currentPeriod->plan_deadline)->format('M d, Y') }}</h5>
                                    <span class="badge {{ $periodDeadlineStatuses['plan_badge'] ?? 'badge-secondary' }}">
                                        {{ $periodDeadlineStatuses['plan_text'] ?? 'N/A' }}
                                    </span>
                                    @if($nextPeriod)
                                        <small class="d-block text-muted mt-2">Planning for Week {{ $nextPeriod->week_number }} • {{ $nextPeriod->date_range }}</small>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <p class="text-muted mb-1">
                                        <i class="fe fe-activity me-1 text-primary"></i>Weekly Report Deadline
                                    </p>
                                    <h5 class="mb-1">{{ \Carbon\Carbon::parse($currentPeriod->report_deadline)->format('M d, Y') }}</h5>
                                    <span class="badge {{ $periodDeadlineStatuses['report_badge'] ?? 'badge-secondary' }}">
                                        {{ $periodDeadlineStatuses['report_text'] ?? 'N/A' }}
                                    </span>
                                    <small class="d-block text-muted mt-2">Reporting on Week {{ $currentPeriod->week_number }}</small>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="alert alert-warning mb-0">
                            <i class="fe fe-alert-triangle me-1"></i>
                            No open work period. Please contact an administrator to create the current week.
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-3">
            <div class="card shadow-none border h-100">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0"><i class="fe fe-zap me-1 text-primary"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    @php
                        $available = $dashboardAvailableTypes ?? [];
                    @endphp
                    <div class="d-grid gap-2">
                        @if(isset($available['weekly_plan']))
                            <a href="{{ route('documents.create') }}?type=weekly_plan" class="btn btn-outline-info btn-block mb-2">
                                <i class="fe fe-calendar me-1"></i> New Weekly Plan
                                @if(($dashboardPendingDocs['weekly_plan'] ?? 0) > 0)
                                    <span class="badge badge-warning ml-2">{{ $dashboardPendingDocs['weekly_plan'] }}</span>
                                @endif
                            </a>
                        @endif
                        @if(isset($available['weekly_report']))
                            <a href="{{ route('documents.create') }}?type=weekly_report" class="btn btn-outline-primary btn-block mb-2">
                                <i class="fe fe-file-text me-1"></i> New Weekly Report
                                @if(($dashboardPendingDocs['weekly_report'] ?? 0) > 0)
                                    <span class="badge badge-warning ml-2">{{ $dashboardPendingDocs['weekly_report'] }}</span>
                                @endif
                            </a>
                        @endif
                        @if(isset($available['monthly_report']))
                            <a href="{{ route('documents.create') }}?type=monthly_report" class="btn btn-outline-dark btn-block">
                                <i class="fe fe-bar-chart me-1"></i> New Monthly Report
                                @if(($dashboardPendingDocs['monthly_report'] ?? 0) > 0)
                                    <span class="badge badge-warning ml-2">{{ $dashboardPendingDocs['monthly_report'] }}</span>
                                @endif
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- TODO: Create documents.partials.employee-dashboard-section partial to enable this section --}}
    {{-- @if(($canSeeWeeklyDashboard ?? false) && !empty($weeklyDashboardData))
        @include('documents.partials.employee-dashboard-section', $weeklyDashboardData + ['showFilters' => false, 'periodFilterId' => null])
    @endif --}}
</x-app-layout>
