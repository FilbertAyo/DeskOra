<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Receipts</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}">Inventory</a></li>
                                <li class="breadcrumb-item" aria-current="page">Receipts</li>
                            </ul>
                        </div>
                        <div class="col-auto d-flex align-items-center gap-2">
                            @can('manage inventory receipts')
                                <a href="{{ route('inventory.receipts.create') }}" class="btn btn-primary btn-sm">
                                    <i class="ti ti-circle-plus me-1"></i> New Receipt
                                </a>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card shadow-none border">
                <div class="card-body">
                    <form method="GET" action="{{ route('inventory.receipts.index') }}">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-5">
                                <label class="form-label small text-muted">Search</label>
                                <input type="text" name="search" class="form-control form-control-sm"
                                    value="{{ $filters['search'] ?? '' }}" placeholder="Receipt number or notes...">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small text-muted">Source</label>
                                <select name="source_type" class="form-control form-control-sm">
                                    <option value="">All sources</option>
                                    @foreach($sourceTypeOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(($filters['source_type'] ?? '') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-none border">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered">
                            <thead class="thead-light">
                                <tr>
                                    <th>Receipt #</th>
                                    <th>Date</th>
                                    <th>Source</th>
                                    <th>Linked to</th>
                                    <th>Location</th>
                                    <th class="text-center">Lines</th>
                                    <th>Received by</th>
                                    <th class="text-center" style="width: 120px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($receipts as $receipt)
                                    <tr>
                                        <td class="fw-semibold">{{ $receipt->receipt_number }}</td>
                                        <td>{{ $receipt->received_at?->format('M d, Y') }}</td>
                                        <td>
                                            <span class="badge bg-light-secondary">
                                                {{ $sourceTypeOptions[$receipt->source_type] ?? $receipt->source_type }}
                                            </span>
                                        </td>
                                        <td>
                                            {{ $receipt->supplier?->name
                                                ?? $receipt->donor?->name
                                                ?? $receipt->project?->name
                                                ?? '—' }}
                                        </td>
                                        <td>{{ $receipt->location?->name ?? '—' }}</td>
                                        <td class="text-center">{{ $receipt->lines_count }}</td>
                                        <td>{{ $receipt->receivedByUser?->name ?? '—' }}</td>
                                        <td class="text-center">
                                            <x-table-action-dropdown>
                                                <a class="dropdown-item" href="{{ route('inventory.receipts.show', $receipt) }}">
                                                    <i class="ti ti-eye me-2"></i> View
                                                </a>
                                                @can('addItems', $receipt)
                                                    @if($receipt->isReceivedToday())
                                                        <a class="dropdown-item" href="{{ route('inventory.receipts.add-items', $receipt) }}">
                                                            <i class="ti ti-plus me-2"></i> Add items
                                                        </a>
                                                    @endif
                                                @endcan
                                            </x-table-action-dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <p class="text-muted mb-2">No receipts found.</p>
                                            @can('manage inventory receipts')
                                                <a href="{{ route('inventory.receipts.create') }}" class="btn btn-sm btn-primary">New Receipt</a>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $receipts->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
