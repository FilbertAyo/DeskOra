@if($showPlanCoverageAlert)
    <div class="alert alert-danger border shadow-none d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div>
            <h6 class="alert-heading mb-1">
                <i class="ti ti-alert-triangle me-1"></i>Year Plan Update Required
            </h6>
            <p class="mb-1">
                Update your sub-department tasks for {{ $planCoverageYear }}. Some tasks still have no planned month selected.
            </p>
            <p class="mb-0 small">
                {{ number_format($planCoverageMissingCount) }} of {{ number_format($planCoverageTotalCount) }} tasks in your assigned sub-department(s) need at least one month (Jan-Dec) checked.
            </p>
        </div>
        @if($planCoverageActionUrl)
            <a href="{{ $planCoverageActionUrl }}" class="btn btn-danger fw-semibold text-nowrap">
                <i class="ti ti-calendar-edit me-1"></i>Update Year Plan Months
            </a>
        @endif
    </div>
@endif
