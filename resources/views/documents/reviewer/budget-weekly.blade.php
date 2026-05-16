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
                <div class="col-md-4">
                    <h5 class="mb-0"><i class="ti ti-wallet me-2 text-muted"></i>Weekly Budget Submissions</h5>
                    @if(isset($defaultPeriod) && $defaultPeriod && !request()->filled('period_id'))
                        <small class="text-muted">Showing latest week: Week {{ $defaultPeriod->week_number }} &bull; {{ $defaultPeriod->year }}</small>
                    @endif
                </div>
                <div class="col-md-8">
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
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        @can('process weekly budgets')
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#midWeekBudgetEntryModal">
                                <i class="ti ti-plus me-1"></i>Add Mid-Week Budget
                            </button>
                        @endcan
                        <a href="{{ route('documents.weekly-budget-review.export.pdf', request()->query()) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="ti ti-file-type-pdf me-1"></i>Download PDF
                        </a>
                        <a href="{{ route('documents.weekly-budget-review.export.excel', request()->query()) }}" class="btn btn-sm btn-outline-secondary">
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
                                <span class="badge bg-light text-dark border me-2">Requested: {{ number_format($dept['requested_total'], 2) }}</span>
                                <span class="badge bg-light text-dark border">Provided: {{ number_format($dept['provided_total'], 2) }}</span>
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
                                        <span class="text-muted me-2">{{ count($staffItems) }} item(s)</span>
                                        <span class="badge bg-light text-dark border me-1">Req: {{ number_format($staffData['requested_total'], 2) }}</span>
                                        <span class="badge bg-light text-dark border me-1">Prov: {{ number_format($staffData['provided_total'], 2) }}</span>
                                        @if($staffSummary['pending'] > 0)
                                            <span class="badge bg-secondary">{{ $staffSummary['pending'] }} pending</span>
                                        @endif
                                        @if($staffSummary['approved'] > 0)
                                            <span class="badge bg-secondary">{{ $staffSummary['approved'] }} approved</span>
                                        @endif
                                        @if($staffSummary['rejected'] > 0)
                                            <span class="badge bg-dark">{{ $staffSummary['rejected'] }} rejected</span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Item rows --}}
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered table-striped mb-0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th style="width: 50px;" class="text-center">#</th>
                                                <th>Item</th>
                                                <th>Description / Purpose</th>
                                                <th class="text-end" style="width: 140px;">Requested</th>
                                                <th class="text-end" style="width: 140px;">Provided</th>
                                                <th style="width: 120px;">Status</th>
                                                <!-- <th>Note</th> -->
                                                <th class="text-center" style="width: 120px;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($staffItems as $idx => $item)
                                                @php
                                                    $isPending = ($item['decision'] ?? 'pending') === \App\Models\Document::BUDGET_REVIEW_PENDING;
                                                    $isApproved = ($item['decision'] ?? '') === \App\Models\Document::BUDGET_REVIEW_APPROVED;
                                                    $isRejected = ($item['decision'] ?? '') === \App\Models\Document::BUDGET_REVIEW_REJECTED;
                                                    $approvalStage = $item['approval_stage'] ?? \App\Models\Document::BUDGET_REVIEW_STAGE_PENDING;
                                                    $isFinalApproved = $isApproved && $approvalStage === \App\Models\Document::BUDGET_REVIEW_STAGE_AUTHORIZED;
                                                    $nextStage = match ($approvalStage) {
                                                        \App\Models\Document::BUDGET_REVIEW_STAGE_PENDING => 'review',
                                                        \App\Models\Document::BUDGET_REVIEW_STAGE_REVIEWED => 'approve',
                                                        \App\Models\Document::BUDGET_REVIEW_STAGE_APPROVED => 'authorize',
                                                        default => null,
                                                    };
                                                    $canProcessStage = $nextStage && (
                                                        auth()->user()?->can('process weekly budgets')
                                                        || ($nextStage === 'review' && auth()->user()?->can('review weekly budgets'))
                                                        || ($nextStage === 'approve' && auth()->user()?->can('approve weekly budgets'))
                                                        || ($nextStage === 'authorize' && auth()->user()?->can('authorize weekly budgets'))
                                                    );
                                                @endphp
                                                <tr>
                                                    <td class="text-center">{{ $idx + 1 }}</td>
                                                    <td>{{ $item['item'] }}</td>
                                                    <td>{{ $item['description_purpose'] }}</td>
                                                    <td class="text-end">{{ $item['amount'] }}</td>
                                                    <td class="text-end">
                                                        @if($isFinalApproved)
                                                            {{ number_format($item['provided_amount'], 2) }}
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($isFinalApproved)
                                                            <span class="badge bg-dark">Approved</span>
                                                        @elseif($isRejected)
                                                            <span class="badge bg-dark">Rejected</span>
                                                        @elseif($approvalStage === \App\Models\Document::BUDGET_REVIEW_STAGE_APPROVED)
                                                            <span class="badge bg-secondary">Pending Authorization</span>
                                                        @elseif($approvalStage === \App\Models\Document::BUDGET_REVIEW_STAGE_REVIEWED)
                                                            <span class="badge bg-secondary">Pending Approval</span>
                                                        @else
                                                            <span class="badge bg-secondary">Pending Review</span>
                                                        @endif
                                                    </td>
                                                    <!-- <td>
                                                        <small>{{ $item['note'] ?? '' }}</small>
                                                    </td> -->
                                                    <td class="text-center">
                                                        @if($canProcessStage)
                                                            <button
                                                                type="button"
                                                                class="btn btn-sm btn-outline-secondary"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#itemReviewModal_{{ $item['document_id'] }}_{{ $item['budget_uid'] }}">
                                                                {{ ucfirst($nextStage) }}
                                                            </button>
                                                        @endif
                                                    </td>
                                                </tr>
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

                                {{-- Per-item review modals --}}
                                @foreach($staffItems as $item)
                                    @php
                                        $approvalStage = $item['approval_stage'] ?? \App\Models\Document::BUDGET_REVIEW_STAGE_PENDING;
                                        $nextStage = match ($approvalStage) {
                                            \App\Models\Document::BUDGET_REVIEW_STAGE_PENDING => 'review',
                                            \App\Models\Document::BUDGET_REVIEW_STAGE_REVIEWED => 'approve',
                                            \App\Models\Document::BUDGET_REVIEW_STAGE_APPROVED => 'authorize',
                                            default => null,
                                        };
                                        $canProcessStage = $nextStage && (
                                            auth()->user()?->can('process weekly budgets')
                                            || ($nextStage === 'review' && auth()->user()?->can('review weekly budgets'))
                                            || ($nextStage === 'approve' && auth()->user()?->can('approve weekly budgets'))
                                            || ($nextStage === 'authorize' && auth()->user()?->can('authorize weekly budgets'))
                                        );
                                    @endphp
                                    @if($canProcessStage)
                                        <div class="modal fade" id="itemReviewModal_{{ $item['document_id'] }}_{{ $item['budget_uid'] }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <form method="POST" action="{{ route('documents.process-weekly-budget-review', $item['document_id']) }}" class="modal-content">
                                                    @csrf
                                                    <input type="hidden" name="budget_uid" value="{{ $item['budget_uid'] }}">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">{{ ucfirst($nextStage) }} Budget Item</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label text-muted">Item</label>
                                                            <div class="fw-semibold">{{ $item['item'] }}</div>
                                                            @if($item['description_purpose'])
                                                                <small class="text-muted">{{ $item['description_purpose'] }}</small>
                                                            @endif
                                                            <div class="mt-1"><strong>Requested:</strong> {{ $item['amount'] }}</div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Decision</label>
                                                            <select name="decision" class="form-control js-budget-decision" required>
                                                                <option value="approved" {{ ($item['decision'] ?? '') === 'approved' ? 'selected' : '' }}>Approve</option>
                                                                <option value="rejected" {{ ($item['decision'] ?? '') === 'rejected' ? 'selected' : '' }}>Reject</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3 js-provided-amount-group">
                                                            <label class="form-label">Provided Amount</label>
                                                            <input
                                                                type="number"
                                                                step="0.01"
                                                                min="0"
                                                                name="provided_amount"
                                                                value="{{ ($item['decision'] ?? '') === 'approved' ? number_format($item['provided_amount'], 2, '.', '') : number_format($item['amount_numeric'], 2, '.', '') }}"
                                                                class="form-control js-provided-amount-input">
                                                            <small class="text-muted">Visible only when decision is approve.</small>
                                                        </div>
                                                        <div class="mb-0 js-note-group">
                                                            <label class="form-label">Note</label>
                                                            <textarea name="note" rows="3" class="form-control js-note-input" placeholder="Optional reason for rejection">{{ $item['note'] ?? '' }}</textarea>
                                                            <small class="text-muted">Visible only when decision is reject.</small>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" class="btn btn-secondary">Save {{ ucfirst($nextStage) }} Decision</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            @endforeach
                        </div>
                        {{-- Department total footer --}}
                        <div class="card-footer bg-light d-flex justify-content-end">
                            <strong class="me-3">Department Total Requested: {{ number_format($dept['requested_total'], 2) }}</strong>
                            <strong>Department Total Provided: {{ number_format($dept['provided_total'], 2) }}</strong>
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
