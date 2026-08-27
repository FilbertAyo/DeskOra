<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Inventory Donors</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}">Inventory</a></li>
                                <li class="breadcrumb-item" aria-current="page">Donors</li>
                            </ul>
                        </div>
                        <div class="col-auto d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-light" onclick="window.location.reload()" title="Refresh">
                                <i class="ti ti-refresh"></i>
                            </button>
                            <button type="button" class="btn btn-primary btn-sm" id="newDonorBtn">
                                <i class="ti ti-circle-plus me-1"></i> New Donor
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
                            <h5 class="mb-0"><i class="ti ti-heart-handshake me-2"></i>Donor List</h5>
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
                        <table class="table table-hover table-bordered table-striped" id="donorsTable">
                            <thead class="thead-light">
                                <tr>
                                    <th class="text-center" style="width: 50px;">No.</th>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Phone / Email</th>
                                    <th class="text-center" style="width: 90px;">Projects</th>
                                    <th class="text-center" style="width: 120px;">Status</th>
                                    <th class="text-center" style="width: 180px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($donors as $donor)
                                    <tr>
                                        <td class="text-center">{{ $loop->iteration }}</td>
                                        <td class="fw-semibold">{{ $donor->name }}</td>
                                        <td>{{ $donor->contact_name ?: '—' }}</td>
                                        <td>
                                            <div class="small">
                                                <div>{{ $donor->phone ?: '—' }}</div>
                                                <div class="text-muted">{{ $donor->email ?: '' }}</div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-light-secondary border border-secondary">{{ $donor->projects_count }}</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge {{ $donor->status === 'active' ? 'bg-light-success border border-success' : 'bg-light-secondary' }}">
                                                {{ ucfirst($donor->status) }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <x-table-action-dropdown>
<button type="button" class="dropdown-item edit-btn"
    data-id="{{ $donor->id }}"
    data-name="{{ e($donor->name) }}"
    data-contact-name="{{ e($donor->contact_name) }}"
    data-phone="{{ e($donor->phone) }}"
    data-email="{{ e($donor->email) }}"
    data-address="{{ e($donor->address) }}"
    data-notes="{{ e($donor->notes) }}"
    data-status="{{ $donor->status }}">
    <i class="ti ti-edit me-2"></i> Edit
</button>
<button type="button" class="dropdown-item text-danger delete-btn"
    data-id="{{ $donor->id }}"
    data-name="{{ e($donor->name) }}">
    <i class="ti ti-trash me-2"></i> Delete
</button>
</x-table-action-dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="ti ti-heart-handshake fs-1 text-muted"></i>
                                            <p class="text-muted mb-0">No donors found</p>
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

    <div class="modal fade" id="donorFormModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="formTitle"><i class="ti ti-circle-plus me-2"></i>Add New Donor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="{{ route('inventory.donors.store') }}" id="donorForm">
                        @csrf
                        <input type="hidden" name="_method" id="formMethod" value="POST">
                        <input type="hidden" name="donor_id" id="donorId">

                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="donorName" class="form-label">Donor Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" id="donorName"
                                    value="{{ old('name') }}" placeholder="Enter donor name" required>
                                @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="donorStatus" class="form-label">Status <span class="text-danger">*</span></label>
                                <select name="status" id="donorStatus" class="form-control @error('status') is-invalid @enderror" required>
                                    <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                </select>
                                @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="donorContactName" class="form-label">Contact Person</label>
                                <input type="text" class="form-control @error('contact_name') is-invalid @enderror" name="contact_name"
                                    id="donorContactName" value="{{ old('contact_name') }}" placeholder="Contact name">
                                @error('contact_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="donorPhone" class="form-label">Phone</label>
                                <input type="text" class="form-control @error('phone') is-invalid @enderror" name="phone"
                                    id="donorPhone" value="{{ old('phone') }}" placeholder="Phone number">
                                @error('phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="donorEmail" class="form-label">Email</label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror" name="email"
                                    id="donorEmail" value="{{ old('email') }}" placeholder="Email address">
                                @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="donorAddress" class="form-label">Address</label>
                                <textarea name="address" id="donorAddress" rows="2" class="form-control @error('address') is-invalid @enderror"
                                    placeholder="Physical or postal address">{{ old('address') }}</textarea>
                                @error('address')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-12 mb-0">
                                <label for="donorNotes" class="form-label">Notes</label>
                                <textarea name="notes" id="donorNotes" rows="2" class="form-control @error('notes') is-invalid @enderror"
                                    placeholder="Optional admin notes">{{ old('notes') }}</textarea>
                                @error('notes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i> Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i> Save Donor</button>
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
                    <p class="mb-0">Are you sure you want to delete the donor <strong id="deleteDonorName"></strong>?</p>
                    <p class="text-danger small mb-0 mt-2">Donors with linked projects cannot be deleted.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form id="deleteForm" method="POST" style="display: inline;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger"><i class="ti ti-trash me-1"></i> Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="donorScriptData" data-has-errors="{{ $errors->any() ? '1' : '0' }}"
        data-store-route="{{ route('inventory.donors.store') }}" class="d-none"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const scriptData = document.getElementById('donorScriptData');
            const form = document.getElementById('donorForm');
            const formTitle = document.getElementById('formTitle');
            const searchInput = document.getElementById('searchInput');
            const table = document.getElementById('donorsTable');
            const rows = Array.from(table.querySelectorAll('tbody tr'));
            const formModal = new bootstrap.Modal(document.getElementById('donorFormModal'));
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

            function resetForm() {
                form.reset();
                form.action = scriptData?.dataset?.storeRoute || '';
                document.getElementById('formMethod').value = 'POST';
                document.getElementById('donorId').value = '';
                document.getElementById('donorStatus').value = 'active';
            }

            document.getElementById('newDonorBtn')?.addEventListener('click', function() {
                resetForm();
                formTitle.innerHTML = '<i class="ti ti-circle-plus me-2"></i>Add New Donor';
                formModal.show();
            });

            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.dataset.id;
                    form.action = `/inventory/donors/${id}`;
                    document.getElementById('formMethod').value = 'PUT';
                    document.getElementById('donorId').value = id;
                    document.getElementById('donorName').value = this.dataset.name;
                    document.getElementById('donorContactName').value = this.dataset.contactName || '';
                    document.getElementById('donorPhone').value = this.dataset.phone || '';
                    document.getElementById('donorEmail').value = this.dataset.email || '';
                    document.getElementById('donorAddress').value = this.dataset.address || '';
                    document.getElementById('donorNotes').value = this.dataset.notes || '';
                    document.getElementById('donorStatus').value = this.dataset.status;
                    formTitle.innerHTML = '<i class="ti ti-edit me-2"></i>Edit Donor';
                    formModal.show();
                });
            });

            document.querySelectorAll('.delete-btn').forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('deleteDonorName').textContent = this.dataset.name;
                    document.getElementById('deleteForm').action = `/inventory/donors/${this.dataset.id}`;
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
