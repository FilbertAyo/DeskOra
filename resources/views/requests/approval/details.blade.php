<x-app-layout>

@php
    $isStaffRequisition = $request->request_type === 'Staff Requisition';
    $currentStage = $request->approvalStage();
    $currentStageLabel = \App\Models\Request::stageLabel(
        in_array($currentStage, \App\Models\Request::APPROVAL_STAGES, true) ? $currentStage : 'review'
    );
    $actionLabel = match ($currentStage) {
        'review' => 'Review Request',
        'approve' => 'Approve Request',
        'authorize' => 'Authorize Request',
        default => 'Take Action',
    };
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
                                <li class="breadcrumb-item">
                                    <a href="{{ route('petty.list', array_filter(['stage' => $returnStage ?? 'review', 'type' => request('type')])) }}">Approvals</a>
                                </li>
                                <li class="breadcrumb-item" aria-current="page">Request by <strong>{{ $request->user->name }}</strong></li>
                            </ul>
                        </div>

                        <div class="col-auto d-flex align-items-center gap-2">
                            <a href="{{ route('petty.list', array_filter(['stage' => $returnStage ?? 'review', 'type' => request('type')])) }}" class="btn btn-sm btn-light">
                                <i class="ti ti-arrow-left me-1"></i> Back
                            </a>
                          
                            @include('requests.approval.logs')
                            @if (($canActOnRequest ?? false) && ($hasSignature ?? false))
                        <button type="button" class="btn btn-primary btn-sm d-flex align-items-center shadow-sm"
                            data-toggle="modal" data-target="#approvalModal" data-bs-toggle="modal" data-bs-target="#approvalModal">
                            <i class="ti ti-check-circle me-2"></i>
                            <span>{{ $actionLabel }}</span>
                        </button>
                @endif

                @if (
                    $isStaffRequisition &&
                    $request->reviewed_at &&
                        $request->approved_at &&
                        $request->authorized_at &&
                        (auth()->user()->can('approve pettycash payments') || auth()->user()->can('authorize staff requisition') || auth()->user()->can('download staff requisition')))
                    <a href="{{ route('petty.requisition.download', $request->id) }}"
                        class="btn btn-danger btn-sm d-flex align-items-center">
                        <i class="ti ti-download me-2"></i>
                        <span>Download</span>
                    </a>
                @endif

                @if (
                    ! $isStaffRequisition
                    && $request->status === 'approved'
                    && $request->authorized_at
                    && ! $request->approvals->where('action', 'paid')->count()
                    && auth()->user()->can('approve pettycash payments'))
                    <form action="{{ route('c_approve.approve', $request->id) }}" method="POST" class="d-inline">
                        @csrf
                        <x-primary-button label="Pay Now" class="btn-sm d-flex align-items-center" />
                    </form>
                @endif
                       </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if (($canActOnRequest ?? false) && ! ($hasSignature ?? false))
        <div class="alert alert-warning border-warning mb-4">
            <div class="d-flex align-items-start gap-2">
                <i class="ti ti-signature fs-4 mt-1"></i>
                <div>
                    <strong>Signature required to {{ strtolower($actionLabel) }}</strong>
                    <p class="mb-2">Upload your signature in Profile Settings before you can review, approve, or authorize requests.</p>
                    <a href="{{ route('profile.edit') }}" class="btn btn-sm btn-primary">Upload Signature</a>
                </div>
            </div>
        </div>
    @endif

    @if (! ($canActOnRequest ?? false) && $currentStage !== 'completed')
        <div class="alert alert-light border mb-4">
            <div class="d-flex align-items-start gap-2">
                <i class="ti ti-info-circle text-primary mt-1"></i>
                <div>
                    <strong>View only</strong>
                    <p class="mb-0 small text-muted">
                        This request is currently awaiting <strong>{{ strtolower($currentStageLabel) }}</strong>.
                        You can review the details below, but you cannot take action at this stage.
                    </p>
                </div>
            </div>
        </div>
    @endif

    @php
        $actionFeedback = match (true) {
            session()->has('success') => [
                'variant' => 'success',
                'icon' => 'ti-circle-check',
                'title' => 'Action recorded',
                'message' => session('success'),
            ],
            session()->has('error') => [
                'variant' => 'danger',
                'icon' => 'ti-alert-circle',
                'title' => 'Action not completed',
                'message' => session('error'),
            ],
            session()->has('warning') => [
                'variant' => 'warning',
                'icon' => 'ti-alert-triangle',
                'title' => 'Please review',
                'message' => session('warning'),
            ],
            default => null,
        };
    @endphp

    @if ($actionFeedback)
        <div class="alert alert-{{ $actionFeedback['variant'] }} alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-start gap-3">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light-{{ $actionFeedback['variant'] }} border border-{{ $actionFeedback['variant'] }} text-{{ $actionFeedback['variant'] }} flex-shrink-0"
                    style="width: 2.5rem; height: 2.5rem;">
                    <i class="ti {{ $actionFeedback['icon'] }} fs-5"></i>
                </span>
                <div class="flex-grow-1 pe-2">
                    <h6 class="alert-heading mb-1">{{ $actionFeedback['title'] }}</h6>
                    <p class="mb-0 small">{{ $actionFeedback['message'] }}</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
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
                                    class="badge bg-light-{{ $statusInfo['class'] }} border border-{{ $statusInfo['class'] }} text-{{ $statusInfo['class'] }} d-inline-flex align-items-center">{{ ucfirst($request->status) }}
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
                                <span class="badge bg-light-{{ $statusInfo['class'] }} border border-{{ $statusInfo['class'] }} text-white px-3 py-2 d-inline-flex align-items-center">{{ ucfirst($request->status) }}
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
                        <span class="rounded-circle bg-light-primary border border-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center flex-shrink-0"
                            style="width: 40px; height: 40px;">
                            <i class="ti ti-user-check fs-5"></i>
                        </span>
                        <div class="min-w-0">
                            <h5 class="modal-title mb-0 fw-semibold" id="approvalModalLabel">Confirm action</h5>
                            <div class="small text-muted text-truncate">{{ $modalApprovalLabel }} · {{ $request->request_type }}</div>
                        </div>
                    </div>
                  
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
                                    <div><span class="badge bg-light-secondary border border-secondary">{{ $request->status }}</span></div>
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
                                <x-primary-button :label="$modalApprovalLabel" />
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
