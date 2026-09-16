<x-app-layout>
    @php
        $assetItems = $dispatch->items->where('item_type', 'asset');
        $stockItems = $dispatch->items->where('item_type', 'stock');
        $assetReturns = $dispatch->assetReturnCounts();
        $damaged = $assetItems->where('return_outcome', 'received_damaged');
        $missing = $assetItems->where('return_outcome', 'missing');
        $pending = $assetItems->whereNull('return_outcome')->count()
            + $stockItems->filter(fn ($item) => ! $item->hasReturnOutcome())->count();
    @endphp
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Return {{ $dispatch->dispatch_number }}</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('inventory.dispatches.index') }}">Dispatch</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('inventory.dispatches.show', $dispatch) }}">{{ $dispatch->title }}</a></li>
                                <li class="breadcrumb-item" aria-current="page">Return</li>
                            </ul>
                        </div>
                        <a href="{{ route('inventory.dispatches.show', $dispatch) }}" class="btn btn-sm btn-light">
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

    <div class="card shadow-none border mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between flex-wrap gap-2">
                <div>
                    <div class="fw-semibold">{{ $dispatch->title }}</div>
                    <div class="small text-muted">
                        {{ $dispatch->destination }} · {{ $dispatch->dispatch_date?->format('M d, Y') }} → {{ $dispatch->expected_return_date?->format('M d, Y') }}
                        · Responsible: {{ $dispatch->responsiblePeopleLabel() }}
                    </div>
                </div>
                <span class="badge bg-light-secondary">{{ $statusOptions[$dispatch->status] ?? $dispatch->status }}</span>
            </div>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="card shadow-none border h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Dispatched lines</div>
                    <div class="fs-4 fw-semibold">{{ $dispatch->items->count() }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-none border h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Received — Good</div>
                    <div class="fs-4 fw-semibold">{{ $assetReturns['received_good'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-none border h-100 border-warning">
                <div class="card-body py-3">
                    <div class="text-muted small">Damaged</div>
                    <div class="fs-4 fw-semibold">{{ $assetReturns['received_damaged'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-none border h-100 border-danger">
                <div class="card-body py-3">
                    <div class="text-muted small">Missing</div>
                    <div class="fs-4 fw-semibold">{{ $assetReturns['missing'] }}</div>
                </div>
            </div>
        </div>
    </div>

    @if($pending === 0 && $dispatch->items->isNotEmpty())
        <div class="alert alert-secondary">Every line has a return outcome. Complete the return, then close the dispatch to restore assignments and stock.</div>
    @endif

    @if($damaged->isNotEmpty() || $missing->isNotEmpty())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-2">Exceptions</div>
            @foreach($missing as $line)
                <div>Missing: {{ $line->itemName() }}</div>
            @endforeach
            @foreach($damaged as $line)
                <div>Damaged: {{ $line->itemName() }}{{ $line->return_notes ? ' — '.$line->return_notes : '' }}</div>
            @endforeach
        </div>
    @endif

    <div class="d-flex flex-wrap gap-2 mb-3">
        <form method="POST" action="{{ route('inventory.dispatches.return.mark-all-good', $dispatch) }}" onsubmit="return confirm('Mark every item as received and good? You can still change exceptions afterwards.');">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm" data-busy-label="Updating…">Mark All as Received &amp; Good</button>
        </form>
        @if($pending === 0 && $dispatch->items->isNotEmpty())
            <form method="POST" action="{{ route('inventory.dispatches.complete-return', $dispatch) }}">
                @csrf
                <button type="submit" class="btn btn-outline-primary btn-sm" data-busy-label="Saving…">Complete return</button>
            </form>
        @endif
    </div>

    <form method="POST" action="{{ route('inventory.dispatches.return.save', $dispatch) }}">
        @csrf
        <div class="card shadow-none border mb-3">
            <div class="card-header bg-white border-bottom"><h6 class="mb-0">Assets</h6></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="thead-light">
                            <tr>
                                <th>Asset</th>
                                <th>Custodian</th>
                                <th>Outcome</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($assetItems as $line)
                                @php
                                    $rowClass = match ($line->return_outcome) {
                                        'received_damaged' => 'table-warning',
                                        'missing' => 'table-danger',
                                        default => '',
                                    };
                                @endphp
                                <tr class="{{ $rowClass }}">
                                    <td>
                                        @if($line->asset)
                                            <a href="{{ route('inventory.assets.show', $line->asset) }}">{{ $line->asset->asset_code }}</a>
                                            <div class="small text-muted">{{ $line->asset->name }}</div>
                                        @else
                                            {{ $line->itemName() }}
                                        @endif
                                    </td>
                                    <td>{{ $line->originalResponsibleUser?->name ?? $line->asset?->responsibleUser?->name ?? '—' }}</td>
                                    <td>
                                        <select name="items[{{ $line->id }}][return_outcome]" class="form-control form-control-sm js-return-outcome">
                                            <option value="">Pending</option>
                                            @foreach($returnOutcomeOptions as $value => $label)
                                                <option value="{{ $value }}" @selected($line->return_outcome === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" name="items[{{ $line->id }}][return_notes]" class="form-control form-control-sm js-return-notes" value="{{ old("items.{$line->id}.return_notes", $line->return_notes) }}" placeholder="Required if damaged">
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-3">No assets on this dispatch.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card shadow-none border mb-3">
            <div class="card-header bg-white border-bottom"><h6 class="mb-0">Stock</h6></div>
            <div class="card-body">
                <p class="small text-muted">Returned + consumed must equal the dispatched quantity.</p>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="thead-light">
                            <tr>
                                <th>Item</th>
                                <th class="text-end">Dispatched</th>
                                <th class="text-end">Returned</th>
                                <th class="text-end">Consumed</th>
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
                                    <td>
                                        <input type="number" min="0" max="{{ $line->quantity }}" name="items[{{ $line->id }}][quantity_returned]" class="form-control form-control-sm text-end" value="{{ old("items.{$line->id}.quantity_returned", $line->quantity_returned) }}">
                                    </td>
                                    <td>
                                        <input type="number" min="0" max="{{ $line->quantity }}" name="items[{{ $line->id }}][quantity_consumed]" class="form-control form-control-sm text-end" value="{{ old("items.{$line->id}.quantity_consumed", $line->quantity_consumed) }}">
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-3">No stock items on this dispatch.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('inventory.dispatches.show', $dispatch) }}" class="btn btn-light">Cancel</a>
            <button type="submit" class="btn btn-primary" data-busy-label="Saving…">Save outcomes</button>
        </div>
    </form>
</x-app-layout>
