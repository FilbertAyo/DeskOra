<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Assets</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}">Inventory</a></li>
                                <li class="breadcrumb-item" aria-current="page">Assets</li>
                            </ul>
                        </div>
                        <div class="col-auto d-flex align-items-center gap-2 flex-wrap">
                            @can('manage inventory assets')
                                <a href="{{ route('inventory.assets.create') }}" class="btn btn-primary btn-sm">
                                    <i class="ti ti-circle-plus me-1"></i> Register Asset
                                </a>
                            @endcan
                            <a href="{{ route('inventory.assets.export', array_merge(['format' => 'xlsx'], request()->query())) }}" class="btn btn-outline-secondary btn-sm">
                                <i class="ti ti-file-spreadsheet me-1"></i> Excel
                            </a>
                            <a href="{{ route('inventory.assets.export', array_merge(['format' => 'pdf'], request()->query())) }}" class="btn btn-outline-secondary btn-sm">
                                <i class="ti ti-file-type-pdf me-1"></i> PDF
                            </a>
                            <a href="{{ route('inventory.assets.labels', request()->query()) }}" class="btn btn-outline-secondary btn-sm">
                                <i class="ti ti-qrcode me-1"></i> Print labels
                            </a>
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
                    <form method="GET" action="{{ route('inventory.assets.index') }}">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small text-muted">Search</label>
                                <input type="text" name="search" class="form-control form-control-sm" value="{{ $filters['search'] ?? '' }}" placeholder="Name, ID, serial...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted">Category</label>
                                <select name="category_id" class="form-control form-control-sm">
                                    <option value="">All</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" @selected(($filters['category_id'] ?? '') == $category->id)>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                @include('inventory.partials.location-room-picker', [
                                    'fieldName' => 'location_id',
                                    'selectedLocationId' => $filters['location_id'] ?? '',
                                    'emptyLabel' => 'All',
                                    'compact' => true,
                                    'locationLabel' => 'Location',
                                ])
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted">Department</label>
                                <select name="department_id" class="form-control form-control-sm">
                                    <option value="">All</option>
                                    @foreach($departments as $department)
                                        <option value="{{ $department->id }}" @selected(($filters['department_id'] ?? '') == $department->id)>{{ $department->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted">Responsible</label>
                                <select name="responsible_user_id" class="form-control form-control-sm">
                                    <option value="">All</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" @selected(($filters['responsible_user_id'] ?? '') == $user->id)>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted">Status</label>
                                <select name="status" class="form-control form-control-sm">
                                    <option value="">All</option>
                                    @foreach($statusOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted">Condition</label>
                                <select name="condition" class="form-control form-control-sm">
                                    <option value="">All</option>
                                    @foreach($conditionOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(($filters['condition'] ?? '') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                                <a href="{{ route('inventory.assets.index') }}" class="btn btn-light btn-sm">Reset</a>
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
                                    <th>Asset ID</th>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Location</th>
                                    <th>Department</th>
                                    <th>Responsible</th>
                                    <th class="text-center">Condition</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center" style="width: 120px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($assets as $asset)
                                    <tr>
                                        <td class="fw-semibold">{{ $asset->asset_code }}</td>
                                        <td>{{ $asset->name }}</td>
                                        <td>{{ $asset->category?->name ?? '—' }}</td>
                                        <td>{{ $asset->location?->pathLabel() ?? '—' }}</td>
                                        <td>{{ $asset->department?->name ?? '—' }}</td>
                                        <td>{{ $asset->responsibleUser?->name ?? '—' }}</td>
                                        <td class="text-center"><span class="badge bg-light-secondary">{{ $conditionOptions[$asset->condition] ?? ucfirst($asset->condition) }}</span></td>
                                        <td class="text-center"><span class="badge bg-light-secondary">{{ $statusOptions[$asset->status] ?? ucfirst($asset->status) }}</span></td>
                                        <td class="text-center">
                                            <x-table-action-dropdown>
                                                <a class="dropdown-item" href="{{ route('inventory.assets.show', $asset) }}"><i class="ti ti-eye me-2"></i> View</a>
                                                @can('update', $asset)
                                                    <a class="dropdown-item" href="{{ route('inventory.assets.edit', $asset) }}"><i class="ti ti-edit me-2"></i> Edit</a>
                                                @endcan
                                                @can('assign', $asset)
                                                    <button type="button" class="dropdown-item assign-btn"
                                                        data-id="{{ $asset->id }}"
                                                        data-name="{{ e($asset->name) }}"
                                                        data-code="{{ e($asset->asset_code) }}"
                                                        data-department="{{ $asset->department_id }}"
                                                        data-user="{{ $asset->responsible_user_id }}">
                                                        <i class="ti ti-user-check me-2"></i> Assign
                                                    </button>
                                                @endcan
                                                @can('transfer', $asset)
                                                    <a class="dropdown-item" href="{{ route('inventory.assets.transfer.form', $asset) }}"><i class="ti ti-arrows-left-right me-2"></i> Transfer</a>
                                                @endcan
                                                @can('delete', $asset)
                                                    <form method="POST" action="{{ route('inventory.assets.destroy', $asset) }}" class="d-inline" onsubmit="return confirm('Delete asset {{ $asset->asset_code }} ({{ $asset->name }})? This cannot be undone.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="ti ti-trash me-2"></i> Delete
                                                        </button>
                                                    </form>
                                                @endcan
                                            </x-table-action-dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <p class="text-muted mb-2">No assets found.</p>
                                            @can('manage inventory assets')
                                                <a href="{{ route('inventory.receipts.create') }}" class="btn btn-sm btn-outline-primary me-1">Record a receipt</a>
                                                <a href="{{ route('inventory.assets.create') }}" class="btn btn-sm btn-primary">Register Asset</a>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $assets->links('pagination::bootstrap-5') }}</div>
                </div>
            </div>
        </div>
    </div>

    @include('inventory.partials.assign-modal')
    @include('inventory.partials.picker-scripts')
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalEl = document.getElementById('assignAssetModal');
            if (!modalEl) return;

            const modal = new bootstrap.Modal(modalEl);
            const form = document.getElementById('assignAssetForm');
            const cascade = modalEl.querySelector('.js-ownership-cascade');

            function openAssign(data) {
                form.action = `/inventory/assets/${data.id}/assign`;
                document.getElementById('assignAssetName').textContent = data.name || 'Asset';
                document.getElementById('assignAssetCode').textContent = data.code ? `(${data.code})` : '';
                if (cascade) {
                    cascade.dataset.selectedDept = data.department || '';
                    cascade.dataset.selectedUser = data.user || '';
                    if (window.InventoryPickers) {
                        window.InventoryPickers.rebind(modalEl);
                    }
                }
                modal.show();
            }

            document.querySelectorAll('.assign-btn').forEach(function (button) {
                button.addEventListener('click', function () {
                    openAssign(this.dataset);
                });
            });

            @if(session('open_assign_modal'))
                const firstAssign = document.querySelector('.assign-btn');
                if (firstAssign) {
                    openAssign(firstAssign.dataset);
                } else {
                    modal.show();
                }
            @endif
        });
    </script>
    @endpush
</x-app-layout>
