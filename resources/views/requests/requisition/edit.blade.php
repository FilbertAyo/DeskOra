<x-app-layout>

<div class="page-header">
    <div class="page-block">
        <div class="row align-items-center">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Edit Requisition</h5>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item">Requests</li>
                            <li class="breadcrumb-item" aria-current="page">Edit Requisition</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    
    @php
        $requisitionDetails = $request->requisitionDetails;
        $selectedActivityCode = old('activity_code', $requisitionDetails?->activity_code);
        $activityCodes = collect($planActivities ?? [])->pluck('code')->all();
        $savedIsOffPlan = filter_var(
            old('is_off_plan', $requisitionDetails?->is_off_plan ? '1' : '0'),
            FILTER_VALIDATE_BOOLEAN
        );
        $oldSelection = old('activity_selection');
        if ($oldSelection === null) {
            $oldSelection = $savedIsOffPlan ? '__off_plan__' : $selectedActivityCode;
        }
        $isOffPlan = $oldSelection === '__off_plan__';
    @endphp

    <form method="POST" action="{{ route('requisition.update', $request->id) }}" novalidate>
        @csrf
        @method('PUT')
        <input type="hidden" name="department_id" id="department_id" value="{{ old('department_id', $request->department_id) }}">
        <div class="card shadow-none border">
            <div class="card-header"><div class="card-title">Requisition Details</div></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mt-2">
                        <label class="form-label">Project / Program</label>
                        <input type="text" class="form-control" name="project_program" value="{{ old('project_program', $requisitionDetails?->project_program) }}" required>
                    </div>
                    <div class="col-md-3 mt-2">
                        <label class="form-label">Activity Code</label>
                        <select class="form-control" name="activity_selection" id="activity_selection" required>
                            <option value="" disabled {{ $oldSelection ? '' : 'selected' }}>Select activity code</option>
                            @if (! $isOffPlan && $selectedActivityCode && ! in_array($selectedActivityCode, $activityCodes, true))
                                <option
                                    value="{{ $selectedActivityCode }}"
                                    data-description="{{ old('activity_title', $requisitionDetails?->activity_title) }}"
                                    data-year="{{ old('budget_code', $requisitionDetails?->budget_code) }}"
                                    data-department-id="{{ old('department_id', $request->department_id) }}"
                                    {{ $oldSelection === $selectedActivityCode ? 'selected' : '' }}>
                                    {{ $selectedActivityCode }} (saved)
                                </option>
                            @endif
                            @foreach (($planActivities ?? collect()) as $activity)
                                <option
                                    value="{{ $activity['code'] }}"
                                    data-description="{{ $activity['description'] }}"
                                    data-year="{{ $activity['year'] }}"
                                    data-department-id="{{ $activity['department_id'] }}"
                                    {{ $oldSelection === $activity['code'] ? 'selected' : '' }}>
                                    {{ $activity['code'] }} - {{ \Illuminate\Support\Str::limit($activity['description'], 90) }} @if($activity['year']) ({{ $activity['year'] }}) @endif
                                </option>
                            @endforeach
                            <option value="__off_plan__" {{ $isOffPlan ? 'selected' : '' }}>Not on the year plan</option>
                        </select>
                        <input type="hidden" name="is_off_plan" id="is_off_plan" value="{{ $isOffPlan ? '1' : '0' }}">
                        <div class="mt-2" id="manual_activity_wrap" @if(! $isOffPlan) style="display:none;" @endif>
                            <input
                                type="text"
                                class="form-control"
                                name="activity_code"
                                id="activity_code"
                                value="{{ $isOffPlan ? old('activity_code', $requisitionDetails?->activity_code) : ($oldSelection && $oldSelection !== '__off_plan__' ? $oldSelection : '') }}"
                                placeholder="Enter activity code"
                                @if($isOffPlan) required @endif>
                        </div>
                        @if (($planActivities ?? collect())->isEmpty())
                            <small class="text-muted">No year plan activities found for your department. Select “Not on the year plan” to enter codes manually.</small>
                        @endif
                    </div>
                    <div class="col-md-3 mt-2">
                        <label class="form-label">Budget Code</label>
                        <input type="text" class="form-control" id="budget_code" name="budget_code" value="{{ old('budget_code', $requisitionDetails?->budget_code) }}" required @if(! $isOffPlan) readonly @endif>
                    </div>
                    <div class="col-md-6 mt-2">
                        <label class="form-label">Activity Title</label>
                        <input type="text" class="form-control" id="activity_title" name="activity_title" value="{{ old('activity_title', $requisitionDetails?->activity_title) }}" required @if(! $isOffPlan) readonly @endif>
                    </div>
                    <div class="col-md-3 mt-2">
                        <label class="form-label">Date of Request</label>
                        <input type="date" class="form-control" name="date_of_request" value="{{ old('date_of_request', optional($request->date_of_request)->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-3 mt-2">
                        <label class="form-label">Date of Requirement</label>
                        <input type="date" class="form-control" name="date_of_requirement" value="{{ old('date_of_requirement', optional($request->date_of_requirement)->format('Y-m-d')) }}">
                    </div>
                </div>

                <div class="mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Line Items</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="addItemRow">Add Row</button>
                    </div>
                    <div class="table-responsive">
                        @php
                            $defaultItems = $request->requisitionItems->map(fn($i) => ['description' => $i->description, 'unit' => $i->unit, 'cost' => $i->cost])->toArray();
                            if ($defaultItems === []) {
                                $defaultItems = [['description' => '', 'unit' => '', 'cost' => '']];
                            }
                            $oldItems = old('items', $defaultItems);
                        @endphp
                        <table class="table table-bordered table-striped" id="itemsTable">
                            <thead><tr><th>S/N</th><th>Description</th><th>Unit</th><th>Cost</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($oldItems as $index => $item)
                                    <tr>
                                        <td class="row-no">{{ $index + 1 }}</td>
                                        <td><input type="text" class="form-control" name="items[{{ $index }}][description]" value="{{ $item['description'] ?? '' }}" required></td>
                                        <td><input type="text" class="form-control" name="items[{{ $index }}][unit]" value="{{ $item['unit'] ?? '' }}"></td>
                                        <td><input type="number" step="0.01" class="form-control item-cost" name="items[{{ $index }}][cost]" value="{{ $item['cost'] ?? '' }}" required></td>
                                        <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">Remove</button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr><th colspan="3" class="text-end">Total</th><th><input type="text" id="requisitionTotal" class="form-control" readonly></th><th></th></tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <div class="card-action m-3 d-flex flex-wrap gap-2">
                @if ($request->status === 'draft')
                    <button type="submit" name="action" value="draft" class="btn btn-outline-secondary lh-1">
                        <span class="spinner-border spinner-border-sm me-2 d-none" role="status"></span>
                        <span class="btn-label">Save as Draft</span>
                    </button>
                    <x-primary-button label="Submit Requisition" name="action" value="submit" />
                @else
                    <x-primary-button label="Update Requisition" />
                @endif
            </div>
        </div>
    </form>

    <script>
    (() => {
        const tableBody = document.querySelector('#itemsTable tbody');
        const addRowBtn = document.getElementById('addItemRow');
        const totalField = document.getElementById('requisitionTotal');
        const activitySelectionField = document.getElementById('activity_selection');
        const activityCodeField = document.getElementById('activity_code');
        const manualActivityWrap = document.getElementById('manual_activity_wrap');
        const isOffPlanField = document.getElementById('is_off_plan');
        const budgetCodeField = document.getElementById('budget_code');
        const activityTitleField = document.getElementById('activity_title');
        const departmentField = document.getElementById('department_id');
        let previousWasOffPlan = activitySelectionField?.value === '__off_plan__';

        function setReadonly(field, readonly) {
            if (!field) return;
            if (readonly) {
                field.setAttribute('readonly', 'readonly');
            } else {
                field.removeAttribute('readonly');
            }
        }

        function syncActivityMode({ clearManual = false } = {}) {
            const selected = activitySelectionField?.options[activitySelectionField.selectedIndex];
            const isOffPlan = selected?.value === '__off_plan__';

            if (isOffPlanField) isOffPlanField.value = isOffPlan ? '1' : '0';
            if (manualActivityWrap) manualActivityWrap.style.display = isOffPlan ? '' : 'none';

            if (isOffPlan) {
                if (activityCodeField) {
                    activityCodeField.required = true;
                    if (clearManual || !previousWasOffPlan) {
                        activityCodeField.value = '';
                    }
                }
                setReadonly(budgetCodeField, false);
                setReadonly(activityTitleField, false);
                if (clearManual || !previousWasOffPlan) {
                    if (budgetCodeField) budgetCodeField.value = '';
                    if (activityTitleField) activityTitleField.value = '';
                }
            } else {
                if (activityCodeField) {
                    activityCodeField.required = false;
                    activityCodeField.value = selected?.value || '';
                }
                setReadonly(budgetCodeField, true);
                setReadonly(activityTitleField, true);
                if (selected && selected.value) {
                    if (budgetCodeField) budgetCodeField.value = selected.dataset.year || '';
                    if (activityTitleField) activityTitleField.value = selected.dataset.description || '';
                    if (departmentField) departmentField.value = selected.dataset.departmentId || departmentField.value;
                }
            }

            previousWasOffPlan = isOffPlan;
        }

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
                <td><input type="text" class="form-control" name="items[${index}][description]" required></td>
                <td><input type="text" class="form-control" name="items[${index}][unit]"></td>
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
        activitySelectionField?.addEventListener('change', () => syncActivityMode({ clearManual: true }));
        syncActivityMode({ clearManual: false });
        recalc();
    })();
    </script>
</x-app-layout>
