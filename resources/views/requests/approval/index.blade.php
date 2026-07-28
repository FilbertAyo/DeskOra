<x-app-layout>

<div class="page-header">
    <div class="page-block">
        <div class="row align-items-center">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">
                            @if (isset($stage))
                                @if (($typeFilter ?? null) === 'petty')
                                    {{ \App\Models\Request::stageLabel($stage) }} Petty Cash
                                @elseif (($typeFilter ?? null) === 'requisition')
                                    {{ \App\Models\Request::stageLabel($stage) }} Requisitions
                                @else
                                    {{ \App\Models\Request::stageQueueTitle($stage) }}
                                @endif
                            @else
                                Requests Approval
                            @endif
                        </h5>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                            @if (isset($stage))
                                <li class="breadcrumb-item"><a href="{{ route('petty.list', array_filter(['stage' => $stage, 'type' => $typeFilter ?? null])) }}">Approvals</a></li>
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

<div class="row my-2">
    <div class="col-md-12">
        <div class="card shadow-none border">
            <div class="card-body">
                <table class="table table-bordered">
                    <thead class="thead-light">
                        <tr>
                            <th>No.</th>
                            <th>Request By</th>
                            <th>Request For / Activity</th>
                            <th>Request Type</th>
                            <th>Your Action</th>
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
                                $statusClass = match ($item->status) {
                                    'pending' => 'danger',
                                    'processing' => 'warning',
                                    'rejected' => 'secondary',
                                    'resubmission' => 'danger',
                                    default => 'success',
                                };
                                $statusTextClass = 'text-'.$statusClass;
                                $canAct = isset($stage) && $item->userCanActAtStage(auth()->user(), $stage);
                                $hasCompleted = isset($stage) && $item->userHasCompletedStage(auth()->user(), $stage);
                                $actionLabel = isset($stage) ? \App\Models\Request::stageLabel($stage) : 'View';
                            @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $item->user->name ?? 'N/A' }}</td>
                                <td>{{ $isStaffRequisition ? ($item->activity_title ?: $item->request_for) : $item->request_for }}</td>
                                <td>
                                    <span class="badge {{ $isStaffRequisition ? 'bg-light-info border border-info text-info' : 'bg-light-primary border border-primary text-primary' }}">
                                        {{ $item->request_type }}
                                    </span>
                                </td>
                                <td>
                                    @if ($canAct)
                                        <span class="badge bg-light-warning border border-warning text-warning">Pending</span>
                                    @elseif ($hasCompleted)
                                        <span class="badge bg-light-success border border-success text-success">Completed</span>
                                    @else
                                        <span class="badge bg-light-secondary border border-secondary text-secondary">—</span>
                                    @endif
                                </td>
                                <td>{{ number_format($item->amount, 2) }}</td>
                                <td>{{ $item->created_at->format('d M, Y') }}</td>
                                <td>
                                    <span class="badge bg-light-{{ $statusClass }} border border-{{ $statusClass }} {{ $statusTextClass }}">{{ $item->status }}</span>
                                </td>
                                <td>
                                    <x-table-action-dropdown>
                                        <a
                                            class="dropdown-item"
                                            href="{{ route('petty.details', array_filter(['petty' => $item->id, 'stage' => $stage ?? null, 'type' => $typeFilter ?? null])) }}"
                                        >
                                            <i class="ti ti-{{ $canAct ? 'check' : 'eye' }} me-2"></i>
                                            {{ $canAct ? $actionLabel : 'View' }}
                                        </a>
                                    </x-table-action-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted">
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
