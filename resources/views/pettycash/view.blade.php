<x-app-layout>



    <div class="row align-items-center mb-3 border-bottom no-gutters">
        <div class="col">
            <ul class="nav nav-tabs border-0" id="myTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="home-tab" data-toggle="tab" href="#home" role="tab"
                        aria-controls="home" aria-selected="true">Request Details</a>
                </li>
            </ul>
        </div>
        <div class="col-auto">
            <a href="{{ route('petty.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-arrow-left me-2"></i>Back to Requests
            </a>
        </div>
    </div>

    <!-- Attachment Upload Alert -->
    @if ($request->is_transporter == true && $request->attachment == null)
        <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center mb-4 borde shadow-none" role="alert">
            <div class="d-flex align-items-center flex-grow-1">
                <div class="alert-icon me-3">
                    <i class="ti ti-alert-triangle fs-3"></i>
                </div>
                <div class="flex-grow-1">
                    <h6 class="alert-heading mb-1">Attachment Required</h6>
                    <p class="mb-0">Please upload the required attachment for this transport request.</p>
                </div>
                <button class="btn btn-warning ms-3" data-toggle="modal" data-target="#uploadAttachmentModal">
                    <i class="ti ti-upload me-2"></i>Upload Now
                </button>
            </div>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
    @elseif($request->request_for == 'Office Supplies' && $request->attachment == null)
        <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center mb-4 borde shadow-none" role="alert">
            <div class="d-flex align-items-center flex-grow-1">
                <div class="alert-icon me-3">
                    <i class="ti ti-alert-triangle fs-3"></i>
                </div>
                <div class="flex-grow-1">
                    <h6 class="alert-heading mb-1">Attachment Required</h6>
                    <p class="mb-0">Please upload the required attachment for your office supplies request.</p>
                </div>
                <button class="btn btn-warning ms-3" data-toggle="modal" data-target="#uploadAttachmentModal">
                    <i class="ti ti-upload me-2"></i>Upload Now
                </button>
            </div>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
    @endif

    <!-- Upload Attachment Modal -->
    <div class="modal fade" id="uploadAttachmentModal" tabindex="-1" aria-labelledby="uploadAttachmentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('petty.updateAttachment', $request->id) }}" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow-lg">
                @csrf
                @method('POST')
                <div class="modal-header bg-light border-bottom-0">
                    <h5 class="modal-title fw-bold d-flex align-items-center" id="uploadAttachmentModalLabel">
                        <i class="ti ti-upload me-2 text-primary"></i>
                        <span>Upload Attachment</span>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="attachment" class="form-label fw-bold">Choose File <span class="text-danger">*</span></label>
                        <input type="file" name="attachment" id="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                        <small class="text-muted mt-1 d-block">Accepted formats: PDF, JPG, PNG, DOC, DOCX (Max 10MB)</small>
                    </div>
                </div>
                <div class="modal-footer border-top-0">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary d-flex align-items-center">
                        <i class="ti ti-upload me-2"></i>Upload
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <!-- Request Details Card -->
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1 fw-bold">Request Details</h5>
                            <small class="text-muted">Code: <strong class="text-primary">{{ $request->code }}</strong></small>
                        </div>
                        <div class="d-flex gap-2">
                            @if ($request->status == 'approved' || $request->status == 'paid')
                                <button type="button" class="btn btn-outline-success d-flex align-items-center" data-toggle="modal" data-target="#pettyCashModal">
                                    <i class="ti ti-download me-2"></i>Download PDF
                                </button>
                            @endif
                            @if ($request->status == 'pending' || $request->status == 'resubmission')
                                <a href="{{ route('petty.edit.step1', $request->id) }}" class="btn btn-outline-warning d-flex align-items-center">
                                    <i class="ti ti-edit me-2"></i>Edit Request
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-muted small">Request For</label>
                            <input type="text" class="form-control bg-light" value="{{ $request->request_for }}" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-muted small">Request Type</label>
                            <input type="text" class="form-control bg-light" value="{{ $request->request_type }}" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-muted small">Date Issued</label>
                            <input type="text" class="form-control bg-light" value="{{ $request->created_at->format('d M, Y') }}" disabled>
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
                                <input type="text" class="form-control bg-light" value="{{ \Carbon\Carbon::parse($request->paid_date)->format('d M, Y') }}" disabled>
                            </div>
                        @endif
                        <div class="col-md-2">
                            <label class="form-label fw-semibold text-muted small">Status</label>
                            <div class="mt-2">
                                @php
                                    $statusMap = [
                                        'pending' => ['class' => 'warning', 'icon' => 'fe-clock', 'text' => 'Pending'],
                                        'processing' => ['class' => 'info', 'icon' => 'fe-refresh-cw', 'text' => 'Processing'],
                                        'rejected' => ['class' => 'danger', 'icon' => 'fe-x-circle', 'text' => 'Rejected'],
                                        'resubmission' => ['class' => 'warning', 'icon' => 'fe-corner-up-left', 'text' => 'Resubmission', 'editable' => true],
                                        'approved' => ['class' => 'success', 'icon' => 'fe-check-circle', 'text' => 'Approved'],
                                        'paid' => ['class' => 'success', 'icon' => 'fe-dollar-sign', 'text' => 'Paid'],
                                    ];
                                    $statusInfo = $statusMap[$request->status] ?? ['class' => 'secondary', 'icon' => 'fe-info', 'text' => ucfirst($request->status)];
                                @endphp
                                @if ($request->status == 'resubmission')
                                    <a href="{{ route('petty.edit.step1', $request->id) }}" class="btn btn-{{ $statusInfo['class'] }} text-white d-inline-flex align-items-center px-3 py-2">
                                        <i class="ti ti-edit me-2"></i>
                                        Edit & Resubmit
                                    </a>
                                @else
                                    <span class="badge bg-{{ $statusInfo['class'] }} text-white px-3 py-2 d-inline-flex align-items-center fs-6">
                                        <i class="fe {{ $statusInfo['icon'] }} me-1"></i>
                                        {{ $statusInfo['text'] }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>


                    @include('elements.details')

                    <!-- Total Amount -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="alert alert-primary border-0 text-center py-4 mb-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                <div class="d-flex align-items-center justify-content-center">
                                    <i class="ti ti-dollar-sign fs-1 me-3"></i>
                                    <div>
                                        <h4 class="mb-1 fw-bold">Total Amount</h4>
                                        <h2 class="mb-0">TZS {{ number_format($request->amount, 2) }}</h2>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    @include('elements.approvals')

    @include('elements.print')

    <style>
        /* Enhanced UI Styling */
        .page-header-title h4 {
            font-weight: 600;
            color: #2c3e50;
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

        /* File input styling */
        input[type="file"] {
            cursor: pointer;
        }

        input[type="file"]::-webkit-file-upload-button {
            padding: 0.5rem 1rem;
            background: #e9ecef;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        input[type="file"]::-webkit-file-upload-button:hover {
            background: #dee2e6;
        }
    </style>

</x-app-layout>
