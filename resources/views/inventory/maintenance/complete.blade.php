<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Complete Repair</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('inventory.maintenance.index') }}">Maintenance</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('inventory.maintenance.show', $record) }}">{{ $record->record_number }}</a></li>
                                <li class="breadcrumb-item" aria-current="page">Complete</li>
                            </ul>
                        </div>
                        <a href="{{ route('inventory.maintenance.show', $record) }}" class="btn btn-sm btn-light">Back</a>
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
                    <p class="text-muted">{{ $record->asset?->asset_code }} — {{ $record->asset?->name }}</p>
                    <p>{{ $record->problem_description }}</p>
                    <form method="POST" action="{{ route('inventory.maintenance.complete', $record) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Repair provider <span class="text-danger">*</span></label>
                            <input type="text" name="repair_provider" class="form-control" required value="{{ old('repair_provider') }}">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Repair date <span class="text-danger">*</span></label>
                                <input type="date" name="repaired_on" class="form-control" required value="{{ old('repaired_on', now()->toDateString()) }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Cost (TZS)</label>
                                <input type="number" min="0" step="0.01" name="repair_cost" class="form-control" value="{{ old('repair_cost') }}">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Condition after repair <span class="text-danger">*</span></label>
                            <select name="condition_after" class="form-control" required>
                                @foreach($conditionOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('condition_after', 'good') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="repair_notes" rows="3" class="form-control">{{ old('repair_notes') }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Save completion</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
