
<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-white border-bottom">
        <div class="d-flex align-items-center">
            <i class="ti ti-check-circle me-2 text-primary"></i>
            <h5 class="mb-0 fw-bold">Approval Timeline</h5>
        </div>
    </div>
    <div class="card-body p-4">
        <ul class="timeline-horizontal">
            <li class="timeline-item">
                <div class="timeline-badge bg-primary text-white">
                    <i class="ti ti-clipboard"></i>
                </div>
                <div class="timeline-panel">
                    <div class="timeline-heading">
                        <h6 class="timeline-title mb-1 fw-bold">{{ $request->request_for }}</h6>
                        <small class="text-muted">
                            <i class="ti ti-clock me-1"></i>
                            {{ $request->created_at->format('d M, Y h:i A') }}
                        </small>
                        <br>
                        <small class="text-primary fw-semibold">Request Created</small>
                    </div>
                </div>
            </li>

            @foreach ($approval_logs as $approval)
                @php
                    $statusStyles = [
                        'rejected' => ['class' => 'danger', 'icon' => 'fe-x-circle', 'text' => 'Rejected'],
                        'approved' => ['class' => 'success', 'icon' => 'fe-check-circle', 'text' => 'Approved'],
                        'paid' => ['class' => 'success', 'icon' => 'fe-dollar-sign', 'text' => 'Paid'],
                        'resubmission' => ['class' => 'warning', 'icon' => 'fe-corner-up-left', 'text' => 'Resubmission'],
                        'resubmitted' => ['class' => 'secondary', 'icon' => 'fe-rotate-ccw', 'text' => 'Resubmitted'],
                        'created' => ['class' => 'info', 'icon' => 'fe-plus-circle', 'text' => 'Created'],
                    ];

                    $action = strtolower($approval->action);
                    $badgeClass = $statusStyles[$action]['class'] ?? 'secondary';
                    $iconClass = $statusStyles[$action]['icon'] ?? 'fe-info';
                    $actionText = $statusStyles[$action]['text'] ?? ucfirst($approval->action);
                @endphp

                <li class="timeline-item">
                    <div class="timeline-badge {{ $badgeClass }}">
                        <i class="fe {{ $iconClass }}"></i>
                    </div>
                    <div class="timeline-panel">
                        <div class="timeline-heading">
                            <h6 class="timeline-title mb-1 fw-bold">{{ $actionText }}</h6>
                            <small class="text-muted">
                                <i class="ti ti-user me-1"></i>
                                {{ $approval->user->name }}
                            </small>
                            <br>
                            <small class="text-muted">
                                <i class="ti ti-clock me-1"></i>
                                {{ $approval->created_at->format('d M, Y h:i A') }}
                            </small>
                        </div>
                        @if(!empty($approval->comment))
                            <div class="timeline-body mt-2">
                                <div class="alert alert-light border-start border-3 border-{{ $badgeClass }} mb-0">
                                    <small class="text-muted mb-0">{{ $approval->comment }}</small>
                                </div>
                            </div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</div>


 <style>
     .timeline-horizontal {
         list-style: none;
         padding: 0;
         margin: 0;
         display: flex;
         align-items: flex-start;
         overflow-x: auto;
         white-space: nowrap;
         padding-bottom: 1rem;
         scrollbar-width: thin;
         scrollbar-color: #dee2e6 transparent;
     }

     .timeline-horizontal::-webkit-scrollbar {
         height: 6px;
     }

     .timeline-horizontal::-webkit-scrollbar-track {
         background: #f1f3f4;
         border-radius: 3px;
     }

     .timeline-horizontal::-webkit-scrollbar-thumb {
         background: #dee2e6;
         border-radius: 3px;
     }

     .timeline-horizontal::-webkit-scrollbar-thumb:hover {
         background: #adb5bd;
     }

     .timeline-horizontal .timeline-item {
         position: relative;
         display: inline-flex;
         flex-direction: column;
         align-items: center;
         margin-right: 2.5rem;
         min-width: 180px;
         flex-shrink: 0;
     }

     .timeline-horizontal .timeline-item:after {
         content: "";
         position: absolute;
         top: 20px;
         left: 50%;
         width: 100%;
         height: 3px;
         background: linear-gradient(90deg, #e2e6ea 0%, #f8f9fa 100%);
         transform: translateX(50%);
         z-index: 0;
         border-radius: 2px;
     }

     .timeline-horizontal .timeline-item:last-child:after {
         display: none;
     }

     .timeline-horizontal .timeline-badge {
         z-index: 1;
         width: 40px;
         height: 40px;
         border-radius: 50%;
         background: #ffffff;
         border: 3px solid #dee2e6;
         display: flex;
         align-items: center;
         justify-content: center;
         color: #495057;
         box-shadow: 0 4px 12px rgba(0,0,0,.1);
         transition: all 0.3s ease;
         font-size: 1.1rem;
     }

     .timeline-horizontal .timeline-badge:hover {
         transform: scale(1.1);
         box-shadow: 0 6px 20px rgba(0,0,0,.15);
     }

     .timeline-horizontal .timeline-badge.bg-primary {
         background: #007bff !important;
         border-color: #007bff !important;
         color: white !important;
     }

     .timeline-horizontal .timeline-badge.success {
         background: #28a745 !important;
         border-color: #28a745 !important;
         color: white !important;
     }

     .timeline-horizontal .timeline-badge.warning {
         background: #ffc107 !important;
         border-color: #ffc107 !important;
         color: #212529 !important;
     }

     .timeline-horizontal .timeline-badge.danger {
         background: #dc3545 !important;
         border-color: #dc3545 !important;
         color: white !important;
     }

     .timeline-horizontal .timeline-badge.secondary {
         background: #6c757d !important;
         border-color: #6c757d !important;
         color: white !important;
     }

     .timeline-horizontal .timeline-badge.info {
         background: #17a2b8 !important;
         border-color: #17a2b8 !important;
         color: white !important;
     }

     .timeline-horizontal .timeline-panel {
         margin-top: 1rem;
         text-align: center;
         max-width: 200px;
         white-space: normal;
         padding: 0.5rem;
         border-radius: 0.5rem;
         background: rgba(248, 249, 250, 0.5);
         transition: all 0.3s ease;
     }

     .timeline-horizontal .timeline-panel:hover {
         background: rgba(248, 249, 250, 0.8);
         transform: translateY(-2px);
     }

     .timeline-horizontal .timeline-title {
         font-weight: 600;
         color: #2c3e50;
     }

     .timeline-horizontal .timeline-heading small {
         font-size: 0.75rem;
         line-height: 1.4;
     }
 </style>
