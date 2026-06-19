<x-app-layout>


<div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Staff Requisitions</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="javascript: void(0)">Petty Cash</a></li>
                                <li class="breadcrumb-item" aria-current="page">Staff Requisitions</li>
                            </ul>
                        </div>

                        <div class="col-auto d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-light" onclick="reloadPage()"
                                title="Refresh">
                                <i class="ti ti-refresh"></i>
                            </button>
                            <a href="{{ route('requisition.create') }}" class="btn btn-primary btn-sm">New Requisition</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

 

    <div class="card shadow-none border">
        <div class="card-body">
            <table class="table table-bordered table-striped datatables">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Activity</th>
                        <th>Amount</th>
                        <th>Date Requested</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @if ($requests->count() > 0)
                    @foreach ($requests as $item)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $item->activity_title ?: $item->request_for }}</td>
                            <td>{{ number_format($item->amount, 2) }}</td>
                            <td>{{ $item->created_at->format('d M, Y') }}</td>
                            <td>
                                @if ($item->status == 'draft')
                                    <span class="badge bg-light-warning text-warning">Draft</span>
                                @elseif ($item->status == 'pending')
                                    <span class="badge bg-light-danger text-danger">{{ $item->status }}</span>
                                @elseif ($item->status == 'processing')
                                    <span class="badge bg-light-primary text-primary">{{ $item->status }}</span>
                                @elseif ($item->status == 'rejected')
                                    <span class="badge bg-light-secondary text-secondary">{{ $item->status }}</span>
                                @elseif ($item->status == 'resubmission')
                                    <span class="badge bg-light-danger text-danger">{{ $item->status }}</span>
                                @else
                                    <span class="badge bg-light-success text-success">{{ $item->status }}</span>
                                @endif
                            </td>
                            <td>
                                <x-table-action-dropdown>
    <a class="dropdown-item" href="{{ route('requisition.show', $item->id) }}">
    <i class="ti ti-eye me-2"></i> View
    </a>
    @if ($item->status === 'draft')
    <a class="dropdown-item" href="{{ route('requisition.edit', $item->id) }}">
    <i class="ti ti-edit me-2"></i> Continue Draft
    </a>
    @endif
</x-table-action-dropdown>
                            </td>
                        </tr>
                    @endforeach
                    @else
                    <tr>
                        <td colspan="6" class="text-center">No requisitions found</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
