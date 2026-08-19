<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Transfer Asset</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('inventory.assets.index') }}">Assets</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('inventory.assets.show', $asset) }}">{{ $asset->asset_code }}</a></li>
                                <li class="breadcrumb-item" aria-current="page">Transfer</li>
                            </ul>
                        </div>
                        <a href="{{ route('inventory.assets.show', $asset) }}" class="btn btn-sm btn-light">
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
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0">{{ $asset->name }} <span class="text-muted fw-normal">{{ $asset->asset_code }}</span></h6>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-4">
                        Current location: {{ $asset->location?->name ?? 'Not set' }}
                    </p>

                    <form method="POST" action="{{ route('inventory.assets.transfer', $asset) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">To Location <span class="text-danger">*</span></label>
                            <select name="to_location_id" class="form-control" required>
                                <option value="">-- Select location --</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}" @selected(old('to_location_id') == $location->id)>{{ $location->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason <span class="text-danger">*</span></label>
                            <textarea name="reason" rows="4" class="form-control" required placeholder="Why is this asset being moved?">{{ old('reason') }}</textarea>
                        </div>
                        <p class="text-muted small">Transfers do not require approval. A reason is required for the history record.</p>
                        <button type="submit" class="btn btn-primary">Transfer Asset</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
