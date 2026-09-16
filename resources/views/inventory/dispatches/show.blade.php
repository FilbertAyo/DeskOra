<x-app-layout>
    @php
        $itemCounts = $dispatch->itemCounts();
        $assetReturns = $dispatch->assetReturnCounts();
        $assetItems = $dispatch->items->where('item_type', 'asset');
        $stockItems = $dispatch->items->where('item_type', 'stock');
        $pickerQuery = request()->except(['picker', 'assets_page', 'stock_page']);
    @endphp
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">{{ $dispatch->dispatch_number }}</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('inventory.dispatches.index') }}">Dispatch</a></li>
                                <li class="breadcrumb-item" aria-current="page">{{ $dispatch->title }}</li>
                            </ul>
                        </div>
                        <a href="{{ route('inventory.dispatches.index') }}" class="btn btn-sm btn-light">
                            <i class="ti ti-arrow-left me-1"></i> Back
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-none border mb-3">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">{{ $dispatch->title }}</h6>
                    <span class="badge bg-light-secondary">{{ $statusOptions[$dispatch->status] ?? $dispatch->status }}</span>
                </div>
                <div class="card-body">
                    <p class="mb-2"><strong>Purpose:</strong> {{ $dispatch->purpose }}</p>
                    <p class="mb-2"><strong>Destination:</strong> {{ $dispatch->destination }}</p>
                    <p class="mb-2">
                        <strong>Dates:</strong>
                        {{ $dispatch->dispatch_date?->format('M d, Y') }}
                        → {{ $dispatch->expected_return_date?->format('M d, Y') }}
                        @if($dispatch->isOverdue())
                            <span class="badge bg-danger ms-1">Overdue</span>
                        @endif
                    </p>
                    <p class="mb-2"><strong>Temporary responsible people:</strong> {{ $dispatch->responsiblePeopleLabel() }}</p>
                    <p class="mb-2"><strong>Created by:</strong> {{ $dispatch->createdBy?->name ?? '—' }} on {{ $dispatch->created_at->format('M d, Y') }}</p>
                    @if($dispatch->dispatched_at)
                        <p class="mb-2"><strong>Dispatched by:</strong> {{ $dispatch->dispatchedBy?->name ?? '—' }} at {{ $dispatch->dispatched_at->format('M d, Y h:i A') }}</p>
                    @endif
                    @if($dispatch->returned_at)
                        <p class="mb-2"><strong>Return completed by:</strong> {{ $dispatch->returnedBy?->name ?? '—' }} at {{ $dispatch->returned_at->format('M d, Y h:i A') }}</p>
                    @endif
                    @if($dispatch->closed_at)
                        <p class="mb-2"><strong>Closed by:</strong> {{ $dispatch->closedBy?->name ?? '—' }} at {{ $dispatch->closed_at->format('M d, Y h:i A') }}</p>
                    @endif
                    <p class="mb-0"><strong>Notes:</strong> {{ $dispatch->notes ?: '—' }}</p>
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6 col-md-3">
                    <div class="card shadow-none border h-100">
                        <div class="card-body py-3">
                            <div class="text-muted small">Total lines</div>
                            <div class="fs-4 fw-semibold">{{ $itemCounts['total'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card shadow-none border h-100">
                        <div class="card-body py-3">
                            <div class="text-muted small">Assets</div>
                            <div class="fs-4 fw-semibold">{{ $itemCounts['assets'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card shadow-none border h-100">
                        <div class="card-body py-3">
                            <div class="text-muted small">Stock items</div>
                            <div class="fs-4 fw-semibold">{{ $itemCounts['stock'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card shadow-none border h-100">
                        <div class="card-body py-3">
                            <div class="text-muted small">Responsible</div>
                            <div class="fs-4 fw-semibold">{{ $dispatch->responsibleUsers->count() }}</div>
                        </div>
                    </div>
                </div>
            </div>

            @if(in_array($dispatch->status, ['returning', 'returned', 'closed'], true) && $itemCounts['assets'] > 0)
                <div class="alert alert-secondary">
                    Return summary:
                    <strong>{{ $assetReturns['received_good'] }}</strong> received,
                    <strong>{{ $assetReturns['received_damaged'] }}</strong> damaged,
                    <strong>{{ $assetReturns['missing'] }}</strong> missing
                    @if($assetReturns['pending'] > 0)
                        , <strong>{{ $assetReturns['pending'] }}</strong> still pending
                    @endif
                </div>
            @endif

            <div class="card shadow-none border mb-3">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0">Assets ({{ $itemCounts['assets'] }})</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Asset</th>
                                    <th>Custodian</th>
                                    <th>Location</th>
                                    <th>Condition</th>
                                    @if(! $dispatch->canEditItems())
                                        <th>Return</th>
                                    @endif
                                    @if($dispatch->canEditItems())
                                        <th class="text-center" style="width: 90px;">Action</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($assetItems as $line)
                                    <tr>
                                        <td>
                                            @if($line->asset)
                                                <a href="{{ route('inventory.assets.show', $line->asset) }}">{{ $line->asset->asset_code }}</a>
                                                <div class="small text-muted">{{ $line->asset->name }}</div>
                                            @else
                                                {{ $line->itemName() }}
                                            @endif
                                        </td>
                                        <td>{{ $line->originalResponsibleUser?->name ?? $line->asset?->responsibleUser?->name ?? '—' }}</td>
                                        <td>{{ $line->originalLocation?->pathLabel() ?? $line->asset?->location?->pathLabel() ?? '—' }}</td>
                                        <td>{{ $conditionOptions[$line->condition_at_dispatch ?? $line->original_condition] ?? ($line->original_condition ?: '—') }}</td>
                                        @if(! $dispatch->canEditItems())
                                            <td>
                                                @if($line->return_outcome)
                                                    <span class="badge {{ $line->return_outcome === 'received_good' ? 'bg-light-secondary' : 'bg-danger' }}">
                                                        {{ $returnOutcomeOptions[$line->return_outcome] ?? $line->return_outcome }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">Pending</span>
                                                @endif
                                            </td>
                                        @endif
                                        @if($dispatch->canEditItems())
                                            <td class="text-center">
                                                <form method="POST" action="{{ route('inventory.dispatches.items.destroy', [$dispatch, $line]) }}" onsubmit="return confirm('Remove this asset from the dispatch?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-light">Remove</button>
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $dispatch->canEditItems() ? 5 : 5 }}" class="text-center text-muted py-3">No assets added yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow-none border mb-3">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0">Stock ({{ $itemCounts['stock'] }})</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Item</th>
                                    <th class="text-end">Qty</th>
                                    @if(! $dispatch->canEditItems())
                                        <th class="text-end">Returned</th>
                                        <th class="text-end">Consumed</th>
                                    @endif
                                    @if($dispatch->canEditItems())
                                        <th class="text-center" style="width: 90px;">Action</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($stockItems as $line)
                                    <tr>
                                        <td>
                                            {{ $line->stockItem?->name ?? $line->itemName() }}
                                            @if($line->stockItem?->unit)
                                                <small class="text-muted">{{ $line->stockItem->unit }}</small>
                                            @endif
                                        </td>
                                        <td class="text-end">{{ $line->quantity }}</td>
                                        @if(! $dispatch->canEditItems())
                                            <td class="text-end">{{ $line->quantity_returned ?? '—' }}</td>
                                            <td class="text-end">{{ $line->quantity_consumed ?? '—' }}</td>
                                        @endif
                                        @if($dispatch->canEditItems())
                                            <td class="text-center">
                                                <form method="POST" action="{{ route('inventory.dispatches.items.destroy', [$dispatch, $line]) }}" onsubmit="return confirm('Remove this stock line from the dispatch?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-light">Remove</button>
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $dispatch->canEditItems() ? 3 : 4 }}" class="text-center text-muted py-3">No stock items added yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-none border mb-3">
                <div class="card-header bg-white border-bottom"><h6 class="mb-0">Actions</h6></div>
                <div class="card-body">
                    @can('update', $dispatch)
                        @if($dispatch->isEditable())
                            <a href="{{ route('inventory.dispatches.edit', $dispatch) }}" class="btn btn-sm btn-light w-100 mb-2">Edit details</a>
                        @endif
                        @if($dispatch->status === 'draft')
                            <form method="POST" action="{{ route('inventory.dispatches.ready', $dispatch) }}" class="mb-2">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-primary w-100" data-busy-label="Saving…">Mark ready for review</button>
                            </form>
                        @endif
                        @if($dispatch->status === 'ready')
                            <form method="POST" action="{{ route('inventory.dispatches.reopen', $dispatch) }}" class="mb-2">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-light w-100">Back to draft</button>
                            </form>
                        @endif
                    @endcan

                    @can('confirm', $dispatch)
                        <div class="border rounded p-2 mb-2 small">
                            <div class="fw-semibold mb-1">Ready to dispatch?</div>
                            {{ $itemCounts['total'] }} lines · {{ $itemCounts['assets'] }} assets · {{ $itemCounts['stock'] }} stock<br>
                            {{ $dispatch->destination }} · {{ $dispatch->dispatch_date?->format('M d') }} → {{ $dispatch->expected_return_date?->format('M d') }}<br>
                            Responsible: {{ $dispatch->responsiblePeopleLabel() }}
                        </div>
                        <form method="POST" action="{{ route('inventory.dispatches.confirm', $dispatch) }}" class="mb-2" onsubmit="return confirm('Confirm this dispatch? Assets stay with their current custodians. Stock quantities will be deducted.');">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm w-100" data-busy-label="Dispatching…">Confirm Dispatch</button>
                        </form>
                    @endcan

                    @can('returnItems', $dispatch)
                        @if($dispatch->canStartReturn() || $dispatch->canRecordReturn())
                            <a href="{{ route('inventory.dispatches.return', $dispatch) }}" class="btn btn-primary btn-sm w-100 mb-2">
                                {{ $dispatch->canRecordReturn() ? 'Continue return' : 'Start return' }}
                            </a>
                        @endif
                    @endcan

                    @can('close', $dispatch)
                        <form method="POST" action="{{ route('inventory.dispatches.close', $dispatch) }}" onsubmit="return confirm('Close this dispatch and restore assets to their previous assignments and locations?');">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm w-100" data-busy-label="Closing…">Close Dispatch</button>
                        </form>
                    @endcan

                    @can('delete', $dispatch)
                        <form method="POST" action="{{ route('inventory.dispatches.destroy', $dispatch) }}" class="mt-2" onsubmit="return confirm('Delete this draft dispatch?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm w-100">Delete draft</button>
                        </form>
                    @endcan

                    @if(! $dispatch->canConfirm() && ! $dispatch->canStartReturn() && ! $dispatch->canRecordReturn() && ! $dispatch->canClose() && ! $dispatch->isEditable())
                        <p class="text-muted mb-0">This dispatch is closed. History is preserved.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($dispatch->canEditItems())
        <div class="card shadow-none border">
            <div class="card-header bg-white border-bottom">
                @include('inventory.partials.tabs', [
                    'current' => $picker,
                    'tabs' => [
                        ['key' => 'assets', 'label' => 'Add assets', 'url' => route('inventory.dispatches.show', array_merge(['dispatch' => $dispatch, 'picker' => 'assets'], $pickerQuery))],
                        ['key' => 'stock', 'label' => 'Add stock', 'url' => route('inventory.dispatches.show', array_merge(['dispatch' => $dispatch, 'picker' => 'stock'], $pickerQuery))],
                    ],
                ])
            </div>
            <div class="card-body">
                @if($picker === 'stock')
                    <form method="GET" action="{{ route('inventory.dispatches.show', $dispatch) }}" class="mb-3">
                        <input type="hidden" name="picker" value="stock">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label small text-muted">Search</label>
                                <input type="text" name="stock_search" class="form-control form-control-sm" value="{{ $stockFilters['stock_search'] ?? '' }}" placeholder="Stock name...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted">Category</label>
                                <select name="stock_category_id" class="form-control form-control-sm">
                                    <option value="">All</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" @selected(($stockFilters['stock_category_id'] ?? '') == $category->id)>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                @include('inventory.partials.location-room-picker', [
                                    'fieldName' => 'stock_location_id',
                                    'selectedLocationId' => $stockFilters['stock_location_id'] ?? '',
                                    'emptyLabel' => 'All',
                                    'compact' => true,
                                ])
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                            </div>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-hover table-bordered">
                            <thead class="thead-light">
                                <tr>
                                    <th>Item</th>
                                    <th>Category</th>
                                    <th>Location</th>
                                    <th class="text-end">Remaining</th>
                                    <th style="width: 220px;">Add quantity</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($pickerStockItems ?? [] as $stockItem)
                                    <tr>
                                        <td>{{ $stockItem->name }}</td>
                                        <td>{{ $stockItem->category?->name ?? '—' }}</td>
                                        <td>{{ $stockItem->location?->pathLabel() ?? '—' }}</td>
                                        <td class="text-end">{{ $stockItem->quantity_remaining }} {{ $stockItem->unit }}</td>
                                        <td>
                                            <form method="POST" action="{{ route('inventory.dispatches.stock.add', $dispatch) }}" class="d-flex gap-1">
                                                @csrf
                                                <input type="hidden" name="stock_item_id" value="{{ $stockItem->id }}">
                                                <input type="number" min="1" max="{{ $stockItem->quantity_remaining }}" name="quantity" class="form-control form-control-sm" value="1" required>
                                                <button type="submit" class="btn btn-sm btn-primary" data-busy-label="Adding…">Add</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-3">No stock items match these filters.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $pickerStockItems?->links() }}</div>
                @else
                    <form method="GET" action="{{ route('inventory.dispatches.show', $dispatch) }}" class="mb-3">
                        <input type="hidden" name="picker" value="assets">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small text-muted">Search</label>
                                <input type="text" name="search" class="form-control form-control-sm" value="{{ $assetFilters['search'] ?? '' }}" placeholder="Name, ID, serial...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted">Category</label>
                                <select name="category_id" class="form-control form-control-sm">
                                    <option value="">All</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" @selected(($assetFilters['category_id'] ?? '') == $category->id)>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                @include('inventory.partials.location-room-picker', [
                                    'fieldName' => 'location_id',
                                    'selectedLocationId' => $assetFilters['location_id'] ?? '',
                                    'emptyLabel' => 'All',
                                    'compact' => true,
                                ])
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted">Custodian</label>
                                <select name="responsible_user_id" class="form-control form-control-sm">
                                    <option value="">All</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" @selected(($assetFilters['responsible_user_id'] ?? '') == $user->id)>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted">Status</label>
                                <select name="status" class="form-control form-control-sm">
                                    <option value="">Available or assigned</option>
                                    @foreach(collect($assetStatusOptions)->only(['available', 'assigned']) as $value => $label)
                                        <option value="{{ $value }}" @selected(($assetFilters['status'] ?? '') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted">Condition</label>
                                <select name="condition" class="form-control form-control-sm">
                                    <option value="">All</option>
                                    @foreach($conditionOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(($assetFilters['condition'] ?? '') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                                <a href="{{ route('inventory.dispatches.show', ['dispatch' => $dispatch, 'picker' => 'assets']) }}" class="btn btn-light btn-sm">Reset</a>
                            </div>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('inventory.dispatches.assets.add', $dispatch) }}" id="addAssetsForm">
                        @csrf
                        @foreach($assetFilters as $key => $value)
                            @if(filled($value))
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endif
                        @endforeach
                        <input type="hidden" name="picker" value="assets">
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <button type="submit" class="btn btn-primary btn-sm" data-busy-label="Adding…">Add selected</button>
                            <button type="submit" name="add_all_matching" value="1" class="btn btn-outline-primary btn-sm" data-busy-label="Adding…" onclick="return confirm('Add every asset matching the current filters (up to 500)?');">Select all matching filters</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width: 40px;">
                                            <input type="checkbox" id="selectAllAssets">
                                        </th>
                                        <th>Asset ID</th>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Location</th>
                                        <th>Custodian</th>
                                        <th>Status</th>
                                        <th>Condition</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($pickerAssets ?? [] as $asset)
                                        <tr>
                                            <td><input type="checkbox" class="asset-checkbox" name="asset_ids[]" value="{{ $asset->id }}"></td>
                                            <td class="fw-semibold">{{ $asset->asset_code }}</td>
                                            <td>{{ $asset->name }}</td>
                                            <td>{{ $asset->category?->name ?? '—' }}</td>
                                            <td>{{ $asset->location?->pathLabel() ?? '—' }}</td>
                                            <td>{{ $asset->responsibleUser?->name ?? '—' }}</td>
                                            <td>{{ $assetStatusOptions[$asset->status] ?? $asset->status }}</td>
                                            <td>{{ $conditionOptions[$asset->condition] ?? $asset->condition }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="8" class="text-center text-muted py-3">No dispatchable assets match these filters.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </form>
                    <div class="mt-3">{{ $pickerAssets?->links() }}</div>
                @endif
            </div>
        </div>
        @include('inventory.partials.picker-scripts')
        <script>
            document.getElementById('selectAllAssets')?.addEventListener('change', function () {
                document.querySelectorAll('.asset-checkbox').forEach((box) => {
                    box.checked = this.checked;
                });
            });
        </script>
    @endif
</x-app-layout>
