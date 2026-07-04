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

    <!-- Filters -->
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-none border">
                <div class="card-body">
                    <form method="GET" action="{{ route('documents.index') }}" id="filterForm">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label small text-muted">Work Period</label>
                                <select class="form-control form-control-sm" name="period_id" id="periodFilter">
                                        <option value="" {{ request('period_id') ? '' : 'selected' }}>Last 2 work periods</option>
                                        @foreach($periods as $period)
                                            <option value="{{ $period->id }}" {{ request('period_id') == $period->id ? 'selected' : '' }}>
                                                Week {{ $period->week_number }}, {{ $period->year }} ({{ $period->date_range }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted">Year</label>
                                <select class="form-control form-control-sm" name="year" id="yearFilter">
                                    <option value="" {{ request('year') ? '' : 'selected' }}>All Years</option>
                                    @foreach($years as $year)
                                        <option value="{{ $year }}" {{ request('year') == $year ? 'selected' : '' }}>
                                            {{ $year }}
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
                        @php
                            $clearFiltersUrl = route('documents.index');
                        @endphp
                        @if(request()->hasAny(['period_id', 'year', 'review_status']))
                            <div class="row mt-2">
                                <div class="col-md-12">
                                    <a href="{{ $clearFiltersUrl }}" class="btn btn-sm btn-light">
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

    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-none border mb-4">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0"><i class="ti ti-list me-2"></i>Submitted documents (all staff)</h5>
                </div>
                <div class="card-body">
                    @php
                        $weeklyRows = $weeklyRows ?? $documents;
                    @endphp

                    @if($weeklyRows->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered">
                                    <thead class="thead-light">
                                        <tr>
                                            <th class="text-center" style="width: 50px;">No.</th>
                                            <th style="width: 160px;">Week Period</th>
                                            <th>Staff</th>
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
                                                $planSharedUsers = collect($row['plan_shared_users'] ?? []);
                                                $reportSharedUsers = collect($row['report_shared_users'] ?? []);
                                            @endphp
                                            <tr>
                                                <td class="text-center">{{ ($weeklyRows->currentPage() - 1) * $weeklyRows->perPage() + $loop->iteration }}</td>
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
                                                            <span class="badge bg-light-info border border-info text-info mt-1" title="{{ $planSharedNames !== '' ? 'Shared with: '.$planSharedNames : 'Shared' }}">
                                                                {{ $planSharedNames !== '' ? 'Shared with: '.$planSharedNames : 'Shared' }}
                                                            </span>
                                                        @endif
                                                      
                                                        @if(isset($returnedDocumentMap[$planDoc->id]))
                                                            <span class="badge bg-light-danger border border-danger mt-1">Returned for Revision</span>
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
                                                            <span class="badge bg-light-info border border-info text-info mt-1" title="{{ $reportSharedNames !== '' ? 'Shared with: '.$reportSharedNames : 'Shared' }}">
                                                                {{ $reportSharedNames !== '' ? 'Shared with: '.$reportSharedNames : 'Shared' }}
                                                            </span>
                                                        @endif
                                                     
                                                        @if(isset($returnedDocumentMap[$reportDoc->id]))
                                                            <span class="badge bg-light-danger border border-danger mt-1">Returned for Revision</span>
                                                        @endif
                                                    @else
                                                        <span class="text-muted">Not created</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($row['updated_at'])
                                                        <small class="text-muted">
                                                          
                                                            <span>{{ $row['updated_at']->diffForHumans() }}</span>
                                                        </small>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    @if($planDoc || $reportDoc)
                                                        <x-table-action-dropdown>
    @if($planDoc)
    <a class="dropdown-item" href="{{ route('documents.show', $planDoc->id) }}">
    <i class="ti ti-target me-2"></i> View Plan
    </a>
    @endif
    @if($reportDoc)
    <a class="dropdown-item" href="{{ route('documents.show', $reportDoc->id) }}">
    <i class="ti ti-activity me-2"></i> View Report
    </a>
    @endif

</x-table-action-dropdown>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3">
                                {{ $weeklyRows->links('pagination::bootstrap-4') }}
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

