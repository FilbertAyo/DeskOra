<x-app-layout>

    <div class="row align-items-center mb-3 border-bottom no-gutters">
        <div class="col">
            <div class="page-header-title d-flex align-items-center">
                <i class="ti ti-dollar-sign me-2 text-primary"></i>
                <h5 class="mb-0 text-muted">
                    Request by <strong>{{ $request->user->name }}</strong>
                </h5>
            </div>
        </div>

        <div class="col-auto">
            <div class="btn-toolbar gap-2">
                @if ($request->status == 'pending')
                    @can('first pettycash approval')
                        <button class="btn btn-primary btn-sm d-flex align-items-center shadow-sm" data-toggle="modal"
                            data-target="#approvalModal">
                            <i class="ti ti-check-circle me-2"></i>
                            <span>Review Request</span>
                        </button>
                    @endcan
                @endif

                @if ($request->status == 'processing')
                    @can('last pettycash approval')
                        <button class="btn btn-primary btn-sm d-flex align-items-center shadow-sm" data-toggle="modal"
                            data-target="#approvalModal">
                            <i class="ti ti-check-circle me-2"></i>
                            <span>Review Request</span>
                        </button>
                    @endcan
                @endif

                @if ($request->status == 'approved')
                    @can('approve petycash payments')
                        <button class="btn btn-outline-primary btn-sm d-flex align-items-center shadow-sm"
                            data-toggle="modal" data-target="#approvalModal">
                            <i class="ti ti-eye me-2"></i>
                            <span>View Details</span>
                        </button>
                        <a href="javascript:void(0);" class="btn btn-success btn-sm d-flex align-items-center shadow-sm"
                            onclick="confirmApproval('{{ route('c_approve.approve', ['id' => $request->id]) }}')">
                            <i class="ti ti-credit-card me-2"></i>
                            <span>Pay Now</span>
                        </a>
                    @endcan
                @endif

                <button type="button" class="btn btn-outline-secondary d-flex align-items-center shadow-sm"
                    data-toggle="modal" data-target="#pettyCashModal">
                    <i class="ti ti-printer me-2"></i>
                    <span>Print</span>
                </button>
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
                                    @can('approve petycash payments')
                                        {{ $request->code }}
                                    @endcan
                                </strong>
                            </small>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-muted small">Request For</label>
                            <input type="text" class="form-control bg-light" value="{{ $request->request_for }}"
                                disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-muted small">Request Type</label>
                            <input type="text" class="form-control bg-light" value="{{ $request->request_type }}"
                                disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-muted small">Date Issued</label>
                            <input type="text" class="form-control bg-light"
                                value="{{ $request->created_at->format('d M, Y') }}" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">Description</label>
                            <div class="bg-light border rounded p-3">
                                <p class="mb-0 text-dark">{!! nl2br(e($request->reason)) !!}</p>
                            </div>
                        </div>
                        @if ($request->paid_date)
                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-muted small">Date Paid</label>
                                <input type="text" class="form-control bg-light"
                                    value="{{ \Carbon\Carbon::parse($request->paid_date)->format('d M, Y') }}" disabled>
                            </div>
                        @endif
                        <div class="col-md-2">
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
                                    class="badge bg-{{ $statusInfo['class'] }} text-white px-3 py-2 d-inline-flex align-items-center">
                                    <i class="fe {{ $statusInfo['icon'] }} me-1"></i>
                                    {{ ucfirst($request->status) }}
                                </span>
                            </div>
                        </div>
                    </div>


                    @include('elements.details')

                    <!-- Total Amount -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="alert alert-primary bg-gradient border-0 text-center py-3 mb-0">
                                <h5 class="mb-0 d-flex align-items-center justify-content-center">
                                    <i class="ti ti-dollar-sign me-2"></i>
                                    <span>Total Amount: <strong>TZS
                                            {{ number_format($request->amount, 2) }}/=</strong></span>
                                </h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    @include('elements.approvals')
    @include('elements.print')



    <!-- Approval Modal -->
    <div class="modal fade" id="approvalModal" data-backdrop="static" tabindex="-1"
        aria-labelledby="approvalModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-light border-bottom-0">
                    <h5 class="modal-title fw-bold d-flex align-items-center" id="approvalModalLabel">
                        <i class="ti ti-user-check me-2 text-primary"></i>
                        <span>Request Approval</span>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <!-- Step 1: Approval Decision -->
                    <div id="step-approval">
                        <div class="text-center mb-4">
                            <div class="mb-3">
                                <i class="ti ti-file-text text-primary" style="font-size: 3rem;"></i>
                            </div>
                            <p class=" mb-0">
                                After reviewing <strong class="text-dark">{{ $request->user->name }}</strong>'s
                                request,
                                please choose to approve or reject.
                            </p>
                        </div>
                        <div class="d-flex gap-2 justify-content-center">
                            <button class="btn btn-danger d-flex align-items-center mx-4"
                                onclick="goToRejectStep()">
                                <i class="ti ti-x-circle me-2"></i>
                                <span>Reject</span>
                            </button>
                            <form
                                action="{{ $request->status == 'processing' ? route('l_approve.approve', ['id' => $request->id]) : route('f_approve.approve', ['id' => $request->id]) }}"
                                method="POST" class="d-inline">
                                @csrf

                                <x-primary-button label="Approve" />
                            </form>
                        </div>
                    </div>

                    <!-- Step 2: Rejection Form -->
                    <div id="step-reject" class="d-none">
                        <form method="POST" action="{{ route('petty.reject', ['id' => $request->id]) }}">
                            @csrf
                            <div class="mb-4">
                                <label class="form-label fw-bold mb-3">Select Action <span
                                        class="text-danger">*</span></label>
                                <div class="form-check mb-3 p-3 border rounded">
                                    <input class="form-check-input" type="radio" name="action" value="rejected"
                                        id="rejectRadio" required>
                                    <label class="form-check-label d-flex align-items-start" for="rejectRadio">
                                        <div class="ms-2">
                                            <strong>Reject Request</strong>
                                            <p class="text-muted small mb-0">Permanently reject this request</p>
                                        </div>
                                    </label>
                                </div>
                                <div class="form-check p-3 border rounded">
                                    <input class="form-check-input" type="radio" name="action"
                                        value="resubmission" id="resubmitRadio" required>
                                    <label class="form-check-label d-flex align-items-start" for="resubmitRadio">
                                        <div class="ms-2">
                                            <strong>Request Resubmission</strong>
                                            <p class="text-muted small mb-0">Ask the requester to resubmit with
                                                corrections</p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label for="comment" class="form-label fw-bold">Reason <span
                                        class="text-danger">*</span></label>
                                <textarea class="form-control" name="comment" id="comment" rows="4"
                                    placeholder="Please provide a detailed reason..." required></textarea>
                            </div>
                            <div class="d-flex gap-2 justify-content-between">
                                <button type="button" class="btn btn-outline-secondary d-flex align-items-center"
                                    onclick="backToApprovalStep()">
                                    <i class="ti ti-arrow-left me-2"></i>
                                    <span>Back</span>
                                </button>
                              <x-primary-button label="Submit" />
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        function confirmApproval(url) {
            if (confirm('Are you sure you want to pay this request?')) {
                window.location.href = url;
            }
        }

        function goToRejectStep() {
            document.getElementById('step-approval').classList.add('d-none');
            document.getElementById('step-reject').classList.remove('d-none');
        }

        function backToApprovalStep() {
            document.getElementById('step-reject').classList.add('d-none');
            document.getElementById('step-approval').classList.remove('d-none');
        }
    </script>

    <style>
        /* Enhanced UI Styling */
        .page-header-title h4 {
            font-weight: 600;
            color: #2c3e50;
        }

        .btn-toolbar {
            display: flex;
            gap: 0.5rem;
        }

        .card {
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-2px);
        }

        .badge {
            font-size: 0.875rem;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        .form-control:disabled,
        .form-control.bg-light {
            background-color: #f8f9fa !important;
            border-color: #e9ecef;
            cursor: not-allowed;
        }

        .alert {
            border-radius: 0.5rem;
        }

        .alert-icon {
            font-size: 2rem;
        }

        .modal-content {
            border-radius: 0.75rem;
            overflow: hidden;
        }

        .modal-header {
            padding: 1.25rem 1.5rem;
        }

        .form-check {
            transition: all 0.2s ease;
        }

        .form-check:hover {
            background-color: #f8f9fa;
        }

        .btn {
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .bg-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white !important;
        }

        .bg-gradient * {
            color: white !important;
        }

        /* Smooth animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card,
        .alert {
            animation: fadeIn 0.3s ease-out;
        }
    </style>

</x-app-layout>
