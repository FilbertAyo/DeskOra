@php
    $assetModel = $asset ?? null;
@endphp

<div class="card shadow-none border">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0"><i class="ti ti-package me-2"></i>Asset Details</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label">Asset Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                    value="{{ old('name', $assetModel?->name) }}" required>
                @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Serial Number</label>
                <input type="text" name="serial_number" class="form-control @error('serial_number') is-invalid @enderror"
                    value="{{ old('serial_number', $assetModel?->serial_number) }}">
                @error('serial_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Category</label>
                <select name="category_id" class="form-control @error('category_id') is-invalid @enderror">
                    <option value="">-- Select --</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id', $assetModel?->category_id) == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Condition <span class="text-danger">*</span></label>
                <select name="condition" class="form-control @error('condition') is-invalid @enderror" required>
                    @foreach($conditionOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('condition', $assetModel?->condition ?? 'good') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('condition')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Status <span class="text-danger">*</span></label>
                <select name="status" class="form-control @error('status') is-invalid @enderror" required>
                    @foreach($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $assetModel?->status ?? 'available') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-12">
                <label class="form-label">Description</label>
                <textarea name="description" rows="2" class="form-control @error('description') is-invalid @enderror">{{ old('description', $assetModel?->description) }}</textarea>
                @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="card shadow-none border mt-3">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0"><i class="ti ti-map-pin me-2"></i>Location & Ownership</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-12">
                @include('inventory.partials.location-room-picker', [
                    'fieldName' => 'location_id',
                    'selectedLocationId' => old('location_id', $assetModel?->location_id),
                ])
            </div>
            <div class="col-md-12">
                @include('inventory.partials.ownership-cascade', [
                    'departmentField' => 'department_id',
                    'userField' => 'responsible_user_id',
                    'selectedDepartmentId' => old('department_id', $assetModel?->department_id),
                    'selectedUserId' => old('responsible_user_id', $assetModel?->responsible_user_id),
                    'idPrefix' => 'asset_ownership',
                ])
            </div>
        </div>
    </div>
</div>

<div class="card shadow-none border mt-3">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0"><i class="ti ti-receipt me-2"></i>Source & Value</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Source Type <span class="text-danger">*</span></label>
                <select name="source_type" class="form-control @error('source_type') is-invalid @enderror" required>
                    @foreach($sourceTypeOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('source_type', $assetModel?->source_type ?? 'other') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Date Received</label>
                <input type="date" name="date_received" class="form-control @error('date_received') is-invalid @enderror"
                    value="{{ old('date_received', optional($assetModel?->date_received)->format('Y-m-d')) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Supplier</label>
                <select name="supplier_id" class="form-control">
                    <option value="">-- Select --</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected(old('supplier_id', $assetModel?->supplier_id) == $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Donor</label>
                <select name="donor_id" class="form-control">
                    <option value="">-- Select --</option>
                    @foreach($donors as $donor)
                        <option value="{{ $donor->id }}" @selected(old('donor_id', $assetModel?->donor_id) == $donor->id)>{{ $donor->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Project</label>
                <select name="project_id" class="form-control">
                    <option value="">-- Select --</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}" @selected(old('project_id', $assetModel?->project_id) == $project->id)>{{ $project->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Purchase Value (TZS)</label>
                <input type="number" step="0.01" min="0" name="purchase_value" class="form-control"
                    value="{{ old('purchase_value', $assetModel?->purchase_value) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Estimated Value (TZS)</label>
                <input type="number" step="0.01" min="0" name="estimated_value" class="form-control"
                    value="{{ old('estimated_value', $assetModel?->estimated_value) }}">
            </div>
            <div class="col-md-8">
                <label class="form-label">Warranty Info</label>
                <input type="text" name="warranty_info" class="form-control"
                    value="{{ old('warranty_info', $assetModel?->warranty_info) }}">
            </div>
            <div class="col-md-12">
                <label class="form-label">Notes</label>
                <textarea name="notes" rows="2" class="form-control">{{ old('notes', $assetModel?->notes) }}</textarea>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-3">
    <a href="{{ route('inventory.assets.index') }}" class="btn btn-light">Cancel</a>
    <button type="submit" class="btn btn-primary" data-busy-label="Saving…"><i class="ti ti-device-floppy me-1"></i> Save Asset</button>
</div>
