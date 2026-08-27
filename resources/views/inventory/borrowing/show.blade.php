<x-app-layout>
    @php
        $item = $borrowRequest;
    @endphp
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">{{ $item->request_number }}</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('inventory.borrowing.index') }}">Borrowing</a></li>
                                <li class="breadcrumb-item" aria-current="page">Details</li>
                            </ul>
                        </div>
                        <a href="{{ route('inventory.borrowing.index') }}" class="btn btn-sm btn-light">
                            <i class="ti ti-arrow-left me-1"></i> Back
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        <div class="col-md-7">
            <div class="card shadow-none border mb-3">
                <div class="card-header bg-white border-bottom d-flex justify-content-between">
                    <h6 class="mb-0">Request Details</h6>
                    <span class="badge bg-light-secondary">{{ $statusOptions[$item->status] ?? $item->status }}</span>
                </div>
                <div class="card-body">
                    <p class="mb-2"><strong>Item:</strong> {{ $item->itemName() }}</p>
                    @if($item->asset)
                        <p class="mb-2"><strong>Asset ID:</strong> <a href="{{ route('inventory.assets.show', $item->asset) }}">{{ $item->asset->asset_code }}</a></p>
                    @endif
                    <p class="mb-2"><strong>Quantity:</strong> {{ $item->quantity }}</p>
                    <p class="mb-2"><strong>Borrower:</strong> {{ $item->borrower?->name ?? '—' }}</p>
                    <p class="mb-2"><strong>Purpose:</strong> {{ $item->purpose }}</p>
                    <p class="mb-2">
                        <strong>Due:</strong> {{ $item->expected_return_date?->format('M d, Y') }}
                        @if($item->isOverdue())
                            <span class="badge bg-danger ms-1">Overdue</span>
                        @endif
                    </p>
                    <p class="mb-0"><strong>Requested by:</strong> {{ $item->requestedBy?->name ?? '—' }} on {{ $item->created_at->format('M d, Y') }}</p>
                </div>
            </div>

            @if($item->reviewed_at)
                <div class="card shadow-none border mb-3">
                    <div class="card-header bg-white border-bottom"><h6 class="mb-0">Review</h6></div>
                    <div class="card-body">
                        <p class="mb-2"><strong>By:</strong> {{ $item->reviewedBy?->name ?? '—' }}</p>
                        <p class="mb-2"><strong>At:</strong> {{ $item->reviewed_at->format('M d, Y h:i A') }}</p>
                        <p class="mb-0"><strong>Notes:</strong> {{ $item->review_notes ?: '—' }}</p>
                    </div>
                </div>
            @endif

            @if($item->issued_at)
                <div class="card shadow-none border mb-3">
                    <div class="card-header bg-white border-bottom"><h6 class="mb-0">Issue / Return</h6></div>
                    <div class="card-body">
                        <p class="mb-2"><strong>Issued by:</strong> {{ $item->issuedBy?->name ?? '—' }} at {{ $item->issued_at->format('M d, Y h:i A') }}</p>
                        @if($item->returned_at)
                            <p class="mb-2"><strong>Returned by:</strong> {{ $item->returnedBy?->name ?? '—' }} at {{ $item->returned_at->format('M d, Y h:i A') }}</p>
                            <p class="mb-2"><strong>Condition:</strong> {{ $returnConditionOptions[$item->return_condition] ?? $item->return_condition }}</p>
                            <p class="mb-2"><strong>Qty returned:</strong> {{ $item->quantity_returned ?? '—' }}</p>
                            <p class="mb-0"><strong>Return notes:</strong> {{ $item->return_notes ?: '—' }}</p>
                        @endif
                    </div>
                </div>
            @endif
        </div>
        <div class="col-md-5">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom"><h6 class="mb-0">Actions</h6></div>
                <div class="card-body">
                    @if($item->status === 'requested')
                        @can('approve', $item)
                            <form method="POST" action="{{ route('inventory.borrowing.approve', $item) }}" class="mb-3">
                                @csrf
                                <label class="form-label">Review notes</label>
                                <textarea name="review_notes" rows="2" class="form-control mb-2"></textarea>
                                <button type="submit" class="btn btn-primary btn-sm" data-busy-label="Approving…">Approve</button>
                            </form>
                            <form method="POST" action="{{ route('inventory.borrowing.reject', $item) }}">
                                @csrf
                                <label class="form-label">Rejection reason <span class="text-danger">*</span></label>
                                <textarea name="review_notes" rows="2" class="form-control mb-2" required></textarea>
                                <button type="submit" class="btn btn-light btn-sm" data-busy-label="Rejecting…">Reject</button>
                            </form>
                        @else
                            <p class="text-muted mb-0">Waiting for review.</p>
                        @endcan
                    @elseif($item->status === 'approved')
                        @can('issue', $item)
                            <form method="POST" action="{{ route('inventory.borrowing.issue', $item) }}">
                                @csrf
                                <p class="text-muted small">Issue this item to the borrower. Asset status will change to Borrowed, or stock quantity will be deducted.</p>
                                <button type="submit" class="btn btn-primary btn-sm" data-busy-label="Issuing…">Issue Item</button>
                            </form>
                        @else
                            <p class="text-muted mb-0">Approved. Waiting to be issued.</p>
                        @endcan
                    @elseif($item->status === 'issued')
                        @can('returnItem', $item)
                            <form method="POST" action="{{ route('inventory.borrowing.return', $item) }}">
                                @csrf
                                <div class="mb-2">
                                    <label class="form-label">Return condition <span class="text-danger">*</span></label>
                                    <select name="return_condition" class="form-control" required>
                                        @foreach($returnConditionOptions as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @if($item->item_type === 'stock')
                                    <div class="mb-2">
                                        <label class="form-label">Quantity returned</label>
                                        <input type="number" min="0" max="{{ $item->quantity }}" name="quantity_returned"
                                            class="form-control" value="{{ $item->quantity }}">
                                    </div>
                                @endif
                                <div class="mb-2">
                                    <label class="form-label">Return notes</label>
                                    <textarea name="return_notes" rows="2" class="form-control"></textarea>
                                </div>
                                <p class="text-muted small">Damaged asset returns are sent to Under Repair. Missing sets status to Missing.</p>
                                <button type="submit" class="btn btn-primary btn-sm" data-busy-label="Saving…">Record Return</button>
                            </form>
                        @else
                            <p class="text-muted mb-0">Issued. Waiting for return.</p>
                        @endcan
                    @else
                        <p class="text-muted mb-0">No further actions on this request.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
