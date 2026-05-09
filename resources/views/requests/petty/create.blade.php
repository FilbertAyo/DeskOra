<x-app-layout>
@php
    $selectedRequestType = old('request_type', $defaultRequestType ?? request('request_type', 'Petty Cash'));
    $isRequisition = $selectedRequestType === 'Staff Requisition';
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
                                <li class="breadcrumb-item"><a href="javascript: void(0)">Petty Cash</a></li>
                                <li class="breadcrumb-item" aria-current="page">New Requisition</li>
                            </ul>
                        </div>

                        <div class="col-auto d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-light" onclick="reloadPage()"
                                title="Refresh">
                                <i class="ti ti-refresh"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <form method="POST" action="{{ route('petty.store') }}" enctype="multipart/form-data" id="pettyForm">
        @csrf

        <div class="card shadow-none border">
            <div class="card-header">
                <div class="card-title">Request Info</div>
            </div>
            <div class="card-body">
             
                <div class="row">
                    <input type="hidden" name="user_id" value="{{ Auth::user()->id }}">
                    <input type="hidden" name="department_id" value="{{ Auth::user()->department_id }}">

                    <div class="col-md-6 col-lg-6 mt-3">
                        <div class="form-group">
                            <label for="request_type">Request Type: <span class="text-danger">*</span></label>
                            <select name="request_type" id="request_type" class="form-control" required>
                                <option value="Petty Cash" {{ $selectedRequestType == 'Petty Cash' ? 'selected' : '' }}>
                                    Petty Cash
                                </option>
                              
                                <option value="Staff Requisition" {{ $selectedRequestType == 'Staff Requisition' ? 'selected' : '' }}>
                                    Staff Requisition
                                </option>
                            </select>
                        </div>
                    </div>

                </div>

                <div id="requisitionFields" class="mt-3 {{ $isRequisition ? '' : 'd-none' }}">
                    <h5 class="mb-3">Fund Requisition Details</h5>
                    <div class="row">
                        <div class="col-md-6 mt-2">
                            <label class="form-label">Project / Program</label>
                            <input type="text" class="form-control" name="project_program" value="{{ old('project_program') }}">
                        </div>
                        <div class="col-md-3 mt-2">
                            <label class="form-label">Activity Code</label>
                            <input type="text" class="form-control" name="activity_code" value="{{ old('activity_code') }}">
                        </div>
                        <div class="col-md-3 mt-2">
                            <label class="form-label">Budget Code</label>
                            <input type="text" class="form-control" name="budget_code" value="{{ old('budget_code') }}">
                        </div>
                        <div class="col-md-6 mt-2">
                            <label class="form-label">Activity Title</label>
                            <input type="text" class="form-control" name="activity_title" value="{{ old('activity_title') }}">
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
                                        <th>S/N</th>
                                        <th>Description</th>
                                        <th>Unit</th>
                                        <th>Cost</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $oldItems = old('items', [['description' => '', 'unit' => '', 'cost' => '']]);
                                    @endphp
                                    @foreach ($oldItems as $index => $item)
                                        <tr>
                                            <td class="row-no">{{ $index + 1 }}</td>
                                            <td><input type="text" class="form-control" name="items[{{ $index }}][description]" value="{{ $item['description'] ?? '' }}"></td>
                                            <td><input type="text" class="form-control" name="items[{{ $index }}][unit]" value="{{ $item['unit'] ?? '' }}"></td>
                                            <td><input type="number" step="0.01" class="form-control item-cost" name="items[{{ $index }}][cost]" value="{{ $item['cost'] ?? '' }}"></td>
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
            </div>

            <div class="card-action m-3">
                <button type="submit" class="btn btn-primary">
                    Submit Request
                </button>
            </div>
        </div>
    </form>

    <script>
        (function() {
            const typeField = document.getElementById('request_type');
            const requisitionFields = document.getElementById('requisitionFields');
            const tableBody = document.querySelector('#itemsTable tbody');
            const addRowBtn = document.getElementById('addItemRow');
            const totalField = document.getElementById('requisitionTotal');

            function renderMode() {
                const requisition = typeField.value === 'Staff Requisition';
                requisitionFields.classList.toggle('d-none', !requisition);
            }

            function recalc() {
                const costs = document.querySelectorAll('.item-cost');
                let total = 0;
                costs.forEach((input) => {
                    total += parseFloat(input.value || 0);
                });
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

            addRowBtn?.addEventListener('click', function() {
                const index = tableBody.querySelectorAll('tr').length;
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td class="row-no">${index + 1}</td>
                    <td><input type="text" class="form-control" name="items[${index}][description]"></td>
                    <td><input type="text" class="form-control" name="items[${index}][unit]"></td>
                    <td><input type="number" step="0.01" class="form-control item-cost" name="items[${index}][cost]"></td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">Remove</button></td>
                `;
                tableBody.appendChild(row);
                recalc();
            });

            tableBody?.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-row')) {
                    e.target.closest('tr').remove();
                    renumberRows();
                    recalc();
                }
            });

            tableBody?.addEventListener('input', function(e) {
                if (e.target.classList.contains('item-cost')) {
                    recalc();
                }
            });

            typeField?.addEventListener('change', renderMode);
            renderMode();
            recalc();
        })();
    </script>

</x-app-layout>
