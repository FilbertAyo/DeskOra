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
                            @if($tab === 'rooms')
                                <button type="button" class="btn btn-primary btn-sm" id="newLocationBtn" data-type="room"
                                    @disabled($parentLocations->isEmpty())>
                                    <i class="ti ti-circle-plus me-1"></i> New Room
                                </button>
                            @else
                                <button type="button" class="btn btn-primary btn-sm" id="newLocationBtn" data-type="location">
                                    <i class="ti ti-circle-plus me-1"></i> New Location
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('inventory.partials.tabs', [
        'current' => $tab,
        'tabs' => [
            [
                'key' => 'locations',
                'label' => 'Locations',
                'url' => route('inventory.locations.index', ['tab' => 'locations']),
                'count' => $sites->count(),
            ],
            [
                'key' => 'rooms',
                'label' => 'Rooms',
                'url' => route('inventory.locations.index', ['tab' => 'rooms']),
                'count' => $rooms->count(),
            ],
        ],
    ])

    <div class="card shadow-none border mt-3">
        <div class="card-header bg-white border-bottom">
            <div class="row align-items-center">
                <div class="col-md-6 mb-2 mb-md-0">
                    <h5 class="mb-0">
                        @if($tab === 'rooms')
                            <i class="ti ti-layout me-2"></i>Rooms
                        @else
                            <i class="ti ti-map-pin me-2"></i>Locations
                        @endif
                    </h5>
                    <p class="text-muted small mb-0">
                        @if($tab === 'rooms')
                            Rooms sit under a location, e.g. Adilisha HQ → Board Room.
                        @else
                            Sites and branches. Add rooms under a location from the Rooms tab.
                        @endif
                    </p>
                </div>
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-white">
                            <i class="ti ti-search"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" id="searchInput"
                            placeholder="{{ $tab === 'rooms' ? 'Search rooms by name, location, or description...' : 'Search locations by name or description...' }}">
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body">
            @if($tab === 'rooms' && $parentLocations->isEmpty())
                <div class="alert alert-secondary mb-3">
                    Create a location first, then you can add rooms under it.
                    <a href="{{ route('inventory.locations.index', ['tab' => 'locations']) }}" class="alert-link">Go to Locations</a>
                </div>
            @endif
            <div class="table-responsive">
                @if($tab === 'rooms')
                    <table class="table table-hover table-bordered table-striped" id="locationsTable">
                        <thead class="thead-light">
                            <tr>
                                <th class="text-center" style="width: 50px;">No.</th>
                                <th>Room</th>
                                <th>Location</th>
                                <th>Description</th>
                                <th class="text-center" style="width: 120px;">Status</th>
                                <th class="text-center" style="width: 180px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rooms as $location)
                                <tr>
                                    <td class="text-center">{{ $loop->iteration }}</td>
                                    <td class="fw-semibold">{{ $location->name }}</td>
                                    <td>{{ $location->parent?->name ?? '—' }}</td>
                                    <td class="text-muted">{{ $location->description ?: '—' }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-light-secondary">{{ ucfirst($location->status) }}</span>
                                    </td>
                                    <td class="text-center">
                                        <x-table-action-dropdown>
                                            <button type="button" class="dropdown-item edit-btn"
                                                data-id="{{ $location->id }}"
                                                data-name="{{ $location->name }}"
                                                data-description="{{ $location->description }}"
                                                data-status="{{ $location->status }}"
                                                data-parent-id="{{ $location->parent_id }}"
                                                data-type="room">
                                                <i class="ti ti-edit me-2"></i> Edit
                                            </button>
                                            <button type="button" class="dropdown-item text-danger delete-btn"
                                                data-id="{{ $location->id }}"
                                                data-name="{{ $location->name }}"
                                                data-kind="room">
                                                <i class="ti ti-trash me-2"></i> Delete
                                            </button>
                                        </x-table-action-dropdown>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <i class="ti ti-layout fs-1 text-muted"></i>
                                        <p class="text-muted mb-0">No rooms yet. Add rooms under a location such as Adilisha HQ.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                @else
                    <table class="table table-hover table-bordered table-striped" id="locationsTable">
                        <thead class="thead-light">
                            <tr>
                                <th class="text-center" style="width: 50px;">No.</th>
                                <th>Location</th>
                                <th>Description</th>
                                <th class="text-center" style="width: 110px;">Rooms</th>
                                <th class="text-center" style="width: 120px;">Status</th>
                                <th class="text-center" style="width: 180px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($sites as $location)
                                <tr>
                                    <td class="text-center">{{ $loop->iteration }}</td>
                                    <td class="fw-semibold">{{ $location->name }}</td>
                                    <td class="text-muted">{{ $location->description ?: '—' }}</td>
                                    <td class="text-center">
                                        <a href="{{ route('inventory.locations.index', ['tab' => 'rooms']) }}" class="badge bg-light-secondary border border-secondary text-decoration-none">
                                            {{ $location->children_count }}
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light-secondary">{{ ucfirst($location->status) }}</span>
                                    </td>
                                    <td class="text-center">
                                        <x-table-action-dropdown>
                                            <button type="button" class="dropdown-item edit-btn"
                                                data-id="{{ $location->id }}"
                                                data-name="{{ $location->name }}"
                                                data-description="{{ $location->description }}"
                                                data-status="{{ $location->status }}"
                                                data-parent-id=""
                                                data-type="location">
                                                <i class="ti ti-edit me-2"></i> Edit
                                            </button>
                                            <button type="button" class="dropdown-item text-danger delete-btn"
                                                data-id="{{ $location->id }}"
                                                data-name="{{ $location->name }}"
                                                data-kind="location">
                                                <i class="ti ti-trash me-2"></i> Delete
                                            </button>
                                        </x-table-action-dropdown>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <i class="ti ti-map-pin fs-1 text-muted"></i>
                                        <p class="text-muted mb-0">No locations yet. Add a site such as Adilisha HQ.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                @endif
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
                        <input type="hidden" name="return_tab" id="returnTab" value="{{ $tab }}">

                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="locationName" class="form-label" id="nameLabel">
                                    Location name <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="ti ti-map-pin" id="nameIcon"></i>
                                    </span>
                                    <input type="text"
                                        class="form-control @error('name') is-invalid @enderror"
                                        name="name"
                                        id="locationName"
                                        placeholder="Enter name"
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

                            <div class="col-md-12 mb-3 d-none" id="parentLocationWrapper">
                                <label for="parentLocation" class="form-label">
                                    Location <span class="text-danger">*</span>
                                </label>
                                <select name="parent_id"
                                    id="parentLocation"
                                    class="form-control @error('parent_id') is-invalid @enderror">
                                    <option value="">-- Select location --</option>
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
                                    placeholder="Optional short description">{{ old('description') }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                                <i class="ti ti-x me-1"></i> Cancel
                            </button>
                            <button type="submit" class="btn btn-primary" id="saveBtn">
                                <i class="ti ti-device-floppy me-1"></i> Save
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
                    <p class="mb-0">Are you sure you want to delete <strong id="deleteLocationName"></strong>?</p>
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
                        <button type="submit" class="btn btn-danger" id="deleteSubmitBtn">
                            <i class="ti ti-trash me-1"></i> Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="locationScriptData"
        data-has-errors="{{ $errors->any() ? '1' : '0' }}"
        data-store-route="{{ route('inventory.locations.store') }}"
        data-default-type="{{ $tab === 'rooms' ? 'room' : 'location' }}"
        class="d-none"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const scriptData = document.getElementById('locationScriptData');
            const locationForm = document.getElementById('locationForm');
            const formTitle = document.getElementById('formTitle');
            const newLocationBtn = document.getElementById('newLocationBtn');
            const searchInput = document.getElementById('searchInput');
            const parentLocationWrapper = document.getElementById('parentLocationWrapper');
            const parentLocation = document.getElementById('parentLocation');
            const nameLabel = document.getElementById('nameLabel');
            const nameIcon = document.getElementById('nameIcon');
            const table = document.getElementById('locationsTable');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const formModal = new bootstrap.Modal(document.getElementById('locationFormModal'));
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            let currentType = scriptData?.dataset?.defaultType || 'location';

            function applyType(type) {
                currentType = type === 'room' ? 'room' : 'location';
                const isRoom = currentType === 'room';
                parentLocationWrapper.classList.toggle('d-none', !isRoom);
                if (isRoom) {
                    parentLocation.setAttribute('required', 'required');
                    nameLabel.innerHTML = 'Room name <span class="text-danger">*</span>';
                    nameIcon.className = 'ti ti-layout';
                    document.getElementById('locationName').placeholder = 'e.g. Board Room';
                } else {
                    parentLocation.removeAttribute('required');
                    parentLocation.value = '';
                    nameLabel.innerHTML = 'Location name <span class="text-danger">*</span>';
                    nameIcon.className = 'ti ti-map-pin';
                    document.getElementById('locationName').placeholder = 'e.g. Adilisha HQ';
                }
            }

            function resetForm(type) {
                locationForm.reset();
                locationForm.action = scriptData?.dataset?.storeRoute || '';
                document.getElementById('formMethod').value = 'POST';
                document.getElementById('locationId').value = '';
                document.getElementById('locationStatus').value = 'active';
                applyType(type || scriptData?.dataset?.defaultType || 'location');
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

                    const text = Array.from(cells).map(cell => cell.textContent.toLowerCase()).join(' ');
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });

                updateRowNumbers();
            }

            if (newLocationBtn && !newLocationBtn.disabled) {
                newLocationBtn.addEventListener('click', function() {
                    const type = this.dataset.type || 'location';
                    resetForm(type);
                    formTitle.innerHTML = type === 'room'
                        ? '<i class="ti ti-circle-plus me-2"></i>Add New Room'
                        : '<i class="ti ti-circle-plus me-2"></i>Add New Location';
                    formModal.show();
                });
            }

            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.dataset.id;
                    const type = this.dataset.type || (this.dataset.parentId ? 'room' : 'location');
                    locationForm.action = `/inventory/locations/${id}`;
                    document.getElementById('formMethod').value = 'PUT';
                    document.getElementById('locationId').value = id;
                    document.getElementById('locationName').value = this.dataset.name;
                    document.getElementById('locationDescription').value = this.dataset.description || '';
                    document.getElementById('locationStatus').value = this.dataset.status;
                    applyType(type);
                    parentLocation.value = this.dataset.parentId || '';
                    formTitle.innerHTML = type === 'room'
                        ? '<i class="ti ti-edit me-2"></i>Edit Room'
                        : '<i class="ti ti-edit me-2"></i>Edit Location';
                    formModal.show();
                });
            });

            document.querySelectorAll('.delete-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const kind = this.dataset.kind === 'room' ? 'room' : 'location';
                    document.getElementById('deleteLocationName').textContent = this.dataset.name;
                    document.getElementById('deleteForm').action = `/inventory/locations/${this.dataset.id}`;
                    document.getElementById('deleteSubmitBtn').innerHTML = '<i class="ti ti-trash me-1"></i> Delete ' + (kind === 'room' ? 'Room' : 'Location');
                    deleteModal.show();
                });
            });

            if (searchInput) {
                searchInput.addEventListener('keyup', filterTable);
            }

            if (scriptData?.dataset?.hasErrors === '1') {
                applyType(parentLocation && parentLocation.value ? 'room' : (scriptData.dataset.defaultType || 'location'));
                formTitle.innerHTML = currentType === 'room'
                    ? '<i class="ti ti-circle-plus me-2"></i>Add New Room'
                    : '<i class="ti ti-circle-plus me-2"></i>Add New Location';
                formModal.show();
            }
        });
    </script>
</x-app-layout>
