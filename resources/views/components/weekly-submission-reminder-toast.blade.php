@php
    /** @var \App\Data\WeeklySubmissionReminder|null $weeklySubmissionReminder */
    $r = $weeklySubmissionReminder ?? null;
    $fixedWrapper = $fixedWrapper ?? true;
    $withScript = $withScript ?? true;
@endphp
@if($r && $r->showToast)
    @if($fixedWrapper)
        <div class="weekly-reminder-toast-stack position-fixed bottom-0 end-0 p-2 p-sm-3 d-flex flex-column gap-2 align-items-stretch"
             style="z-index: 1080; width: 100%; max-width: min(22rem, calc(100vw - 1rem)); box-sizing: border-box; pointer-events: none;">
    @endif
        <div id="weeklySubmissionReminderToast" class="toast hide border shadow-sm w-100 border-warning" role="alert" aria-live="polite" aria-atomic="true" style="max-width: 100%; pointer-events: auto;">
            <div class="toast-header py-2 bg-warning bg-opacity-10 border-bottom border-warning border-opacity-25">
                <i class="ti ti-calendar-event text-warning me-2"></i>
                <strong class="me-auto text-truncate" style="min-width: 0;">Weekly submissions</strong>
                <small class="text-muted flex-shrink-0">
                    @if($r->report && $r->report->showCta)
                        RW{{ $r->report->period->week_number }}
                    @endif
                    @if($r->plan && $r->plan->showCta)
                        @if($r->report && $r->report->showCta)
                            /
                        @endif
                        PW{{ $r->plan->period->week_number }}
                    @endif
                </small>
                <button type="button" class="btn-close ms-2" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body py-2 small">
                <p class="mb-2 fw-semibold">Needs your attention</p>
                <ul class="mb-3 ps-3">
                    @if($r->plan && $r->plan->showCta)
                        <li>
                            <strong>Plan</strong> for next week (W{{ $r->plan->period->week_number }}, {{ $r->plan->period->year }}) —
                            @if($r->plan->document && $r->plan->document->isReturnedForRevision())
                                reviewer asked for changes; please revise and resubmit.
                            @elseif($r->plan->document)
                                finish your draft and submit before the deadline.
                            @else
                                not started yet.
                            @endif
                        </li>
                    @endif
                    @if($r->report && $r->report->showCta)
                        <li>
                            <strong>Report</strong> for this week (W{{ $r->report->period->week_number }}, {{ $r->report->period->year }}) —
                            @if($r->report->document && $r->report->document->isReturnedForRevision())
                                reviewer asked for changes; please revise and resubmit.
                            @elseif($r->report->document)
                                finish your draft and submit before the deadline.
                            @else
                                not started yet.
                            @endif
                        </li>
                    @endif
                </ul>
                <div class="d-flex flex-wrap gap-2 justify-content-end">
                    @if($r->plan && $r->plan->showCta)
                        <a href="{{ $r->plan->document
                            ? route('documents.edit', $r->plan->document->id)
                            : route('documents.create', ['type' => 'weekly_plan', 'period_id' => $r->plan->period->id]) }}"
                           class="btn btn-sm btn-primary">
                            <i class="ti {{ $r->plan->document ? 'ti-edit' : 'ti-circle-plus' }} me-1"></i>
                            @if($r->plan->document && $r->plan->document->isReturnedForRevision())
                                Revise plan
                            @elseif($r->plan->document)
                                Open plan
                            @else
                                Create plan
                            @endif
                        </a>
                    @endif
                    @if($r->report && $r->report->showCta)
                        <a href="{{ $r->report->document
                            ? route('documents.edit', $r->report->document->id)
                            : route('documents.create', ['type' => 'weekly_report', 'period_id' => $r->report->period->id]) }}"
                           class="btn btn-sm btn-outline-primary">
                            <i class="ti {{ $r->report->document ? 'ti-edit' : 'ti-circle-plus' }} me-1"></i>
                            @if($r->report->document && $r->report->document->isReturnedForRevision())
                                Revise report
                            @elseif($r->report->document)
                                Open report
                            @else
                                Create report
                            @endif
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @if($fixedWrapper)
        </div>
    @endif

    @if($withScript)
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var el = document.getElementById('weeklySubmissionReminderToast');
                if (!el || typeof bootstrap === 'undefined' || !bootstrap.Toast) return;
                new bootstrap.Toast(el, { autohide: false }).show();
            });
        </script>
    @endif
@endif
