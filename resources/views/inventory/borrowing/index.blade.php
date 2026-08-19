<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Borrowing</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}">Inventory</a></li>
                                <li class="breadcrumb-item" aria-current="page">Borrowing</li>
                            </ul>
                        </div>
                        @can('manage inventory borrowing')
                            <a href="{{ route('inventory.borrowing.create') }}" class="btn btn-primary btn-sm">
                                <i class="ti ti-circle-plus me-1"></i> New Request
                            </a>
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
                    ['key' => 'all', 'label' => 'All', 'url' => route('inventory.borrowing.index'), 'count' => $counts['all']],
                    ['key' => 'mine', 'label' => 'My Requests', 'url' => route('inventory.borrowing.index', ['tab' => 'mine']), 'count' => $counts['mine']],
                    ['key' => 'pending', 'label' => 'Pending Review', 'url' => route('inventory.borrowing.index', ['tab' => 'pending']), 'count' => $counts['pending'], 'alert' => true],
                    ['key' => 'issued', 'label' => 'Issued', 'url' => route('inventory.borrowing.index', ['tab' => 'issued']), 'count' => $counts['issued']],
                    ['key' => 'overdue', 'label' => 'Overdue', 'url' => route('inventory.borrowing.index', ['tab' => 'overdue']), 'count' => $counts['overdue'], 'alert' => true],
                ],
            ])
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered">
                    <thead class="thead-light">
                        <tr>
                            <th>Request #</th>
                            <th>Item</th>
                            <th>Borrower</th>
                            <th>Due</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" style="width: 120px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $item)
                            <tr>
                                <td class="fw-semibold">{{ $item->request_number }}</td>
                                <td>
                                    {{ $item->itemName() }}
                                    @if($item->item_type === 'stock')
                                        <small class="text-muted">× {{ $item->quantity }}</small>
                                    @endif
                                </td>
                                <td>{{ $item->borrower?->name ?? '—' }}</td>
                                <td>
                                    {{ $item->expected_return_date?->format('M d, Y') }}
                                    @if($item->isOverdue())
                                        <span class="badge bg-danger ms-1">Overdue</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light-secondary">{{ $statusOptions[$item->status] ?? $item->status }}</span>
                                </td>
                                <td class="text-center">
                                    <x-table-action-dropdown>
                                        <a class="dropdown-item" href="{{ route('inventory.borrowing.show', $item) }}">
                                            <i class="ti ti-eye me-2"></i> View
                                        </a>
                                    </x-table-action-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <p class="text-muted mb-2">No borrow requests found.</p>
                                    @can('manage inventory borrowing')
                                        <a href="{{ route('inventory.borrowing.create') }}" class="btn btn-sm btn-primary">New Request</a>
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
