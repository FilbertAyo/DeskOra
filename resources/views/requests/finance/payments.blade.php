<x-app-layout>

<div class="page-header">
    <div class="page-block">
        <div class="row align-items-center">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Petty Cash Payments</h5>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                            <li class="breadcrumb-item">Finance & Transactions</li>
                            <li class="breadcrumb-item" aria-current="page">Petty Cash Payments</li>
                        </ul>
                    </div>

                    <div class="col-auto d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-light" onclick="reloadPage()" title="Refresh">
                            <i class="ti ti-refresh"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row my-2">
    <div class="col-md-12">
        <div class="card shadow-none border">
            <div class="card-body">
                <table class="table table-bordered table-striped datatables" id="dataTable-1">
                    <thead class="thead-light">
                        <tr>
                            <th>No.</th>
                            <th>Request By</th>
                            <th>Request For</th>
                            <th>Request Type</th>
                            <th>Amount</th>
                            <th>Date Requested</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($requests as $item)
                            @php
                                $isPaid = $item->status === 'paid';
                                $canPay = ! $isPaid
                                    && $item->authorized_at !== null
                                    && auth()->user()->can('approve pettycash payments');
                            @endphp
                            <tr @class(['table-warning' => $canPay])>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $item->user->name ?? 'N/A' }}</td>
                                <td>{{ $item->request_for }}</td>
                                <td>
                                    <span class="badge bg-light-primary border border-primary">{{ $item->request_type }}</span>
                                </td>
                                <td>{{ number_format($item->amount, 2) }}</td>
                                <td>{{ $item->created_at->format('d M, Y') }}</td>
                                <td>
                                    @if ($isPaid)
                                        <span class="badge bg-light-success border border-success text-success">Paid</span>
                                    @else
                                        <span class="badge bg-light-warning border border-warning text-warning">Awaiting Payment</span>
                                    @endif
                                </td>
                                <td>
                                    <x-table-action-dropdown>
                                        <a class="dropdown-item" href="{{ route('petty.details', $item->id) }}">
                                            <i class="ti ti-eye me-2"></i> View
                                        </a>
                                        @if ($canPay)
                                            <form action="{{ route('c_approve.approve', $item->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="dropdown-item text-success">
                                                    <i class="ti ti-cash me-2"></i> Pay Now
                                                </button>
                                            </form>
                                        @endif
                                    </x-table-action-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    No petty cash requests awaiting payment.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</x-app-layout>
