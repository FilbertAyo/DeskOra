<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Stock Items</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}">Inventory</a></li>
                                <li class="breadcrumb-item" aria-current="page">Stock Items</li>
                            </ul>
                        </div>
                        <div class="col-auto d-flex align-items-center gap-2 flex-wrap">
                            @can('manage inventory stock')
                                <a href="{{ route('inventory.stock-items.create') }}" class="btn btn-primary btn-sm">
                                    <i class="ti ti-circle-plus me-1"></i> New Stock Item
                                </a>
                            @endcan
                            <a href="{{ route('inventory.stock-items.export', array_merge(['format' => 'xlsx'], request()->query())) }}" class="btn btn-outline-secondary btn-sm">
                                <i class="ti ti-file-spreadsheet me-1"></i> Excel
                            </a>
                            <a href="{{ route('inventory.stock-items.export', array_merge(['format' => 'pdf'], request()->query())) }}" class="btn btn-outline-secondary btn-sm">
                                <i class="ti ti-file-type-pdf me-1"></i> PDF
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($lowStockCount > 0)
        <div class="alert alert-secondary">
            <strong>{{ $lowStockCount }}</strong> stock item(s) are at or below their minimum level.
            <a href="{{ route('inventory.stock-items.index', ['low_stock' => 1]) }}" class="alert-link ms-1">View low-stock items</a>
        </div>
    @endif

    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card shadow-none border">
                <div class="card-body">
                    <form method="GET" action="{{ route('inventory.stock-items.index') }}">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label small text-muted">Search</label>
                                <input type="text" name="search" class="form-control form-control-sm" value="{{ $filters['search'] ?? '' }}" placeholder="Item name...">
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
                                ])
                            </div>
                            <div class="col-md-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="low_stock" value="1" id="lowStockFilter" @checked($filters['low_stock'] ?? false)>
                                    <label class="form-check-label" for="lowStockFilter">Low stock only</label>
                                </div>
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
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Location</th>
                                    <th class="text-center">Received</th>
                                    <th class="text-center">Issued</th>
                                    <th class="text-center">Remaining</th>
                                    <th class="text-center">Minimum</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center" style="width: 160px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($stockItems as $item)
                                    <tr>
                                        <td class="fw-semibold">
                                            {{ $item->name }}
                                            @if($item->unit)<small class="text-muted">({{ $item->unit }})</small>@endif
                                            @if($item->isLowStock())
                                                <span class="badge bg-danger ms-1">Low stock</span>
                                            @endif
                                        </td>
                                        <td>{{ $item->category?->name ?? '—' }}</td>
                                        <td>{{ $item->location?->pathLabel() ?? '—' }}</td>
                                        <td class="text-center">{{ $item->quantity_received }}</td>
                                        <td class="text-center">{{ $item->quantity_issued }}</td>
                                        <td class="text-center fw-bold">{{ $item->quantity_remaining }}</td>
                                        <td class="text-center">{{ $item->minimum_level }}</td>
                                        <td class="text-center">
                                            <span class="badge bg-light-secondary">
                                                {{ ucfirst($item->status) }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <x-table-action-dropdown>
                                                @can('manage inventory stock')
                                                    <a class="dropdown-item" href="{{ route('inventory.stock-items.edit', $item) }}"><i class="ti ti-edit me-2"></i> Edit</a>
                                                    <button type="button" class="dropdown-item issue-btn"
                                                        data-id="{{ $item->id }}"
                                                        data-name="{{ e($item->name) }}"
                                                        data-remaining="{{ $item->quantity_remaining }}">
                                                        <i class="ti ti-package-export me-2"></i> Issue Stock
                                                    </button>
                                                    <button type="button" class="dropdown-item adjust-btn"
                                                        data-id="{{ $item->id }}"
                                                        data-name="{{ e($item->name) }}"
                                                        data-remaining="{{ $item->quantity_remaining }}">
                                                        <i class="ti ti-adjustments me-2"></i> Adjust Stock
                                                    </button>
                                                @endcan
                                            </x-table-action-dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <p class="text-muted mb-2">No stock items found.</p>
                                            @can('manage inventory stock')
                                                <a href="{{ route('inventory.receipts.create') }}" class="btn btn-sm btn-outline-primary me-1">Record a receipt</a>
                                                <a href="{{ route('inventory.stock-items.create') }}" class="btn btn-sm btn-primary">New Stock Item</a>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $stockItems->links() }}</div>
                </div>
            </div>
        </div>
    </div>

    @can('manage inventory stock')
    <div class="modal fade" id="issueModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="issueForm">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Issue Stock</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">Item: <strong id="issueItemName"></strong> | Remaining: <strong id="issueRemaining"></strong></p>
                        <div class="mb-3">
                            <label class="form-label">Quantity <span class="text-danger">*</span></label>
                            <input type="number" min="1" name="quantity" class="form-control" required>
                        </div>
                        @include('inventory.partials.ownership-cascade', [
                            'departmentField' => 'issued_to_department_id',
                            'userField' => 'issued_to_user_id',
                            'required' => true,
                            'idPrefix' => 'stock_issue',
                        ])
                        <div class="mt-3 mb-3">
                            <label class="form-label">Purpose</label>
                            <input type="text" name="purpose" class="form-control" placeholder="What is this stock for?">
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" rows="2" class="form-control"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Issue Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="adjustModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="adjustForm">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Adjust Stock</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">Item: <strong id="adjustItemName"></strong> | Remaining: <strong id="adjustRemaining"></strong></p>
                        <div class="mb-3">
                            <label class="form-label">Direction <span class="text-danger">*</span></label>
                            <select name="direction" class="form-control" required>
                                <option value="increase">Increase</option>
                                <option value="decrease">Decrease</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Quantity <span class="text-danger">*</span></label>
                            <input type="number" min="1" name="quantity" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason <span class="text-danger">*</span></label>
                            <select name="reason_code" class="form-control" required>
                                @foreach($adjustmentReasons as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" rows="2" class="form-control"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Adjustment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const issueModal = new bootstrap.Modal(document.getElementById('issueModal'));
            const adjustModal = new bootstrap.Modal(document.getElementById('adjustModal'));
            const issueForm = document.getElementById('issueForm');
            const adjustForm = document.getElementById('adjustForm');

            document.querySelectorAll('.issue-btn').forEach(button => {
                button.addEventListener('click', function() {
                    issueForm.action = `/inventory/stock-items/${this.dataset.id}/issue`;
                    document.getElementById('issueItemName').textContent = this.dataset.name;
                    document.getElementById('issueRemaining').textContent = this.dataset.remaining;
                    if (window.InventoryPickers) {
                        const wrap = issueForm.querySelector('.js-ownership-cascade');
                        if (wrap) {
                            wrap.dataset.selectedDept = '';
                            wrap.dataset.selectedUser = '';
                            window.InventoryPickers.rebind(issueForm);
                        }
                    }
                    issueModal.show();
                });
            });

            document.querySelectorAll('.adjust-btn').forEach(button => {
                button.addEventListener('click', function() {
                    adjustForm.action = `/inventory/stock-items/${this.dataset.id}/adjust`;
                    document.getElementById('adjustItemName').textContent = this.dataset.name;
                    document.getElementById('adjustRemaining').textContent = this.dataset.remaining;
                    adjustModal.show();
                });
            });
        });
    </script>
    @endcan
    @include('inventory.partials.picker-scripts')
</x-app-layout>
