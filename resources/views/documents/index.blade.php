<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Documents</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item" aria-current="page">Documents</li>
                            </ul>
                        </div>
                        <div class="col-auto d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-light" onclick="window.location.reload()" title="Refresh">
                                <i class="ti ti-refresh"></i>
                            </button>
                          
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
                            <h5 class="mb-0"><i class="ti ti-list me-2"></i>Submitted documents (all staff)</h5>
                        </div>
                        <div class="col-md-6">
                            <form method="GET" action="{{ route('documents.index') }}" class="row">
                                @if(request()->filled('type'))
                                    <input type="hidden" name="type" value="{{ request('type') }}">
                                @endif
                                @if(request('type') !== 'monthly_report')
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
                                @else
                                <div class="col-md-4 mb-2">
                                    <select name="month" class="form-control">
                                        <option value="" {{ ! request()->filled('month') ? 'selected' : '' }}>All months</option>
                                        @foreach([
                                            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
                                        ] as $num => $label)
                                            <option value="{{ $num }}" {{ (string) request('month') === (string) $num ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @endif
                                <div class="col-md-4 mb-2">
                                    <select name="year" class="form-control">
                                        <option value="" {{ request('year') ? '' : 'selected' }}>All Years</option>
                                        @foreach($years as $year)
                                            <option value="{{ $year }}" {{ request('year') == $year ? 'selected' : '' }}>
                                                {{ $year }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                              
                                <div class="col-md-3 d-flex justify-content-end gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="ti ti-filter me-1"></i>Apply
                                    </button>
                                    @php
                                        $resetMonthly = request('type') === 'monthly_report'
                                            ? route('documents.index', ['type' => 'monthly_report'])
                                            : route('documents.index');
                                    @endphp
                                    @if(request()->hasAny(['period_id', 'year', 'review_status', 'month']))
                                        <a href="{{ $resetMonthly }}" class="btn btn-light btn-sm">
                                            <i class="ti ti-x me-1"></i>Reset
                                        </a>
                                    @endif
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    @if(request('review_status') !== 'returned')
                        <div class="alert alert-light border mb-3 mb-md-4">
                            This page lists <strong>submitted</strong> weekly plans, weekly reports, monthly reports, and action logs from everyone, so the team can review them together (for example before Monday check-ins). Drafts and documents returned for revision stay private to authors and collaborators until they are submitted again.
                        </div>
                    @endif
                    @if(request('type') === 'monthly_report')
                        <div class="alert alert-light border mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
                            <div class="flex-grow-1">
                                <strong>Monthly reports</strong> pull planned activities from the
                                <a href="{{ route('year-plan.index') }}">year plan</a>
                                for your sub-departments and the month you select. Report progress on those lines and add anything done outside the plan.
                            </div>
                            <a href="{{ route('documents.create', ['type' => 'monthly_report']) }}" class="btn btn-primary btn-sm text-nowrap">
                                <i class="ti ti-plus-circle me-1"></i> New monthly report
                            </a>
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
                                                        @if($planDoc->sharedUsers->isNotEmpty())
                                                            @php
                                                                $planSharedNames = $planDoc->sharedUsers->pluck('name')->filter()->values()->implode(', ');
                                                            @endphp
                                                            <span class="badge bg-light-info text-info mt-1" title="{{ $planSharedNames !== '' ? 'Shared with: '.$planSharedNames : 'Shared' }}">
                                                                {{ $planSharedNames !== '' ? 'Shared with: '.$planSharedNames : 'Shared' }}
                                                            </span>
                                                        @endif
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
                                                        @if($reportDoc->sharedUsers->isNotEmpty())
                                                            @php
                                                                $reportSharedNames = $reportDoc->sharedUsers->pluck('name')->filter()->values()->implode(', ');
                                                            @endphp
                                                            <span class="badge bg-light-info text-info mt-1" title="{{ $reportSharedNames !== '' ? 'Shared with: '.$reportSharedNames : 'Shared' }}">
                                                                {{ $reportSharedNames !== '' ? 'Shared with: '.$reportSharedNames : 'Shared' }}
                                                            </span>
                                                        @endif
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
                                                            <a href="{{ route('documents.show', $planDoc->id) }}"
                                                                class="btn btn-info text-white btn-sm" title="View Plan">
                                                                <i class="ti ti-eye"></i> Plan
                                                            </a>
                                                        @endif
                                                        @if($reportDoc)
                                                            <a href="{{ route('documents.show', $reportDoc->id) }}"
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
                                                    <a href="{{ $document->type === \App\Models\Document::TYPE_ACTION_LOG ? route('action-logs.show', $document->id) : route('documents.show', $document->id) }}"
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
                         
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

