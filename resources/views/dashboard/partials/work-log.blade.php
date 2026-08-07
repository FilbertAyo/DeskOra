@php
    $todayWorkLog = $todayWorkLog ?? null;
    $reportReadyHint = (bool) ($reportReadyHint ?? false);
    $currentPeriod = $currentPeriod ?? null;
    $canLogToday = (bool) $currentPeriod;
@endphp

<div class="dash-panel">
    <div class="dash-panel__header">
        <h6 class="dash-panel__title">
            <i class="ti ti-notebook text-primary"></i>Today’s work log
        </h6>
        @if($canLogToday)
            <button type="button"
                class="btn btn-link btn-sm p-0 small text-decoration-none"
                data-bs-toggle="modal"
                data-bs-target="#dashboardWorkLogModal"
                data-feature="feature.dashboard.work_log_open">
                {{ $todayWorkLog ? 'Edit' : 'Add' }}
            </button>
        @endif
    </div>
    <div class="dash-panel__body">
        @if($todayWorkLog)
            <p class="mb-2 small text-muted">Logged for {{ now()->format('D, M j') }}</p>
            <p class="mb-2">{{ \Illuminate\Support\Str::limit($todayWorkLog->done, 160) }}</p>
            @if($todayWorkLog->blocker)
                <p class="mb-0 small text-warning">
                    <i class="ti ti-alert-triangle me-1"></i>{{ \Illuminate\Support\Str::limit($todayWorkLog->blocker, 120) }}
                </p>
            @endif
            <button type="button"
                class="btn btn-sm btn-outline-primary mt-3"
                data-bs-toggle="modal"
                data-bs-target="#dashboardWorkLogModal"
                data-feature="feature.dashboard.work_log_open">
                <i class="ti ti-edit me-1"></i>Edit today
            </button>
        @elseif($canLogToday)
            <p class="mb-3 text-muted">Capture what you did today — it helps fill your weekly report later.</p>
            <button type="button"
                class="btn btn-sm btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#dashboardWorkLogModal"
                data-feature="feature.dashboard.work_log_open">
                <i class="ti ti-plus me-1"></i>Add work log
            </button>
        @else
            <p class="mb-0 text-muted">No work period is available yet.</p>
        @endif

        @if($reportReadyHint)
            <div class="alert alert-light border mt-3 mb-0 py-2 px-3 small">
                You have work logs for this week.
                <a href="{{ route('documents.create', ['type' => 'weekly_report']) }}">Start your weekly report</a>
                and use Draft with AI to fill the blanks.
            </div>
        @endif
    </div>
</div>
