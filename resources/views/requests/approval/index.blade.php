<x-app-layout>


<div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Requests Approval</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                              <li class="breadcrumb-item" aria-current="page">Requests Approval</li>
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


 

    <div class="row my-2">
        <div class="col-md-12">
            <div class="card shadow-none border">
                <div class="card-body">
                    <table class="table table-bordered datatables" id="dataTable-1">
                        <thead class="thead-light">
                            <tr>
                                <th>No.</th>
                                <th>Request By</th>
                                <th>Request For / Activity</th>
                                <th>Request Type</th>
                                <th>Approval Stage</th>
                                <th>Amount</th>
                                <th>Date Requested</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                           @forelse ($requests as $item)
                                @php
                                    $isStaffRequisition = $item->request_type === 'Staff Requisition';
                                    $isPettyMissingAttachment =
                                        !$isStaffRequisition &&
                                        (($item->is_transporter == true && $item->attachment == null) ||
                                            ($item->request_for == 'Office Supplies' && $item->attachment == null));
                                    $statusClass = match ($item->status) {
                                        'pending' => 'danger',
                                        'processing' => 'warning',
                                        'rejected' => 'secondary',
                                        'resubmission' => 'danger',
                                        default => 'success',
                                    };
                                @endphp
                                <tr class="{{ $isPettyMissingAttachment ? 'alert alert-danger' : '' }}">
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $item->user->name ?? 'N/A' }}</td>
                                    <td>{{ $isStaffRequisition ? ($item->activity_title ?: $item->request_for) : $item->request_for }}</td>
                                    <td>
                                        <span class="badge {{ $isStaffRequisition ? 'bg-info' : 'bg-primary' }}">
                                            {{ $item->request_type }}
                                        </span>
                                    </td>
                                    <td>
                                        @if (!$item->reviewed_at)
                                            <span class="badge bg-warning">Review</span>
                                        @elseif(!$item->approved_at)
                                            <span class="badge bg-primary">Approve</span>
                                        @elseif(!$item->authorized_at)
                                            <span class="badge bg-secondary">Authorize</span>
                                        @elseif($item->status === 'approved' && $item->request_type !== 'Staff Requisition')
                                            <span class="badge bg-success">Awaiting Payment</span>
                                        @else
                                            <span class="badge bg-success">Completed</span>
                                        @endif
                                    </td>
                                    <td>{{ number_format($item->amount, 2) }}</td>
                                    <td>{{ $item->created_at->format('d M, Y') }}</td>
                                    <td>
                                        <span class="badge bg-{{ $statusClass }} text-white">{{ $item->status }}</span>
                                    </td>
                                    <td>
                                        <a href="{{ route('petty.details', $item->id) }}"
                                            class="btn btn-sm btn-primary text-white">
                                            <i class="ti ti-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted">No approval requests found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                </div>
            </div>
        </div>


    </div>




</x-app-layout>
