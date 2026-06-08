<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Submitted Documents Dashboard</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('documents.index') }}">Documents</a></li>
                                <li class="breadcrumb-item" aria-current="page">Reviewer Dashboard</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-md-3 col-sm-6">
            <div class="card border shadow-none">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <span class="text-muted d-block">Total Submitted</span>
                            <h3 class="mb-0">{{ $stats['total_submitted'] }}</h3>
                        </div>
                        <div class="text-primary">
                            <i class="ti ti-file-text fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border shadow-none">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <span class="text-muted d-block">Weekly Plans</span>
                            <h3 class="mb-0 text-info">{{ $stats['weekly_plans'] }}</h3>
                        </div>
                        <div class="text-info">
                            <i class="ti ti-calendar fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border shadow-none">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <span class="text-muted d-block">Weekly Reports</span>
                            <h3 class="mb-0 text-success">{{ $stats['weekly_reports'] }}</h3>
                        </div>
                        <div class="text-success">
                            <i class="ti ti-file-text fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card border shadow-none">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <span class="text-muted d-block">Action Logs</span>
                            <h3 class="mb-0 text-primary">{{ $stats['action_logs'] ?? 0 }}</h3>
                        </div>
                        <div class="text-primary">
                            <i class="ti ti-clipboard-list fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-none border">
                <div class="card-body">
                    <form method="GET" action="{{ route('documents.reviewer-dashboard') }}" id="filterForm">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label small text-muted">User</label>
                                <select class="form-control form-control-sm" name="user_id" id="userFilter">
                                    <option value="">All Users</option>
                                    @foreach($usersWithDocuments as $user)
                                        <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                            {{ $user->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted">Work Period</label>
                                <select class="form-control form-control-sm" name="work_period_id" id="workPeriodFilter">
                                    <option value="{{ $defaultWorkPeriodFilter }}" {{ (string) ($selectedWorkPeriodId ?? $defaultWorkPeriodFilter) === (string) $defaultWorkPeriodFilter ? 'selected' : '' }}>
                                        Last 2 work periods
                                    </option>
                                    @foreach($periodOptions as $periodOption)
                                        <option value="{{ $periodOption->id }}" {{ (string) ($selectedWorkPeriodId ?? $defaultWorkPeriodFilter) === (string) $periodOption->id ? 'selected' : '' }}>
                                            Week {{ $periodOption->week_number }}, {{ $periodOption->year }} ({{ $periodOption->date_range }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary btn-sm w-100">
                                    <i class="ti ti-filter me-1"></i> Filter
                                </button>
                            </div>
                        </div>
                        @if(request()->filled('user_id') || (request()->has('work_period_id') && (string) request('work_period_id') !== (string) ($defaultWorkPeriodFilter ?? 'last_2')))
                            <div class="row mt-2">
                                <div class="col-md-12">
                                    <a href="{{ route('documents.reviewer-dashboard') }}" class="btn btn-sm btn-light">
                                        <i class="ti ti-x me-1"></i> Clear Filters
                                    </a>
                                </div>
                            </div>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Grouped Documents by Type -->
    <div class="row">
        @php
            $typeLabels = [
                'action_log' => ['name' => 'Action Logs', 'icon' => 'ti ti-clipboard-list', 'color' => 'primary'],
            ];
        @endphp

        <div class="col-md-12">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="ti ti-calendar-stats me-2 text-info"></i>
                        Weekly Plan + Report 
                        <span class="badge badge-info ml-2">{{ $mergedWeeklyRows->count() }}</span>
                    </h5>
                </div>
                <div class="card-body">
                    @if($mergedWeeklyRows->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered table-striped">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="text-center" style="width: 50px;">No.</th>
                                        <th style="width: 160px;">Week Period</th>
                                        <th>Staff</th>
                                        <th>Weekly Plan</th>
                                        <th>Weekly Report</th>
                                        <th>Last Updated</th>
                                        <th class="text-center" style="width: 190px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($mergedWeeklyRows as $row)
                                        @php
                                            $planDoc = $row['plan'];
                                            $reportDoc = $row['report'];
                                            $period = $row['period'];
                                            $planSharedUsers = collect($row['plan_shared_users'] ?? []);
                                            $reportSharedUsers = collect($row['report_shared_users'] ?? []);
                                        @endphp
                                        <tr>
                                            <td class="text-center">{{ $loop->iteration }}</td>
                                            <td>
                                                @if($period)
                                                    <strong>Week {{ $period->week_number }} of {{ $period->year }}</strong><br>
                                                    <small class="text-muted">{{ $period->date_range }}</small>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if(($row['participants'] ?? collect())->isNotEmpty())
                                                    {{ $row['participants']->pluck('name')->implode(', ') }}
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($planDoc)
                                                    <div class="fw-semibold">{{ $planDoc->display_title }}</div>

                                                    @if($planSharedUsers->isNotEmpty())
                                                        @php
                                                            $planSharedNames = $planSharedUsers->pluck('name')->filter()->unique()->values()->implode(', ');
                                                        @endphp
                                                        <span class="badge bg-light-info text-info mt-1" title="{{ $planSharedNames !== '' ? 'Shared with: '.$planSharedNames : 'Shared' }}">
                                                            {{ $planSharedNames !== '' ? 'Shared with: '.$planSharedNames : 'Shared' }}
                                                        </span>
                                                    @endif
                                                @else
                                                    <span class="text-muted">Not created</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($reportDoc)
                                                    <div class="fw-semibold">{{ $reportDoc->display_title }}</div>

                                                    @if($reportSharedUsers->isNotEmpty())
                                                        @php
                                                            $reportSharedNames = $reportSharedUsers->pluck('name')->filter()->unique()->values()->implode(', ');
                                                        @endphp
                                                        <span class="badge bg-light-info text-info mt-1" title="{{ $reportSharedNames !== '' ? 'Shared with: '.$reportSharedNames : 'Shared' }}">
                                                            {{ $reportSharedNames !== '' ? 'Shared with: '.$reportSharedNames : 'Shared' }}
                                                        </span>
                                                    @endif
                                                @else
                                                    <span class="text-muted">Not created</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($row['updated_at'])
                                                    <small class="text-muted">
                                                        {{ $row['updated_at']->diffForHumans() }}
                                                    </small>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if($planDoc)
                                                    <a href="{{ route('documents.reviewer-show', $planDoc->id) }}"
                                                        class="btn btn-icon btn-outline-info btn-sm mx-1" title="View Plan">
                                                        <i class="ti ti-file-text"></i>
                                                    </a>
                                                @else
                                                    <span class="text-muted mx-1" title="Plan not created">
                                                        <i class="ti ti-file-x"></i>
                                                    </span>
                                                @endif

                                                @if($reportDoc)
                                                    <a href="{{ route('documents.reviewer-show', $reportDoc->id) }}"
                                                        class="btn btn-icon btn-outline-primary btn-sm mx-1" title="View Report">
                                                        <i class="ti ti-file-description"></i>
                                                    </a>
                                                @else
                                                    <span class="text-muted mx-1" title="Report not created">
                                                        <i class="ti ti-file-x"></i>
                                                    </span>
                                                @endif
                                            </td>
                                       
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="ti ti-calendar-off fs-1 text-muted"></i>
                            <p class="text-muted mb-0 mt-2">No weekly plans/reports found for selected filters</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @foreach($groupedDocuments as $type => $documents)
            @if($documents->count() > 0)
                <div class="col-md-12 mb-4">
                    <div class="card shadow-none border">
                        <div class="card-header bg-white border-bottom">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="{{ $typeLabels[$type]['icon'] }} me-2 text-{{ $typeLabels[$type]['color'] }}"></i>
                                    {{ $typeLabels[$type]['name'] }}
                                    <span class="badge badge-{{ $typeLabels[$type]['color'] }} ml-2">{{ $documents->count() }}</span>
                                </h5>
                            </div>
                        </div>
                        <div class="card-body">
                            @if($documents->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-hover table-bordered table-striped">
                                        <thead class="thead-light">
                                            <tr>
                                                <th class="text-center" style="width: 50px;">No.</th>
                                                <th>Title</th>
                                                <th>Created By</th>
                                                <th>Submitted</th>
                                                <th class="text-center" style="width: 150px;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($documents as $document)
                                                <tr>
                                                    <td class="text-center">{{ $loop->iteration }}</td>
                                                    <td>
                                                        <strong>{{ $document->display_title }}</strong>
                                                    </td>
                                                    <td>
                                                       {{ $document->user->name }}
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            {{ $document->updated_at->format('M d, Y') }}
                                                            <br>
                                                            <span class="text-muted">{{ $document->updated_at->diffForHumans() }}</span>
                                                        </small>
                                                    </td>
                                                    <td class="text-center">
                                                        @if($document->type === \App\Models\Document::TYPE_ACTION_LOG)
                                                            <a href="{{ route('action-logs.show', $document->id) }}"
                                                                class="btn btn-info btn-sm text-white" title="View">
                                                                <i class="ti ti-eye"></i>
                                                            </a>
                                                        @elseif(in_array($document->type, [\App\Models\Document::TYPE_WEEKLY_PLAN, \App\Models\Document::TYPE_WEEKLY_REPORT], true))
                                                            <a href="{{ route('documents.reviewer-show', $document->id) }}"
                                                                class="btn btn-info btn-sm text-white" title="View">
                                                                <i class="ti ti-eye"></i>
                                                            </a>
                                                        @else
                                                            <a href="{{ route('documents.show', $document->id) }}"
                                                                class="btn btn-info btn-sm text-white" title="View">
                                                                <i class="ti ti-eye"></i>
                                                            </a>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-center py-4">
                                    <i class="{{ $typeLabels[$type]['icon'] }} fs-1 text-muted"></i>
                                    <p class="text-muted mb-0 mt-2">No {{ strtolower($typeLabels[$type]['name']) }} found</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        @endforeach

        @if(collect($groupedDocuments)->sum(fn($docs) => $docs->count()) === 0)
            <div class="col-md-12">
                <div class="card shadow-none border">
                    <div class="card-body">
                        <div class="text-center py-5">
                            <i class="ti ti-clipboard fs-1 text-muted"></i>
                            <p class="text-muted mb-0 mt-3">No submitted documents found matching your filters</p>
                            <a href="{{ route('documents.reviewer-dashboard') }}" class="btn btn-primary btn-sm mt-3">
                                <i class="ti ti-refresh me-1"></i> Clear Filters
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
