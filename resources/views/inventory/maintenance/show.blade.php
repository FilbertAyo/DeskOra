<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">{{ $record->record_number }}</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('inventory.maintenance.index') }}">Maintenance</a></li>
                                <li class="breadcrumb-item" aria-current="page">Details</li>
                            </ul>
                        </div>
                        <div class="d-flex gap-2">
                            @can('complete', $record)
                                <a href="{{ route('inventory.maintenance.complete.form', $record) }}" class="btn btn-sm btn-primary">Complete repair</a>
                            @endcan
                            <a href="{{ route('inventory.maintenance.index') }}" class="btn btn-sm btn-light">Back</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-7">
            <div class="card shadow-none border mb-3">
                <div class="card-header bg-white border-bottom d-flex justify-content-between">
                    <h6 class="mb-0">Problem</h6>
                    <span class="badge bg-light-secondary">
                        {{ $statusOptions[$record->status] ?? $record->status }}
                    </span>
                </div>
                <div class="card-body">
                    <p class="mb-2"><strong>Asset:</strong>
                        @if($record->asset)
                            <a href="{{ route('inventory.assets.show', $record->asset) }}">{{ $record->asset->asset_code }}</a>
                            — {{ $record->asset->name }}
                        @endif
                    </p>
                    <p class="mb-2"><strong>Reported by:</strong> {{ $record->reportedBy?->name ?? '—' }} on {{ $record->reported_at?->format('M d, Y h:i A') }}</p>
                    <p class="mb-0"><strong>Description:</strong> {{ $record->problem_description }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom"><h6 class="mb-0">Repair</h6></div>
                <div class="card-body">
                    @if($record->isOpen())
                        <p class="text-muted mb-0">Repair is still in progress.</p>
                    @else
                        <p class="mb-2"><strong>Provider:</strong> {{ $record->repair_provider ?: '—' }}</p>
                        <p class="mb-2"><strong>Date:</strong> {{ $record->repaired_on?->format('M d, Y') ?? '—' }}</p>
                        <p class="mb-2"><strong>Cost:</strong> {{ $record->repair_cost !== null ? number_format((float) $record->repair_cost, 0).' TZS' : '—' }}</p>
                        <p class="mb-2"><strong>Condition after:</strong> {{ $conditionOptions[$record->condition_after] ?? ($record->condition_after ?: '—') }}</p>
                        <p class="mb-0"><strong>Notes:</strong> {{ $record->repair_notes ?: '—' }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
