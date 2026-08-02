<div class="page-header">
    <div class="page-block">
        <div class="row align-items-center">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Dashboard</h5>
                        <x-dashboard-greeting />
                    </div>
                    @if (($currentPeriod ?? null))
                        <span class="wm-matrix-cta-wrap">
                            <button type="button" class="wm-matrix-cta-btn" data-feature="feature.dashboard.matrix_modal" data-bs-toggle="modal" data-bs-target="#dashboardMatrixModal">
                                <i class="ti ti-layout-grid me-1"></i>
                                View weekly matrix
                            </button>
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
