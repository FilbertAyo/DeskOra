{{-- <x-app-layout>

    <div class="page-header">
        <h3 class="fw-bold mb-3">Petty Cash Reports</h3>

    </div>

    <form action="{{ route('reports.petties') }}" method="GET" class="row g-3 mb-3">
        <div class="col-md-4">
            <label>From Date</label>
            <input type="date" name="from" value="{{ request('from') }}" class="form-control">
        </div>
        <div class="col-md-4">
            <label>To Date</label>
            <input type="date" name="to" value="{{ request('to') }}" class="form-control">
        </div>
        <div class="col-md-3">
            <label>Status</label>
            <select name="status" class="form-control">
                <option value="">-- All Petty Cash --</option>
                <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Paid</option>
                <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                <!-- Add more status options if needed -->
            </select>
        </div>
        <div class="col-md-1 align-self-end">
            <button class="btn btn-primary w-100">Filter</button>
        </div>
    </form>


    <div class="mb-3 row">
        <div class="col-8">
            <a href="{{ route('reports.petties.download', ['type' => 'pdf'] + request()->all()) }}"
                class="btn btn-danger">
                <i class="bi bi-file-earmark-pdf-fill me-2"></i> Download PDF
            </a>
            <a href="{{ route('reports.petties.download', ['type' => 'excel'] + request()->all()) }}"
                class="btn btn-success">
                <i class="bi bi-file-earmark-excel-fill"></i> Download Excel
            </a>
        </div>
    </div>

    <table class="table table-bordered">
        <thead class="table-light">
            <tr>
                <th>Date Issued</th>
                <th>Date Paid</th>
                <th>Particulars</th>
                <th>Amount (TZS)</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($petties as $petty)
                <tr>
                    <td>{{ $petty->created_at->format('d/m/Y') }}</td>
                    <td>{{ $petty->paid_date ? \Carbon\Carbon::parse($petty->paid_date)->format('d/m/Y') : '-' }}</td>
                    <td><strong>{{ $petty->request_for }}</strong></td>
                    <td><strong>{{ number_format($petty->amount, 2) }}</strong></td>
                    <td>{{ ucfirst($petty->status) }}</td>
                </tr>

                <tr>

                    <td></td>
                    <td></td>
                    <td>
                        <div class="mb-1">
                            <strong>Name:</strong> {{ $petty->user->name }}
                        </div>
                        <div class="mb-1">
                            {{ $petty->reason }}
                        </div>

                        @if ($petty->request_for == 'Sales Delivery')
                            <div class="mb-1">
                                <ul class="mb-1">
                                    @foreach ($petty->attachments as $attachment)
                                        <li>{{ $attachment->name }}: {{ $attachment->product_name }}</li>
                                    @endforeach
                                </ul>
                            </div>
                            <div>
                                <strong><em>Routes:</em></strong>
                                <ul>
                                    @foreach ($petty->trips as $trip)
                                        <li>
                                            {{ $trip->startPoint->name }}
                                            @foreach ($trip->stops as $stop)
                                                → {{ $stop->destination }}
                                            @endforeach
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @elseif ($petty->request_for == 'Transport')
                            <div>
                                <strong><em>Routes:</em></strong>
                                <ul>
                                    @foreach ($petty->trips as $trip)
                                        <li>
                                            {{ $trip->startPoint->name }}
                                            @foreach ($trip->stops as $stop)
                                                → {{ $stop->destination }}
                                            @endforeach
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @elseif ($petty->request_for == 'Office Supplies')
                            <div>
                                <strong><em>Items:</em></strong>
                                <ul>
                                    @foreach ($petty->lists as $item)
                                        <li>
                                            {{ $item->item_name }} ({{ $item->quantity }}) –
                                            TZS {{ number_format($item->price) }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </td>
                    <td></td>
                    <td></td>

                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center">No petties found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>



</x-app-layout> --}}


<x-app-layout>

    <!-- Header with Tabs + Refresh Button -->
    <div class="row align-items-center mb-3 border-bottom no-gutters">
        <div class="col">
            <ul class="nav nav-tabs border-0" id="myTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="petty-tab" data-toggle="tab" href="#petty" role="tab"
                        aria-controls="petty" aria-selected="true">Petty Cash Reports</a>
                </li>
            </ul>
        </div>
        <div class="col-auto">
            <button type="button" class="btn btn-sm" onclick="reloadPage()">
                <i class="ti ti-16 fe-refresh-ccw text-muted"></i>
            </button>
        </div>
    </div>

    <div class="row my-2">
        <div class="col-md-12">
            <div class="card shadow-none border">
                <div class="card-body">

                    <!-- Filter Form -->
                    <form action="{{ route('reports.petties') }}" method="GET" class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label>From Date</label>
                            <input type="date" name="from" value="{{ request('from') }}" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label>To Date</label>
                            <input type="date" name="to" value="{{ request('to') }}" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="">-- All Petty Cash --</option>
                                <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Paid</option>
                                <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                                <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                            </select>
                        </div>
                        <div class="col-md-1 align-self-end">
                            <button class="btn btn-primary w-100">Filter</button>
                        </div>
                    </form>

                    <!-- Download Buttons -->
                    <div class="mb-3 row">
                        <div class="col-8">
                            <a href="{{ route('reports.petties.download', ['type' => 'pdf'] + request()->all()) }}"
                                class="btn btn-danger me-2">
                                <i class="bi bi-file-earmark-pdf-fill me-2"></i> Download PDF
                            </a>
                            <a href="{{ route('reports.petties.download', ['type' => 'excel'] + request()->all()) }}"
                                class="btn btn-success">
                                <i class="bi bi-file-earmark-excel-fill"></i> Download Excel
                            </a>
                        </div>
                    </div>

                    <!-- Report Table -->
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="thead-light">
                                <tr>
                                    <th>Date Issued</th>
                                    <th>Date Paid</th>
                                    <th>Particulars</th>
                                    <th>Amount (TZS)</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($petties as $petty)
                <tr>
                    <td>{{ $petty->created_at->format('d/m/Y') }}</td>
                    <td>{{ $petty->paid_date ? \Carbon\Carbon::parse($petty->paid_date)->format('d/m/Y') : '-' }}</td>
                    <td><strong>{{ $petty->request_for }}</strong></td>
                    <td><strong>{{ number_format($petty->amount, 2) }}</strong></td>
                    <td>{{ ucfirst($petty->status) }}</td>
                </tr>

                <tr>

                    <td></td>
                    <td></td>
                    <td>
                        <div class="mb-1">
                            <strong>Name:</strong> {{ $petty->user->name }}
                        </div>
                        <div class="mb-1">
                            {{ $petty->reason }}
                        </div>

                        @if ($petty->request_for == 'Sales Delivery')
                            <div class="mb-1">
                                <ul class="mb-1">
                                    @foreach ($petty->attachments as $attachment)
                                        <li>{{ $attachment->name }}: {{ $attachment->product_name }}</li>
                                    @endforeach
                                </ul>
                            </div>
                            <div>
                                <strong><em>Routes:</em></strong>
                                <ul>
                                    @foreach ($petty->trips as $trip)
                                        <li>
                                            {{ $trip->startPoint->name }}
                                            @foreach ($trip->stops as $stop)
                                                → {{ $stop->destination }}
                                            @endforeach
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @elseif ($petty->request_for == 'Transport')
                            <div>
                                <strong><em>Routes:</em></strong>
                                <ul>
                                    @foreach ($petty->trips as $trip)
                                        <li>
                                            {{ $trip->startPoint->name }}
                                            @foreach ($trip->stops as $stop)
                                                → {{ $stop->destination }}
                                            @endforeach
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @elseif ($petty->request_for == 'Office Supplies')
                            <div>
                                <strong><em>Items:</em></strong>
                                <ul>
                                    @foreach ($petty->lists as $item)
                                        <li>
                                            {{ $item->item_name }} ({{ $item->quantity }}) –
                                            TZS {{ number_format($item->price) }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </td>
                    <td></td>
                    <td></td>

                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center">No petties found.</td>
                </tr>
            @endforelse
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>

</x-app-layout>
