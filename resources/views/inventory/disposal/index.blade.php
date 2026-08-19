<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Disposal</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}">Inventory</a></li>
                                <li class="breadcrumb-item" aria-current="page">Disposal</li>
                            </ul>
                        </div>
                        @can('manage inventory assets')
                            <a href="{{ route('inventory.disposal.create') }}" class="btn btn-primary btn-sm">Request Disposal</a>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card tbl-card mb-2">
        <div class="card-header bg-white border-bottom">
            @include('inventory.partials.tabs', [
                'current' => $tab,
                'tabs' => [
                    ['key' => 'pending', 'label' => 'Pending Review', 'url' => route('inventory.disposal.index', ['tab' => 'pending']), 'count' => $counts['pending'], 'alert' => true],
                    ['key' => 'approved', 'label' => 'Approved', 'url' => route('inventory.disposal.index', ['tab' => 'approved']), 'count' => $counts['approved']],
                    ['key' => 'rejected', 'label' => 'Rejected', 'url' => route('inventory.disposal.index', ['tab' => 'rejected']), 'count' => $counts['rejected']],
                    ['key' => 'all', 'label' => 'All', 'url' => route('inventory.disposal.index', ['tab' => 'all']), 'count' => $counts['all']],
                ],
            ])
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered">
                    <thead class="thead-light">
                        <tr>
                            <th>Request #</th>
                            <th>Asset</th>
                            <th>Method</th>
                            <th>Requested by</th>
                            <th class="text-center">Approval</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $item)
                            <tr>
                                <td class="fw-semibold">{{ $item->request_number }}</td>
                                <td>{{ $item->asset?->asset_code }} — {{ $item->asset?->name }}</td>
                                <td>{{ $methodOptions[$item->method] ?? $item->method }}</td>
                                <td>{{ $item->requestedBy?->name ?? '—' }}</td>
                                <td class="text-center">
                                    <span class="badge bg-light-secondary">
                                        @if($item->status === 'requested')
                                            Awaiting review
                                        @elseif($item->status === 'approved')
                                            Approved
                                        @else
                                            Rejected
                                        @endif
                                    </span>
                                </td>
                                <td class="text-center">
                                    <x-table-action-dropdown>
                                        <a class="dropdown-item" href="{{ route('inventory.disposal.show', $item) }}"><i class="ti ti-eye me-2"></i> View</a>
                                    </x-table-action-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <p class="text-muted mb-2">No disposal requests found.</p>
                                    @can('manage inventory assets')
                                        <a href="{{ route('inventory.disposal.create') }}" class="btn btn-sm btn-primary">Request Disposal</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $requests->links() }}</div>
        </div>
    </div>
</x-app-layout>
