<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Add Items to {{ $receipt->receipt_number }}</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('inventory.receipts.index') }}">Receipts</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('inventory.receipts.show', $receipt) }}">{{ $receipt->receipt_number }}</a></li>
                                <li class="breadcrumb-item" aria-current="page">Add Items</li>
                            </ul>
                        </div>
                        <a href="{{ route('inventory.receipts.show', $receipt) }}" class="btn btn-sm btn-light">
                            <i class="ti ti-arrow-left me-1"></i> Back
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>Please fix the following errors:</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow-none border mb-3">
        <div class="card-header bg-white border-bottom">
            <h5 class="mb-0"><i class="ti ti-receipt me-2"></i>Receipt Details</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <p class="mb-1 small text-muted">Source</p>
                    <p class="mb-0 fw-semibold">{{ $sourceTypeOptions[$receipt->source_type] ?? $receipt->source_type }}</p>
                </div>
                <div class="col-md-4">
                    <p class="mb-1 small text-muted">Date Received</p>
                    <p class="mb-0 fw-semibold">{{ $receipt->received_at?->format('M d, Y') }}</p>
                </div>
                <div class="col-md-4">
                    <p class="mb-1 small text-muted">Location</p>
                    <p class="mb-0 fw-semibold">{{ $receipt->location?->pathLabel() ?? '—' }}</p>
                </div>
                <div class="col-md-4">
                    <p class="mb-1 small text-muted">Linked to</p>
                    <p class="mb-0 fw-semibold">
                        {{ $receipt->supplier?->name ?? $receipt->donor?->name ?? $receipt->project?->name ?? '—' }}
                    </p>
                </div>
                <div class="col-md-8">
                    <p class="mb-1 small text-muted">Notes</p>
                    <p class="mb-0">{{ $receipt->notes ?: '—' }}</p>
                </div>
            </div>
        </div>
    </div>

    @if($receipt->lines->isNotEmpty())
        <div class="card shadow-none border mb-3">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0">Already on this receipt</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Name</th>
                                <th class="text-center">Qty</th>
                                <th>Category</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($receipt->lines as $line)
                                <tr>
                                    <td>{{ $line->item_type === 'asset' ? 'Asset' : 'Stock' }}</td>
                                    <td>{{ $line->name }}</td>
                                    <td class="text-center">{{ $line->quantity }}</td>
                                    <td>{{ $line->category?->name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('inventory.receipts.store-items', $receipt) }}" id="receiptForm">
        @csrf

        <div class="card shadow-none border mb-3">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="ti ti-list me-2"></i>Items to Add</h5>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addLineBtn">
                    <i class="ti ti-plus me-1"></i> Add Line
                </button>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Extra items stay on this receipt. Existing lines are not changed.
                </p>
                <div id="receiptLines"></div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('inventory.receipts.show', $receipt) }}" class="btn btn-light">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <i class="ti ti-device-floppy me-1"></i> Add Items
            </button>
        </div>
    </form>

    @include('inventory.receipts.partials.line-editor')
</x-app-layout>
