<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Inventory</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item" aria-current="page">Inventory</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <p class="text-muted mb-3">When items arrive, record a receipt. Then assign, borrow, repair, or dispose from the asset or stock record.</p>

    <div class="row g-2 mb-4">
        <div class="col-6 col-md-4 col-xl-2">
            <a href="{{ route('inventory.assets.index') }}" class="card shadow-none border h-100 text-decoration-none text-body">
                <div class="card-body py-3">
                    <div class="text-muted small">Assets</div>
                    <div class="fs-4 fw-semibold">{{ $stats['asset_count'] ?? 0 }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <a href="{{ route('inventory.stock-items.index') }}" class="card shadow-none border h-100 text-decoration-none text-body">
                <div class="card-body py-3">
                    <div class="text-muted small">Stock items</div>
                    <div class="fs-4 fw-semibold">{{ $stats['stock_count'] ?? 0 }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <a href="{{ route('inventory.stock-items.index', ['low_stock' => 1]) }}" class="card shadow-none border h-100 text-decoration-none text-body">
                <div class="card-body py-3">
                    <div class="text-muted small">Low stock</div>
                    <div class="fs-4 fw-semibold">{{ $stats['low_stock_count'] ?? 0 }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <a href="{{ route('inventory.borrowing.index', ['tab' => 'pending']) }}" class="card shadow-none border h-100 text-decoration-none text-body">
                <div class="card-body py-3">
                    <div class="text-muted small">Borrow review</div>
                    <div class="fs-4 fw-semibold">{{ $stats['pending_borrow_count'] ?? 0 }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <a href="{{ route('inventory.borrowing.index', ['tab' => 'overdue']) }}" class="card shadow-none border h-100 text-decoration-none text-body">
                <div class="card-body py-3">
                    <div class="text-muted small">Overdue</div>
                    <div class="fs-4 fw-semibold">{{ $stats['overdue_borrow_count'] ?? 0 }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <a href="{{ route('inventory.maintenance.index') }}" class="card shadow-none border h-100 text-decoration-none text-body">
                <div class="card-body py-3">
                    <div class="text-muted small">Under repair</div>
                    <div class="fs-4 fw-semibold">{{ $stats['under_repair_count'] ?? 0 }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <a href="{{ route('inventory.dispatches.index', ['tab' => 'dispatched']) }}" class="card shadow-none border h-100 text-decoration-none text-body">
                <div class="card-body py-3">
                    <div class="text-muted small">Open dispatches</div>
                    <div class="fs-4 fw-semibold">{{ $stats['open_dispatch_count'] ?? 0 }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <a href="{{ route('inventory.disposal.index', ['tab' => 'pending']) }}" class="card shadow-none border h-100 text-decoration-none text-body">
                <div class="card-body py-3">
                    <div class="text-muted small">Disposal review</div>
                    <div class="fs-4 fw-semibold">{{ $stats['pending_disposal_count'] ?? 0 }}</div>
                </div>
            </a>
        </div>
    </div>

    <h6 class="text-muted text-uppercase small mb-2">Daily work</h6>
    <div class="row g-2 mb-4">
        @foreach([
            ['label' => 'Receive items', 'hint' => 'New assets and stock', 'url' => route('inventory.receipts.index'), 'icon' => 'ti-receipt'],
            ['label' => 'Assets', 'hint' => 'Tracked equipment', 'url' => route('inventory.assets.index'), 'icon' => 'ti-device-laptop'],
            ['label' => 'Stock', 'hint' => 'Consumables', 'url' => route('inventory.stock-items.index'), 'icon' => 'ti-packages'],
            ['label' => 'Reports', 'hint' => 'Counts and export', 'url' => route('inventory.reports.index'), 'icon' => 'ti-chart-bar'],
        ] as $card)
            <div class="col-6 col-md-3">
                <a href="{{ $card['url'] }}" class="card shadow-none border h-100 text-decoration-none text-body">
                    <div class="card-body py-3">
                        <i class="ti {{ $card['icon'] }} me-1"></i>
                        <span class="fw-semibold">{{ $card['label'] }}</span>
                        <div class="small text-muted mt-1">{{ $card['hint'] }}</div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <h6 class="text-muted text-uppercase small mb-2">Operations</h6>
    <div class="row g-2 mb-4">
        @foreach([
            ['label' => 'Borrowing', 'hint' => 'Lend and return', 'url' => route('inventory.borrowing.index'), 'icon' => 'ti-hand-grab'],
            ['label' => 'Dispatch', 'hint' => 'Workshops and field kits', 'url' => route('inventory.dispatches.index'), 'icon' => 'ti-truck-delivery'],
            ['label' => 'Maintenance', 'hint' => 'Repair tracking', 'url' => route('inventory.maintenance.index'), 'icon' => 'ti-tool'],
            ['label' => 'Disposal', 'hint' => 'Needs approval', 'url' => route('inventory.disposal.index'), 'icon' => 'ti-trash'],
            ['label' => 'Verification', 'hint' => 'Count what we have', 'url' => route('inventory.verification.index'), 'icon' => 'ti-clipboard-check'],
        ] as $card)
            <div class="col-6 col-md-3">
                <a href="{{ $card['url'] }}" class="card shadow-none border h-100 text-decoration-none text-body">
                    <div class="card-body py-3">
                        <i class="ti {{ $card['icon'] }} me-1"></i>
                        <span class="fw-semibold">{{ $card['label'] }}</span>
                        <div class="small text-muted mt-1">{{ $card['hint'] }}</div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    @can('manage inventory settings')
        <h6 class="text-muted text-uppercase small mb-2">Setup</h6>
        <div class="row g-2">
            @foreach([
                ['label' => 'Categories', 'url' => route('inventory.categories.index')],
                ['label' => 'Locations', 'url' => route('inventory.locations.index')],
                ['label' => 'Suppliers', 'url' => route('inventory.suppliers.index')],
                ['label' => 'Donors', 'url' => route('inventory.donors.index')],
                ['label' => 'Projects', 'url' => route('inventory.projects.index')],
            ] as $card)
                <div class="col-6 col-md-2">
                    <a href="{{ $card['url'] }}" class="card shadow-none border h-100 text-decoration-none text-body">
                        <div class="card-body py-3">
                            <span class="fw-semibold">{{ $card['label'] }}</span>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endcan
</x-app-layout>
