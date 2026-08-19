<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">New Receipt</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('inventory.receipts.index') }}">Receipts</a></li>
                                <li class="breadcrumb-item" aria-current="page">Create</li>
                            </ul>
                        </div>
                        <a href="{{ route('inventory.receipts.index') }}" class="btn btn-sm btn-light">
                            <i class="ti ti-arrow-left me-1"></i> Back
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>Please fix the following errors:</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('inventory.receipts.store') }}" id="receiptForm">
        @csrf

        <div class="card shadow-none border mb-3">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0"><i class="ti ti-receipt me-2"></i>Receipt Details</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Source Type <span class="text-danger">*</span></label>
                        <select name="source_type" id="sourceType" class="form-control" required>
                            @foreach($sourceTypeOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('source_type', 'purchase') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date Received <span class="text-danger">*</span></label>
                        <input type="date" name="received_at" class="form-control" required
                            value="{{ old('received_at', now()->toDateString()) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Location</label>
                        <select name="location_id" class="form-control">
                            <option value="">-- Select --</option>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}" @selected(old('location_id') == $location->id)>{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 source-field" data-sources="purchase">
                        <label class="form-label">Supplier <span class="text-danger supplier-required">*</span></label>
                        <select name="supplier_id" id="supplierField" class="form-control">
                            <option value="">-- Select supplier --</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 source-field" data-sources="donor grant_project">
                        <label class="form-label">Donor <span class="text-danger donor-required d-none">*</span></label>
                        <select name="donor_id" id="donorField" class="form-control">
                            <option value="">-- Select donor --</option>
                            @foreach($donors as $donor)
                                <option value="{{ $donor->id }}" @selected(old('donor_id') == $donor->id)>{{ $donor->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 source-field" data-sources="grant_project">
                        <label class="form-label">Project <span class="text-danger project-required">*</span></label>
                        <select name="project_id" id="projectField" class="form-control">
                            <option value="">-- Select project --</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}" @selected(old('project_id') == $project->id)>{{ $project->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" rows="2" class="form-control" placeholder="Optional receipt notes">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-none border mb-3">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="ti ti-list me-2"></i>Items Received</h5>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addLineBtn">
                    <i class="ti ti-plus me-1"></i> Add Line
                </button>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Use <strong>Tracked Asset</strong> for individually numbered items. Use <strong>Stock</strong> for consumables — pick an existing item or leave it blank to create a new one.
                </p>
                <div id="receiptLines"></div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('inventory.receipts.index') }}" class="btn btn-light">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <i class="ti ti-device-floppy me-1"></i> Confirm Receipt
            </button>
        </div>
    </form>

    @include('inventory.receipts.partials.line-editor')

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sourceType = document.getElementById('sourceType');

            function toggleSourceFields() {
                const value = sourceType.value;
                document.querySelectorAll('.source-field').forEach(field => {
                    const sources = (field.dataset.sources || '').split(' ');
                    field.classList.toggle('d-none', !sources.includes(value));
                });
            }

            sourceType.addEventListener('change', toggleSourceFields);
            toggleSourceFields();
        });
    </script>
</x-app-layout>
