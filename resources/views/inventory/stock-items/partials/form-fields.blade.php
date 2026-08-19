@php
    $stockItemModel = $stockItem ?? null;
@endphp

<div class="card shadow-none border">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label">Item Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                    value="{{ old('name', $stockItemModel?->name) }}" required>
                @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Status <span class="text-danger">*</span></label>
                <select name="status" class="form-control" required>
                    <option value="active" @selected(old('status', $stockItemModel?->status ?? 'active') === 'active')>Active</option>
                    <option value="inactive" @selected(old('status', $stockItemModel?->status) === 'inactive')>Inactive</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Category</label>
                <select name="category_id" class="form-control">
                    <option value="">-- Select --</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id', $stockItemModel?->category_id) == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Unit</label>
                <input type="text" name="unit" class="form-control" value="{{ old('unit', $stockItemModel?->unit) }}" placeholder="e.g. boxes, pieces">
            </div>
            <div class="col-md-4">
                <label class="form-label">Location</label>
                <select name="location_id" class="form-control">
                    <option value="">-- Select --</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" @selected(old('location_id', $stockItemModel?->location_id) == $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Quantity Received <span class="text-danger">*</span></label>
                <input type="number" min="0" name="quantity_received" class="form-control @error('quantity_received') is-invalid @enderror"
                    value="{{ old('quantity_received', $stockItemModel?->quantity_received ?? 0) }}" required>
                @error('quantity_received')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Minimum Level <span class="text-danger">*</span></label>
                <input type="number" min="0" name="minimum_level" class="form-control @error('minimum_level') is-invalid @enderror"
                    value="{{ old('minimum_level', $stockItemModel?->minimum_level ?? 0) }}" required>
                @error('minimum_level')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Supplier</label>
                <select name="supplier_id" class="form-control">
                    <option value="">-- Select --</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected(old('supplier_id', $stockItemModel?->supplier_id) == $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-12">
                <label class="form-label">Description</label>
                <textarea name="description" rows="2" class="form-control">{{ old('description', $stockItemModel?->description) }}</textarea>
            </div>
            @if($stockItemModel)
                <div class="col-md-12">
                    <p class="text-muted mb-0 small">Issued: <strong>{{ $stockItemModel->quantity_issued }}</strong> · Remaining: <strong>{{ $stockItemModel->quantity_remaining }}</strong></p>
                </div>
            @endif
        </div>
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-3">
    <a href="{{ route('inventory.stock-items.index') }}" class="btn btn-light">Cancel</a>
    <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i> Save Stock Item</button>
</div>
