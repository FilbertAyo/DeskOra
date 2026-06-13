<x-app-layout>

@php
    $isStaffRequisition = $request->request_type === 'Staff Requisition';
    $reviewPermission = $isStaffRequisition ? 'review staff requisition' : 'first pettycash approval';
    $approvePermission = $isStaffRequisition ? 'approve staff requisition' : 'last pettycash approval';
    $authorizePermission = $isStaffRequisition ? 'authorize staff requisition' : 'authorize requests';
@endphp


<div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                                <h5 class="m-b-10">Request Details</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                              <li class="breadcrumb-item" aria-current="page">Request Details</li>
                              <li class="breadcrumb-item" aria-current="page">Request by <strong>{{ $request->user->name }}</strong></li>
                            </ul>
                        </div>

                        <div class="col-auto d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-light" onclick="reloadPage()"
                                title="Refresh">
                                <i class="ti ti-refresh"></i>
                            </button>
                            @if ($request->status == 'pending' && !$request->reviewed_at)
                    @if (auth()->user()->can($reviewPermission))
                        <button type="button" class="btn btn-primary btn-sm d-flex align-items-center shadow-sm"
                            data-toggle="modal" data-target="#approvalModal" data-bs-toggle="modal" data-bs-target="#approvalModal">
                            <i class="ti ti-check-circle me-2"></i>
                            <span>Review Request</span>
                        </button>
                    @endif
                @endif

                @if ($request->status == 'processing' && $request->reviewed_at && !$request->approved_at)
                    @if (auth()->user()->can($approvePermission))
                        <button type="button" class="btn btn-primary btn-sm d-flex align-items-center shadow-sm"
                            data-toggle="modal" data-target="#approvalModal" data-bs-toggle="modal" data-bs-target="#approvalModal">
                            <i class="ti ti-check-circle me-2"></i>
                            <span>Approve Request</span>
                        </button>
                    @endif
                @endif

                @if ($request->status == 'processing' && $request->approved_at && !$request->authorized_at)
                    @if (auth()->user()->can($authorizePermission))
                        <button type="button" class="btn btn-primary btn-sm d-flex align-items-center shadow-sm"
                            data-toggle="modal" data-target="#approvalModal" data-bs-toggle="modal" data-bs-target="#approvalModal">
                            <i class="ti ti-check-circle me-2"></i>
                            <span>Authorize Request</span>
                        </button>
                    @endif
                @endif

                @if (
                    $isStaffRequisition &&
                    $request->reviewed_at &&
                        $request->approved_at &&
                        $request->authorized_at &&
                        (auth()->user()->can('approve pettycash payments') || auth()->user()->can('authorize staff requisition')))
                    <a href="{{ route('petty.requisition.download', $request->id) }}"
                        class="btn btn-outline-secondary d-flex align-items-center shadow-sm">
                        <i class="ti ti-download me-2"></i>
                        <span>Download</span>
                    </a>
                @endif
                       </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

  

    <!-- Alert Messages -->
    @if ($approval == 'approved')
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4 borde shadow-none"
            role="alert">
            <div class="d-flex align-items-center">
                <div class="alert-icon me-3">
                    <i class="ti ti-check-circle fs-3"></i>
                </div>
                <div>
                    <h6 class="alert-heading mb-1">Request Approved</h6>
                    <p class="mb-0">You have successfully approved this request.</p>
                </div>
            </div>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span
                    aria-hidden="true">&times;</span></button>
        </div>
    @elseif($approval == 'rejected')
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4 borde shadow-none"
            role="alert">
            <div class="d-flex align-items-center">
                <div class="alert-icon me-3">
                    <i class="ti ti-x-circle fs-3"></i>
                </div>
                <div>
                    <h6 class="alert-heading mb-1">Request Rejected</h6>
                    <p class="mb-0">You have rejected this request.</p>
                </div>
            </div>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span
                    aria-hidden="true">&times;</span></button>
        </div>
    @elseif($approval == 'paid')
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4 borde shadow-none"
            role="alert">
            <div class="d-flex align-items-center">
                <div class="alert-icon me-3">
                    <i class="ti ti-check-circle fs-3"></i>
                </div>
                <div>
                    <h6 class="alert-heading mb-1">Payment Completed</h6>
                    <p class="mb-0">You have successfully paid this request.</p>
                </div>
            </div>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span
                    aria-hidden="true">&times;</span></button>
        </div>
    @endif

    <div class="row">
        <!-- Request Details Card -->
        <div class="col-12">
            <div class="card border shadow-none">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1 fw-bold">Request Details</h5>
                            <small class="text-muted">
                                Code: <strong class="text-primary">
                                    @can('approve pettycash payments')
                                        {{ $request->code }}
                                    @endcan
                                </strong>
                            </small>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        @if ($isStaffRequisition)
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-muted small">Request Type</label>
                            <input type="text" class="form-control bg-light" value="{{ $request->request_type }}"
                                disabled>
                        </div>
                    
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-muted small">Date of Request</label>
                            <input type="text" class="form-control bg-light"
                                value="{{ optional($request->date_of_request)->format('d M, Y') }}" disabled>
                        </div>
                            <div class="col-md-3">
                            <label class="form-label fw-semibold text-muted small">Date of Requirement</label>
                            <input type="text" class="form-control bg-light"
                                value="{{ optional($request->date_of_requirement)->format('d M, Y') }}" disabled>
                        </div>
                    
                     
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-muted small">Status</label>
                            <div class="mt-2">
                                @php
                                    $statusMap = [
                                        'pending' => ['class' => 'warning', 'icon' => 'fe-clock'],
                                        'processing' => ['class' => 'info', 'icon' => 'fe-refresh-cw'],
                                        'rejected' => ['class' => 'danger', 'icon' => 'fe-x-circle'],
                                        'resubmission' => ['class' => 'warning', 'icon' => 'fe-corner-up-left'],
                                        'approved' => ['class' => 'success', 'icon' => 'fe-check-circle'],
                                        'paid' => ['class' => 'success', 'icon' => 'fe-dollar-sign'],
                                    ];
                                    $statusInfo = $statusMap[$request->status] ?? [
                                        'class' => 'secondary',
                                        'icon' => 'fe-info',
                                    ];
                                @endphp
                                <span
                                    class="badge bg-light-{{ $statusInfo['class'] }} text-white px-3 py-2 d-inline-flex align-items-center">
                                    <i class="fe {{ $statusInfo['icon'] }} me-1"></i>
                                    {{ ucfirst($request->status) }}
                                </span>
                            </div>
                        </div>
                        @else
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-muted small">Request Type</label>
                            <input type="text" class="form-control bg-light" value="{{ $request->request_type }}" disabled>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-muted small">Request For</label>
                            <input type="text" class="form-control bg-light" value="{{ $request->request_for }}" disabled>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-muted small">Date Issued</label>
                            <input type="text" class="form-control bg-light"
                                value="{{ optional($request->created_at)->format('d M, Y') }}" disabled>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-muted small">Status</label>
                            <div class="mt-2">
                                @php
                                    $statusMap = [
                                        'pending' => ['class' => 'warning', 'icon' => 'fe-clock'],
                                        'processing' => ['class' => 'info', 'icon' => 'fe-refresh-cw'],
                                        'rejected' => ['class' => 'danger', 'icon' => 'fe-x-circle'],
                                        'resubmission' => ['class' => 'warning', 'icon' => 'fe-corner-up-left'],
                                        'approved' => ['class' => 'success', 'icon' => 'fe-check-circle'],
                                        'paid' => ['class' => 'success', 'icon' => 'fe-dollar-sign'],
                                    ];
                                    $statusInfo = $statusMap[$request->status] ?? [
                                        'class' => 'secondary',
                                        'icon' => 'fe-info',
                                    ];
                                @endphp
                                <span class="badge bg-light-{{ $statusInfo['class'] }} text-white px-3 py-2 d-inline-flex align-items-center">
                                    <i class="fe {{ $statusInfo['icon'] }} me-1"></i>
                                    {{ ucfirst($request->status) }}
                                </span>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <label class="form-label fw-semibold text-muted small">Description</label>
                            <textarea class="form-control bg-light" rows="3" disabled>{{ $request->reason }}</textarea>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold text-muted small">Amount</label>
                            <input type="text" class="form-control bg-light"
                                value="TZS {{ number_format($request->amount, 2) }}" disabled>
                        </div>
                        @endif
                    </div>

                    @if ($isStaffRequisition)
                        @include('requests.requisition.partials.form-preview')
                    @else
                        @include('elements.details')
                    @endif
                </div>
            </div>
        </div>
    </div>


    @include('requests.approval.logs')



    @php
        if ($request->status === 'processing' && $request->approved_at && ! $request->authorized_at) {
            $modalApprovalRoute = route('requests.authorize', ['petty' => $request->id]);
            $modalApprovalLabel = 'Authorize';
            $modalStageDescription = 'You are authorizing this request after review and approval.';
        } elseif ($request->status === 'processing') {
            $modalApprovalRoute = route('l_approve.approve', ['petty' => $request->id]);
            $modalApprovalLabel = 'Approve';
            $modalStageDescription = 'You are approving this request after review.';
        } else {
            $modalApprovalRoute = route('f_approve.approve', ['petty' => $request->id]);
            $modalApprovalLabel = 'Review';
            $modalStageDescription = 'You are reviewing this request as the first step.';
        }
    @endphp

    <!-- Approval Modal (default placement, not vertically centered) -->
    <div class="modal fade" id="approvalModal" data-backdrop="static" tabindex="-1"
        aria-labelledby="approvalModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content shadow-sm border">
                <div class="modal-header py-3 border-bottom align-items-center">
                    <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">
                        <span class="rounded-circle bg-light-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center flex-shrink-0"
                            style="width: 40px; height: 40px;">
                            <i class="ti ti-user-check fs-5"></i>
                        </span>
                        <div class="min-w-0">
                            <h5 class="modal-title mb-0 fw-semibold" id="approvalModalLabel">Confirm action</h5>
                            <div class="small text-muted text-truncate">{{ $modalApprovalLabel }} · {{ $request->request_type }}</div>
                        </div>
                    </div>
                    <button type="button" class="close ms-2" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0">
                    <!-- Step 1: approve or go to reject -->
                    <div id="step-approval" class="p-4">
                        <div class="rounded border bg-light p-3 mb-4 small">
                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <span class="text-muted">Requester</span>
                                    <div class="fw-medium">{{ $request->user->name }}</div>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted">Reference</span>
                                    <div class="fw-medium font-monospace">{{ $request->code }}</div>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted">Amount</span>
                                    <div class="fw-medium">TZS {{ number_format($request->amount, 2) }}</div>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted">Status</span>
                                    <div><span class="badge bg-light-secondary">{{ $request->status }}</span></div>
                                </div>
                            </div>
                        </div>
                        <p class="text-dark mb-4 mb-md-3">
                            {{ $modalStageDescription }}
                            Use <strong>Reject</strong> if the request should not proceed, or <strong>{{ $modalApprovalLabel }}</strong> to continue.
                        </p>
                        <div class="d-flex flex-column flex-sm-row gap-2 align-items-stretch align-items-sm-center justify-content-sm-end pt-2 border-top">
                            <button type="button" class="btn btn-outline-danger order-2 order-sm-1 me-sm-auto"
                                onclick="goToRejectStep()">
                                <i class="ti ti-x-circle me-1"></i>
                                Reject or request changes
                            </button>
                            <form action="{{ $modalApprovalRoute }}" method="POST" class="order-1 order-sm-2 d-grid d-sm-inline">
                                @csrf
                                <button type="submit" class="btn btn-primary">
                                    <i class="ti ti-check me-1"></i>
                                    {{ $modalApprovalLabel }}
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Step 2: rejection / resubmission -->
                    <div id="step-reject" class="d-none p-4 border-top">
                        <h6 class="fw-semibold mb-3">Reject or ask for resubmission</h6>
                        <form method="POST" action="{{ route('petty.reject', ['petty' => $request->id]) }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Outcome <span class="text-danger">*</span></label>
                                <div class="form-check mb-2 p-3 border rounded bg-white">
                                    <input class="form-check-input" type="radio" name="action" value="rejected"
                                        id="rejectRadio" required>
                                    <label class="form-check-label" for="rejectRadio">
                                        <strong>Reject</strong>
                                        <span class="d-block small text-muted">Close the request permanently.</span>
                                    </label>
                                </div>
                                <div class="form-check p-3 border rounded bg-white">
                                    <input class="form-check-input" type="radio" name="action" value="resubmission"
                                        id="resubmitRadio" required>
                                    <label class="form-check-label" for="resubmitRadio">
                                        <strong>Request resubmission</strong>
                                        <span class="d-block small text-muted">Return to the requester with feedback.</span>
                                    </label>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label for="comment" class="form-label fw-semibold">Comment <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="comment" id="comment" rows="4"
                                    placeholder="Explain the reason or what needs to change…" required></textarea>
                            </div>
                            <div class="d-flex flex-column flex-sm-row gap-2 justify-content-between">
                                <button type="button" class="btn btn-outline-secondary" onclick="backToApprovalStep()">
                                    <i class="ti ti-arrow-left me-1"></i>
                                    Back
                                </button>
                                <x-primary-button label="Submit" />
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function goToRejectStep() {
                document.getElementById('step-approval')?.classList.add('d-none');
                document.getElementById('step-reject')?.classList.remove('d-none');
            }
            function backToApprovalStep() {
                document.getElementById('step-reject')?.classList.add('d-none');
                document.getElementById('step-approval')?.classList.remove('d-none');
            }
            const approvalModalEl = document.getElementById('approvalModal');
            approvalModalEl?.addEventListener('hidden.bs.modal', function () {
                backToApprovalStep();
            });
            if (typeof jQuery !== 'undefined') {
                jQuery('#approvalModal').on('hidden.bs.modal', function () {
                    backToApprovalStep();
                });
            }
        </script>
    @endpush

</x-app-layout>
