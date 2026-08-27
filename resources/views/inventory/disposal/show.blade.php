<x-app-layout>
    @php $item = $disposal; @endphp
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">{{ $item->request_number }}</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('inventory.disposal.index') }}">Disposal</a></li>
                                <li class="breadcrumb-item" aria-current="page">Details</li>
                            </ul>
                        </div>
                        <a href="{{ route('inventory.disposal.index') }}" class="btn btn-sm btn-light">Back</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="row">
        <div class="col-md-7">
            <div class="card shadow-none border mb-3">
                <div class="card-header bg-white border-bottom d-flex justify-content-between">
                    <h6 class="mb-0">Request</h6>
                    <span class="badge bg-light-secondary">{{ $statusOptions[$item->status] ?? $item->status }}</span>
                </div>
                <div class="card-body">
                    <p class="mb-2"><strong>Asset:</strong>
                        @if($item->asset)
                            <a href="{{ route('inventory.assets.show', $item->asset) }}">{{ $item->asset->asset_code }}</a>
                            — {{ $item->asset->name }}
                        @endif
                    </p>
                    <p class="mb-2"><strong>Method:</strong> {{ $methodOptions[$item->method] ?? $item->method }}</p>
                    <p class="mb-2"><strong>Reason:</strong> {{ $item->reason }}</p>
                    <p class="mb-0"><strong>Requested by:</strong> {{ $item->requestedBy?->name ?? '—' }} on {{ $item->created_at->format('M d, Y') }}</p>
                </div>
            </div>
            @if($item->reviewed_at)
                <div class="card shadow-none border">
                    <div class="card-header bg-white border-bottom"><h6 class="mb-0">Review</h6></div>
                    <div class="card-body">
                        <p class="mb-2"><strong>By:</strong> {{ $item->reviewedBy?->name ?? '—' }}</p>
                        <p class="mb-2"><strong>At:</strong> {{ $item->reviewed_at->format('M d, Y h:i A') }}</p>
                        <p class="mb-0"><strong>Notes:</strong> {{ $item->review_notes ?: '—' }}</p>
                    </div>
                </div>
            @endif
        </div>
        <div class="col-md-5">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom"><h6 class="mb-0">Approval</h6></div>
                <div class="card-body">
                    @if($item->status === 'requested')
                        @can('approve', $item)
                            <form method="POST" action="{{ route('inventory.disposal.approve', $item) }}" class="mb-3">
                                @csrf
                                <label class="form-label">Review notes</label>
                                <textarea name="review_notes" rows="2" class="form-control mb-2"></textarea>
                                <button type="submit" class="btn btn-primary btn-sm" data-busy-label="Approving…">Approve disposal</button>
                            </form>
                            <form method="POST" action="{{ route('inventory.disposal.reject', $item) }}">
                                @csrf
                                <label class="form-label">Rejection reason <span class="text-danger">*</span></label>
                                <textarea name="review_notes" rows="2" class="form-control mb-2" required></textarea>
                                <button type="submit" class="btn btn-light btn-sm" data-busy-label="Rejecting…">Reject</button>
                            </form>
                        @else
                            <p class="text-muted mb-0">Waiting for review. You cannot approve your own request.</p>
                        @endcan
                    @else
                        <p class="text-muted mb-0">No further approval actions.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
