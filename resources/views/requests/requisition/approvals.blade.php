<x-app-layout>
    <div class="row align-items-center mb-3 border-bottom no-gutters">
        <div class="col">
            <h5 class="mb-0">Staff Requisition Approvals</h5>
        </div>
    </div>

    <div class="card shadow-none border">
        <div class="card-body">
            <table class="table table-bordered table-striped datatables">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Requested By</th>
                        <th>Activity</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requests as $item)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $item->user->name ?? 'N/A' }}</td>
                            <td>{{ $item->activity_title ?: $item->request_for }}</td>
                            <td>{{ number_format($item->amount, 2) }}</td>
                            <td><span class="badge bg-light-info">{{ $item->status }}</span></td>
                            <td>
                                <x-table-action-dropdown>
    <a class="dropdown-item" href="{{ route('petty.details', $item->id) }}">
    <i class="ti ti-eye me-2"></i> View
    </a>
</x-table-action-dropdown>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
