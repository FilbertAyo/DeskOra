<x-app-layout>
    <div class="row align-items-center mb-3 border-bottom no-gutters">
        <div class="col">
            <ul class="nav nav-tabs border-0" id="myTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="home-tab" data-toggle="tab" href="#home" role="tab"
                        aria-controls="home" aria-selected="true">
                        <i class="ti ti-home me-1"></i> My Dashboard
                    </a>
                </li>
            </ul>
        </div>
    </div>

    @php
        $currentPeriod = $periodInfo['current'] ?? null;
        $nextPeriod = $periodInfo['next'] ?? null;
        $returnedForRevisionCount = $pendingDocuments->filter(fn ($document) => $document->isReturnedForRevision())->count();
    @endphp

    <div class="row mb-3">
        <div class="col-md-12">
            <form method="GET" action="{{ route('documents.employee-dashboard') }}" class="card shadow-none border">
                <div class="card-body d-md-flex align-items-center">
                    <div class="flex-grow-1 mb-3 mb-md-0">
                        <label for="periodSelector" class="form-label text-muted mb-1">
                            <i class="ti ti-calendar me-1"></i>Select Week to View
                        </label>
                        <div class="row">
                            <div class="col-md-6">
                                <select name="period_id" id="periodSelector" class="form-control">
                                    <option value="" disabled {{ (old('type') || isset($selectedType)) ? '' : 'selected' }}>
                                        Current Week @if($currentPeriod) (Week {{ $currentPeriod->week_number }} - {{ $currentPeriod->date_range }}) @endif
                                    </option>
                                    @foreach($periodOptions as $option)
                                        <option value="{{ $option->id }}" {{ (string)$periodFilterId === (string)$option->id ? 'selected' : '' }}>
                                            Week {{ $option->week_number }} • {{ $option->date_range }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mt-3 mt-md-0">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="ti ti-filter me-1"></i>Apply
                                </button>
                            </div>
                            @if($periodFilterId)
                                <div class="col-md-3 mt-3 mt-md-0">
                                    <a href="{{ route('documents.employee-dashboard') }}" class="btn btn-light btn-block">
                                        <i class="ti ti-x me-1"></i>Reset
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if($currentPeriod)
        <div class="row mb-4">
            <div class="col-md-8 mb-3">
                <div class="card border shadow-none h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="text-muted mb-1">Current Work Week</p>
                                <h4 class="mb-1">Week {{ $currentPeriod->week_number }} • {{ $currentPeriod->date_range }}</h4>
                                @if($nextPeriod)
                                    <small class="text-muted">Preparing plan for Week {{ $nextPeriod->week_number }} ({{ $nextPeriod->date_range }})</small>
                                @endif
                            </div>
                            <span class="badge badge-{{ $currentPeriod->status === 'open' ? 'success' : 'secondary' }}">
                                {{ ucfirst($currentPeriod->status) }}
                            </span>
                        </div>
                        @if($deadlineStatuses)
                            <div class="row mt-4">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <div class="border rounded p-3 h-100">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <p class="text-muted mb-1">
                                                    <i class="ti ti-target text-info me-1"></i>Plan Deadline
                                                </p>
                                                <h5 class="mb-1">{{ \Carbon\Carbon::parse($deadlineStatuses['plan_due'])->format('M d, Y') }}</h5>
                                                <small class="text-muted">Submit plan for next week</small>
                                            </div>
                                            <span class="badge {{ $deadlineStatuses['plan_badge'] }}">
                                                {{ $deadlineStatuses['plan_text'] }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded p-3 h-100">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <p class="text-muted mb-1">
                                                    <i class="ti ti-activity text-primary me-1"></i>Report Deadline
                                                </p>
                                                <h5 class="mb-1">{{ \Carbon\Carbon::parse($deadlineStatuses['report_due'])->format('M d, Y') }}</h5>
                                                <small class="text-muted">Submit report for this week</small>
                                            </div>
                                            <span class="badge {{ $deadlineStatuses['report_badge'] }}">
                                                {{ $deadlineStatuses['report_text'] }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="alert alert-warning mt-3 mb-0">
                                <i class="ti ti-alert-triangle me-1"></i>
                                No open work period found. Please contact your administrator.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card border shadow-none h-100">
                    <div class="card-header bg-white border-bottom">
                        <h6 class="mb-0"><i class="ti ti-zap me-1 text-primary"></i>Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        @if(isset($availableTypes['weekly_plan']))
                            <a href="{{ route('documents.create') }}?type=weekly_plan" class="btn btn-outline-info btn-block mb-2">
                                <i class="ti ti-calendar me-1"></i> New Weekly Plan
                            </a>
                        @else
                            <button class="btn btn-outline-info btn-block mb-2" disabled>
                                <i class="ti ti-calendar-off me-1"></i> Plan Unavailable
                            </button>
                        @endif

                        @if(isset($availableTypes['weekly_report']))
                            <a href="{{ route('documents.create') }}?type=weekly_report" class="btn btn-outline-primary btn-block mb-2">
                                <i class="ti ti-file-text me-1"></i> New Weekly Report
                            </a>
                        @else
                            <button class="btn btn-outline-primary btn-block mb-2" disabled>
                                <i class="ti ti-file-minus me-1"></i> Report Unavailable
                            </button>
                        @endif

                        @if(isset($availableTypes['monthly_report']))
                            <a href="{{ route('documents.create') }}?type=monthly_report" class="btn btn-outline-dark btn-block">
                                <i class="ti ti-bar-chart me-1"></i> New Monthly Report
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-warning">
            <i class="ti ti-alert-triangle me-1"></i>
            No active work week available. Your administrator needs to create the current work period before you can submit weekly plans or reports.
        </div>
    @endif

    <!-- Statistics Cards -->
    <div class="row mb-3">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card border shadow-none">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <span class="text-muted d-block">Pending Submissions</span>
                            <h3 class="mb-0 text-warning">{{ $stats['total_pending'] }}</h3>
                        </div>
                        <div class="text-warning">
                            <i class="ti ti-clock fs-1"></i>
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
                            <span class="text-muted d-block">Submitted</span>
                            <h3 class="mb-0 text-success">{{ $stats['total_submitted'] }}</h3>
                        </div>
                        <div class="text-success">
                            <i class="ti ti-check-circle fs-1"></i>
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
                            <span class="text-muted d-block">Weekly Plans Pending</span>
                            <h3 class="mb-0 text-info">{{ $stats['weekly_plan_pending'] }}</h3>
                            @if($deadlineStatuses)
                                <span class="badge {{ $deadlineStatuses['plan_badge'] }}">
                                    {{ $deadlineStatuses['plan_text'] }}
                                </span>
                            @endif
                        </div>
                        <div class="text-info">
                            <i class="ti ti-calendar fs-1"></i>
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
                            <span class="text-muted d-block">Returned for Revision</span>
                            <h3 class="mb-0 text-danger">{{ $returnedForRevisionCount }}</h3>
                            <small class="text-muted">Drafts sent back by reviewer</small>
                        </div>
                        <div class="text-danger">
                            <i class="ti ti-arrow-back-up fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Submissions Section -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="ti ti-clock me-2 text-warning"></i>
                            Pending Submissions
                            <span class="badge bg-warning ml-2">{{ $pendingDocuments->count() }}</span>
                        </h5>
                        <a href="{{ route('documents.create') }}" class="btn btn-sm btn-primary">
                            <i class="ti ti-plus-circle me-1"></i> Create New Document
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @if($pendingDocuments->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="text-center" style="width: 50px;">No.</th>
                                        <th>Document Type</th>
                                        <th>Title</th>
                                        <th>Created</th>
                                        <th>Last Updated</th>
                                        <th class="text-center" style="width: 150px;">Status</th>
                                        <th class="text-center" style="width: 150px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pendingDocuments as $document)
                                        <tr>
                                            <td class="text-center">{{ $loop->iteration }}</td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <i class="ti ti-file me-1"></i>
                                                    {{ $document->type_name }}
                                                </span>
                                            </td>
                                            <td>
                                                <strong>{{ $document->display_title }}</strong>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    {{ $document->created_at->format('M d, Y') }}
                                                </small>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    {{ $document->updated_at->format('M d, Y') }}
                                                    <br>
                                                    <span class="text-muted">{{ $document->updated_at->diffForHumans() }}</span>
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-warning">
                                                    <i class="ti ti-edit me-1"></i>Draft
                                                </span>
                                                @if($document->isReturnedForRevision())
                                                    <span class="badge bg-danger mt-1">
                                                        <i class="ti ti-arrow-back-up me-1"></i>Returned
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="{{ route('documents.show', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}"
                                                        class="btn btn-info text-white" title="View">
                                                        <i class="ti ti-eye"></i>
                                                    </a>
                                                    <a href="{{ route('documents.edit', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}"
                                                        class="btn btn-primary text-white" title="Edit">
                                                        <i class="ti ti-edit"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="ti ti-check-circle fs-1 text-success"></i>
                            <p class="text-muted mb-3 mt-3">No pending submissions</p>
                            <p class="text-muted small">All your documents have been submitted!</p>
                            <a href="{{ route('documents.create') }}" class="btn btn-primary">
                                <i class="ti ti-plus-circle me-1"></i>Create New Document
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- History Section -->
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="ti ti-list me-2"></i>
                        Document History
                        <span class="badge bg-primary ml-2">{{ $allDocuments->count() }}</span>
                    </h5>
                </div>
                <div class="card-body">
                    @if($allDocuments->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="text-center" style="width: 50px;">No.</th>
                                        <th>Document Type</th>
                                        <th>Title</th>
                                        <th>Created</th>
                                        <th>Submitted</th>
                                        <th class="text-center" style="width: 120px;">Status</th>
                                        <th class="text-center" style="width: 100px;">Comments</th>
                                        <th class="text-center" style="width: 100px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($allDocuments as $document)
                                        <tr>
                                            <td class="text-center">{{ $loop->iteration }}</td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <i class="ti ti-file me-1"></i>
                                                    {{ $document->type_name }}
                                                </span>
                                            </td>
                                            <td>
                                                <strong>{{ $document->display_title }}</strong>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    {{ $document->created_at->format('M d, Y') }}
                                                </small>
                                            </td>
                                            <td>
                                                @if($document->state === 'submitted')
                                                    <small class="text-muted">
                                                        {{ $document->updated_at->format('M d, Y') }}
                                                        <br>
                                                        <span class="text-muted">{{ $document->updated_at->diffForHumans() }}</span>
                                                    </small>
                                                @else
                                                    <small class="text-muted">-</small>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if($document->state === 'draft')
                                                    <span class="badge bg-warning">
                                                        <i class="ti ti-edit me-1"></i>Draft
                                                    </span>
                                                    @if($document->isReturnedForRevision())
                                                        <span class="badge bg-danger mt-1">
                                                            <i class="ti ti-arrow-back-up me-1"></i>Returned
                                                        </span>
                                                    @endif
                                                @else
                                                    <span class="badge bg-success">
                                                        <i class="ti ti-check-circle me-1"></i>Submitted
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-primary rounded-pill">
                                                    {{ $document->comments->count() }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <a href="{{ route('documents.show', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}"
                                                    class="btn btn-info btn-sm text-white" title="View">
                                                    <i class="ti ti-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="ti ti-file-text fs-1 text-muted"></i>
                            <p class="text-muted mb-3 mt-3">No documents yet</p>
                            <a href="{{ route('documents.create') }}" class="btn btn-primary">
                                <i class="ti ti-plus-circle me-1"></i>Create Your First Document
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Timeline Section -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="ti ti-git-merge me-2"></i> Recent Week Timeline
                    </h5>
                    <small class="text-muted">Track your plan/report status over the last 6 weeks</small>
                </div>
                <div class="card-body">
                    @if($timelineData->count())
                        <div class="timeline">
                            @foreach($timelineData as $entry)
                                @php
                                    $period = $entry['period'];
                                    $planStatus = $entry['plan_status'];
                                    $reportStatus = $entry['report_status'];
                                    $planBadge = $planStatus === 'submitted' ? 'bg-success' : ($planStatus === 'draft' ? 'bg-warning' : 'bg-secondary');
                                    $reportBadge = $reportStatus === 'submitted' ? 'bg-success' : ($reportStatus === 'draft' ? 'bg-warning' : 'bg-secondary');
                                @endphp
                                <div class="timeline-item pb-3 mb-3 border-bottom">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1">Week {{ $period->week_number }} • {{ $period->date_range }}</h6>
                                            <small class="text-muted">Plan due {{ \Carbon\Carbon::parse($period->plan_deadline)->format('M d') }} • Report due {{ \Carbon\Carbon::parse($period->report_deadline)->format('M d') }}</small>
                                        </div>
                                        <span class="badge badge-outline-{{ $period->isOpen() ? 'success' : 'secondary' }}">
                                            {{ ucfirst($period->status) }}
                                        </span>
                                    </div>
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <div class="border rounded p-3 h-100">
                                                <p class="text-muted mb-1"><i class="ti ti-target me-1 text-info"></i>Weekly Plan</p>
                                                @if($planStatus === 'missing')
                                                    <span class="badge bg-secondary">Not created</span>
                                                @else
                                                    <span class="badge {{ $planBadge }}">{{ ucfirst($planStatus) }}</span>
                                                    @if($entry['plan'] && $entry['plan']->isReturnedForRevision())
                                                        <span class="badge bg-danger ml-1">Returned</span>
                                                    @endif
                                                    @if($entry['plan'])
                                                        <a href="{{ route('documents.show', \Vinkla\Hashids\Facades\Hashids::encode($entry['plan']->id)) }}" class="btn btn-link btn-sm p-0 ml-2">View</a>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="border rounded p-3 h-100">
                                                <p class="text-muted mb-1"><i class="ti ti-activity me-1 text-primary"></i>Weekly Report</p>
                                                @if($reportStatus === 'missing')
                                                    <span class="badge bg-secondary">Not created</span>
                                                @else
                                                    <span class="badge {{ $reportBadge }}">{{ ucfirst($reportStatus) }}</span>
                                                    @if($entry['report'] && $entry['report']->isReturnedForRevision())
                                                        <span class="badge bg-danger ml-1">Returned</span>
                                                    @endif
                                                    @if($entry['report'])
                                                        <a href="{{ route('documents.show', \Vinkla\Hashids\Facades\Hashids::encode($entry['report']->id)) }}" class="btn btn-link btn-sm p-0 ml-2">View</a>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="ti ti-git-branch fs-1 text-muted"></i>
                            <p class="text-muted mt-2 mb-0">No timeline data available yet.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
