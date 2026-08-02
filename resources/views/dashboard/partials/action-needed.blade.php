@php
    $approvalActionMeta = [
        'Review petty cash' => ['icon' => 'ti-cash', 'tone' => 'warning', 'cta' => 'Review'],
        'Review requisitions' => ['icon' => 'ti-file-text', 'tone' => 'warning', 'cta' => 'Review'],
        'Approve petty cash' => ['icon' => 'ti-circle-check', 'tone' => 'primary', 'cta' => 'Approve'],
        'Approve requisitions' => ['icon' => 'ti-file-check', 'tone' => 'primary', 'cta' => 'Approve'],
        'Authorize petty cash' => ['icon' => 'ti-shield-check', 'tone' => 'danger', 'cta' => 'Authorize'],
        'Authorize requisitions' => ['icon' => 'ti-certificate', 'tone' => 'danger', 'cta' => 'Authorize'],
    ];
    $hasWeeklyActions = $showWeeklyToast && $wr && (
        ($wr->plan && $wr->plan->showCta) || ($wr->report && $wr->report->showCta)
    );
    $hasActionNeeded = $approvalActions->isNotEmpty() || $hasWeeklyActions;
    $actionNeededCount = $approvalActions->sum('count')
        + ($hasWeeklyActions ? (($wr->plan && $wr->plan->showCta ? 1 : 0) + ($wr->report && $wr->report->showCta ? 1 : 0)) : 0);
@endphp

<div class="dash-panel">
    <div class="dash-panel__header">
        <h6 class="dash-panel__title">
            <i class="ti ti-alert-circle text-warning"></i>Action needed
        </h6>
        @if($hasActionNeeded)
            <span class="badge bg-light-warning text-warning border border-warning">
                {{ $actionNeededCount }}
            </span>
        @endif
    </div>
    <div class="dash-panel__body dash-panel__body--flush">
        @if($hasActionNeeded)
            @foreach($approvalActions as $action)
                @php
                    $meta = $approvalActionMeta[$action['label']] ?? ['icon' => 'ti-bell', 'tone' => 'secondary', 'cta' => 'Open'];
                    $tone = $meta['tone'];
                @endphp
                <a href="{{ $action['url'] }}" class="dash-action-item">
                    <div class="dash-action-item__icon text-{{ $tone }} bg-light-{{ $tone }}">
                        <i class="ti {{ $meta['icon'] }}"></i>
                    </div>
                    <div class="dash-action-item__text">
                        <h6>{{ $action['label'] }}</h6>
                        <p>{{ $action['count'] }} {{ \Illuminate\Support\Str::plural('item', $action['count']) }} waiting</p>
                    </div>
                    <span class="badge bg-{{ $tone }}">{{ $meta['cta'] }}</span>
                </a>
            @endforeach

            @if($hasWeeklyActions && $wr->plan && $wr->plan->showCta)
                @php
                    $planNeedsRevision = $wr->plan->document && $wr->plan->document->isReturnedForRevision();
                    $planUrl = $wr->plan->document
                        ? route('documents.edit', $wr->plan->document->id)
                        : route('documents.create', ['type' => 'weekly_plan', 'period_id' => $wr->plan->period->id]);
                    $planTone = $planNeedsRevision ? 'danger' : 'info';
                    $planCta = $planNeedsRevision ? 'Revise' : ($wr->plan->document ? 'Open' : 'Create');
                    $planSubtitle = $planNeedsRevision
                        ? 'Returned for revision — resubmit soon'
                        : ($wr->plan->document
                            ? 'Draft in progress — submit before deadline'
                            : 'Not started yet');
                @endphp
                <a href="{{ $planUrl }}" class="dash-action-item">
                    <div class="dash-action-item__icon text-{{ $planTone }} bg-light-{{ $planTone }}">
                        <i class="ti {{ $planNeedsRevision ? 'ti-alert-triangle' : 'ti-calendar-event' }}"></i>
                    </div>
                    <div class="dash-action-item__text">
                        <h6>Weekly plan</h6>
                        <p>W{{ $wr->plan->period->week_number }}, {{ $wr->plan->period->year }} · {{ $planSubtitle }}</p>
                    </div>
                    <span class="badge bg-{{ $planTone }}">{{ $planCta }}</span>
                </a>
            @endif

            @if($hasWeeklyActions && $wr->report && $wr->report->showCta)
                @php
                    $reportNeedsRevision = $wr->report->document && $wr->report->document->isReturnedForRevision();
                    $reportUrl = $wr->report->document
                        ? route('documents.edit', $wr->report->document->id)
                        : route('documents.create', ['type' => 'weekly_report', 'period_id' => $wr->report->period->id]);
                    $reportTone = $reportNeedsRevision ? 'danger' : 'success';
                    $reportCta = $reportNeedsRevision ? 'Revise' : ($wr->report->document ? 'Open' : 'Create');
                    $reportSubtitle = $reportNeedsRevision
                        ? 'Returned for revision — resubmit soon'
                        : ($wr->report->document
                            ? 'Draft in progress — submit before deadline'
                            : 'Not started yet');
                @endphp
                <a href="{{ $reportUrl }}" class="dash-action-item">
                    <div class="dash-action-item__icon text-{{ $reportTone }} bg-light-{{ $reportTone }}">
                        <i class="ti {{ $reportNeedsRevision ? 'ti-alert-triangle' : 'ti-report' }}"></i>
                    </div>
                    <div class="dash-action-item__text">
                        <h6>Weekly report</h6>
                        <p>W{{ $wr->report->period->week_number }}, {{ $wr->report->period->year }} · {{ $reportSubtitle }}</p>
                    </div>
                    <span class="badge bg-{{ $reportTone }}">{{ $reportCta }}</span>
                </a>
            @endif
        @else
            <div class="dash-panel__empty">
                <div class="avtar avtar-s rounded-circle text-success bg-light-success mx-auto mb-2">
                    <i class="ti ti-mood-smile f-18"></i>
                </div>
                <p>No action needed right now.</p>
            </div>
        @endif
    </div>
</div>
