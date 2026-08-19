<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">{{ $asset->asset_code }}</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('inventory.assets.index') }}">Assets</a></li>
                                <li class="breadcrumb-item" aria-current="page">Scan</li>
                            </ul>
                        </div>
                        <a href="{{ route('inventory.assets.show', $asset) }}" class="btn btn-sm btn-primary">Full record</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-none border">
        <div class="card-body">
            <h4 class="mb-1">{{ $asset->name }}</h4>
            <p class="text-muted">{{ $asset->asset_code }}</p>
            <p class="mb-2"><strong>Status:</strong> {{ $statusOptions[$asset->status] ?? $asset->status }}</p>
            <p class="mb-2"><strong>Condition:</strong> {{ $conditionOptions[$asset->condition] ?? $asset->condition }}</p>
            <p class="mb-2"><strong>Location:</strong> {{ $asset->location?->name ?? '—' }}</p>
            <p class="mb-2"><strong>Department:</strong> {{ $asset->department?->name ?? '—' }}</p>
            <p class="mb-0"><strong>Responsible:</strong> {{ $asset->responsibleUser?->name ?? '—' }}</p>
        </div>
    </div>
</x-app-layout>
