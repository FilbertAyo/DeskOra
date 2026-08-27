@php
    $departmentField = $departmentField ?? 'department_id';
    $userField = $userField ?? 'responsible_user_id';
    $selectedDepartmentId = (string) old($departmentField, $selectedDepartmentId ?? '');
    $selectedUserId = (string) old($userField, $selectedUserId ?? '');
    $required = $required ?? false;
    $notesField = $notesField ?? 'notes';
    $showNotes = $showNotes ?? false;
    $idPrefix = $idPrefix ?? $departmentField;
@endphp

<div class="js-ownership-cascade"
    data-required="{{ $required ? '1' : '0' }}"
    data-selected-dept="{{ $selectedDepartmentId }}"
    data-selected-user="{{ $selectedUserId }}">
    <div class="mb-3">
        <label class="form-label">
            Department
            @if($required)<span class="text-danger">*</span>@endif
        </label>
        <select name="parent_department_id" class="form-control js-own-parent" @required($required)>
            <option value="">-- Select department --</option>
        </select>
    </div>
    <div class="form-check mb-3">
        <input class="form-check-input js-own-whole-dept" type="checkbox" name="assign_whole_department" value="1" id="{{ $idPrefix }}_whole_dept">
        <label class="form-check-label" for="{{ $idPrefix }}_whole_dept">
            Assign to the whole department
        </label>
    </div>
    <div class="js-own-sub-wrap d-none">
        <div class="mb-3">
            <label class="form-label">Sub-department</label>
            <select name="sub_department_id" class="form-control js-own-sub">
                <option value="">-- Select sub-department --</option>
            </select>
        </div>
        <div class="form-check mb-3 js-own-whole-sub-wrap d-none">
            <input class="form-check-input js-own-whole-sub" type="checkbox" name="assign_whole_sub_department" value="1" id="{{ $idPrefix }}_whole_sub">
            <label class="form-check-label" for="{{ $idPrefix }}_whole_sub">
                Assign to the whole sub-department
            </label>
        </div>
    </div>
    <div class="js-own-user-wrap d-none mb-3">
        <label class="form-label">Staff member</label>
        <select name="{{ $userField }}" class="form-control js-own-user">
            <option value="">-- Select staff --</option>
        </select>
    </div>
    <input type="hidden" name="{{ $departmentField }}" class="js-own-dept-id" value="{{ $selectedDepartmentId }}">
    <div class="alert alert-light border mb-0 py-2">
        <div class="small text-muted mb-0 js-own-summary">Choose a department, then optionally a sub-department or person.</div>
    </div>
    @if($showNotes)
        <div class="mt-3 mb-0">
            <label class="form-label">Notes</label>
            <textarea name="{{ $notesField }}" rows="2" class="form-control" placeholder="Optional notes">{{ old($notesField) }}</textarea>
        </div>
    @endif
</div>
