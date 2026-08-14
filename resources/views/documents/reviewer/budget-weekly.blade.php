<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Weekly Budget Review</h5>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('documents.index') }}">Documents</a></li>
                            <li class="breadcrumb-item" aria-current="page">Weekly Budget Review</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Summary stats cards --}}
    <div class="row">
        <div class="col-md-2 col-sm-6 mb-2">
            <div class="card border-start border">
                <div class="card-body">
                    <h6 class="mb-1 text-muted">Pending Items</h6>
                    <h4 class="mb-0">{{ $stats['pending'] }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6 mb-2">
            <div class="card border-start border">
                <div class="card-body">
                    <h6 class="mb-1 text-muted">Approved Items</h6>
                    <h4 class="mb-0">{{ $stats['approved'] }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6 mb-2">
            <div class="card border-start border">
                <div class="card-body">
                    <h6 class="mb-1 text-muted">Rejected Items</h6>
                    <h4 class="mb-0">{{ $stats['rejected'] }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-2">
            <div class="card border-start border">
                <div class="card-body">
                    <h6 class="mb-1 text-muted">Requested Total</h6>
                    <h4 class="mb-0">{{ number_format((float) $stats['requested_total'], 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-12 mb-2">
            <div class="card border-start border">
                <div class="card-body">
                    <h6 class="mb-1 text-muted">Provided Total</h6>
                    <h4 class="mb-0">{{ number_format((float) $stats['provided_total'], 2) }}</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="card tbl-card">
        <div class="card-header bg-white border-bottom">
            <div class="row align-items-center">
                <!-- <div class="col-md-4">
                    <h5 class="mb-0"><i class="ti ti-wallet me-2 text-muted"></i>Weekly Budget Submissions</h5>
                    @if(isset($defaultPeriod) && $defaultPeriod && !request()->filled('period_id'))
                        <small class="text-muted">Showing latest week: Week {{ $defaultPeriod->week_number }} &bull; {{ $defaultPeriod->year }}</small>
                    @endif
                </div> -->
                <div class="col-md-12">
                    <div class="alert alert-light border mb-2 py-2">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <small class="text-muted d-block mb-1">Approval flow</small>
                                <div class="d-flex flex-wrap align-items-center gap-1">
                                    @foreach($approvalFlowSteps as $index => $step)
                                        @if($index > 0)
                                            <span class="text-muted mx-1">→</span>
                                        @endif
                                        <span class="badge bg-light-light border border text-dark">{{ $step['label'] }}</span>
                                    @endforeach
                                </div>
                                <small class="text-muted d-block mt-1">
                                    Each item moves Review → Approve → Authorize.
                                    Final authorizers may authorize directly; earlier steps are auto-completed.
                                </small>
                            </div>
                            @if(! empty($userApprovalActions))
                                <span class="badge bg-light-primary border border-primary">
                                    Your actions: {{ $userApprovalActions }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <form method="GET" action="{{ route('documents.weekly-budget-review') }}" id="budgetFilterForm" class="row g-2">
                        <div class="col-md-3">
                            <select name="user_id" class="form-control" onchange="document.getElementById('budgetFilterForm').submit();">
                                <option value="">All Staff</option>
                                @foreach($usersWithBudgets as $staffUser)
                                    <option value="{{ $staffUser->id }}" {{ request('user_id') == $staffUser->id ? 'selected' : '' }}>
                                        {{ $staffUser->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="period_id" class="form-control" onchange="document.getElementById('budgetFilterForm').submit();">
                                <option value="" {{ empty($activePeriodId) ? 'selected' : '' }}>All Weeks</option>
                                @foreach($periodOptions as $periodOption)
                                    <option value="{{ $periodOption->id }}" {{ ($activePeriodId ?? request('period_id')) == $periodOption->id ? 'selected' : '' }}>
                                        Week {{ $periodOption->week_number }} &bull; {{ $periodOption->year }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="year" class="form-control" onchange="document.getElementById('budgetFilterForm').submit();">
                                <option value="">All Years</option>
                                @foreach($years as $year)
                                    <option value="{{ $year }}" {{ request('year') == $year ? 'selected' : '' }}>{{ $year }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-control" onchange="document.getElementById('budgetFilterForm').submit();">
                                <option value="">All Statuses</option>
                                <option value="pending" {{ $statusFilter === 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="approved" {{ $statusFilter === 'approved' ? 'selected' : '' }}>Approved</option>
                                <option value="rejected" {{ $statusFilter === 'rejected' ? 'selected' : '' }}>Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <a href="{{ route('documents.weekly-budget-review') }}" class="btn btn-outline-secondary w-100">Clear</a>
                        </div>
                    </form>
                    @if(! empty($showingAllPendingWeeks))
                        <div class="alert alert-warning border py-2 mt-2 mb-0">
                            Showing all pending weekly budgets across weeks, oldest first.
                            Finish older items before the current week piles up again.
                        </div>
                    @endif
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        @can('process weekly budgets')
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#midWeekBudgetEntryModal">
                                <i class="ti ti-plus me-1"></i>Add Mid-Week Budget
                            </button>
                        @endcan
                        <a href="{{ route('documents.weekly-budget-review.export.pdf', request()->query()) }}" class="btn btn-sm btn-outline-danger">
                            <i class="ti ti-file-type-pdf me-1"></i>Download PDF
                        </a>
                        <a href="{{ route('documents.weekly-budget-review.export.excel', request()->query()) }}" class="btn btn-sm btn-outline-danger">
                            <i class="ti ti-file-spreadsheet me-1"></i>Download Excel
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body">
            @if(count($grouped) > 0)
                @foreach($grouped as $departmentId => $dept)
                    {{-- Department header --}}
                    <div class="card border mb-4">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="ti ti-building me-1"></i>{{ $dept['department_name'] }}
                            </h6>
                            <div class="text-end">
                                <span class="badge bg-light-light border border text-dark me-2">Requested: {{ number_format($dept['requested_total'], 2) }}</span>
                                <span class="badge bg-light-light border border text-dark">Provided: {{ number_format($dept['provided_total'], 2) }}</span>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            @foreach($dept['staff'] as $userId => $staffData)
                                @php
                                    $staffUser = $staffData['user'];
                                    $staffDocument = $staffData['document'];
                                    $staffItems = $staffData['items'];
                                    $staffSummary = $staffData['summary'];
                                @endphp
                                {{-- Staff sub-header --}}
                                <div class="border-bottom px-3 py-2 bg-white d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>{{ $staffUser?->name ?? 'Unknown' }}</strong>
                                        @if($staffDocument->period)
                                            <small class="text-muted ms-2">
                                                Week {{ $staffDocument->period->week_number }} &bull; {{ $staffDocument->period->date_range }}
                                            </small>
                                        @endif
                                        <a href="{{ route('documents.show', $staffDocument->id) }}" class="btn btn-sm btn-outline-secondary ms-2" title="View plan">
                                            <i class="ti ti-eye"></i>
                                        </a>
                                    </div>
                                    <div class="text-end">
                
                                        @if($staffSummary['pending'] > 0)
                                            <span class="badge bg-light-warning border border-warning text-dark">{{ $staffSummary['pending'] }} pending</span>
                                        @endif
                                        @if($staffSummary['approved'] > 0)
                                            <span class="badge bg-light-success border border-success">{{ $staffSummary['approved'] }} approved</span>
                                        @endif
                                        @if($staffSummary['rejected'] > 0)
                                            <span class="badge bg-light-danger border border-danger">{{ $staffSummary['rejected'] }} rejected</span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Item rows --}}
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead class="thead-secondary">
                                            <tr>
                                                <th style="width: 50px;" class="text-center">#</th>
                                                <th>Item</th>
                                                <th>Description / Purpose</th>
                                                <th class="text-end" style="width: 140px;">Requested</th>
                                                <th class="text-end" style="width: 140px;">Provided</th>
                                                <th style="width: 120px;">Status</th>
                                                <th class="text-center" style="width: 120px;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($staffItems as $idx => $item)
                                                @include('documents.partials.reviewer.budget-item-row', [
                                                    'item' => $item,
                                                    'rowNumber' => $idx + 1,
                                                ])
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr class="fw-bold">
                                                <td colspan="3" class="text-end">Staff Total</td>
                                                <td class="text-end">{{ number_format($staffData['requested_total'], 2) }}</td>
                                                <td class="text-end">{{ number_format($staffData['provided_total'], 2) }}</td>
                                                <td colspan="2"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                @foreach($staffItems as $item)
                                    @include('documents.partials.reviewer.budget-item-modal', ['item' => $item])
                                @endforeach

                            @endforeach
                        </div>
                     
                    </div>
                @endforeach

                <div class="mt-3">
                    {{ $documents->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <i class="ti ti-wallet fs-1 text-muted"></i>
                    <p class="text-muted mb-0 mt-3">No weekly plan budgets found for selected filters.</p>
                </div>
            @endif
        </div>
    </div>

    @can('process weekly budgets')
        <div class="modal fade" id="midWeekBudgetEntryModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('documents.weekly-budget-review.add-midweek-budget') }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add Mid-Week Budget</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Staff</label>
                            <select name="user_id" class="form-control" required>
                                <option value="">Select staff</option>
                                @foreach($financeTargetUsers as $targetUser)
                                    <option value="{{ $targetUser->id }}">{{ $targetUser->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Work Period</label>
                            <select name="period_id" class="form-control" required>
                                @foreach($periodOptions as $periodOption)
                                    <option
                                        value="{{ $periodOption->id }}"
                                        {{ ($currentBudgetEntryPeriod && $periodOption->id === $currentBudgetEntryPeriod->id) ? 'selected' : '' }}>
                                        Week {{ $periodOption->week_number }} • {{ $periodOption->date_range }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Defaults to current week.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amount</label>
                            <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Reason (What For)</label>
                            <textarea name="reason" rows="3" class="form-control" maxlength="2000" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Budget Entry</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modalSelector = '[id^="itemReviewModal_"]';
                const decisionSelector = '.js-budget-decision';

                function toggleDecisionFields(modalEl) {
                    const decisionInput = modalEl.querySelector(decisionSelector);
                    if (!decisionInput) return;

                    const isApproved = decisionInput.value === 'approved';
                    const providedGroup = modalEl.querySelector('.js-provided-amount-group');
                    const providedInput = modalEl.querySelector('.js-provided-amount-input');
                    const noteGroup = modalEl.querySelector('.js-note-group');
                    const noteInput = modalEl.querySelector('.js-note-input');

                    if (providedGroup && providedInput) {
                        providedGroup.style.display = isApproved ? '' : 'none';
                        providedInput.disabled = !isApproved;
                        providedInput.required = isApproved;
                    }

                    if (noteGroup && noteInput) {
                        noteGroup.style.display = isApproved ? 'none' : '';
                        noteInput.disabled = isApproved;
                        noteInput.required = !isApproved;
                    }
                }

                document.querySelectorAll(modalSelector).forEach(function (modalEl) {
                    const decisionInput = modalEl.querySelector(decisionSelector);
                    if (!decisionInput) return;

                    toggleDecisionFields(modalEl);
                    decisionInput.addEventListener('change', function () {
                        toggleDecisionFields(modalEl);
                    });
                });
            });
        </script>
    @endpush
</x-app-layout>
