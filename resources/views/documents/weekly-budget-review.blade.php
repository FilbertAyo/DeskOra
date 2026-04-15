<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Weekly Budget Review</h5>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item"><a href="javascript:void(0)">Documents</a></li>
                            <li class="breadcrumb-item" aria-current="page">Weekly Budget Review</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-2 col-sm-6 mb-2">
            <div class="card border-start border h-100">
                <div class="card-body py-3">
                    <h6 class="mb-1 text-muted">Pending</h6>
                    <h4 class="mb-0">{{ $stats['pending'] }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6 mb-2">
            <div class="card border-start border h-100">
                <div class="card-body py-3">
                    <h6 class="mb-1 text-muted">Approved</h6>
                    <h4 class="mb-0">{{ $stats['approved'] }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6 mb-2">
            <div class="card border-start border h-100">
                <div class="card-body py-3">
                    <h6 class="mb-1 text-muted">Rejected</h6>
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
                                        Week {{ $periodOption->week_number }} • {{ $periodOption->year }}
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
            @if($documents->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th class="text-center" style="width: 55px;">No.</th>
                                <th>Staff</th>
                                <th>Week</th>
                                <th class="text-end">Requested Amount</th>
                                <th class="text-end">Provided Amount</th>
                                <th>Status</th>
                                <th>Reviewed By</th>
                                <th>Reviewed At</th>
                                <th class="text-center" style="width: 250px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($documents as $document)
                                @php
                                    $status = $document->budget_review_status_label;
                                    $isPending = $document->budget_review_status === null || $document->budget_review_status === \App\Models\Document::BUDGET_REVIEW_PENDING;
                                    $requestedAmount = collect($document->content['budget'] ?? [])->sum(function ($row) {
                                        if (! is_array($row)) {
                                            return 0;
                                        }
                                        $normalized = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', (string) ($row['amount'] ?? '0')));
                                        return is_numeric($normalized) ? (float) $normalized : 0;
                                    });
                                @endphp
                                <tr>
                                    <td class="text-center">{{ ($documents->currentPage() - 1) * $documents->perPage() + $loop->iteration }}</td>
                                    <td>{{ $document->user?->name ?? '-' }}</td>
                                    <td>
                                        @if($document->period)
                                            Week {{ $document->period->week_number }} • {{ $document->period->date_range }}
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ number_format($requestedAmount, 2) }}</td>
                                    <td class="text-end">{{ number_format((float) ($document->budget_provided_amount ?? 0), 2) }}</td>
                                    <td>
                                        @if($status === 'Approved')
                                            <span class="badge bg-success">Approved</span>
                                        @elseif($status === 'Rejected')
                                            <span class="badge bg-danger">Rejected</span>
                                        @else
                                            <span class="badge bg-warning">Pending</span>
                                        @endif
                                    </td>
                                    <td>{{ $document->budgetReviewedBy?->name ?? '-' }}</td>
                                    <td>{{ $document->budget_reviewed_at?->format('M d, Y H:i') ?? '-' }}</td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-2">
                                            <a href="{{ route('documents.show', $document->id) }}" class="btn btn-sm btn-info text-white">
                                                <i class="ti ti-eye"></i>
                                            </a>
                                            @can('process weekly budgets')
                                                <button
                                                    type="button"
                                                    class="btn btn-sm {{ $isPending ? 'btn-primary' : 'btn-outline-primary' }}"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#budgetReviewModal{{ $document->id }}">
                                                    Process
                                                </button>
                                            @endcan
                                        </div>
                                        @can('process weekly budgets')
                                            <div class="modal fade text-start" id="budgetReviewModal{{ $document->id }}" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <form method="POST" action="{{ route('documents.process-weekly-budget-review', $document->id) }}" class="modal-content">
                                                        @csrf
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Process Weekly Budget</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label class="form-label">Decision</label>
                                                                <select name="decision" class="form-control" required>
                                                                    <option value="approved">Approve</option>
                                                                    <option value="rejected">Reject</option>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Provided Amount</label>
                                                                <input
                                                                    type="number"
                                                                    step="0.01"
                                                                    min="0"
                                                                    name="provided_amount"
                                                                    value="{{ $document->budget_provided_amount ?? number_format($requestedAmount, 2, '.', '') }}"
                                                                    class="form-control"
                                                                    required>
                                                            </div>
                                                            <div class="mb-0">
                                                                <label class="form-label">Note</label>
                                                                <textarea name="note" rows="3" class="form-control" placeholder="Reason (required on rejection)">{{ $document->budget_review_note }}</textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                                                            <button type="submit" class="btn btn-primary">Save Decision</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        @endcan
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
                    <i class="ti ti-wallet fs-1 text-muted"></i>
                    <p class="text-muted mb-0 mt-3">No weekly plan budgets found for selected filters.</p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
