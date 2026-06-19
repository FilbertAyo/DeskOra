<x-app-layout>

<div class="page-header">
    <div class="page-block">
        <div class="row align-items-center">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">{{ isset($stage) ? \App\Models\Request::stageQueueTitle($stage) : 'Requests Approval' }}</h5>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                            @if (isset($stage))
                                <li class="breadcrumb-item"><a href="{{ route('petty.list', ['stage' => $stage]) }}">Approvals</a></li>
                                <li class="breadcrumb-item" aria-current="page">{{ $stageLabel ?? ucfirst($stage) }}</li>
                            @else
                                <li class="breadcrumb-item" aria-current="page">Requests Approval</li>
                            @endif
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

@if (isset($stage, $accessibleStages) && count($accessibleStages) > 0)
    <div class="alert alert-light border mb-3 py-2">
        <small class="mb-0 d-block">
            <strong>How it works:</strong> Requests move through Review, Approve, then Authorize.
            This page shows only items waiting for <strong>your</strong> action at the <strong>{{ strtolower($stageLabel) }}</strong> step.
        </small>
    </div>

    <ul class="nav nav-pills mb-3 gap-2">
        @foreach ($accessibleStages as $accessibleStage)
            <li class="nav-item">
                <a
                    href="{{ route('petty.list', ['stage' => $accessibleStage]) }}"
                    @class(['nav-link', 'active' => $accessibleStage === $stage])
                >
                    {{ \App\Models\Request::stageLabel($accessibleStage) }}
                    @php
                        $stageCount = match ($accessibleStage) {
                            'review' => $approvalsReviewCount ?? 0,
                            'approve' => $approvalsApproveCount ?? 0,
                            'authorize' => $approvalsAuthorizeCount ?? 0,
                            default => 0,
                        };
                    @endphp
                    @if ($stageCount > 0)
                        <span class="badge bg-light-danger ms-1">{{ $stageCount }}</span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
@endif

<div class="row my-2">
    <div class="col-md-12">
        <div class="card shadow-none border">
            <div class="card-body">
                <table class="table table-bordered table-striped datatables" id="dataTable-1">
                    <thead class="thead-light">
                        <tr>
                            <th>No.</th>
                            <th>Request By</th>
                            <th>Request For / Activity</th>
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
                                $canAct = isset($stage) && $item->userCanActAtStage(auth()->user(), $stage);
                                $actionLabel = isset($stage) ? \App\Models\Request::stageLabel($stage) : 'View';
                            @endphp
                            <tr class="{{ $isPettyMissingAttachment ? 'alert alert-danger' : '' }}">
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $item->user->name ?? 'N/A' }}</td>
                                <td>{{ $isStaffRequisition ? ($item->activity_title ?: $item->request_for) : $item->request_for }}</td>
                                <td>
                                    <span class="badge {{ $isStaffRequisition ? 'bg-light-info' : 'bg-light-primary' }}">
                                        {{ $item->request_type }}
                                    </span>
                                </td>
                                <td>{{ number_format($item->amount, 2) }}</td>
                                <td>{{ $item->created_at->format('d M, Y') }}</td>
                                <td>
                                    <span class="badge bg-light-{{ $statusClass }} text-white">{{ $item->status }}</span>
                                </td>
                                <td>
                                    <x-table-action-dropdown>
                                        <a
                                            class="dropdown-item"
                                            href="{{ route('petty.details', ['petty' => $item->id, 'stage' => $stage ?? null]) }}"
                                        >
                                            <i class="ti ti-{{ $canAct ? 'check' : 'eye' }} me-2"></i>
                                            {{ $canAct ? $actionLabel : 'View' }}
                                        </a>
                                    </x-table-action-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted">
                                    {{ isset($stage) ? \App\Models\Request::stageEmptyMessage($stage) : 'No approval requests found.' }}
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
