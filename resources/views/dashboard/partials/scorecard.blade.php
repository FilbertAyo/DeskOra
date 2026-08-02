@php
    $scorecard = $weeklyScorecard ?? null;
@endphp

@if($scorecard && ($scorecard['period'] ?? null))
    <div class="dash-panel">
        <div class="dash-panel__header">
            <h6 class="dash-panel__title">
                <i class="ti ti-chart-bar text-info"></i>Weekly scorecard
            </h6>
            <span class="small text-muted">
                W{{ $scorecard['period']->week_number }}
            </span>
        </div>
        <div class="dash-panel__body">
            <div class="row g-2 text-center">
                <div class="col-6">
                    <div class="border rounded py-2 px-1">
                        <div class="fw-semibold">{{ $scorecard['plan_submitted'] ? 'Yes' : 'No' }}</div>
                        <div class="small text-muted">Plan submitted</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="border rounded py-2 px-1">
                        <div class="fw-semibold">{{ $scorecard['report_submitted'] ? 'Yes' : 'No' }}</div>
                        <div class="small text-muted">Report submitted</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="border rounded py-2 px-1">
                        <div class="fw-semibold">{{ $scorecard['work_log_days'] }}/{{ $scorecard['work_log_expected_days'] }}</div>
                        <div class="small text-muted">Log days</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="border rounded py-2 px-1">
                        <div class="fw-semibold">{{ $scorecard['open_actions'] }}</div>
                        <div class="small text-muted">Open actions</div>
                    </div>
                </div>
            </div>
            @if(($scorecard['carry_overs'] ?? 0) > 0)
                <p class="small text-muted mb-0 mt-2">
                    {{ $scorecard['carry_overs'] }} {{ \Illuminate\Support\Str::plural('activity', $scorecard['carry_overs']) }} marked to carry over.
                </p>
            @endif
        </div>
    </div>
@endif
