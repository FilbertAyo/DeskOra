<div class="mb-4">

    @if ($request->is_transporter == true)
        <div class="mt-4">
            <h5 class="text-dark mb-3 fw-bold d-flex align-items-center">
                <i class="ti ti-file me-2 text-primary"></i>
                Transport Attachment
            </h5>
            @if (!empty($request->attachment))
                <a href="{{ asset($request->attachment) }}" download
                    class="btn btn-primary d-inline-flex align-items-center shadow-sm">
                    <i class="ti ti-download me-2"></i>
                    <span>Download Attachment</span>
                </a>
            @else
                <div class="alert alert-danger d-flex align-items-center border-0">
                    <i class="ti ti-alert-circle me-2 fs-5"></i>
                    <span>No attachment available</span>
                </div>
            @endif
        </div>
    @else
        @if (!empty($request->attachment))
            <div class="mt-4">
                <h5 class="text-dark mb-3 fw-bold d-flex align-items-center">
                    <i class="ti ti-file me-2 text-primary"></i>
                    Attachment
                </h5>
                <a href="{{ asset($request->attachment) }}" download
                    class="btn btn-primary d-inline-flex align-items-center shadow-sm">
                    <i class="ti ti-download me-2"></i>
                    <span>Download Attachment</span>
                </a>
            </div>
        @endif
    @endif

</div>

<style>
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
</style>
