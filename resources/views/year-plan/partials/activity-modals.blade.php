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
