<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Verification</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}">Inventory</a></li>
                                <li class="breadcrumb-item" aria-current="page">Verification</li>
                            </ul>
                        </div>
                        @can('manage inventory assets')
                            <a href="{{ route('inventory.verification.create') }}" class="btn btn-primary btn-sm">New Session</a>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-none border">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered">
                    <thead class="thead-light">
                        <tr>
                            <th>Session #</th>
                            <th>Name</th>
                            <th>Scope</th>
                            <th>Started</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Missing</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sessions as $session)
                            <tr>
                                <td class="fw-semibold">{{ $session->session_number }}</td>
                                <td>{{ $session->name }}</td>
                                <td>{{ $session->location?->name ?? $session->department?->name ?? 'All assets' }}</td>
                                <td>{{ $session->started_at?->format('M d, Y') }}</td>
                                <td class="text-center">
                                    <span class="badge bg-light-secondary">
                                        {{ $statusOptions[$session->status] ?? $session->status }}
                                    </span>
                                </td>
                                <td class="text-center">{{ $session->missing_count }}</td>
                                <td class="text-center">
                                    <x-table-action-dropdown>
                                        <a class="dropdown-item" href="{{ route('inventory.verification.show', $session) }}"><i class="ti ti-eye me-2"></i> View</a>
                                    </x-table-action-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <p class="text-muted mb-2">No verification sessions yet.</p>
                                    @can('manage inventory assets')
                                        <a href="{{ route('inventory.verification.create') }}" class="btn btn-sm btn-primary">New Session</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $sessions->links() }}</div>
        </div>
    </div>
</x-app-layout>
