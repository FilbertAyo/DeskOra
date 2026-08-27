<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Inventory Suppliers</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}">Inventory</a></li>
                                <li class="breadcrumb-item" aria-current="page">Suppliers</li>
                            </ul>
                        </div>
                        <div class="col-auto d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-light" onclick="window.location.reload()" title="Refresh">
                                <i class="ti ti-refresh"></i>
                            </button>
                            <button type="button" class="btn btn-primary btn-sm" id="newSupplierBtn">
                                <i class="ti ti-circle-plus me-1"></i> New Supplier
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom">
                    <div class="row align-items-center">
                        <div class="col-md-6 mb-2 mb-md-0">
                            <h5 class="mb-0"><i class="ti ti-truck-delivery me-2"></i>Supplier List</h5>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="ti ti-search"></i></span>
                                <input type="text" class="form-control border-start-0" id="searchInput"
                                    placeholder="Search by name, contact, phone, or email...">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered table-striped" id="suppliersTable">
                            <thead class="thead-light">
                                <tr>
                                    <th class="text-center" style="width: 50px;">No.</th>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Phone / Email</th>
                                    <th class="text-center" style="width: 120px;">Status</th>
                                    <th class="text-center" style="width: 180px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($suppliers as $supplier)
                                    <tr>
                                        <td class="text-center">{{ $loop->iteration }}</td>
                                        <td class="fw-semibold">{{ $supplier->name }}</td>
                                        <td>{{ $supplier->contact_name ?: '—' }}</td>
                                        <td>
                                            <div class="small">
                                                <div>{{ $supplier->phone ?: '—' }}</div>
                                                <div class="text-muted">{{ $supplier->email ?: '' }}</div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge {{ $supplier->status === 'active' ? 'bg-light-success border border-success' : 'bg-light-secondary' }}">
                                                {{ ucfirst($supplier->status) }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <x-table-action-dropdown>
<button type="button" class="dropdown-item edit-btn"
    data-id="{{ $supplier->id }}"
    data-name="{{ e($supplier->name) }}"
    data-contact-name="{{ e($supplier->contact_name) }}"
    data-phone="{{ e($supplier->phone) }}"
    data-email="{{ e($supplier->email) }}"
    data-address="{{ e($supplier->address) }}"
    data-notes="{{ e($supplier->notes) }}"
    data-status="{{ $supplier->status }}">
    <i class="ti ti-edit me-2"></i> Edit
</button>
<button type="button" class="dropdown-item text-danger delete-btn"
    data-id="{{ $supplier->id }}"
    data-name="{{ e($supplier->name) }}">
    <i class="ti ti-trash me-2"></i> Delete
</button>
</x-table-action-dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <i class="ti ti-truck-delivery fs-1 text-muted"></i>
                                            <p class="text-muted mb-0">No suppliers found</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="supplierFormModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="formTitle"><i class="ti ti-circle-plus me-2"></i>Add New Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="{{ route('inventory.suppliers.store') }}" id="supplierForm">
                        @csrf
                        <input type="hidden" name="_method" id="formMethod" value="POST">
                        <input type="hidden" name="supplier_id" id="supplierId">

                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="supplierName" class="form-label">Supplier Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" id="supplierName"
                                    value="{{ old('name') }}" placeholder="Enter supplier name" required>
                                @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="supplierStatus" class="form-label">Status <span class="text-danger">*</span></label>
                                <select name="status" id="supplierStatus" class="form-control @error('status') is-invalid @enderror" required>
                                    <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                </select>
                                @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="supplierContactName" class="form-label">Contact Person</label>
                                <input type="text" class="form-control @error('contact_name') is-invalid @enderror" name="contact_name"
                                    id="supplierContactName" value="{{ old('contact_name') }}" placeholder="Contact name">
                                @error('contact_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="supplierPhone" class="form-label">Phone</label>
                                <input type="text" class="form-control @error('phone') is-invalid @enderror" name="phone"
                                    id="supplierPhone" value="{{ old('phone') }}" placeholder="Phone number">
                                @error('phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="supplierEmail" class="form-label">Email</label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror" name="email"
                                    id="supplierEmail" value="{{ old('email') }}" placeholder="Email address">
                                @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="supplierAddress" class="form-label">Address</label>
                                <textarea name="address" id="supplierAddress" rows="2" class="form-control @error('address') is-invalid @enderror"
                                    placeholder="Physical or postal address">{{ old('address') }}</textarea>
                                @error('address')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-12 mb-0">
                                <label for="supplierNotes" class="form-label">Notes</label>
                                <textarea name="notes" id="supplierNotes" rows="2" class="form-control @error('notes') is-invalid @enderror"
                                    placeholder="Optional admin notes">{{ old('notes') }}</textarea>
                                @error('notes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i> Cancel</button>
                            <button type="submit" class="btn btn-primary" data-busy-label="Saving…"><i class="ti ti-device-floppy me-1"></i> Save Supplier</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="ti ti-alert-triangle me-2"></i>Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Are you sure you want to delete the supplier <strong id="deleteSupplierName"></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form id="deleteForm" method="POST" style="display: inline;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger" data-busy-label="Deleting…"><i class="ti ti-trash me-1"></i> Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="supplierScriptData" data-has-errors="{{ $errors->any() ? '1' : '0' }}"
        data-store-route="{{ route('inventory.suppliers.store') }}" class="d-none"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const scriptData = document.getElementById('supplierScriptData');
            const form = document.getElementById('supplierForm');
            const formTitle = document.getElementById('formTitle');
            const searchInput = document.getElementById('searchInput');
            const table = document.getElementById('suppliersTable');
            const rows = Array.from(table.querySelectorAll('tbody tr'));
            const formModal = new bootstrap.Modal(document.getElementById('supplierFormModal'));
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

            function resetForm() {
                form.reset();
                form.action = scriptData?.dataset?.storeRoute || '';
                document.getElementById('formMethod').value = 'POST';
                document.getElementById('supplierId').value = '';
                document.getElementById('supplierStatus').value = 'active';
            }

            document.getElementById('newSupplierBtn')?.addEventListener('click', function() {
                resetForm();
                formTitle.innerHTML = '<i class="ti ti-circle-plus me-2"></i>Add New Supplier';
                formModal.show();
            });

            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.dataset.id;
                    form.action = `/inventory/suppliers/${id}`;
                    document.getElementById('formMethod').value = 'PUT';
                    document.getElementById('supplierId').value = id;
                    document.getElementById('supplierName').value = this.dataset.name;
                    document.getElementById('supplierContactName').value = this.dataset.contactName || '';
                    document.getElementById('supplierPhone').value = this.dataset.phone || '';
                    document.getElementById('supplierEmail').value = this.dataset.email || '';
                    document.getElementById('supplierAddress').value = this.dataset.address || '';
                    document.getElementById('supplierNotes').value = this.dataset.notes || '';
                    document.getElementById('supplierStatus').value = this.dataset.status;
                    formTitle.innerHTML = '<i class="ti ti-edit me-2"></i>Edit Supplier';
                    formModal.show();
                });
            });

            document.querySelectorAll('.delete-btn').forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('deleteSupplierName').textContent = this.dataset.name;
                    document.getElementById('deleteForm').action = `/inventory/suppliers/${this.dataset.id}`;
                    deleteModal.show();
                });
            });

            searchInput?.addEventListener('keyup', function() {
                const term = this.value.toLowerCase();
                rows.forEach(row => {
                    if (row.querySelectorAll('td').length === 1) return;
                    row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
                });
            });

            if (scriptData?.dataset?.hasErrors === '1') formModal.show();
        });
    </script>
</x-app-layout>
