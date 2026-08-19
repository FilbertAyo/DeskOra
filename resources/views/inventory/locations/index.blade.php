<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Inventory Locations</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}">Inventory</a></li>
                                <li class="breadcrumb-item" aria-current="page">Locations</li>
                            </ul>
                        </div>
                        <div class="col-auto d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-light" onclick="window.location.reload()" title="Refresh">
                                <i class="ti ti-refresh"></i>
                            </button>
                            <button type="button" class="btn btn-primary btn-sm" id="newLocationBtn">
                                <i class="ti ti-circle-plus me-1"></i> New Location
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
                            <h5 class="mb-0"><i class="ti ti-map-pin me-2"></i>Location List</h5>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="ti ti-search"></i>
                                </span>
                                <input type="text" class="form-control border-start-0" id="searchInput"
                                    placeholder="Search locations by name, parent, or description...">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered table-striped" id="locationsTable">
                            <thead class="thead-light">
                                <tr>
                                    <th class="text-center" style="width: 50px;">No.</th>
                                    <th>Name</th>
                                    <th>Parent</th>
                                    <th>Description</th>
                                    <th class="text-center" style="width: 110px;">Children</th>
                                    <th class="text-center" style="width: 120px;">Status</th>
                                    <th class="text-center" style="width: 180px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($locations as $location)
                                    <tr>
                                        <td class="text-center">{{ $loop->iteration }}</td>
                                        <td class="fw-semibold">{{ $location->name }}</td>
                                        <td>
                                            @if($location->parent)
                                                <span class="text-muted">{{ $location->parent->name }}</span>
                                            @else
                                                <span class="text-muted">Top-Level</span>
                                            @endif
                                        </td>
                                        <td class="text-muted">{{ $location->description ?: '—' }}</td>
                                        <td class="text-center">
                                            <span class="badge bg-light-secondary border border-secondary">{{ $location->children_count }}</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-light-secondary">
                                                {{ ucfirst($location->status) }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <x-table-action-dropdown>
<button type="button" class="dropdown-item edit-btn"
    data-id="{{ $location->id }}"
    data-name="{{ $location->name }}"
    data-description="{{ $location->description }}"
    data-status="{{ $location->status }}"
    data-parent-id="{{ $location->parent_id }}">
    <i class="ti ti-edit me-2"></i> Edit
</button>
<button type="button" class="dropdown-item text-danger delete-btn"
    data-id="{{ $location->id }}"
    data-name="{{ $location->name }}">
    <i class="ti ti-trash me-2"></i> Delete
</button>
</x-table-action-dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="ti ti-map-pin fs-1 text-muted"></i>
                                            <p class="text-muted mb-0">No locations found</p>
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

    <div class="modal fade" id="locationFormModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="formTitle">
                        <i class="ti ti-circle-plus me-2"></i>Add New Location
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="{{ route('inventory.locations.store') }}" id="locationForm">
                        @csrf
                        <input type="hidden" name="_method" id="formMethod" value="POST">
                        <input type="hidden" name="location_id" id="locationId">

                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="locationName" class="form-label">
                                    Location Name <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="ti ti-map-pin"></i>
                                    </span>
                                    <input type="text"
                                        class="form-control @error('name') is-invalid @enderror"
                                        name="name"
                                        id="locationName"
                                        placeholder="Enter location name"
                                        value="{{ old('name') }}"
                                        required>
                                </div>
                                @error('name')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="locationStatus" class="form-label">
                                    Status <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="ti ti-activity"></i>
                                    </span>
                                    <select name="status"
                                        id="locationStatus"
                                        class="form-control @error('status') is-invalid @enderror"
                                        required>
                                        <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Active</option>
                                        <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                    </select>
                                </div>
                                @error('status')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="locationType" class="form-label">
                                    Create As <span class="text-danger">*</span>
                                </label>
                                <select id="locationType" class="form-control">
                                    <option value="location">Top-Level Location</option>
                                    <option value="sub_location">Sub-Location</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3 d-none" id="parentLocationWrapper">
                                <label for="parentLocation" class="form-label">
                                    Parent Location <span class="text-danger">*</span>
                                </label>
                                <select name="parent_id"
                                    id="parentLocation"
                                    class="form-control @error('parent_id') is-invalid @enderror">
                                    <option value="">-- Select Parent Location --</option>
                                    @foreach($parentLocations as $parentLocation)
                                        <option value="{{ $parentLocation->id }}" {{ old('parent_id') == $parentLocation->id ? 'selected' : '' }}>
                                            {{ $parentLocation->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('parent_id')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-12 mb-0">
                                <label for="locationDescription" class="form-label">Description</label>
                                <textarea
                                    name="description"
                                    id="locationDescription"
                                    rows="4"
                                    class="form-control @error('description') is-invalid @enderror"
                                    placeholder="Optional short description for admins">{{ old('description') }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                                <i class="ti ti-x me-1"></i> Cancel
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-device-floppy me-1"></i> Save Location
                            </button>
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
                    <h5 class="modal-title">
                        <i class="ti ti-alert-triangle me-2"></i>Confirm Deletion
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Are you sure you want to delete the location <strong id="deleteLocationName"></strong>?</p>
                    <p class="text-danger small mb-0 mt-2">
                        <i class="ti ti-alert-circle me-1"></i>
                        This action cannot be undone.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="ti ti-x me-1"></i> Cancel
                    </button>
                    <form id="deleteForm" method="POST" style="display: inline;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="ti ti-trash me-1"></i> Delete Location
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="locationScriptData"
        data-has-errors="{{ $errors->any() ? '1' : '0' }}"
        data-store-route="{{ route('inventory.locations.store') }}"
        class="d-none"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const scriptData = document.getElementById('locationScriptData');
            const locationForm = document.getElementById('locationForm');
            const formTitle = document.getElementById('formTitle');
            const newLocationBtn = document.getElementById('newLocationBtn');
            const searchInput = document.getElementById('searchInput');
            const locationType = document.getElementById('locationType');
            const parentLocationWrapper = document.getElementById('parentLocationWrapper');
            const parentLocation = document.getElementById('parentLocation');
            const table = document.getElementById('locationsTable');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const formModal = new bootstrap.Modal(document.getElementById('locationFormModal'));
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

            function toggleParentLocation() {
                const isSub = locationType && locationType.value === 'sub_location';

                parentLocationWrapper.classList.toggle('d-none', !isSub);
                if (isSub) {
                    parentLocation.setAttribute('required', 'required');
                } else {
                    parentLocation.removeAttribute('required');
                    parentLocation.value = '';
                }
            }

            function resetForm() {
                locationForm.reset();
                locationForm.action = scriptData?.dataset?.storeRoute || '';
                document.getElementById('formMethod').value = 'POST';
                document.getElementById('locationId').value = '';
                document.getElementById('locationStatus').value = 'active';
                locationType.value = 'location';
                toggleParentLocation();
            }

            function updateRowNumbers() {
                let visibleIndex = 1;
                rows.forEach(row => {
                    if (row.style.display !== 'none' && row.querySelectorAll('td').length > 1) {
                        row.querySelector('td:first-child').textContent = visibleIndex++;
                    }
                });
            }

            function filterTable() {
                const searchTerm = searchInput.value.toLowerCase();

                rows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    if (cells.length === 1) return;

                    const name = cells[1].textContent.toLowerCase();
                    const parent = cells[2].textContent.toLowerCase();
                    const description = cells[3].textContent.toLowerCase();
                    const matchesSearch = name.includes(searchTerm)
                        || parent.includes(searchTerm)
                        || description.includes(searchTerm);

                    row.style.display = matchesSearch ? '' : 'none';
                });

                updateRowNumbers();
            }

            if (newLocationBtn) {
                newLocationBtn.addEventListener('click', function() {
                    resetForm();
                    formTitle.innerHTML = '<i class="ti ti-circle-plus me-2"></i>Add New Location';
                    formModal.show();
                });
            }

            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.dataset.id;
                    locationForm.action = `/inventory/locations/${id}`;
                    document.getElementById('formMethod').value = 'PUT';
                    document.getElementById('locationId').value = id;
                    document.getElementById('locationName').value = this.dataset.name;
                    document.getElementById('locationDescription').value = this.dataset.description || '';
                    document.getElementById('locationStatus').value = this.dataset.status;
                    parentLocation.value = this.dataset.parentId || '';
                    locationType.value = this.dataset.parentId ? 'sub_location' : 'location';
                    toggleParentLocation();
                    formTitle.innerHTML = '<i class="ti ti-edit me-2"></i>Edit Location';
                    formModal.show();
                });
            });

            document.querySelectorAll('.delete-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.dataset.id;
                    const name = this.dataset.name;
                    document.getElementById('deleteLocationName').textContent = name;
                    document.getElementById('deleteForm').action = `/inventory/locations/${id}`;
                    deleteModal.show();
                });
            });

            if (locationType) {
                locationType.addEventListener('change', toggleParentLocation);
            }

            if (searchInput) {
                searchInput.addEventListener('keyup', filterTable);
            }

            if (scriptData?.dataset?.hasErrors === '1') {
                if (parentLocation && parentLocation.value) {
                    locationType.value = 'sub_location';
                }
                toggleParentLocation();
                formModal.show();
            }
        });
    </script>
</x-app-layout>
