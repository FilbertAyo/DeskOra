<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">{{ $asset->name }}</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item">
                                    @can('viewAny', App\Models\InventoryAsset::class)
                                        <a href="{{ route('inventory.assets.index') }}">Assets</a>
                                    @else
                                        <a href="{{ route('inventory.my-items.index') }}">My items</a>
                                    @endcan
                                </li>
                                <li class="breadcrumb-item" aria-current="page">{{ $asset->asset_code }}</li>
                            </ul>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            @if(! $asset->isDisposed())
                                @can('update', $asset)
                                    <a href="{{ route('inventory.assets.edit', $asset) }}" class="btn btn-sm btn-primary"><i class="ti ti-edit me-1"></i> Edit</a>
                                @endcan
                                @if(auth()->user()?->can('assign', $asset)
                                    || auth()->user()?->can('transfer', $asset)
                                    || auth()->user()?->can('manage inventory borrowing')
                                    || auth()->user()?->can('reportProblem', $asset)
                                    || auth()->user()?->can('requestDisposal', $asset))
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">More</button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            @can('assign', $asset)
                                                <li>
                                                    <button type="button" class="dropdown-item assign-btn"
                                                        data-id="{{ $asset->id }}"
                                                        data-name="{{ e($asset->name) }}"
                                                        data-code="{{ e($asset->asset_code) }}"
                                                        data-department="{{ $asset->department_id }}"
                                                        data-user="{{ $asset->responsible_user_id }}">
                                                        <i class="ti ti-user-check me-2"></i> Assign
                                                    </button>
                                                </li>
                                            @endcan
                                            @can('transfer', $asset)
                                                <li><a class="dropdown-item" href="{{ route('inventory.assets.transfer.form', $asset) }}"><i class="ti ti-arrows-left-right me-2"></i> Transfer</a></li>
                                            @endcan
                                            @can('manage inventory borrowing')
                                                <li><a class="dropdown-item" href="{{ route('inventory.borrowing.create', ['asset_id' => $asset->id]) }}"><i class="ti ti-hand-grab me-2"></i> Request borrow</a></li>
                                            @endcan
                                            @can('reportProblem', $asset)
                                                <li><a class="dropdown-item" href="{{ route('inventory.maintenance.create', ['asset_id' => $asset->id]) }}"><i class="ti ti-tool me-2"></i> Report problem</a></li>
                                            @endcan
                                            @can('requestDisposal', $asset)
                                                <li><a class="dropdown-item" href="{{ route('inventory.disposal.create', ['asset_id' => $asset->id]) }}"><i class="ti ti-trash me-2"></i> Dispose</a></li>
                                            @endcan
                                        </ul>
                                    </div>
                                @endif
                            @endif
                            @can('delete', $asset)
                                <form method="POST" action="{{ route('inventory.assets.destroy', $asset) }}" class="d-inline" onsubmit="return confirm('Delete asset {{ $asset->asset_code }} ({{ $asset->name }})? This cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" data-busy-label="Deleting…">
                                        <i class="ti ti-trash me-1"></i> Delete
                                    </button>
                                </form>
                            @endcan
                            @can('viewAny', App\Models\InventoryAsset::class)
                                <a href="{{ route('inventory.assets.index') }}" class="btn btn-sm btn-light"><i class="ti ti-arrow-left me-1"></i> Back</a>
                            @else
                                <a href="{{ route('inventory.my-items.index') }}" class="btn btn-sm btn-light"><i class="ti ti-arrow-left me-1"></i> Back</a>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($asset->isDisposed())
        <div class="alert alert-secondary">This asset is disposed. History stays visible and editing is locked.</div>
    @endif

    @if($currentDispatch ?? null)
        <div class="alert alert-secondary">
            Currently on dispatch
            <a href="{{ route('inventory.dispatches.show', $currentDispatch) }}">{{ $currentDispatch->dispatch_number }}</a>
            — {{ $currentDispatch->title }}
            ({{ $currentDispatch->destination }}).
            Temporary responsible people: {{ $currentDispatch->responsiblePeopleLabel() }}.
            The permanent custodian has not changed.
        </div>
    @endif

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-none border mb-3">
                <div class="card-header bg-white border-bottom"><h6 class="mb-0">Summary</h6></div>
                <div class="card-body">
                    <p class="mb-2"><strong>Asset ID:</strong> {{ $asset->asset_code }}</p>
                    <p class="mb-2"><strong>Serial:</strong> {{ $asset->serial_number ?: '—' }}</p>
                    <p class="mb-2"><strong>Category:</strong> {{ $asset->category?->name ?? '—' }}</p>
                    <p class="mb-2"><strong>Location:</strong> {{ $asset->location?->pathLabel() ?? '—' }}</p>
                    <p class="mb-2"><strong>Department:</strong> {{ $asset->department?->pathLabel() ?? '—' }}</p>
                    <p class="mb-2"><strong>Responsible:</strong> {{ $asset->responsibleUser?->name ?? '—' }}</p>
                    <p class="mb-2">
                        <strong>Condition:</strong>
                        <span class="badge bg-light-secondary">{{ $conditionOptions[$asset->condition] ?? $asset->condition }}</span>
                    </p>
                    <p class="mb-0">
                        <strong>Status:</strong>
                        <span class="badge bg-light-secondary">{{ $statusOptions[$asset->status] ?? $asset->status }}</span>
                    </p>
                </div>
            </div>
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom"><h6 class="mb-0">Source</h6></div>
                <div class="card-body">
                    <p class="mb-2"><strong>Type:</strong> {{ $sourceTypeOptions[$asset->source_type] ?? $asset->source_type }}</p>
                    <p class="mb-2"><strong>Supplier:</strong> {{ $asset->supplier?->name ?? '—' }}</p>
                    <p class="mb-2"><strong>Donor:</strong> {{ $asset->donor?->name ?? '—' }}</p>
                    <p class="mb-2"><strong>Project:</strong> {{ $asset->project?->name ?? '—' }}</p>
                    <p class="mb-0"><strong>Received:</strong> {{ $asset->date_received?->format('M d, Y') ?? '—' }}</p>
                </div>
            </div>
            <div class="card shadow-none border mt-3">
                <div class="card-header bg-white border-bottom"><h6 class="mb-0">QR label</h6></div>
                <div class="card-body text-center">
                    <img src="{{ $qrDataUri }}" alt="QR {{ $asset->asset_code }}" width="140" height="140">
                    <div class="small text-muted mt-2">Scan to open this asset</div>
                    @can('viewAny', App\Models\InventoryAsset::class)
                        <a href="{{ route('inventory.assets.labels', ['search' => $asset->asset_code]) }}" class="btn btn-sm btn-outline-secondary mt-2">Print label</a>
                    @endcan
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom"><h6 class="mb-0"><i class="ti ti-history me-2"></i>History Timeline</h6></div>
                <div class="card-body">
                    @forelse($timeline as $entry)
                        <div class="border rounded p-3 mb-3">
                            @if($entry['type'] === 'receipt')
                                @php $row = $entry['record']; @endphp
                                <div class="d-flex justify-content-between">
                                    <strong><i class="ti ti-receipt me-1"></i> Receipt</strong>
                                    <small class="text-muted">{{ $entry['at']->format('M d, Y') }}</small>
                                </div>
                                <div class="small mt-2">
                                    Receipt: <a href="{{ route('inventory.receipts.show', $row) }}">{{ $row->receipt_number }}</a><br>
                                    Received by: {{ $row->receivedByUser?->name ?? '—' }}
                                </div>
                            @elseif($entry['type'] === 'assignment')
                                @php $row = $entry['record']; @endphp
                                <div class="d-flex justify-content-between">
                                    <strong><i class="ti ti-user-check me-1"></i> Assignment</strong>
                                    <small class="text-muted">{{ $entry['at']->format('M d, Y h:i A') }}</small>
                                </div>
                                <div class="small mt-2">
                                    Assigned to:
                                    @if($row->assignedToUser && $row->assignedToDepartment)
                                        {{ $row->assignedToDepartment->pathLabel() ?? $row->assignedToDepartment->name }} → {{ $row->assignedToUser->name }}
                                    @elseif($row->assignedToUser)
                                        {{ $row->assignedToUser->name }}
                                    @elseif($row->assignedToDepartment)
                                        {{ $row->assignedToDepartment->pathLabel() ?? $row->assignedToDepartment->name }}
                                    @else
                                        —
                                    @endif
                                    <br>
                                    By: {{ $row->assignedByUser?->name ?? '—' }}
                                    @if($row->notes)<br>Notes: {{ $row->notes }}@endif
                                </div>
                            @elseif($entry['type'] === 'transfer')
                                @php $row = $entry['record']; @endphp
                                <div class="d-flex justify-content-between">
                                    <strong><i class="ti ti-arrows-left-right me-1"></i> Transfer</strong>
                                    <small class="text-muted">{{ $entry['at']->format('M d, Y h:i A') }}</small>
                                </div>
                                <div class="small mt-2">
                                    From: {{ $row->fromLocation?->pathLabel() ?? '—' }} → To: {{ $row->toLocation?->pathLabel() ?? '—' }}<br>
                                    Reason: {{ $row->reason }}<br>
                                    By: {{ $row->transferredByUser?->name ?? '—' }}
                                </div>
                            @elseif($entry['type'] === 'borrow')
                                @php $row = $entry['record']; @endphp
                                <div class="d-flex justify-content-between">
                                    <strong><i class="ti ti-hand-grab me-1"></i> Borrow requested</strong>
                                    <small class="text-muted">{{ $entry['at']->format('M d, Y h:i A') }}</small>
                                </div>
                                <div class="small mt-2">
                                    Request: <a href="{{ route('inventory.borrowing.show', $row) }}">{{ $row->request_number }}</a><br>
                                    Borrower: {{ $row->borrower?->name ?? '—' }}
                                </div>
                            @elseif($entry['type'] === 'borrow_issue')
                                @php $row = $entry['record']; @endphp
                                <div class="d-flex justify-content-between">
                                    <strong><i class="ti ti-package-export me-1"></i> Borrow issued</strong>
                                    <small class="text-muted">{{ $entry['at']->format('M d, Y h:i A') }}</small>
                                </div>
                                <div class="small mt-2">
                                    Request: <a href="{{ route('inventory.borrowing.show', $row) }}">{{ $row->request_number }}</a>
                                </div>
                            @elseif($entry['type'] === 'borrow_return')
                                @php $row = $entry['record']; @endphp
                                <div class="d-flex justify-content-between">
                                    <strong><i class="ti ti-package-import me-1"></i> Borrow returned</strong>
                                    <small class="text-muted">{{ $entry['at']->format('M d, Y h:i A') }}</small>
                                </div>
                                <div class="small mt-2">
                                    Request: <a href="{{ route('inventory.borrowing.show', $row) }}">{{ $row->request_number }}</a>
                                    @if($row->return_condition)
                                        <br>Condition: {{ \App\Models\InventoryBorrowRequest::returnConditionOptions()[$row->return_condition] ?? $row->return_condition }}
                                    @endif
                                </div>
                            @elseif($entry['type'] === 'dispatch_included')
                                @php $row = $entry['record']; $dispatch = $row->dispatch; @endphp
                                <div class="d-flex justify-content-between">
                                    <strong><i class="ti ti-clipboard-list me-1"></i> Included in dispatch</strong>
                                    <small class="text-muted">{{ $entry['at']->format('M d, Y h:i A') }}</small>
                                </div>
                                <div class="small mt-2">
                                    @if($dispatch)
                                        Dispatch: <a href="{{ route('inventory.dispatches.show', $dispatch) }}">{{ $dispatch->dispatch_number }}</a>
                                        — {{ $dispatch->title }}<br>
                                        Destination: {{ $dispatch->destination }}<br>
                                        Temporary responsible: {{ $dispatch->responsiblePeopleLabel() }}
                                    @endif
                                    @if($row->originalResponsibleUser || $row->original_responsible_user_id)
                                        <br>Normal assignment kept: {{ $row->originalResponsibleUser?->name ?? 'assigned staff' }}
                                    @endif
                                </div>
                            @elseif($entry['type'] === 'dispatch_out')
                                @php $row = $entry['record']; $dispatch = $row->dispatch; @endphp
                                <div class="d-flex justify-content-between">
                                    <strong><i class="ti ti-truck-delivery me-1"></i> Dispatched</strong>
                                    <small class="text-muted">{{ $entry['at']->format('M d, Y h:i A') }}</small>
                                </div>
                                <div class="small mt-2">
                                    @if($dispatch)
                                        <a href="{{ route('inventory.dispatches.show', $dispatch) }}">{{ $dispatch->dispatch_number }}</a>
                                        to {{ $dispatch->destination }}
                                    @endif
                                    @if($row->condition_at_dispatch)
                                        <br>Condition at dispatch: {{ \App\Models\InventoryAsset::conditionOptions()[$row->condition_at_dispatch] ?? $row->condition_at_dispatch }}
                                    @endif
                                </div>
                            @elseif($entry['type'] === 'dispatch_return')
                                @php $row = $entry['record']; $dispatch = $row->dispatch; @endphp
                                <div class="d-flex justify-content-between">
                                    <strong><i class="ti ti-package-import me-1"></i> Dispatch returned</strong>
                                    <small class="text-muted">{{ $entry['at']->format('M d, Y h:i A') }}</small>
                                </div>
                                <div class="small mt-2">
                                    @if($dispatch)
                                        <a href="{{ route('inventory.dispatches.show', $dispatch) }}">{{ $dispatch->dispatch_number }}</a>
                                    @endif
                                    @if($row->return_outcome)
                                        <br>Return condition: {{ \App\Models\InventoryDispatchItem::returnOutcomeOptions()[$row->return_outcome] ?? $row->return_outcome }}
                                    @endif
                                    @if($row->return_notes)
                                        <br>Notes: {{ $row->return_notes }}
                                    @endif
                                </div>
                            @elseif($entry['type'] === 'dispatch_closed')
                                @php $row = $entry['record']; $dispatch = $row->dispatch; @endphp
                                <div class="d-flex justify-content-between">
                                    <strong><i class="ti ti-circle-check me-1"></i> Back to normal assignment</strong>
                                    <small class="text-muted">{{ $entry['at']->format('M d, Y h:i A') }}</small>
                                </div>
                                <div class="small mt-2">
                                    @if($dispatch)
                                        Closed: <a href="{{ route('inventory.dispatches.show', $dispatch) }}">{{ $dispatch->dispatch_number }}</a><br>
                                    @endif
                                    Restored custodian: {{ $row->originalResponsibleUser?->name ?? '—' }}
                                    @if($row->originalLocation)
                                        · Location: {{ $row->originalLocation->pathLabel() }}
                                    @endif
                                </div>
                            @elseif($entry['type'] === 'maintenance_report')
                                @php $row = $entry['record']; @endphp
                                <div class="d-flex justify-content-between">
                                    <strong><i class="ti ti-tool me-1"></i> Problem reported</strong>
                                    <small class="text-muted">{{ $entry['at']->format('M d, Y h:i A') }}</small>
                                </div>
                                <div class="small mt-2">
                                    <a href="{{ route('inventory.maintenance.show', $row) }}">{{ $row->record_number }}</a>
                                    — {{ \Illuminate\Support\Str::limit($row->problem_description, 80) }}
                                </div>
                            @elseif($entry['type'] === 'maintenance_complete')
                                @php $row = $entry['record']; @endphp
                                <div class="d-flex justify-content-between">
                                    <strong><i class="ti ti-tool me-1"></i> Repair completed</strong>
                                    <small class="text-muted">{{ $entry['at']->format('M d, Y h:i A') }}</small>
                                </div>
                                <div class="small mt-2">
                                    <a href="{{ route('inventory.maintenance.show', $row) }}">{{ $row->record_number }}</a>
                                    @if($row->repair_provider)
                                        — {{ $row->repair_provider }}
                                    @endif
                                    @if($row->repair_cost !== null)
                                        · {{ number_format((float) $row->repair_cost, 0) }} TZS
                                    @endif
                                </div>
                            @elseif($entry['type'] === 'disposal_request')
                                @php $row = $entry['record']; @endphp
                                <div class="d-flex justify-content-between">
                                    <strong><i class="ti ti-trash me-1"></i> Disposal requested</strong>
                                    <small class="text-muted">{{ $entry['at']->format('M d, Y h:i A') }}</small>
                                </div>
                                <div class="small mt-2">
                                    <a href="{{ route('inventory.disposal.show', $row) }}">{{ $row->request_number }}</a>
                                    — {{ $row->reason }}
                                </div>
                            @else
                                @php $row = $entry['record']; @endphp
                                <div class="d-flex justify-content-between">
                                    <strong><i class="ti ti-trash me-1"></i> Disposed</strong>
                                    <small class="text-muted">{{ $entry['at']->format('M d, Y h:i A') }}</small>
                                </div>
                                <div class="small mt-2">
                                    <a href="{{ route('inventory.disposal.show', $row) }}">{{ $row->request_number }}</a>
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted mb-0">No history yet. Receipt, assignment, transfer, borrow, dispatch, repair, and disposal events will appear here.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @include('inventory.partials.assign-modal')
    @include('inventory.partials.picker-scripts')
    @include('inventory.partials.assign-modal-scripts', [
        'assignFallbackUrl' => route('inventory.assets.assign', $asset),
    ])
</x-app-layout>
