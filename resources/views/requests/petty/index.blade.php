<x-app-layout>


<div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Petty Cash Requests</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="javascript: void(0)">Petty Cash</a></li>
                                <li class="breadcrumb-item" aria-current="page">Petty Cash Requests</li>
                            </ul>
                        </div>

                        <div class="col-auto d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-light" onclick="reloadPage()"
                                title="Refresh">
                                <i class="ti ti-refresh"></i>
                            </button>
                            <a href="{{ route('petty.create') }}" class="btn btn-primary btn-sm">New Request</a>
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
                                <th>Request for</th>
                                <th>Request Type</th>
                                <th>Amount</th>
                                <th>Date Requested</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if ($requests->count() > 0)
                           @foreach ($requests as $index => $item)

                             @if (
                                                    ($item->is_transporter == true && $item->attachment == null) ||
                                                        ($item->request_for == 'Office Supplies' && $item->attachment == null))
                                        <tr class="alert alert-danger">
                                            <td>{{ $loop->iteration }}
                                            </td>
                                            <td> {{ $item->request_for }}</td>
                                            <td>{{ $item->request_type }}</td>
                                            <td>{{ number_format($item->amount) }}</td>
                                            <td>{{ $item->created_at }}</td>

                                            <td>
                                                @if ($item->status == 'pending')
                                                    <span class="badge bg-light-danger">{{ $item->status }}</span>
                                                @elseif($item->status == 'processing')
                                                    <span class="badge bg-light-warning">{{ $item->status }}</span>
                                                @elseif($item->status == 'rejected')
                                                    <span class="badge bg-light-secondary">{{ $item->status }}</span>
                                                @elseif($item->status == 'resubmission')
                                                    <span class="badge bg-light-danger">{{ $item->status }}</span>
                                                @else
                                                    <span class="badge bg-light-success">{{ $item->status }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <x-table-action-dropdown>
    <a class="dropdown-item" href="{{ route('petty.show', $item->id) }}">
    <i class="ti ti-eye me-2"></i> View
    </a>
</x-table-action-dropdown>
                                            </td>
                                        </tr>
                                        @else

                                            <tr>
                                            <td>{{ $loop->iteration }}

                                            </td>
                                            <td> {{ $item->request_for }}</td>
                                            <td>{{ $item->request_type }}</td>
                                            <td>{{ number_format($item->amount) }}</td>
                                            <td>{{ $item->created_at }}</td>

                                            <td>
                                                @if ($item->status == 'pending')
                                                    <span class="badge bg-light-danger">{{ $item->status }}</span>
                                                @elseif($item->status == 'processing')
                                                    <span class="badge bg-light-warning ">{{ $item->status }}</span>
                                                @elseif($item->status == 'rejected')
                                                    <span class="badge bg-light-secondary">{{ $item->status }}</span>
                                                @elseif($item->status == 'resubmission')
                                                    <span class="badge bg-light-danger">{{ $item->status }}</span>
                                                @else
                                                    <span class="badge bg-light-success">{{ $item->status }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <x-table-action-dropdown>
    <a class="dropdown-item" href="{{ route('petty.show', $item->id) }}">
    <i class="ti ti-eye me-2"></i> View
    </a>
</x-table-action-dropdown>
                                            </td>
                                        </tr>

                                        @endif
                                    @endforeach
                                    @else
                                    <tr>
                                        <td colspan="6" class="text-center">No requests found</td>
                                    </tr>
                                    @endif
                        </tbody>
                    </table>

                </div>
            </div>
        </div>


    </div>



</x-app-layout>
