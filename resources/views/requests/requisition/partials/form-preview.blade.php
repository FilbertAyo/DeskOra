<div class="card border shadow mt-3">
    <div class="card-body p-4">
        <div class="text-center mb-4">
            <img src="{{ asset('assets/images/logo.png') }}" alt="Logo" style="height: 56px;">
            <h4 class="mt-2 mb-0">FUND REQUISITION FORM</h4>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-12">
                <label class="form-label">Project/program</label>
                <input class="form-control bg-light border-b" value="{{ $request->project_program }}" disabled>
            </div>
            <div class="col-md-3">
                <label class="form-label">Activity code</label>
                <input class="form-control bg-light" value="{{ $request->activity_code }}" disabled>
            </div>
            <div class="col-md-9">
                <label class="form-label">Activity title</label>
                <input class="form-control bg-light" value="{{ $request->activity_title }}" disabled>
            </div>
            <div class="col-md-4">
                <label class="form-label">Budget code</label>
                <input class="form-control bg-light" value="{{ $request->budget_code }}" disabled>
            </div>
           
            <div class="col-md-4">
                <label class="form-label">Date of request</label>
                <input class="form-control bg-light" value="{{ optional($request->date_of_request)->format('d M, Y') }}" disabled>
            </div>
            <div class="col-md-4">
                <label class="form-label">Date of requirement</label>
                <input class="form-control bg-light" value="{{ optional($request->date_of_requirement)->format('d M, Y') }}" disabled>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-striped mb-3">
                <thead class="thead-light">
                    <tr>
                        <th style="width: 8%;">S/NO</th>
                        <th style="width: 52%;">DESCRIPTION</th>
                        <th style="width: 15%;">UNIT</th>
                        <th style="width: 25%;">COST</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($request->requisitionItems as $item)
                        <tr>
                            <td>{{ $item->serial_no }}</td>
                            <td>{!! nl2br(e($item->description)) !!}</td>
                            <td>{{ $item->unit }}</td>
                            <td>{{ number_format($item->cost, 2) }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        
                        <td colspan="3" class="text-end">Total amount:</td>
                        <td>
                                <strong>TZS {{ number_format($request->amount, 2) }}</strong>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        @include('requests.partials.approval-signatories')
    </div>
</div>
