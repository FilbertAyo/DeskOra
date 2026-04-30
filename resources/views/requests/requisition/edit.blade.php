<x-app-layout>
    <div class="row align-items-center mb-3 border-bottom no-gutters">
        <div class="col">
            <h5 class="mb-0">Edit Requisition</h5>
        </div>
        <div class="col-auto">
            <a href="{{ route('requisition.show', $request->id) }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-arrow-left me-1"></i>Back
            </a>
        </div>
    </div>

    <form method="POST" action="{{ route('requisition.update', $request->id) }}">
        @csrf
        @method('PUT')
        <div class="card shadow-none border">
            <div class="card-header"><div class="card-title">Requisition Details</div></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mt-2">
                        <label class="form-label">Project / Program</label>
                        <input type="text" class="form-control" name="project_program" value="{{ old('project_program', $request->project_program) }}" required>
                    </div>
                    <div class="col-md-3 mt-2">
                        <label class="form-label">Activity Code</label>
                        <input type="text" class="form-control" name="activity_code" value="{{ old('activity_code', $request->activity_code) }}" required>
                    </div>
                    <div class="col-md-3 mt-2">
                        <label class="form-label">Budget Code</label>
                        <input type="text" class="form-control" name="budget_code" value="{{ old('budget_code', $request->budget_code) }}" required>
                    </div>
                    <div class="col-md-6 mt-2">
                        <label class="form-label">Activity Title</label>
                        <input type="text" class="form-control" name="activity_title" value="{{ old('activity_title', $request->activity_title) }}" required>
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
                            $oldItems = old('items', $defaultItems);
                        @endphp
                        <table class="table table-bordered" id="itemsTable">
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
            <div class="card-action m-3">
                <button class="btn btn-primary" type="submit">Update Requisition</button>
            </div>
        </div>
    </form>

    <script>
    (() => {
        const tableBody = document.querySelector('#itemsTable tbody');
        const addRowBtn = document.getElementById('addItemRow');
        const totalField = document.getElementById('requisitionTotal');
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
        recalc();
    })();
    </script>
</x-app-layout>
