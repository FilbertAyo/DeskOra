@if($weeklySubmissionReminder && count($weeklySubmissionReminder->missedMessages) > 0)
    <div class="alert alert-danger border shadow-none">
        <p class="mb-1 fw-semibold"><i class="ti ti-alert-circle me-1"></i>Missed submission window</p>
        <p class="mb-2 small text-muted">
            @if(isset($deadlineStatuses['plan_due']) || isset($deadlineStatuses['report_due']))
                @if(isset($deadlineStatuses['plan_due']))
                    Plan cutoff: {{ \Carbon\Carbon::parse($deadlineStatuses['plan_due'])->format('M d, Y H:i') }}.
                @endif
                @if(isset($deadlineStatuses['report_due']))
                    Report cutoff: {{ \Carbon\Carbon::parse($deadlineStatuses['report_due'])->format('M d, Y H:i') }}.
                @endif
            @else
                Weekly plan and report cut-off is managed by the active work period deadlines.
            @endif
        </p>
        <ul class="mb-0 ps-3 small">
            @foreach($weeklySubmissionReminder->missedMessages as $line)
                <li>{{ $line }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if($hasWeeklyGraceWindow)
    <div class="alert alert-warning border shadow-none">
        <p class="mb-2 fw-semibold"><i class="ti ti-clock-hour-4 me-1"></i>Grace period is active</p>
        <p class="mb-2 small text-muted">
            Your administrator extended the deadline. Use the buttons below to create or finish the document before the new cutoff.
        </p>
        <ul class="mb-0 ps-3 small">
            @foreach($planGraceEntries as $planGraceEntry)
                <li class="mb-2">
                    <span class="d-block">
                        Weekly plan deadline was extended
                        @if($planGraceEntry->period)
                            for Week {{ $planGraceEntry->period->week_number }} of {{ $planGraceEntry->period->year }}
                        @endif.
                        @if($planGraceEntry->graceEndsAt)
                            You can still submit until {{ $planGraceEntry->graceEndsAt->format('M d, Y H:i') }}.
                        @endif
                    </span>
                    @if(isset($availableTypes['weekly_plan']))
                        <span class="d-inline-block mt-1">
                            @include('documents.partials.weekly-submission-action-button', [
                                'slot' => $planGraceEntry,
                                'documentType' => 'weekly_plan',
                                'btnClass' => 'btn-warning',
                            ])
                        </span>
                    @endif
                </li>
            @endforeach
            @foreach($reportGraceEntries as $reportGraceEntry)
                <li class="{{ $loop->last ? 'mb-0' : 'mb-2' }}">
                    <span class="d-block">
                        Weekly report deadline was extended
                        @if($reportGraceEntry->period)
                            for Week {{ $reportGraceEntry->period->week_number }} of {{ $reportGraceEntry->period->year }}
                        @endif.
                        @if($reportGraceEntry->graceEndsAt)
                            You can still submit until {{ $reportGraceEntry->graceEndsAt->format('M d, Y H:i') }}.
                        @endif
                    </span>
                    @if(isset($availableTypes['weekly_report']))
                        <span class="d-inline-block mt-1">
                            @include('documents.partials.weekly-submission-action-button', [
                                'slot' => $reportGraceEntry,
                                'documentType' => 'weekly_report',
                                'btnClass' => 'btn-warning',
                            ])
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
