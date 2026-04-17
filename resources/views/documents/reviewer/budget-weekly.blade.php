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
    <div class="row mb-3">
        <div class="col-md-2 col-sm-6 mb-2">
            <div class="card border-start border h-100">
                <div class="card-body py-3">
                    <h6 class="mb-1 text-muted">Pending Items</h6>
                    <h4 class="mb-0">{{ $stats['pending'] }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6 mb-2">
            <div class="card border-start border h-100">
                <div class="card-body py-3">
                    <h6 class="mb-1 text-muted">Approved Items</h6>
                    <h4 class="mb-0">{{ $stats['approved'] }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6 mb-2">
            <div class="card border-start border h-100">
                <div class="card-body py-3">
                    <h6 class="mb-1 text-muted">Rejected Items</h6>
                    <h4 class="mb-0">{{ $stats['rejected'] }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-2">
            <div class="card border-start border h-100">
                <div class="card-body py-3">
                    <h6 class="mb-1 text-muted">Requested Total</h6>
                    <h4 class="mb-0">{{ number_format((float) $stats['requested_total'], 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-12 mb-2">
            <div class="card border-start border h-100">
                <div class="card-body py-3">
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
                    <h5 class="mb-0"><i class="ti ti-wallet me-2 text-primary"></i>Weekly Budget Submissions</h5>
                    @if(isset($defaultPeriod) && $defaultPeriod && !request()->filled('period_id'))
                        <small class="text-muted">Showing next week: Week {{ $defaultPeriod->week_number }} &bull; {{ $defaultPeriod->year }}</small>
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
                                <option value="">All Weeks</option>
                                @foreach($periodOptions as $periodOption)
                                    <option value="{{ $periodOption->id }}" {{ request('period_id') == $periodOption->id ? 'selected' : '' }}>
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
                                <span class="badge bg-secondary me-2">Requested: {{ number_format($dept['requested_total'], 2) }}</span>
                                <span class="badge bg-primary">Provided: {{ number_format($dept['provided_total'], 2) }}</span>
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
                                        <a href="{{ route('documents.show', $staffDocument->id) }}" class="btn btn-sm btn-outline-info ms-2" title="View plan">
                                            <i class="ti ti-eye"></i>
                                        </a>
                                    </div>
                                    <div class="text-end">
                                        <span class="text-muted me-2">{{ count($staffItems) }} item(s)</span>
                                        <span class="badge bg-secondary me-1">Req: {{ number_format($staffData['requested_total'], 2) }}</span>
                                        <span class="badge bg-primary me-1">Prov: {{ number_format($staffData['provided_total'], 2) }}</span>
                                        @if($staffSummary['pending'] > 0)
                                            <span class="badge bg-warning">{{ $staffSummary['pending'] }} pending</span>
                                        @endif
                                        @if($staffSummary['approved'] > 0)
                                            <span class="badge bg-success">{{ $staffSummary['approved'] }} approved</span>
                                        @endif
                                        @if($staffSummary['rejected'] > 0)
                                            <span class="badge bg-danger">{{ $staffSummary['rejected'] }} rejected</span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Item rows --}}
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th style="width: 50px;" class="text-center">#</th>
                                                <th>Item</th>
                                                <th>Description / Purpose</th>
                                                <th class="text-end" style="width: 140px;">Requested</th>
                                                <th class="text-end" style="width: 140px;">Provided</th>
                                                <th style="width: 120px;">Status</th>
                                                <th>Note</th>
                                                <th class="text-center" style="width: 120px;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($staffItems as $idx => $item)
                                                @php
                                                    $isPending = ($item['decision'] ?? 'pending') === \App\Models\Document::BUDGET_REVIEW_PENDING;
                                                    $isApproved = ($item['decision'] ?? '') === \App\Models\Document::BUDGET_REVIEW_APPROVED;
                                                    $isRejected = ($item['decision'] ?? '') === \App\Models\Document::BUDGET_REVIEW_REJECTED;
                                                @endphp
                                                <tr>
                                                    <td class="text-center">{{ $idx + 1 }}</td>
                                                    <td>{{ $item['item'] }}</td>
                                                    <td>{{ $item['description_purpose'] }}</td>
                                                    <td class="text-end">{{ $item['amount'] }}</td>
                                                    <td class="text-end">
                                                        @if($isApproved)
                                                            {{ number_format($item['provided_amount'], 2) }}
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($isApproved)
                                                            <span class="badge bg-success">Approved</span>
                                                        @elseif($isRejected)
                                                            <span class="badge bg-danger">Rejected</span>
                                                        @else
                                                            <span class="badge bg-warning">Pending</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <small>{{ $item['note'] ?? '' }}</small>
                                                    </td>
                                                    <td class="text-center">
                                                        @can('process weekly budgets')
                                                            <button
                                                                type="button"
                                                                class="btn btn-sm {{ $isPending ? 'btn-primary' : 'btn-outline-primary' }}"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#itemReviewModal_{{ $item['document_id'] }}_{{ $item['budget_uid'] }}">
                                                                {{ $isPending ? 'Process' : 'Edit' }}
                                                            </button>
                                                        @endcan
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr class="fw-bold">
                                                <td colspan="3" class="text-end">Staff Total</td>
                                                <td class="text-end">{{ number_format($staffData['requested_total'], 2) }}</td>
                                                <td class="text-end">{{ number_format($staffData['provided_total'], 2) }}</td>
                                                <td colspan="3"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                {{-- Per-item review modals --}}
                                @can('process weekly budgets')
                                    @foreach($staffItems as $item)
                                        <div class="modal fade" id="itemReviewModal_{{ $item['document_id'] }}_{{ $item['budget_uid'] }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <form method="POST" action="{{ route('documents.process-weekly-budget-review', $item['document_id']) }}" class="modal-content">
                                                    @csrf
                                                    <input type="hidden" name="budget_uid" value="{{ $item['budget_uid'] }}">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Review Budget Item</h5>
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
                                                            <select name="decision" class="form-control" required>
                                                                <option value="approved" {{ ($item['decision'] ?? '') === 'approved' ? 'selected' : '' }}>Approve</option>
                                                                <option value="rejected" {{ ($item['decision'] ?? '') === 'rejected' ? 'selected' : '' }}>Reject</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Provided Amount</label>
                                                            <input
                                                                type="number"
                                                                step="0.01"
                                                                min="0"
                                                                name="provided_amount"
                                                                value="{{ ($item['decision'] ?? '') === 'approved' ? number_format($item['provided_amount'], 2, '.', '') : number_format($item['amount_numeric'], 2, '.', '') }}"
                                                                class="form-control">
                                                        </div>
                                                        <div class="mb-0">
                                                            <label class="form-label">Note</label>
                                                            <textarea name="note" rows="3" class="form-control" placeholder="Reason (required on rejection)">{{ $item['note'] ?? '' }}</textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" class="btn btn-primary">Save Decision</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    @endforeach
                                @endcan
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
</x-app-layout>
