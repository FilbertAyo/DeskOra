<x-app-layout>
@php
    $selectedRequestType = 'Staff Requisition';
    $isRequisition = true;
@endphp

<div class="page-header">
    <div class="page-block">
        <div class="row align-items-center">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">New Requisition</h5>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item">Requests</li>
                            <li class="breadcrumb-item" aria-current="page">New Requisition</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<form method="POST" action="{{ route('requisition.store') }}" id="requisitionForm">
    @csrf
    <input type="hidden" name="user_id" value="{{ Auth::user()->id }}">
    <input type="hidden" name="department_id" id="department_id" value="{{ old('department_id', Auth::user()->department_id) }}">
    <input type="hidden" name="request_type" value="Staff Requisition">

    <div class="card shadow-none border">
        <div class="card-header"><div class="card-title">Fund Requisition Details</div></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mt-2">
                    <label class="form-label">Project / Program</label>
                    <input type="text" class="form-control" name="project_program" value="{{ old('project_program') }}" required>
                </div>
                <div class="col-md-3 mt-2">
                    <label class="form-label">Activity Code</label>
                    <select class="form-control" name="activity_code" id="activity_code" required>
                        <option value="" disabled {{ old('activity_code') ? '' : 'selected' }}>Select activity code</option>
                        @foreach (($planActivities ?? collect()) as $activity)
                            <option
                                value="{{ $activity['code'] }}"
                                data-description="{{ $activity['description'] }}"
                                data-year="{{ $activity['year'] }}"
                                data-department-id="{{ $activity['department_id'] }}"
                                {{ old('activity_code') === $activity['code'] ? 'selected' : '' }}>
                                {{ $activity['code'] }} - {{ \Illuminate\Support\Str::limit($activity['description'], 90) }} @if($activity['year']) ({{ $activity['year'] }}) @endif
                            </option>
                        @endforeach
                    </select>
                    @if (($planActivities ?? collect())->isEmpty())
                        <small class="text-danger">No year plan activities found for your assigned sub-department(s).</small>
                    @endif
                </div>
                <div class="col-md-3 mt-2">
                    <label class="form-label">Budget Code</label>
                    <input type="text" class="form-control" id="budget_code" name="budget_code" value="{{ old('budget_code') }}" required readonly>
                </div>
                <div class="col-md-6 mt-2">
                    <label class="form-label">Activity Title</label>
                    <input type="text" class="form-control" id="activity_title" name="activity_title" value="{{ old('activity_title') }}" required readonly>
                </div>
                <div class="col-md-3 mt-2">
                    <label class="form-label">Date of Request</label>
                    <input type="date" class="form-control" name="date_of_request" value="{{ old('date_of_request') }}">
                </div>
                <div class="col-md-3 mt-2">
                    <label class="form-label">Date of Requirement</label>
                    <input type="date" class="form-control" name="date_of_requirement" value="{{ old('date_of_requirement') }}">
                </div>
            </div>

            <div class="mt-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Line Items</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addItemRow">Add Row</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="itemsTable">
                        <thead>
                            <tr>
                                <th style="width: 8%;">S/N</th>
                                <th style="width: 50%;">Description</th>
                                <th style="width: 14%;">Unit</th>
                                <th style="width: 18%;">Cost</th>
                                <th style="width: 10%;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (old('items', [['description' => '', 'unit' => '', 'cost' => '']]) as $index => $item)
                                <tr>
                                    <td class="row-no">{{ $index + 1 }}</td>
                                    <td>
                                        <textarea class="form-control form-control-lg" rows="5" name="items[{{ $index }}][description]" required>{{ $item['description'] ?? '' }}</textarea>
                                    </td>
                                    <td><input type="text" class="form-control form-control-sm" name="items[{{ $index }}][unit]" value="{{ $item['unit'] ?? '' }}"></td>
                                    <td><input type="number" step="0.01" class="form-control item-cost" name="items[{{ $index }}][cost]" value="{{ $item['cost'] ?? '' }}" required></td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">Remove</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Total</th>
                                <th><input type="text" id="requisitionTotal" class="form-control" readonly></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <div class="card-action m-3">
            <button type="submit" class="btn btn-primary">Submit Requisition</button>
        </div>
    </div>
</form>

<script>
(() => {
    const tableBody = document.querySelector('#itemsTable tbody');
    const addRowBtn = document.getElementById('addItemRow');
    const totalField = document.getElementById('requisitionTotal');
    const activityCodeField = document.getElementById('activity_code');
    const budgetCodeField = document.getElementById('budget_code');
    const activityTitleField = document.getElementById('activity_title');
    const departmentField = document.getElementById('department_id');
    function recalc() {
        let total = 0;
        document.querySelectorAll('.item-cost').forEach((input) => total += parseFloat(input.value || 0));
        totalField.value = total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function renumberRows() {
        tableBody.querySelectorAll('tr').forEach((row, index) => {
            row.querySelector('.row-no').textContent = index + 1;
            row.querySelectorAll('input').forEach((input) => {
                input.name = input.name.replace(/items\[\d+\]/, `items[${index}]`);
            });
        });
    }
    addRowBtn?.addEventListener('click', () => {
        const index = tableBody.querySelectorAll('tr').length;
        const row = document.createElement('tr');
        row.innerHTML = `<td class="row-no">${index + 1}</td>
            <td><textarea class="form-control form-control-lg" rows="5" name="items[${index}][description]" required></textarea></td>
            <td><input type="text" class="form-control form-control-sm" name="items[${index}][unit]"></td>
            <td><input type="number" step="0.01" class="form-control item-cost" name="items[${index}][cost]" required></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">Remove</button></td>`;
        tableBody.appendChild(row);
        recalc();
    });
    tableBody?.addEventListener('click', (e) => {
        if (e.target.classList.contains('remove-row')) {
            e.target.closest('tr').remove();
            renumberRows();
            recalc();
        }
    });
    tableBody?.addEventListener('input', (e) => {
        if (e.target.classList.contains('item-cost')) recalc();
    });
    activityCodeField?.addEventListener('change', () => {
        const selected = activityCodeField.options[activityCodeField.selectedIndex];
        if (selected) {
            if (budgetCodeField) budgetCodeField.value = selected.dataset.year || '';
            if (activityTitleField) activityTitleField.value = selected.dataset.description || '';
            if (departmentField) departmentField.value = selected.dataset.departmentId || departmentField.value;
        }
    });
    if (activityCodeField && activityCodeField.selectedIndex > 0) {
        const selected = activityCodeField.options[activityCodeField.selectedIndex];
        if (budgetCodeField) budgetCodeField.value = selected?.dataset.year || budgetCodeField.value;
        if (activityTitleField) activityTitleField.value = selected?.dataset.description || activityTitleField.value;
        if (departmentField) departmentField.value = selected?.dataset.departmentId || departmentField.value;
    }
    recalc();
})();
</script>
</x-app-layout>
