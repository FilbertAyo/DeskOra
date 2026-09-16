<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Dispatch</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}">Inventory</a></li>
                                <li class="breadcrumb-item" aria-current="page">Dispatch</li>
                            </ul>
                        </div>
                        @can('manage inventory dispatches')
                            <a href="{{ route('inventory.dispatches.create') }}" class="btn btn-primary btn-sm">
                                <i class="ti ti-circle-plus me-1"></i> New Dispatch
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
                    ['key' => 'all', 'label' => 'All', 'url' => route('inventory.dispatches.index'), 'count' => $counts['all']],
                    ['key' => 'draft', 'label' => 'Draft / Ready', 'url' => route('inventory.dispatches.index', ['tab' => 'draft']), 'count' => $counts['draft']],
                    ['key' => 'dispatched', 'label' => 'Dispatched', 'url' => route('inventory.dispatches.index', ['tab' => 'dispatched']), 'count' => $counts['dispatched']],
                    ['key' => 'returning', 'label' => 'Returning', 'url' => route('inventory.dispatches.index', ['tab' => 'returning']), 'count' => $counts['returning']],
                    ['key' => 'overdue', 'label' => 'Overdue', 'url' => route('inventory.dispatches.index', ['tab' => 'overdue']), 'count' => $counts['overdue'], 'alert' => true],
                    ['key' => 'closed', 'label' => 'Closed', 'url' => route('inventory.dispatches.index', ['tab' => 'closed']), 'count' => $counts['closed']],
                ],
            ])
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered">
                    <thead class="thead-light">
                        <tr>
                            <th>Dispatch #</th>
                            <th>Name</th>
                            <th>Destination</th>
                            <th>Dates</th>
                            <th>Responsible</th>
                            <th class="text-center">Items</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" style="width: 120px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dispatches as $item)
                            <tr>
                                <td class="fw-semibold">{{ $item->dispatch_number }}</td>
                                <td>{{ $item->title }}</td>
                                <td>{{ $item->destination }}</td>
                                <td>
                                    {{ $item->dispatch_date?->format('M d, Y') }}
                                    → {{ $item->expected_return_date?->format('M d, Y') }}
                                    @if($item->isOverdue())
                                        <span class="badge bg-danger ms-1">Overdue</span>
                                    @endif
                                </td>
                                <td>{{ $item->responsiblePeopleLabel() }}</td>
                                <td class="text-center">{{ (int) $item->asset_count + (int) $item->stock_count }}</td>
                                <td class="text-center">
                                    <span class="badge bg-light-secondary">{{ $statusOptions[$item->status] ?? $item->status }}</span>
                                </td>
                                <td class="text-center">
                                    <x-table-action-dropdown>
                                        <a class="dropdown-item" href="{{ route('inventory.dispatches.show', $item) }}">
                                            <i class="ti ti-eye me-2"></i> View
                                        </a>
                                    </x-table-action-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <p class="text-muted mb-2">No dispatches found.</p>
                                    @can('manage inventory dispatches')
                                        <a href="{{ route('inventory.dispatches.create') }}" class="btn btn-sm btn-primary">New Dispatch</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $dispatches->links() }}</div>
        </div>
    </div>
</x-app-layout>
