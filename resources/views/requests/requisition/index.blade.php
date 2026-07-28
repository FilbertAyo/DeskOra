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

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="ti ti-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="ti ti-alert-circle me-2"></i>{{ session('error') }}
            <a href="{{ route('profile.edit') }}" class="alert-link ms-1">Go to Profile Settings</a>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow-none border">
        <div class="card-body">
            <table class="table table-bordered datatables">
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
                                    <span class="badge bg-light-warning border border-warning text-warning">Draft</span>
                                @elseif ($item->status == 'pending')
                                    <span class="badge bg-light-danger border border-danger text-danger">{{ $item->status }}</span>
                                @elseif ($item->status == 'processing')
                                    <span class="badge bg-light-primary border border-primary text-primary">{{ $item->status }}</span>
                                @elseif ($item->status == 'rejected')
                                    <span class="badge bg-light-secondary border border-secondary text-secondary">{{ $item->status }}</span>
                                @elseif ($item->status == 'resubmission')
                                    <span class="badge bg-light-danger border border-danger text-danger">{{ $item->status }}</span>
                                @else
                                    <span class="badge bg-light-success border border-success text-success">{{ $item->status }}</span>
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
    <form action="{{ route('requisition.destroy', $item->id) }}" method="POST"
    onsubmit="return confirm('Delete this draft requisition?');">
    @csrf
    @method('DELETE')
    <button type="submit" class="dropdown-item text-danger">
    <i class="ti ti-trash me-2"></i> Delete Draft
    </button>
    </form>
    @elseif ($item->status === 'resubmission')
    <a class="dropdown-item" href="{{ route('requisition.edit', $item->id) }}">
    <i class="ti ti-edit me-2"></i> Edit
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
