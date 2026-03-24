<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">My Documents</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item" aria-current="page">Documents</li>
                            </ul>
                        </div>
                        <div class="col-auto d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-light" onclick="window.location.reload()" title="Refresh">
                                <i class="ti ti-refresh"></i>
                            </button>
                            @if(request('type') === 'monthly_report')
                                <a href="{{ route('documents.create', ['type' => 'monthly_report']) }}" class="btn btn-primary btn-sm">
                                    <i class="ti ti-plus-circle me-1"></i> New Monthly Report
                                </a>
                            @else
                                <a href="{{ route('documents.create') }}" class="btn btn-primary btn-sm">
                                    <i class="ti ti-plus-circle me-1"></i> New Document
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-none border mb-4">
                <div class="card-header bg-white border-bottom">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0"><i class="ti ti-list me-2"></i>Documents List</h5>
                        </div>
                        <div class="col-md-6">
                            <form method="GET" action="{{ route('documents.index') }}" class="row">
                              
                                <div class="col-md-3 mb-2">
                                    <select name="period_id" class="form-control">
                                        <option value="" {{ request('period_id') ? '' : 'selected' }}>All Weeks</option>
                                        @foreach($periods as $period)
                                            <option value="{{ $period->id }}" {{ request('period_id') == $period->id ? 'selected' : '' }}>
                                                Week {{ $period->week_number }} • {{ $period->date_range }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3  mb-2">
                                    <select name="year" class="form-control">
                                        <option value="" {{ request('year') ? '' : 'selected' }}>All Years</option>
                                        @foreach($years as $year)
                                            <option value="{{ $year }}" {{ request('year') == $year ? 'selected' : '' }}>
                                                {{ $year }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <select name="review_status" class="form-control">
                                        <option value="">All Review Statuses</option>
                                        <option value="returned" {{ request('review_status') === 'returned' ? 'selected' : '' }}>
                                            Returned for Revision
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex justify-content-end gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="ti ti-filter me-1"></i>Apply
                                    </button>
                                    @if(request()->hasAny(['period_id', 'year', 'review_status']))
                                        <a href="{{ route('documents.index') }}" class="btn btn-light btn-sm">
                                            <i class="ti ti-x me-1"></i>Reset
                                        </a>
                                    @endif
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    @if(request('type') !== 'monthly_report')
                        <div class="row mb-4">
                            <div class="col-md-4 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="text-muted mb-1"><i class="ti ti-target me-1 text-info"></i>Plan Deadline</p>
                                            @if($deadlineStatuses)
                                                <h5 class="mb-1">{{ \Carbon\Carbon::parse($deadlineStatuses['plan_due'])->format('M d, Y') }}</h5>
                                                <span class="badge {{ $deadlineStatuses['plan_badge'] }}">{{ $deadlineStatuses['plan_text'] }}</span>
                                            @else
                                                <span class="text-muted">No current period</span>
                                            @endif
                                        </div>
                                        <a href="{{ route('documents.index', ['type' => 'weekly_plan']) }}" class="btn btn-outline-info btn-sm">
                                            View Plans
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="text-muted mb-1"><i class="ti ti-activity me-1 text-primary"></i>Report Deadline</p>
                                            @if($deadlineStatuses)
                                                <h5 class="mb-1">{{ \Carbon\Carbon::parse($deadlineStatuses['report_due'])->format('M d, Y') }}</h5>
                                                <span class="badge {{ $deadlineStatuses['report_badge'] }}">{{ $deadlineStatuses['report_text'] }}</span>
                                            @else
                                                <span class="text-muted">No current period</span>
                                            @endif
                                        </div>
                                        <a href="{{ route('documents.index', ['type' => 'weekly_report']) }}" class="btn btn-outline-primary btn-sm">
                                            View Reports
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="border rounded p-3 h-100">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="text-muted mb-1"><i class="ti ti-calendar me-1 text-secondary"></i>Quick Period</p>
                                            @if($currentPeriod)
                                                <h5 class="mb-1">Week {{ $currentPeriod->week_number }}</h5>
                                                <small class="text-muted">{{ $currentPeriod->date_range }}</small>
                                            @else
                                                <span class="text-muted">No period</span>
                                            @endif
                                        </div>
                                        <a href="{{ route('work-periods.index') }}" class="btn btn-outline-secondary btn-sm">
                                            Work Periods
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if($documents->count() > 0)
                        @php
                            $docCollection = $documents->getCollection();
                            $weeklyDocs = $docCollection->whereIn('type', [\App\Models\Document::TYPE_WEEKLY_PLAN, \App\Models\Document::TYPE_WEEKLY_REPORT]);
                            $otherDocs = $docCollection->whereNotIn('type', [\App\Models\Document::TYPE_WEEKLY_PLAN, \App\Models\Document::TYPE_WEEKLY_REPORT]);
                            $weeklyRows = $weeklyDocs->groupBy(fn($doc) => ($doc->period_id ?? 'none') . '_' . ($doc->user_id ?? 'none'))
                                ->map(function ($items) {
                                    $plan = $items->firstWhere('type', \App\Models\Document::TYPE_WEEKLY_PLAN);
                                    $report = $items->firstWhere('type', \App\Models\Document::TYPE_WEEKLY_REPORT);
                                    return [
                                        'plan' => $plan,
                                        'report' => $report,
                                        'period' => $plan?->period ?? $report?->period,
                                        'user' => $plan?->user ?? $report?->user,
                                        'updated_at' => collect([$plan?->updated_at, $report?->updated_at])->filter()->max(),
                                    ];
                                })
                                ->values();
                        @endphp

                        @if($weeklyRows->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered">
                                    <thead class="thead-light">
                                        <tr>
                                            <th class="text-center" style="width: 50px;">No.</th>
                                            <th>Week Period</th>
                                            <th>Author</th>
                                            <th>Weekly Plan</th>
                                            <th>Weekly Report</th>
                                            <th>Last Updated</th>
                                            <th class="text-center" style="width: 200px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($weeklyRows as $idx => $row)
                                            @php
                                                $planDoc = $row['plan'];
                                                $reportDoc = $row['report'];
                                                $period = $row['period'];
                                            @endphp
                                            <tr>
                                                <td class="text-center">{{ $idx + 1 }}</td>
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
                                                        <div class="fw-semibold">{{ $planDoc->display_title }}</div>
                                                        <span class="badge {{ $planDoc->state === 'submitted' ? 'bg-success' : 'bg-warning' }}">
                                                            {{ ucfirst($planDoc->state) }}
                                                        </span>
                                                        @if(isset($returnedDocumentMap[$planDoc->id]))
                                                            <span class="badge bg-danger mt-1">Returned for Revision</span>
                                                        @endif
                                                    @else
                                                        <span class="text-muted">Not created</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($reportDoc)
                                                        <div class="fw-semibold">{{ $reportDoc->display_title }}</div>
                                                        <span class="badge {{ $reportDoc->state === 'submitted' ? 'bg-success' : 'bg-warning' }}">
                                                            {{ ucfirst($reportDoc->state) }}
                                                        </span>
                                                        @if(isset($returnedDocumentMap[$reportDoc->id]))
                                                            <span class="badge bg-danger mt-1">Returned for Revision</span>
                                                        @endif
                                                    @else
                                                        <span class="text-muted">Not created</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($row['updated_at'])
                                                        <small class="text-muted">
                                                            {{ $row['updated_at']->format('M d, Y') }}<br>
                                                            <span>{{ $row['updated_at']->diffForHumans() }}</span>
                                                        </small>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex flex-wrap gap-1 justify-content-center">
                                                        @if($planDoc)
                                                            <a href="{{ route('documents.show', \Vinkla\Hashids\Facades\Hashids::encode($planDoc->id)) }}"
                                                                class="btn btn-info text-white btn-sm" title="View Plan">
                                                                <i class="ti ti-eye"></i> Plan
                                                            </a>
                                                        @endif
                                                        @if($reportDoc)
                                                            <a href="{{ route('documents.show', \Vinkla\Hashids\Facades\Hashids::encode($reportDoc->id)) }}"
                                                                class="btn btn-primary text-white btn-sm" title="View Report">
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
                        @endif

                        @if($otherDocs->count() > 0)
                            <h6 class="mt-4 mb-2"><i class="ti ti-file me-1"></i>Other Documents</h6>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered">
                                    <thead class="thead-light">
                                        <tr>
                                            <th class="text-center" style="width: 50px;">No.</th>
                                            <th>Document Type</th>
                                            <th>Title</th>
                                            <th>Author</th>
                                            <th>Week</th>
                                            <th>Created</th>
                                            <th>Last Updated</th>
                                            <th class="text-center" style="width: 120px;">Status</th>
                                            <th class="text-center" style="width: 100px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($otherDocs as $document)
                                            <tr>
                                                <td class="text-center">{{ $loop->iteration }}</td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <i class="ti ti-file me-1"></i>
                                                        {{ $document->type_name }}
                                                    </span>
                                                </td>
                                                <td><strong>{{ $document->display_title }}</strong></td>
                                                <td>{{ $document->user?->name ?? '-' }}</td>
                                                <td>{{ $document->period ? ('Week ' . $document->period->week_number) : '-' }}</td>
                                                <td><small class="text-muted">{{ $document->created_at->format('M d, Y') }}</small></td>
                                                <td><small class="text-muted">{{ $document->updated_at->format('M d, Y') }}</small></td>
                                                <td class="text-center">
                                                    <span class="badge {{ $document->state === 'submitted' ? 'bg-success' : 'bg-warning' }}">
                                                        {{ ucfirst($document->state) }}
                                                    </span>
                                                    @if(isset($returnedDocumentMap[$document->id]))
                                                        <span class="badge bg-danger mt-1">Returned for Revision</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    <a href="{{ route('documents.show', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}"
                                                        class="btn btn-info text-white btn-sm" title="View">
                                                        <i class="ti ti-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        <div class="mt-3">
                            {{ $documents->links() }}
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="ti ti-file-text fs-1 text-muted"></i>
                            <p class="text-muted mb-3 mt-3">No documents found</p>
                            <p class="text-muted small">Try adjusting your filters or create a new document.</p>
                            <a href="{{ route('documents.create') }}" class="btn btn-primary">
                                <i class="ti ti-plus-circle me-1"></i>Create New Document
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

