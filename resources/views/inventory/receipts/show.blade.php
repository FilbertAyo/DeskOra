<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">{{ $receipt->receipt_number }}</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('inventory.receipts.index') }}">Receipts</a></li>
                                <li class="breadcrumb-item" aria-current="page">Details</li>
                            </ul>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            @can('addItems', $receipt)
                                @if($receipt->isReceivedToday())
                                    <a href="{{ route('inventory.receipts.add-items', $receipt) }}" class="btn btn-sm btn-primary">
                                        <i class="ti ti-plus me-1"></i> Add items
                                    </a>
                                @endif
                            @endcan
                            <a href="{{ route('inventory.receipts.index') }}" class="btn btn-sm btn-light">
                                <i class="ti ti-arrow-left me-1"></i> Back
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-none border mb-3">
                <div class="card-header bg-white border-bottom"><h6 class="mb-0">Receipt Summary</h6></div>
                <div class="card-body">
                    <p class="mb-2"><strong>Number:</strong> {{ $receipt->receipt_number }}</p>
                    <p class="mb-2"><strong>Date:</strong> {{ $receipt->received_at?->format('M d, Y') }}</p>
                    <p class="mb-2">
                        <strong>Source:</strong>
                        <span class="badge bg-light-secondary">
                            {{ $sourceTypeOptions[$receipt->source_type] ?? $receipt->source_type }}
                        </span>
                    </p>
                    <p class="mb-2"><strong>Supplier:</strong> {{ $receipt->supplier?->name ?? '—' }}</p>
                    <p class="mb-2"><strong>Donor:</strong> {{ $receipt->donor?->name ?? '—' }}</p>
                    <p class="mb-2"><strong>Project:</strong> {{ $receipt->project?->name ?? '—' }}</p>
                    <p class="mb-2"><strong>Location:</strong> {{ $receipt->location?->name ?? '—' }}</p>
                    <p class="mb-2"><strong>Received by:</strong> {{ $receipt->receivedByUser?->name ?? '—' }}</p>
                    <p class="mb-0"><strong>Notes:</strong> {{ $receipt->notes ?: '—' }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card shadow-none border mb-3">
                <div class="card-header bg-white border-bottom"><h6 class="mb-0">Lines</h6></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Name</th>
                                    <th class="text-center">Qty</th>
                                    <th>Category</th>
                                    <th>Project</th>
                                    <th>Result</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($receipt->lines as $line)
                                    <tr>
                                        <td>
                                            <span class="badge bg-light-secondary">
                                                {{ $line->item_type === 'asset' ? 'Asset' : 'Stock' }}
                                            </span>
                                        </td>
                                        <td>{{ $line->name }}</td>
                                        <td class="text-center">{{ $line->quantity }}</td>
                                        <td>{{ $line->category?->name ?? '—' }}</td>
                                        <td>{{ $line->project?->name ?? '—' }}</td>
                                        <td>
                                            @if($line->item_type === 'stock' && $line->stockItem)
                                                <a href="{{ route('inventory.stock-items.index') }}">{{ $line->stockItem->name }}</a>
                                            @else
                                                Asset
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if($receipt->assets->isNotEmpty())
                <div class="card shadow-none border">
                    <div class="card-header bg-white border-bottom"><h6 class="mb-0">Assets Created</h6></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Asset ID</th>
                                        <th>Name</th>
                                        <th>Serial</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($receipt->assets as $asset)
                                        <tr>
                                            <td class="fw-semibold">{{ $asset->asset_code }}</td>
                                            <td>{{ $asset->name }}</td>
                                            <td>{{ $asset->serial_number ?: '—' }}</td>
                                            <td class="text-center">
                                                <a href="{{ route('inventory.assets.show', $asset) }}" class="btn btn-sm btn-outline-primary">View</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
