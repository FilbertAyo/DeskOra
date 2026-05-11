<x-app-layout>

  

    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Work Periods Management</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('documents.index') }}">Documents</a></li>
                                <li class="breadcrumb-item" aria-current="page">Work Periods</li>
                            </ul>
                        </div>
                        <div class="col-auto d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-light" onclick="window.location.reload()" title="Refresh">
                                <i class="ti ti-refresh"></i>
                            </button>
                            @if(Auth::user()->hasPermissionTo('manage work periods'))
                                <a href="{{ route('documents.index', ['review_status' => 'draft']) }}" class="btn btn-info btn-sm">
                                    <i class="ti ti-file-search me-1"></i> Retrieve Converted Docs
                                </a>
                                <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#reopenAndResetModal">
                                    <i class="ti ti-rotate-clockwise me-1"></i> Reopen + Reset Docs
                                </button>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createWorkPeriodModal">
                                    <i class="ti ti-plus-circle me-1"></i> Add Work Period
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Current Period Info -->
    @if($currentPeriod)
    <div class="row">
        <div class="col-md-12">
            <div class="card border-primary shadow-none">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="mb-1">
                                <i class="ti ti-calendar me-2 text-primary"></i>
                                Current Period: Week {{ $currentPeriod->week_number }} of {{ $currentPeriod->year }}
                            </h5>
                            <p class="mb-0 text-muted">
                                {{ $currentPeriod->date_range }} |
                                Status: <span class="badge badge-{{ $currentPeriod->status === 'open' ? 'success' : 'secondary' }}">{{ ucfirst($currentPeriod->status) }}</span>
                            </p>
                        </div>
                        <div class="text-right d-flex gap-2">
                            <div class="mb-1">
                                <small class="text-muted">Plan Deadline:</small>
                                <strong class="d-block">{{ $currentPeriod->getPlanDeadlineCutoff()->format('M d, Y H:i') }}</strong>
                            </div>
                            <div>
                                <small class="text-muted">Report Deadline:</small>
                                <strong class="d-block">{{ $currentPeriod->getReportDeadlineCutoff()->format('M d, Y H:i') }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Filters -->
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-none border">
                <div class="card-body">
                    <form method="GET" action="{{ route('work-periods.index') }}" class="row g-3">
                        <div class="col-md-4">
                            <label for="year" class="form-label">Filter by Year</label>
                            <select name="year" id="year" class="form-control">
                                <option value="">All Years</option>
                                @foreach($years as $year)
                                    <option value="{{ $year }}" {{ request('year') == $year ? 'selected' : '' }}>
                                        {{ $year }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="status" class="form-label">Filter by Status</label>
                            <select name="status" id="status" class="form-control">
                                <option value="">All Statuses</option>
                                <option value="open" {{ request('status') == 'open' ? 'selected' : '' }}>Open</option>
                                <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>Closed</option>
                                <option value="archived" {{ request('status') == 'archived' ? 'selected' : '' }}>Archived</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="ti ti-filter me-1"></i> Filter
                            </button>
                            <a href="{{ route('work-periods.index') }}" class="btn btn-light">
                                <i class="ti ti-x me-1"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Work Periods Table -->
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">All Work Periods</h5>
                </div>
                <div class="card-body">
                    @if($periods->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered table-striped">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="text-center" style="width: 50px;">No.</th>
                                        <th>Year</th>
                                        <th>Week</th>
                                        <th>Date Range</th>
                                        <th>Plan Deadline</th>
                                        <th>Report Deadline</th>
                                        <th class="text-center" style="width: 110px;">Status</th>
                                        <th class="text-center" style="width: 110px;">Documents</th>
                                        <th class="text-center" style="width: 150px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($periods as $index => $period)
                                        <tr>
                                            <td class="text-center">{{ ($periods->currentPage() - 1) * $periods->perPage() + $loop->iteration }}</td>
                                            <td><strong>{{ $period->year }}</strong></td>
                                            <td>Week {{ $period->week_number }}</td>
                                            <td>
                                                <small>{{ $period->date_range }}</small>
                                            </td>
                                            <td>
                                                <small>{{ $period->getPlanDeadlineCutoff()->format('M d, Y H:i') }}</small>
                                                @if($period->isPlanDeadlineToday())
                                                    <span class="badge bg-warning ml-1">Today</span>
                                                @elseif($period->isPlanDeadlinePassed())
                                                    <span class="badge bg-light-secondary ml-1">Passed</span>
                                                @else
                                                    <span class="badge bg-info ml-1">{{ $period->getDaysUntilPlanDeadline() }} days</span>
                                                @endif
                                            </td>
                                            <td>
                                                <small>{{ $period->getReportDeadlineCutoff()->format('M d, Y H:i') }}</small>
                                                @if($period->isReportDeadlineToday())
                                                    <span class="badge bg-warning ml-1">Today</span>
                                                @elseif($period->isReportDeadlinePassed())
                                                    <span class="badge bg-light-secondary ml-1">Passed</span>
                                                @else
                                                    <span class="badge bg-info ml-1">{{ $period->getDaysUntilReportDeadline() }} days</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if($period->status === 'open')
                                                    <span class="badge  bg-success">
                                                        <i class="ti ti-circle-check me-1"></i>Open
                                                    </span>
                                                @elseif($period->status === 'closed')
                                                    <span class="badge  bg-secondary">
                                                        <i class="ti ti-lock me-1"></i>Closed
                                                    </span>
                                                @else
                                                    <span class="badge  bg-dark">
                                                        <i class="ti ti-archive me-1"></i>Archived
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-primary">{{ $period->documents()->count() }}</span>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="{{ route('work-periods.show', $period->id) }}"
                                                       class="btn btn-info text-white" title="View">
                                                        <i class="ti ti-eye"></i>
                                                    </a>
                                                    @if(Auth::user()->hasPermissionTo('manage work periods'))
                                                        <a href="{{ route('work-periods.edit', $period->id) }}"
                                                           class="btn btn-primary text-white" title="Edit">
                                                            <i class="ti ti-edit"></i>
                                                        </a>
                                                        @if($period->status === 'open')
                                                            <form action="{{ route('work-periods.close', $period->id) }}"
                                                                  method="POST" class="d-inline"
                                                                  onsubmit="return confirm('Are you sure you want to close this period?');">
                                                                @csrf
                                                                <button type="submit" class="btn btn-secondary" title="Close">
                                                                    <i class="ti ti-lock"></i>
                                                                </button>
                                                            </form>
                                                        @endif
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="mt-3">
                            {{ $periods->links() }}
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="ti ti-calendar fs-1 text-muted"></i>
                            <p class="text-muted mt-3">No work periods found.</p>
                            @if(Auth::user()->hasPermissionTo('manage work periods'))
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createWorkPeriodModal">
                                    <i class="ti ti-plus-circle me-1"></i> Create First Work Period
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Reopen + Reset Modal -->
    @if(Auth::user()->hasPermissionTo('manage work periods'))
    <div class="modal fade" id="reopenAndResetModal" tabindex="-1" role="dialog" aria-labelledby="reopenAndResetModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="POST" action="{{ route('work-periods.reopen-and-reset') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="reopenAndResetModalLabel">
                            <i class="ti ti-alert-triangle me-2 text-warning"></i>
                            Reopen Closed Periods + Reset Weekly Docs
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning mb-3">
                            <strong>Important:</strong> This action affects all staff organization-wide.
                        </div>
                        <ul class="mb-0">
                            <li>All <strong>closed</strong> work periods will be changed to <strong>open</strong>.</li>
                            <li>All weekly plans and weekly reports will be moved to <strong>draft</strong>.</li>
                            <li>Staff will be able to update and re-submit their weekly documents.</li>
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning" onclick="return confirm('Confirm bulk reset for all periods and weekly documents?');">
                            <i class="ti ti-rotate-clockwise me-1"></i> Confirm Reopen + Reset
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    <!-- Create Work Period Modal -->
    @if(Auth::user()->hasPermissionTo('manage work periods'))
    <div class="modal fade" id="createWorkPeriodModal" tabindex="-1" role="dialog" aria-labelledby="createWorkPeriodModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form method="POST" action="{{ route('work-periods.store') }}" id="createPeriodForm">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="createWorkPeriodModalLabel">
                            <i class="ti ti-calendar me-2"></i>Create Work Period
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="ti ti-info me-2"></i>
                            <strong>Important:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Week will run from Monday to Sunday</li>
                                <li>Plan deadline: Saturday 23:59 of the current week (for NEXT week plan)</li>
                                <li>Report deadline: Saturday 23:59 (for THIS week report)</li>
                                <li>Next week is auto-created every Thursday (one week ahead only)</li>
                            </ul>
                        </div>

                        <div class="row">
                            <!-- Year -->
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label for="modal_year" class="form-label">
                                        Year <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="ti ti-calendar"></i>
                                        </span>
                                        <input type="number"
                                            class="form-control @error('year') is-invalid @enderror"
                                            name="year"
                                            id="modal_year"
                                            min="2020"
                                            max="2100"
                                            value="{{ old('year', $nextWeekYear) }}"
                                            readonly
                                            required>
                                    </div>
                                    @error('year')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Week Number -->
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label for="modal_week_number" class="form-label">
                                        Week Number <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="ti ti-hash"></i>
                                        </span>
                                        <input type="number"
                                            class="form-control @error('week_number') is-invalid @enderror"
                                            name="week_number"
                                            id="modal_week_number"
                                            min="1"
                                            max="53"
                                            value="{{ old('week_number', $nextWeekNumber) }}"
                                            readonly
                                            required>
                                    </div>
                                    @error('week_number')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">Week number within the year (1-53)</small>
                                </div>
                            </div>

                            <!-- Week Start Date -->
                            <div class="col-md-12 mb-3">
                                <div class="form-group">
                                    <label for="modal_week_start_date" class="form-label">
                                        Week Start Date (Monday) <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="ti ti-calendar"></i>
                                        </span>
                                        <input type="date"
                                            class="form-control @error('week_start_date') is-invalid @enderror"
                                            name="week_start_date"
                                            id="modal_week_start_date"
                                            value="{{ old('week_start_date', $nextWeekStartDate->format('Y-m-d')) }}"
                                            min="{{ $nextWeekStartDate->format('Y-m-d') }}"
                                            max="{{ $nextWeekStartDate->format('Y-m-d') }}"
                                            readonly
                                            required>
                                    </div>
                                    @error('week_start_date')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">
                                        Fixed to next Monday only (you cannot create two weeks ahead).
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                            <i class="ti ti-x me-1"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-save me-1"></i> Create Work Period
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    <div id="periodScriptData"
        data-has-errors="{{ ($errors->any()) ? '1' : '0' }}"
        data-has-success="{{ session('success') ? '1' : '0' }}"
        data-next-week-year="{{ $nextWeekYear }}"
        data-next-week-number="{{ $nextWeekNumber }}"
        data-next-week-start="{{ $nextWeekStartDate->format('Y-m-d') }}"
        class="d-none"></div>

    @push('scripts')
    <script>
        const periodScriptData = document.getElementById('periodScriptData');
        const hasPeriodFormErrors = periodScriptData?.dataset?.hasErrors === '1';
        const hasPeriodSuccess = periodScriptData?.dataset?.hasSuccess === '1';
        const nextWeekYear = periodScriptData?.dataset?.nextWeekYear;
        const nextWeekNumber = periodScriptData?.dataset?.nextWeekNumber;
        const nextWeekStart = periodScriptData?.dataset?.nextWeekStart;

        const yearInput = document.getElementById('modal_year');
        const weekInput = document.getElementById('modal_week_number');
        const weekStartInput = document.getElementById('modal_week_start_date');

        function enforceNextWeekOnlyValues() {
            if (yearInput && nextWeekYear) {
                yearInput.value = nextWeekYear;
            }
            if (weekInput && nextWeekNumber) {
                weekInput.value = nextWeekNumber;
            }
            if (weekStartInput && nextWeekStart) {
                weekStartInput.value = nextWeekStart;
                weekStartInput.min = nextWeekStart;
                weekStartInput.max = nextWeekStart;
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            enforceNextWeekOnlyValues();

            const modalEl = document.getElementById('createWorkPeriodModal');
            if (modalEl) {
                modalEl.addEventListener('shown.bs.modal', function () {
                    enforceNextWeekOnlyValues();
                });
            }
        });

        // Open modal if there are validation errors
        if (hasPeriodFormErrors) {
            const modalEl = document.getElementById('createWorkPeriodModal');
            if (modalEl && window.bootstrap?.Modal) {
                const modal = new window.bootstrap.Modal(modalEl);
                modal.show();
            }
        }

        // Close modal on successful submission (if no errors)
        if (hasPeriodSuccess) {
            const modalEl = document.getElementById('createWorkPeriodModal');
            if (modalEl && window.bootstrap?.Modal) {
                const modal = window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl);
                modal.hide();
            }
        }
    </script>
    @endpush

</x-app-layout>

