<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">My items</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item" aria-current="page">My items</li>
                            </ul>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            @if($tab === 'stock')
                                <a href="{{ route('inventory.my-items.export', ['type' => 'stock', 'format' => 'xlsx']) }}" class="btn btn-danger btn-sm">
                                    <i class="ti ti-file-spreadsheet me-1"></i> Excel
                                </a>
                                <a href="{{ route('inventory.my-items.export', ['type' => 'stock', 'format' => 'pdf']) }}" class="btn btn-danger btn-sm">
                                    <i class="ti ti-file-type-pdf me-1"></i> PDF
                                </a>
                            @else
                                <a href="{{ route('inventory.my-items.export', ['type' => 'assets', 'format' => 'xlsx']) }}" class="btn btn-danger btn-sm">
                                    <i class="ti ti-file-spreadsheet me-1"></i> Excel
                                </a>
                                <a href="{{ route('inventory.my-items.export', ['type' => 'assets', 'format' => 'pdf']) }}" class="btn btn-danger btn-sm">
                                    <i class="ti ti-file-type-pdf me-1"></i> PDF
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <p class="text-muted">Items assigned to you personally, plus items assigned to your whole department or sub-department.</p>

    @include('inventory.partials.tabs', [
        'current' => $tab,
        'tabs' => [
            ['key' => 'assets', 'label' => 'Assets', 'url' => route('inventory.my-items.index', ['tab' => 'assets']), 'count' => $assets->total()],
            ['key' => 'stock', 'label' => 'Issued stock', 'url' => route('inventory.my-items.index', ['tab' => 'stock']), 'count' => $issues->total()],
        ],
    ])

    <div class="card shadow-none border mt-3">
        <div class="card-body">
            @if($tab === 'stock')
                <div class="table-responsive">
                    <table class="table table-hover table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>Item</th>
                                <th class="text-center">Quantity</th>
                                <th>Issued to</th>
                                <th>Location</th>
                                <th>Purpose</th>
                                <th>Issued on</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($issues as $issue)
                                <tr>
                                    <td class="fw-semibold">{{ $issue->stockItem?->name ?? '—' }}</td>
                                    <td class="text-center">{{ $issue->quantity }}</td>
                                    <td>
                                        @if($issue->issuedToUser && $issue->issuedToDepartment)
                                            {{ $issue->issuedToDepartment->pathLabel() }} → {{ $issue->issuedToUser->name }}
                                        @elseif($issue->issuedToUser)
                                            {{ $issue->issuedToUser->name }}
                                        @elseif($issue->issuedToDepartment)
                                            {{ $issue->issuedToDepartment->pathLabel() }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $issue->stockItem?->location?->pathLabel() ?? '—' }}</td>
                                    <td>{{ $issue->purpose ?: '—' }}</td>
                                    <td>{{ $issue->issued_at?->format('M d, Y') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No stock has been issued to you or your unit.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $issues->links('pagination::bootstrap-5') }}</div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>Asset ID</th>
                                <th>Name</th>
                                <th>Location</th>
                                <th>Department</th>
                                <th>Responsible</th>
                                <th class="text-center">Condition</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($assets as $asset)
                                <tr>
                                    <td class="fw-semibold">
                                        <a href="{{ route('inventory.assets.show', $asset) }}">{{ $asset->asset_code }}</a>
                                    </td>
                                    <td>{{ $asset->name }}</td>
                                    <td>{{ $asset->location?->pathLabel() ?? '—' }}</td>
                                    <td>{{ $asset->department?->pathLabel() ?? '—' }}</td>
                                    <td>{{ $asset->responsibleUser?->name ?? '—' }}</td>
                                    <td class="text-center"><span class="badge bg-light-secondary">{{ $conditionOptions[$asset->condition] ?? ucfirst($asset->condition) }}</span></td>
                                    <td class="text-center"><span class="badge bg-light-secondary">{{ $statusOptions[$asset->status] ?? ucfirst($asset->status) }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No assets are assigned to you or your unit.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $assets->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
