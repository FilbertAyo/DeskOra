<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">New Borrow Request</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('inventory.borrowing.index') }}">Borrowing</a></li>
                                <li class="breadcrumb-item" aria-current="page">Create</li>
                            </ul>
                        </div>
                        <a href="{{ route('inventory.borrowing.index') }}" class="btn btn-sm btn-light">
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

    <form method="POST" action="{{ route('inventory.borrowing.store') }}" id="borrowForm">
        @csrf
        <div class="card shadow-none border">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Item Type <span class="text-danger">*</span></label>
                        <select name="item_type" id="itemType" class="form-control" required>
                            <option value="asset" @selected(old('item_type', $prefillAssetId ? 'asset' : 'asset') === 'asset')>Tracked Asset</option>
                            <option value="stock" @selected(old('item_type', $prefillStockItemId ? 'stock' : '') === 'stock')>Stock / Consumable</option>
                        </select>
                    </div>
                    <div class="col-md-8 asset-field">
                        <label class="form-label">Asset <span class="text-danger">*</span></label>
                        <select name="asset_id" class="form-control">
                            <option value="">-- Select asset --</option>
                            @foreach($assets as $asset)
                                <option value="{{ $asset->id }}" @selected(old('asset_id', $prefillAssetId) == $asset->id)>
                                    {{ $asset->asset_code }} — {{ $asset->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-8 stock-field d-none">
                        <label class="form-label">Stock Item <span class="text-danger">*</span></label>
                        <select name="stock_item_id" class="form-control">
                            <option value="">-- Select stock item --</option>
                            @foreach($stockItems as $item)
                                <option value="{{ $item->id }}" @selected(old('stock_item_id', $prefillStockItemId) == $item->id)>
                                    {{ $item->name }} ({{ $item->quantity_remaining }} remaining)
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 stock-field d-none">
                        <label class="form-label">Quantity <span class="text-danger">*</span></label>
                        <input type="number" min="1" name="quantity" class="form-control" value="{{ old('quantity', 1) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Borrower <span class="text-danger">*</span></label>
                        <select name="borrower_user_id" class="form-control" required>
                            <option value="">-- Select staff --</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" @selected(old('borrower_user_id', auth()->id()) == $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Expected Return Date <span class="text-danger">*</span></label>
                        <input type="date" name="expected_return_date" class="form-control" required
                            value="{{ old('expected_return_date') }}" min="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Purpose <span class="text-danger">*</span></label>
                        <textarea name="purpose" rows="3" class="form-control" required>{{ old('purpose') }}</textarea>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-3">
            <a href="{{ route('inventory.borrowing.index') }}" class="btn btn-light">Cancel</a>
            <button type="submit" class="btn btn-primary" data-busy-label="Submitting…">Submit Request</button>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const itemType = document.getElementById('itemType');
            function toggle() {
                const isStock = itemType.value === 'stock';
                document.querySelectorAll('.asset-field').forEach(el => el.classList.toggle('d-none', isStock));
                document.querySelectorAll('.stock-field').forEach(el => el.classList.toggle('d-none', !isStock));
            }
            itemType.addEventListener('change', toggle);
            toggle();
        });
    </script>
</x-app-layout>
