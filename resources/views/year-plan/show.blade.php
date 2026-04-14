@php
    $monthLabels = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
        7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
    ];
@endphp

<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Year plan {{ $yearPlan->year }}</h5>
                            <ul class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('year-plan.index') }}">Year Plans</a></li>
                                <li class="breadcrumb-item" aria-current="page">{{ $yearPlan->year }}</li>
                            </ul>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#activityModal"
                                id="btnAddActivityTop">
                                <i class="ti ti-circle-plus me-1"></i> Add activity
                            </button>
                            @can('create', \App\Models\Document::class)
                                @if(\App\Models\Document::canUserCreateType(auth()->user(), 'monthly_report'))
                                    @php
                                        $ypY = (int) $yearPlan->year;
                                        $cy = (int) now()->year;
                                        $defaultMrMonth = $ypY === $cy
                                            ? ((int) now()->month === 1 ? 12 : (int) now()->month - 1)
                                            : ($ypY < $cy ? 12 : 1);
                                    @endphp
                                    <a href="{{ route('documents.create', ['type' => 'monthly_report', 'year' => $ypY, 'month' => $defaultMrMonth]) }}"
                                        class="btn btn-outline-secondary btn-sm">
                                        <i class="ti ti-file-text me-1"></i> Monthly report
                                    </a>
                                @endif
                            @endcan
                            <a href="{{ route('year-plan.index') }}" class="btn btn-light btn-sm">
                                <i class="ti ti-arrow-left me-1"></i> All years
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <p class="text-muted small mb-3">
        Total cost per line is <strong>frequency × units × unit value (TZS)</strong>, stored when you save.
    </p>

    @forelse ($sections as $section)
        @php($dept = $section['department'])
        <div class="card shadow-none border mb-4">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0">
                        @if ($dept->parent)
                            <span class="text-muted">{{ $dept->parent->name }}</span>
                            <i class="ti ti-chevron-right mx-1 text-muted small"></i>
                        @endif
                        {{ $dept->name }}
                    </h5>
                    @if ($dept->plan_section_number === null)
                        <span class="badge bg-warning text-dark mt-1">Section number not set — codes show as —.n</span>
                    @endif
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary btn-add-activity" data-bs-toggle="modal"
                    data-bs-target="#activityModal" data-department-id="{{ $dept->id }}">
                    <i class="ti ti-plus me-1"></i> Add line
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0 align-middle text-nowrap">
                        <thead class="table-light">
                            <tr>
                                <th>Code</th>
                                <th style="min-width: 220px;">Description</th>
                                <th class="text-end">Freq.</th>
                                <th class="text-end">Units</th>
                                <th class="text-end">Unit value (TZS)</th>
                                <th class="text-end">Total (TZS)</th>
                                @foreach ($monthLabels as $m => $label)
                                    <th class="text-center small px-1">{{ $label }}</th>
                                @endforeach
                                <th class="text-center" style="width: 100px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($section['activities'] as $activity)
                                <tr>
                                    <td class="fw-semibold">{{ $activity->displayCode() }}</td>
                                    <td class="text-wrap" style="white-space: normal;">{{ $activity->description }}</td>
                                    <td class="text-end">{{ $activity->frequency }}</td>
                                    <td class="text-end">{{ $activity->unit_count }}</td>
                                    <td class="text-end">{{ number_format((float) $activity->unit_value_tzs, 2) }}</td>
                                    <td class="text-end">{{ number_format((float) $activity->total_cost_tzs, 2) }}</td>
                                    @foreach ($monthLabels as $m => $label)
                                        <td class="text-center px-0">
                                            @if ($activity->isPlannedInMonth($m))
                                                <i class="ti ti-check text-success"></i>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-light btn-edit-activity" title="Edit"
                                            data-activity-b64="{{ base64_encode(json_encode([
                                                'id' => $activity->id,
                                                'department_id' => (int) $activity->department_id,
                                                'sort_order' => (int) $activity->sort_order,
                                                'description' => (string) $activity->description,
                                                'frequency' => (string) $activity->frequency,
                                                'unit_count' => (string) $activity->unit_count,
                                                'unit_value_tzs' => (string) $activity->unit_value_tzs,
                                                'planned_months' => array_values(array_map('intval', $activity->planned_months ?? [])),
                                            ], JSON_UNESCAPED_UNICODE)) }}">
                                            <i class="ti ti-edit"></i>
                                        </button>
                                        <form action="{{ route('year-plan.activities.destroy', [$yearPlan->year, $activity]) }}" method="post" class="d-inline"
                                            onsubmit="return confirm('Remove this activity?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light text-danger" title="Delete">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="5" class="text-end">Subtotal (TZS)</th>
                                <th class="text-end">{{ number_format($section['subtotal'], 2) }}</th>
                                <th colspan="13"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    @empty
        <div class="card border shadow-none">
            <div class="card-body text-center text-muted py-5">
                <p class="mb-2">No activities yet. Add a sub-department line using <strong>Add activity</strong>, then pick the sub-department.</p>
                <p class="small mb-0">Sub-departments are managed under Settings › Departments.</p>
            </div>
        </div>
    @endforelse

    <div class="card shadow-none border border-primary mb-4">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fw-semibold">Grand total (TZS)</span>
            <span class="fs-5 mb-0">{{ number_format($grandTotal, 2) }}</span>
        </div>
    </div>

    {{-- Create activity --}}
    <div class="modal fade" id="activityModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form method="post" action="{{ route('year-plan.activities.store', $yearPlan->year) }}" id="activityCreateForm">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="ti ti-circle-plus me-2"></i>Add activity</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Sub-department <span class="text-danger">*</span></label>
                            <select name="department_id" id="activityDepartmentId" class="form-select" required>
                                <option value="">— Select —</option>
                                @foreach ($subDepartments as $sd)
                                    <option value="{{ $sd->id }}">
                                        {{ $sd->parent?->name ? $sd->parent->name . ' › ' : '' }}{{ $sd->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Frequency <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" name="frequency" class="form-control" value="1" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Units (#) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" name="unit_count" class="form-control" value="1" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Unit value (TZS) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" name="unit_value_tzs" class="form-control" value="0" required>
                            </div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Planned months</label>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($monthLabels as $num => $label)
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="planned_months[]" value="{{ $num }}" id="cm{{ $num }}">
                                        <label class="form-check-label" for="cm{{ $num }}">{{ $label }}</label>
                                    </div>
                                @endforeach
                            </div>
                            <p class="text-muted small mt-2 mb-0">Leave unchecked if months are not decided yet.</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save activity</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit activity --}}
    <div class="modal fade" id="editActivityModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form method="post" id="editActivityForm">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="ti ti-edit me-2"></i>Edit activity</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Sub-department <span class="text-danger">*</span></label>
                            <select name="department_id" id="editActivityDepartmentId" class="form-select" required>
                                @foreach ($subDepartments as $sd)
                                    <option value="{{ $sd->id }}">
                                        {{ $sd->parent?->name ? $sd->parent->name . ' › ' : '' }}{{ $sd->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Line number (code suffix) <span class="text-danger">*</span></label>
                            <input type="number" min="1" max="9999" name="sort_order" id="editSortOrder" class="form-control" required>
                            <p class="text-muted small mb-0 mt-1">Code = section number from department settings + this number (e.g. 4 + .2 → 4.2).</p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" id="editDescription" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Frequency <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" name="frequency" id="editFrequency" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Units (#) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" name="unit_count" id="editUnitCount" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Unit value (TZS) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" name="unit_value_tzs" id="editUnitValue" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Planned months</label>
                            <div class="d-flex flex-wrap gap-2" id="editMonthsContainer">
                                @foreach ($monthLabels as $num => $label)
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input edit-month-cb" type="checkbox" name="planned_months[]" value="{{ $num }}" id="em{{ $num }}">
                                        <label class="form-check-label" for="em{{ $num }}">{{ $label }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const activityModal = document.getElementById('activityModal');
            const deptSelect = document.getElementById('activityDepartmentId');
            const createForm = document.getElementById('activityCreateForm');

            document.querySelectorAll('.btn-add-activity').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-department-id');
                    if (id && deptSelect) deptSelect.value = id;
                });
            });

            const topBtn = document.getElementById('btnAddActivityTop');
            if (topBtn && activityModal) {
                topBtn.addEventListener('click', function() {
                    if (deptSelect) deptSelect.value = '';
                    if (createForm) createForm.reset();
                });
            }

            const editForm = document.getElementById('editActivityForm');
            const editModalEl = document.getElementById('editActivityModal');
            const baseUpdateUrl = @json(url('/year-plans/' . $yearPlan->year . '/activities'));

            function decodeActivityPayload(b64) {
                if (!b64) {
                    return {};
                }
                try {
                    const binary = atob(b64);
                    const bytes = new Uint8Array(binary.length);
                    for (let i = 0; i < binary.length; i++) {
                        bytes[i] = binary.charCodeAt(i);
                    }
                    const text = new TextDecoder('utf-8').decode(bytes);
                    return JSON.parse(text);
                } catch (err) {
                    return {};
                }
            }

            document.querySelectorAll('.btn-edit-activity').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const payload = decodeActivityPayload(this.getAttribute('data-activity-b64'));
                    if (!payload.id) {
                        return;
                    }
                    editForm.action = baseUpdateUrl + '/' + payload.id;
                    const deptEl = document.getElementById('editActivityDepartmentId');
                    if (deptEl) {
                        deptEl.value = String(payload.department_id);
                    }
                    document.getElementById('editSortOrder').value = payload.sort_order;
                    document.getElementById('editDescription').value = payload.description != null ? payload.description : '';
                    document.getElementById('editFrequency').value = payload.frequency != null ? payload.frequency : '';
                    document.getElementById('editUnitCount').value = payload.unit_count != null ? payload.unit_count : '';
                    document.getElementById('editUnitValue').value = payload.unit_value_tzs != null ? payload.unit_value_tzs : '';
                    const months = Array.isArray(payload.planned_months) ? payload.planned_months : [];
                    document.querySelectorAll('.edit-month-cb').forEach(function(cb) {
                        const v = parseInt(cb.value, 10);
                        cb.checked = months.includes(v) || months.includes(String(v));
                    });
                    if (editModalEl && typeof bootstrap !== 'undefined') {
                        bootstrap.Modal.getOrCreateInstance(editModalEl).show();
                    }
                });
            });
        });
    </script>
</x-app-layout>
