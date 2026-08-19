<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Maintenance</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}">Inventory</a></li>
                                <li class="breadcrumb-item" aria-current="page">Maintenance</li>
                            </ul>
                        </div>
                        @can('manage inventory maintenance')
                            <a href="{{ route('inventory.maintenance.create') }}" class="btn btn-primary btn-sm">
                                <i class="ti ti-tool me-1"></i> Report Problem
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
                    ['key' => 'open', 'label' => 'Under Repair', 'url' => route('inventory.maintenance.index', ['tab' => 'open']), 'count' => $counts['open'], 'alert' => true],
                    ['key' => 'completed', 'label' => 'Completed', 'url' => route('inventory.maintenance.index', ['tab' => 'completed']), 'count' => $counts['completed']],
                    ['key' => 'all', 'label' => 'All', 'url' => route('inventory.maintenance.index', ['tab' => 'all']), 'count' => $counts['all']],
                ],
            ])
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered">
                    <thead class="thead-light">
                        <tr>
                            <th>Record #</th>
                            <th>Asset</th>
                            <th>Problem</th>
                            <th>Reported</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" style="width: 120px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($records as $record)
                            <tr>
                                <td class="fw-semibold">{{ $record->record_number }}</td>
                                <td>
                                    @if($record->asset)
                                        <a href="{{ route('inventory.assets.show', $record->asset) }}">{{ $record->asset->asset_code }}</a>
                                        <div class="small text-muted">{{ $record->asset->name }}</div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ \Illuminate\Support\Str::limit($record->problem_description, 80) }}</td>
                                <td>{{ $record->reported_at?->format('M d, Y') }}</td>
                                <td class="text-center">
                                    <span class="badge bg-light-secondary">{{ $statusOptions[$record->status] ?? $record->status }}</span>
                                </td>
                                <td class="text-center">
                                    <x-table-action-dropdown>
                                        <a class="dropdown-item" href="{{ route('inventory.maintenance.show', $record) }}"><i class="ti ti-eye me-2"></i> View</a>
                                        @can('complete', $record)
                                            <a class="dropdown-item" href="{{ route('inventory.maintenance.complete.form', $record) }}"><i class="ti ti-check me-2"></i> Complete repair</a>
                                        @endcan
                                    </x-table-action-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <p class="text-muted mb-2">No maintenance records found.</p>
                                    @can('manage inventory maintenance')
                                        <a href="{{ route('inventory.maintenance.create') }}" class="btn btn-sm btn-primary">Report Problem</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $records->links() }}</div>
        </div>
    </div>
</x-app-layout>
