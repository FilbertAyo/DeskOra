<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Monthly Reports</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('documents.index') }}">Documents</a></li>
                                <li class="breadcrumb-item" aria-current="page">Monthly Reports</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card tbl-card mb-2">
                <div class="card-header bg-white border-bottom">
                    <div class="row align-items-center">
                        <div class="col-md-6 mb-2 mb-md-0">
                            <h5 class="mb-0">
                                <i class="ti ti-chart-line me-2 text-primary"></i>Monthly Reports
                            </h5>
                        </div>
                        <div class="col-md-6">
                            <form method="GET" action="{{ route('documents.monthly-report') }}" class="row" id="filterForm">
                                <div class="col-md-6 mb-2">
                                    <select name="month" class="form-control" onchange="document.getElementById('filterForm').submit();">
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
                                <div class="col-md-6 mb-2">
                                    <select name="year" class="form-control" onchange="document.getElementById('filterForm').submit();">
                                        <option value="" {{ ! request('year') ? 'selected' : '' }}>All years</option>
                                        <option value="{{ date('Y') }}" {{ request('year') == date('Y') ? 'selected' : '' }}>
                                            Current year ({{ date('Y') }})
                                        </option>
                                        @foreach($years as $year)
                                            @if((string) $year !== date('Y'))
                                                <option value="{{ $year }}" {{ request('year') == $year ? 'selected' : '' }}>
                                                    {{ $year }}
                                                </option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-light border mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div class="flex-grow-1">
                            <strong>Monthly reports</strong> pull planned activities from the
                            <a href="{{ route('year-plan.index') }}">year plan</a>
                            for your sub-departments. Report progress on those lines and add anything done outside the plan.
                        </div>
                        @if(isset($availableTypes['monthly_report']))
                            <a href="{{ route('documents.create', ['type' => 'monthly_report', 'year' => $defaultReportYear, 'month' => $defaultReportMonth]) }}"
                               class="btn btn-primary btn-sm text-nowrap">
                                <i class="ti ti-plus-circle me-1"></i> New monthly report
                            </a>
                        @endif
                    </div>

                    @if($documents->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered table-striped">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="text-center" style="width: 50px;">No.</th>
                                        <th>Title</th>
                                        <th>Reporting period</th>
                                        <th>Created</th>
                                        <th>Last updated</th>
                                        <th class="text-center" style="width: 120px;">Status</th>
                                        <th class="text-center" style="width: 200px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($documents as $document)
                                        @php
                                            $monthLabel = $document->month
                                                ? \Carbon\Carbon::create((int) ($document->year ?? date('Y')), (int) $document->month, 1)->format('F')
                                                : null;
                                        @endphp
                                        <tr>
                                            <td class="text-center">{{ ($documents->currentPage() - 1) * $documents->perPage() + $loop->iteration }}</td>
                                            <td><strong>{{ $document->display_title }}</strong></td>
                                            <td>
                                                @if($monthLabel && $document->year)
                                                    <span class="fw-semibold">{{ $monthLabel }} {{ $document->year }}</span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                <small class="text-muted">{{ $document->created_at->format('M d, Y') }}</small>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    {{ $document->updated_at->format('M d, Y') }}
                                                    <br>
                                                    <span class="text-muted">{{ $document->updated_at->diffForHumans() }}</span>
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                @if($document->state === 'draft')
                                                    <span class="badge bg-light-warning border border-warning">Draft</span>
                                                    @if(isset($returnedDocumentMap[$document->id]))
                                                        <span class="badge bg-light-danger border border-danger mt-1">Returned</span>
                                                    @endif
                                                @else
                                                    <span class="badge bg-light-success border border-success">Submitted</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <x-table-action-dropdown>
                                                    <a class="dropdown-item" href="{{ route('documents.show', $document->id) }}">
                                                        <i class="ti ti-eye me-2"></i> View
                                                    </a>
                                                    @if($document->canBeEdited())
                                                        <a class="dropdown-item" href="{{ route('documents.edit', $document->id) }}">
                                                            <i class="ti ti-edit me-2"></i> Edit
                                                        </a>
                                                    @endif
                                                </x-table-action-dropdown>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3">
                            {{ $documents->links() }}
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="ti ti-chart-line fs-1 text-muted"></i>
                            <p class="text-muted mb-3 mt-3">No monthly reports found</p>
                            @if(isset($availableTypes['monthly_report']))
                                <a href="{{ route('documents.create', ['type' => 'monthly_report', 'year' => $defaultReportYear, 'month' => $defaultReportMonth]) }}"
                                   class="btn btn-primary btn-sm">
                                    <i class="ti ti-plus-circle me-1"></i> Create your first monthly report
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
