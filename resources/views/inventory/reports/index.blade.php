<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Inventory Reports</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}">Inventory</a></li>
                                <li class="breadcrumb-item" aria-current="page">Reports</li>
                            </ul>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('inventory.reports.export.assets') }}" class="btn btn-sm btn-danger">Export assets</a>
                            <a href="{{ route('inventory.reports.export.stock') }}" class="btn btn-sm btn-danger">Export stock</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-4">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom"><h6 class="mb-0">Assets by status</h6></div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        @foreach($statusOptions as $value => $label)
                            <tr>
                                <td>{{ $label }}</td>
                                <td class="text-end fw-semibold">{{ $statusCounts[$value] ?? 0 }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom"><h6 class="mb-0">Assets by condition</h6></div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        @foreach($conditionOptions as $value => $label)
                            <tr>
                                <td>{{ $label }}</td>
                                <td class="text-end fw-semibold">{{ $conditionCounts[$value] ?? 0 }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom"><h6 class="mb-0">Assets by source</h6></div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        @foreach($sourceTypeOptions as $value => $label)
                            <tr>
                                <td>{{ $label }}</td>
                                <td class="text-end fw-semibold">{{ $sourceCounts[$value] ?? 0 }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom"><h6 class="mb-0">Assets by category</h6></div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        @forelse($categoryCounts as $row)
                            <tr>
                                <td>{{ $row->category_name }}</td>
                                <td class="text-end fw-semibold">{{ $row->total }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-muted">No assets yet.</td></tr>
                        @endforelse
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom"><h6 class="mb-0">Operational alerts</h6></div>
                <div class="card-body">
                    <p class="mb-2">Low stock items: <strong>{{ $lowStockCount }}</strong>
                        @if($lowStockCount > 0)
                            <a href="{{ route('inventory.stock-items.index', ['low_stock' => 1]) }}">View</a>
                        @endif
                    </p>
                    <p class="mb-0">Overdue borrows: <strong>{{ $overdueBorrowCount }}</strong>
                        @if($overdueBorrowCount > 0)
                            <a href="{{ route('inventory.borrowing.index', ['tab' => 'overdue']) }}">View</a>
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-none border">
        <div class="card-header bg-white border-bottom">
            <h6 class="mb-0">Missing assets
                @if($latestSession)
                    <span class="fw-normal text-muted">from {{ $latestSession->session_number }} — {{ $latestSession->name }}</span>
                @endif
            </h6>
        </div>
        <div class="card-body">
            @if(! $latestSession)
                <p class="text-muted mb-0">Complete a verification session to generate a missing-assets report.</p>
            @elseif($missingFromLatest->isEmpty())
                <p class="text-muted mb-0">No missing assets in the latest completed session.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>Asset ID</th>
                                <th>Name</th>
                                <th>Last location</th>
                                <th>Responsible</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($missingFromLatest as $row)
                                <tr>
                                    <td><a href="{{ route('inventory.assets.show', $row->asset) }}">{{ $row->asset?->asset_code }}</a></td>
                                    <td>{{ $row->asset?->name }}</td>
                                    <td>{{ $row->asset?->location?->name ?? '—' }}</td>
                                    <td>{{ $row->asset?->responsibleUser?->name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
