
@php
    $offcanvasId = 'approvalTimelineOffcanvas';
    $offcanvasLabelId = $offcanvasId.'Label';
    $showTrigger = $showTrigger ?? true;
@endphp

@if ($showTrigger)
    <button
        class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center"
        type="button"
        data-bs-toggle="offcanvas"
        data-bs-target="#{{ $offcanvasId }}"
        aria-controls="{{ $offcanvasId }}"
    >
        <i class="ti ti-timeline me-1"></i>
        View Timeline
    </button>
@endif

@once
    <div
        class="offcanvas offcanvas-end"
        tabindex="-1"
        id="{{ $offcanvasId }}"
        aria-labelledby="{{ $offcanvasLabelId }}"
        style="width: min(480px, 100vw);"
    >
        <div class="offcanvas-header border-bottom">
            <div>
                <h5 class="offcanvas-title mb-1" id="{{ $offcanvasLabelId }}">Approval Timeline</h5>
                <p class="text-muted small mb-0">Track each step of this request.</p>
            </div>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>

        <div class="offcanvas-body">
            @isset($request, $approval_logs)
                @include('requests.approval.timeline')
            @else
                <p class="text-muted small mb-0">Timeline is unavailable.</p>
            @endisset
        </div>
    </div>
@endonce
