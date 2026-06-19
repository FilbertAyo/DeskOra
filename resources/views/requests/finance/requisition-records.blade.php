<x-app-layout>

<div class="page-header">
    <div class="page-block">
        <div class="row align-items-center">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Requisition Records</h5>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item">Finance & Transactions</li>
                            <li class="breadcrumb-item" aria-current="page">Requisition Records</li>
                        </ul>
                    </div>
                    <div class="col-auto d-flex align-items-center gap-2">
                        <a href="{{ route('requisition.records.download', ['format' => 'pdf', 'filter_type' => request('filter_type')]) }}"
                            class="btn btn-danger btn-sm">
                            <i class="ti ti-download me-1"></i> PDF
                        </a>
                        <a href="{{ route('requisition.records.download', ['format' => 'excel', 'filter_type' => request('filter_type')]) }}"
                            class="btn btn-primary btn-sm">
                            <i class="ti ti-download me-1"></i> Excel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mb-3 d-flex justify-content-between gap-2">
    <form method="GET" action="{{ route('requisition.records') }}">
        <div class="col-md-12">
            <select name="filter_type" class="form-control" onchange="this.form.submit()">
                <option value="">Show All</option>
                <option value="daily" {{ request('filter_type') == 'daily' ? 'selected' : '' }}>Daily</option>
                <option value="monthly" {{ request('filter_type') == 'monthly' ? 'selected' : '' }}>Monthly</option>
            </select>
        </div>
    </form>
</div>

<div class="row my-2">
    <div class="col-md-12">
        <div class="card shadow-none border">
            <div class="card-body">
                @if ($isFiltered)
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th>Requisitions</th>
                                <th>Amount</th>
                                <th>Cumulative Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($transactions as $tx)
                                <tr>
                                    <td>{{ $tx['label'] }}</td>
                                    <td>{{ $tx['count'] }}</td>
                                    <td>{{ number_format($tx['amount'], 2) }}</td>
                                    <td>{{ number_format($tx['cumulative'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No authorized requisitions found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                @else
                    <table class="table table-bordered table-striped datatables">
                        <thead>
                            <tr>
                                <th>Date Authorized</th>
                                <th>Reference</th>
                                <th>Requester</th>
                                <th>Activity</th>
                                <th>Project</th>
                                <th>Budget Code</th>
                                <th>Amount</th>
                                <th>Cumulative Total</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($transactions as $tx)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($tx['date'])->format('d M, Y') }}</td>
                                    <td><span class="font-monospace">{{ $tx['reference'] }}</span></td>
                                    <td>{{ $tx['requester'] }}</td>
                                    <td>{{ $tx['activity'] }}</td>
                                    <td>{{ $tx['project'] }}</td>
                                    <td>{{ $tx['budget_code'] }}</td>
                                    <td>{{ number_format($tx['amount'], 2) }}</td>
                                    <td>{{ number_format($tx['cumulative'], 2) }}</td>
                                    <td>
                                        <x-table-action-dropdown>
                                            <a class="dropdown-item" href="{{ route('petty.details', $tx['request_id']) }}">
                                                <i class="ti ti-eye me-2"></i> View
                                            </a>
                                            @canany(['download staff requisition', 'authorize staff requisition', 'approve pettycash payments'])
                                                <a class="dropdown-item" href="{{ route('petty.requisition.download', $tx['request_id']) }}">
                                                    <i class="ti ti-download me-2"></i> Download PDF
                                                </a>
                                            @endcanany
                                        </x-table-action-dropdown>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">No authorized requisitions found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>

</x-app-layout>
