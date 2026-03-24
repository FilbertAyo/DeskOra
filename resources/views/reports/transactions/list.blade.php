<x-app-layout>

    <!-- Header with Tabs + Buttons -->
    <div class="row align-items-center mb-3 border-bottom no-gutters">
        <div class="col">
            <ul class="nav nav-tabs border-0" id="myTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="transactions-tab" data-toggle="tab" href="#transactions" role="tab"
                        aria-controls="transactions" aria-selected="true">Pettycash Transactions</a>
                </li>
            </ul>
        </div>
        <div class="col-auto">
            <button type="button" class="btn btn-sm" onclick="reloadPage()">
                <i class="ti ti-16 fe-refresh-ccw text-muted"></i>
            </button>

            <a href="{{ route('reports.transaction.download', ['type' => 'pdf'] + request()->all()) }}"
                class="btn mb-2 btn-danger btn-sm">
                <i class="bi bi-file-earmark-pdf-fill me-2"></i> Download PDF
            </a>
        </div>
    </div>

    <!-- Filter Form -->
    <form action="{{ route('reports.transaction') }}" method="GET" class="row g-3 mb-3">
        <div class="col-md-5">
            <label>From Date</label>
            <input type="date" name="from" value="{{ request('from') }}" class="form-control">
        </div>
        <div class="col-md-5">
            <label>To Date</label>
            <input type="date" name="to" value="{{ request('to') }}" class="form-control">
        </div>
        <div class="col-md-2 align-self-end">
            <button class="btn btn-primary btn-sm w-100">Filter</button>
        </div>
    </form>

    <!-- Transactions Table -->
    <div class="row my-2">
        <div class="col-md-12">
            <div class="card shadow-none border">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="thead-light">
                                <tr>
                                    <th>No.</th>
                                    <th>PVC Number</th>
                                    <th>Name</th>
                                    <th>Date & Time</th>
                                    <th>Total Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $totalAmount = 0;
                                @endphp

                                @foreach ($transactions as $transaction)
                                    @php
                                        $totalAmount += $transaction->amount;
                                    @endphp
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>Payment from #{{ str_pad($transaction->id, 3, '0', STR_PAD_LEFT) }}</td>
                                        <td>{{ $transaction->user->name }}</td>
                                        <td>{{ \Carbon\Carbon::parse($transaction->paid_date)->format('d/m/Y') }}</td>
                                        <td>{{ number_format($transaction->amount, 2) }}</td>
                                        <td>
                                            <span class="badge bg-success text-white">Completed</span>
                                        </td>
                                    </tr>
                                @endforeach

                                @if (count($transactions) > 0)
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Total</strong></td>
                                        <td class="text-end"><strong>{{ number_format($totalAmount, 2) }}</strong></td>
                                        <td></td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
