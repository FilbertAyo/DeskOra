
@php
    // Keep a stable id since this partial is used once per request details page.
    $offcanvasId = 'approvalTimelineOffcanvas';
    $offcanvasLabelId = $offcanvasId.'Label';
@endphp

<button class="btn btn-light-secondary btn-sm" type="button"
    data-bs-toggle="offcanvas" data-bs-target="#{{ $offcanvasId }}" aria-controls="{{ $offcanvasId }}">
    <i class="ti ti-list me-2"></i> Approval Timeline
</button>

<div class="offcanvas offcanvas-end" tabindex="-1" id="{{ $offcanvasId }}" aria-labelledby="{{ $offcanvasLabelId }}">
    <div class="offcanvas-header">
        <h5 id="{{ $offcanvasLabelId }}">Approval</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"
            aria-label="Close"></button>
    </div>

    <div class="offcanvas-body">
        @include('requests.approval.timeline')

        <div class="text-end mt-3">
            <button class="btn btn-light-danger btn-sm" type="button" data-bs-dismiss="offcanvas">
                Close
            </button>
        </div>
    </div>
</div>
