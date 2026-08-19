<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Report Problem</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('inventory.maintenance.index') }}">Maintenance</a></li>
                                <li class="breadcrumb-item" aria-current="page">Report</li>
                            </ul>
                        </div>
                        <a href="{{ route('inventory.maintenance.index') }}" class="btn btn-sm btn-light"><i class="ti ti-arrow-left me-1"></i> Back</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-none border">
                <div class="card-body">
                    <form method="POST" action="{{ route('inventory.maintenance.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Asset <span class="text-danger">*</span></label>
                            <select name="asset_id" class="form-control" required>
                                <option value="">-- Select asset --</option>
                                @foreach($assets as $option)
                                    <option value="{{ $option->id }}" @selected(old('asset_id', $asset?->id) == $option->id)>
                                        {{ $option->asset_code }} — {{ $option->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Reporting a problem sets the asset to Under Repair.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Problem description <span class="text-danger">*</span></label>
                            <textarea name="problem_description" rows="4" class="form-control" required>{{ old('problem_description') }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Report Problem</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
