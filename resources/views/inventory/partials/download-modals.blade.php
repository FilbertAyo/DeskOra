{{-- QR labels: room required --}}
<div class="modal fade" id="qrLabelsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Print QR labels by room</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Select a room to generate QR code labels for assets stored there.</p>
                <div id="qrLabelsRoomPicker">
                    @include('inventory.partials.location-room-picker', [
                        'fieldName' => 'qr_location_id',
                        'selectedLocationId' => '',
                        'emptyLabel' => '-- Select location --',
                        'locationLabel' => 'Location',
                        'roomLabel' => 'Room',
                    ])
                </div>
                <div class="alert alert-danger d-none mt-2 mb-0" id="qrLabelsError">Please select a specific room.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="qrLabelsGoBtn">
                    <i class="ti ti-qrcode me-1"></i> Generate labels
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Asset export: department or room --}}
<div class="modal fade" id="exportAssetsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Download assets</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Filter by department or room, or leave both blank to download all assets.</p>
                <input type="hidden" id="exportAssetsFormat" value="xlsx">

                <div class="mb-3">
                    <label class="form-label">Department</label>
                    <select class="form-control form-control-sm" id="exportDepartmentId">
                        <option value="">All departments</option>
                        @foreach($departments ?? [] as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-0">
                    <div id="exportAssetsRoomPicker">
                        @include('inventory.partials.location-room-picker', [
                            'fieldName' => 'export_location_id',
                            'selectedLocationId' => '',
                            'emptyLabel' => 'All locations',
                            'locationLabel' => 'Location',
                            'roomLabel' => 'Room',
                        ])
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="exportAssetsGoBtn">
                    <i class="ti ti-download me-1"></i> Download
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const labelsBaseUrl = @json(route('inventory.assets.labels'));
    const exportUrlPrefix = @json($exportUrlPrefix ?? url('/inventory/assets/export'));
    const exportFormatInPath = @json($exportFormatInPath ?? true);

    function roomIdFromPicker(wrapId) {
        const wrap = document.getElementById(wrapId);
        if (!wrap) {
            return '';
        }
        const picker = wrap.querySelector('.js-location-room');
        if (!picker) {
            return '';
        }
        const hidden = picker.querySelector('.js-location-id');
        const value = hidden ? hidden.value : '';
        if (!value) {
            return '';
        }
        const locations = (window.inventoryPickerData && window.inventoryPickerData.locations) || [];
        const loc = locations.find(function (location) {
            return String(location.id) === String(value);
        });
        return loc && loc.parent_id ? String(loc.id) : '';
    }

    function locationIdFromPicker(wrapId) {
        const wrap = document.getElementById(wrapId);
        if (!wrap) {
            return '';
        }
        const picker = wrap.querySelector('.js-location-room');
        if (!picker) {
            return '';
        }
        const hidden = picker.querySelector('.js-location-id');
        return hidden ? hidden.value : '';
    }

    function buildQuery(params) {
        const query = new URLSearchParams();
        Object.keys(params).forEach(function (key) {
            if (params[key]) {
                query.set(key, params[key]);
            }
        });
        const serialized = query.toString();
        return serialized ? '?' + serialized : '';
    }

    document.getElementById('qrLabelsGoBtn')?.addEventListener('click', function () {
        const roomId = roomIdFromPicker('qrLabelsRoomPicker');
        const error = document.getElementById('qrLabelsError');
        if (!roomId) {
            error?.classList.remove('d-none');
            return;
        }
        error?.classList.add('d-none');
        window.location.href = labelsBaseUrl + buildQuery({ location_id: roomId });
    });

    document.querySelectorAll('[data-open-export-modal]').forEach(function (button) {
        button.addEventListener('click', function () {
            const formatInput = document.getElementById('exportAssetsFormat');
            if (formatInput) {
                formatInput.value = button.dataset.format || 'xlsx';
            }
            const modal = document.getElementById('exportAssetsModal');
            if (modal && window.bootstrap) {
                window.bootstrap.Modal.getOrCreateInstance(modal).show();
            }
        });
    });

    document.getElementById('exportAssetsGoBtn')?.addEventListener('click', function () {
        const format = document.getElementById('exportAssetsFormat')?.value || @json($exportFormatFixed ?? null) || 'xlsx';
        const departmentId = document.getElementById('exportDepartmentId')?.value || '';
        const locationId = locationIdFromPicker('exportAssetsRoomPicker');
        const params = buildQuery({
            department_id: departmentId,
            location_id: locationId,
        });
        const url = exportFormatInPath
            ? exportUrlPrefix + '/' + encodeURIComponent(format) + params
            : exportUrlPrefix + params;
        window.location.href = url;
    });

    @if(session('open_qr_modal'))
        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('qrLabelsModal');
            if (modal && window.bootstrap) {
                window.bootstrap.Modal.getOrCreateInstance(modal).show();
            }
        });
    @endif
})();
</script>
