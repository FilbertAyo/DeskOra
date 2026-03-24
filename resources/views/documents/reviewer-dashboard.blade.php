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
        <div class="col-md-3 col-sm-6 mb-3">
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
        <div class="col-md-3 col-sm-6 mb-3">
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
        <div class="col-md-2 col-sm-6 mb-3">
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
        <div class="col-md-2 col-sm-6 mb-3">
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
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0"><i class="ti ti-filter me-2"></i>Filters</h6>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('documents.reviewer-dashboard') }}" id="filterForm">
                        <div class="row">
                            <div class="col-md-2 mb-2">
                                <label class="form-label small text-muted">Document Type</label>
                                <select class="form-control form-control-sm" name="type" id="typeFilter">
                                    <option value="">All Types</option>
                                    <option value="weekly_plan" {{ request('type') == 'weekly_plan' ? 'selected' : '' }}>Weekly Plans</option>
                                    <option value="weekly_report" {{ request('type') == 'weekly_report' ? 'selected' : '' }}>Weekly Reports</option>
                                    <option value="action_log" {{ request('type') == 'action_log' ? 'selected' : '' }}>Action Logs</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
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
                            <div class="col-md-2 mb-2">
                                <label class="form-label small text-muted">Period</label>
                                <select class="form-control form-control-sm" name="period" id="periodFilter">
                                    <option value="">All Time</option>
                                    <option value="this_week" {{ request('period') == 'this_week' ? 'selected' : '' }}>This Week</option>
                                    <option value="last_week" {{ request('period') == 'last_week' ? 'selected' : '' }}>Last Week</option>
                                    <option value="this_month" {{ request('period') == 'this_month' ? 'selected' : '' }}>This Month</option>
                                    <option value="last_month" {{ request('period') == 'last_month' ? 'selected' : '' }}>Last Month</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label small text-muted">Specific Week</label>
                                <select class="form-control form-control-sm" name="work_period_id">
                                    <option value="">Any Week</option>
                                    @foreach($periodOptions as $periodOption)
                                        <option value="{{ $periodOption->id }}" {{ request('work_period_id') == $periodOption->id ? 'selected' : '' }}>
                                            Week {{ $periodOption->week_number }} • {{ $periodOption->date_range }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                                <div class="col-md-2 mb-2">
                                <label class="form-label small text-muted">Year</label>
                                <select class="form-control form-control-sm" name="year">
                                    <option value="">Any Year</option>
                                    @foreach($years as $year)
                                        <option value="{{ $year }}" {{ request('year') == $year ? 'selected' : '' }}>
                                            {{ $year }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 mb-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary btn-sm w-100">
                                    <i class="ti ti-filter me-1"></i> Apply Filters
                                </button>
                            </div>
                        </div>
                        @if(request()->hasAny(['type', 'user_id', 'period']))
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
                            <table class="table table-hover table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="text-center" style="width: 50px;">No.</th>
                                        <th>Week</th>
                                        <th>Created By</th>
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
                                                @if($row['user'])
                                                   {{ $row['user']->name }}
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($planDoc)
                                                    <strong class="d-block">{{ $planDoc->display_title }}</strong>
                                                    <span class="badge {{ $planDoc->state === 'submitted' ? 'bg-success' : 'bg-warning' }}">
                                                        {{ ucfirst($planDoc->state) }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">Not submitted</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($reportDoc)
                                                    <strong class="d-block">{{ $reportDoc->display_title }}</strong>
                                                    <span class="badge {{ $reportDoc->state === 'submitted' ? 'bg-success' : 'bg-warning' }}">
                                                        {{ ucfirst($reportDoc->state) }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">Not submitted</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($row['updated_at'])
                                                    <small class="text-muted">
                                                        {{ $row['updated_at']->format('M d, Y') }}<br>
                                                        {{ $row['updated_at']->diffForHumans() }}
                                                    </small>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex flex-wrap gap-1 justify-content-center">
                                                    @if($planDoc)
                                                        <a href="{{ route('documents.show', \Vinkla\Hashids\Facades\Hashids::encode($planDoc->id)) }}"
                                                            class="btn btn-info btn-sm text-white" title="View Plan">
                                                            <i class="ti ti-eye"></i> Plan
                                                        </a>
                                                    @endif
                                                    @if($reportDoc)
                                                        <a href="{{ route('documents.show', \Vinkla\Hashids\Facades\Hashids::encode($reportDoc->id)) }}"
                                                            class="btn btn-primary btn-sm text-white" title="View Report">
                                                            <i class="ti ti-eye"></i> Report
                                                        </a>
                                                    @endif
                                                </div>
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
            @if($documents->count() > 0 || !$selectedType)
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
                                    <table class="table table-hover table-bordered">
                                        <thead class="thead-light">
                                            <tr>
                                                <th class="text-center" style="width: 50px;">No.</th>
                                                <th>Title</th>
                                                <th>Created By</th>
                                                <th>Submitted</th>
                                                <th>Comments</th>
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
                                                        <span class="badge bg-primary rounded-pill">
                                                            {{ $document->comments->count() }}
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        @if($document->type === \App\Models\Document::TYPE_ACTION_LOG)
                                                            <a href="{{ route('action-logs.show', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}"
                                                                class="btn btn-info btn-sm text-white" title="View">
                                                                <i class="ti ti-eye"></i>
                                                            </a>
                                                        @else
                                                            <a href="{{ route('documents.show', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}"
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
