<x-app-layout>
    @php
        $dashboardData = $weeklyDashboardData ?? null;
        $pendingDocs = $dashboardData['pendingDocuments'] ?? collect();
        $allDocs = $dashboardData['allDocuments'] ?? collect();
        $deadlineStatuses = $dashboardData['deadlineStatuses'] ?? ($periodDeadlineStatuses ?? null);
        $availableTypes = $dashboardData['availableTypes'] ?? ($dashboardAvailableTypes ?? []);
        $returnedDocs = collect($pendingDocs)->filter(fn ($doc) => method_exists($doc, 'isReturnedForRevision') && $doc->isReturnedForRevision());
        $plainDraftDocs = collect($pendingDocs)->filter(fn ($doc) => !method_exists($doc, 'isReturnedForRevision') || !$doc->isReturnedForRevision());
        $hasReviewerAccess = auth()->check() && auth()->user()->hasPermissionTo('view all documents');
        $recentReviewerReturns = collect($allDocs)
            ->filter(fn ($doc) => method_exists($doc, 'isReturnedForRevision') && $doc->isReturnedForRevision())
            ->sortByDesc('updated_at')
            ->take(5)
            ->values();

        $activeActionTasks = collect($actionLogProgress['activeTasks'] ?? [])
            ->filter(function ($task) {
                if (!is_array($task)) {
                    return false;
                }

                $latestProgress = strtolower(trim((string) ($task['latest_progress'] ?? 'Not Started')));

                return !in_array($latestProgress, ['done', 'cancelled'], true);
            })
            ->values();
        $activeActionTaskCount = $activeActionTasks->count();
        $assignedWeeklyActivities = collect($assignedWeeklyActivities ?? []);
        $assignedWeeklyActivityCount = $assignedWeeklyActivities->count();
        $sharedWithMeCount = (int) ($sharedWithMeCount ?? 0);
        $collaborationEvents = collect($collaborationEvents ?? []);
        $currentActionLogDraftId = $actionLogProgress['currentDraftId'] ?? null;
        $hasCurrentActionDraft = !empty($currentActionLogDraftId);
        $pendingDraftsTotal =
            (int) ($dashboardPendingDocs['weekly_plan'] ?? 0)
            + (int) ($dashboardPendingDocs['weekly_report'] ?? 0)
            + (int) ($dashboardPendingDocs['monthly_report'] ?? 0);

        $wr = $weeklySubmissionReminder ?? null;
        $showWeeklyToast = $wr?->showToast ?? false;
        $showWeeklyPlanShortcut = isset($availableTypes['weekly_plan'])
            && ($nextPeriod ?? null)
            && $wr
            && ($wr->plan !== null && $wr->plan->showCta);
        $showWeeklyReportShortcut = isset($availableTypes['weekly_report'])
            && ($currentPeriod ?? null)
            && $wr
            && ($wr->report !== null && $wr->report->showCta);
        $planGraceEntries = collect($weeklyPlanGraceEntries ?? []);
        $reportGraceEntries = collect($weeklyReportGraceEntries ?? []);
        $hasWeeklyGraceWindow = $planGraceEntries->isNotEmpty() || $reportGraceEntries->isNotEmpty();
        $focusTaskShowRoute = function ($doc) {
            if (($doc->type ?? null) === \App\Models\Document::TYPE_ACTION_LOG) {
                return route('action-logs.show', $doc->id);
            }

            return route('documents.show', $doc->id);
        };
        $focusTaskEditRoute = function ($doc) {
            if (($doc->type ?? null) === \App\Models\Document::TYPE_ACTION_LOG) {
                return route('action-logs.edit', $doc->id);
            }

            return route('documents.edit', $doc->id);
        };
        $planCoverageAlert = $yearPlanMonthCoverageAlert ?? null;
        $showPlanCoverageAlert = (bool) ($planCoverageAlert['show'] ?? false);
        $planCoverageMissingCount = (int) ($planCoverageAlert['missingMonthCount'] ?? 0);
        $planCoverageTotalCount = (int) ($planCoverageAlert['totalActivities'] ?? 0);
        $planCoverageYear = (int) ($planCoverageAlert['year'] ?? now()->year);
        $planCoverageActionUrl = $planCoverageAlert['actionUrl'] ?? null;
        $todaysWeeklyTasks = collect($todaysWeeklyTasks ?? []);
        $todaysDayName = $todaysDayName ?? now()->format('l');
        $showTodaysWeeklyTasks = in_array($todaysDayName, ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'], true);
        $todaysTasksClear = ! $showTodaysWeeklyTasks || $todaysWeeklyTasks->isEmpty();
    @endphp


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
                                <button type="button" class="wm-matrix-cta-btn" data-bs-toggle="modal" data-bs-target="#dashboardMatrixModal">
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


  
    <div class="row">
        @if($showPlanCoverageAlert)
            <div class="col-12 mb-3">
                <div class="alert alert-danger border shadow-none d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-0">
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
            </div>
        @endif

        <div class="col-12 col-lg-8 col-md-8 mb-3">
            <div class="row g-3">
                <div class="col-6 col-xl-2">
                    <div class="card shadow-none border">
                        <div class="card-body">
                            <p class="mb-1 text-muted small">
                                <i class="ti ti-arrow-back-up text-danger me-1"></i>Returned
                            </p>
                            <h4 class="mb-0 text-dark">{{ $returnedDocs->count() }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-2">
                    <div class="card shadow-none border">
                        <div class="card-body">
                            <p class="mb-1 text-muted small">
                                <i class="ti ti-file-edit text-warning me-1"></i>Drafts
                            </p>
                            <h4 class="mb-0 text-dark">{{ $plainDraftDocs->count() }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-2">
                    <div class="card shadow-none border">
                        <div class="card-body">
                            <p class="mb-1 text-muted small">
                                <i class="ti ti-clipboard-list text-primary me-1"></i>Open
                            </p>
                            <h4 class="mb-0 text-dark">{{ $activeActionTaskCount }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-4">
                    <div class="card shadow-none border">
                        <div class="card-body">
                            <p class="mb-1 text-muted small">
                                <i class="ti ti-users-group text-info me-1"></i>Shared
                            </p>
                            <h4 class="mb-0 text-dark">{{ $sharedWithMeCount }}</h4>
                            @if($sharedWithMeCount > 0)
                                <div class="d-flex flex-wrap gap-2 mt-2 pt-2 border-top small">
                                    @if(isset($dashboardAvailableTypes['weekly_plan']))
                                        <a href="{{ route('documents.weekly-plans') }}" class="text-decoration-none">
                                            <i class="ti ti-target me-1"></i>Weekly plans
                                        </a>
                                    @endif
                                    @if(isset($dashboardAvailableTypes['weekly_report']))
                                        <a href="{{ route('documents.weekly-reports') }}" class="text-decoration-none">
                                            <i class="ti ti-activity me-1"></i>Weekly reports
                                        </a>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-2">
                    <div class="card shadow-none border">
                        <div class="card-body">
                            <p class="mb-1 text-muted small">
                                <i class="ti ti-clipboard-list text-secondary me-1"></i>Assigned
                            </p>
                            <h4 class="mb-0 text-dark">{{ $assignedWeeklyActivityCount }}</h4>
                        </div>
                    </div>
                </div>
            </div>

            @if($weeklySubmissionReminder && count($weeklySubmissionReminder->missedMessages) > 0)
                <div class="alert alert-danger border mb-0 mt-3">
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
                <div class="alert alert-warning border mb-0 mt-3">
                    <p class="mb-2 fw-semibold"><i class="ti ti-clock-hour-4 me-1"></i>Grace period is active</p>
                    <p class="mb-2 small text-muted">
                        Your administrator extended the deadline. Use the buttons below to create or finish the document before the new cutoff.
                    </p>
                    <ul class="mb-3 ps-3 small">
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

            @if($collaborationEvents->isNotEmpty())
                <div class="card shadow-none border mt-3">
                    <div class="card-body">
                        <p class="fw-semibold mb-2"><i class="ti ti-users-group me-1"></i>Recent collaboration updates</p>
                        @foreach($collaborationEvents as $event)
                            <div class="d-flex align-items-start gap-2 mb-2">
                                <span class="badge {{ $event['badge_class'] ?? 'bg-light' }} align-self-center">{{ $event['badge_label'] ?? '' }}</span>
                                <div class="flex-grow-1 min-w-0">
                                    @if(!empty($event['document_id']))
                                        <a href="{{ route('documents.show', $event['document_id']) }}" class="text-decoration-none small d-block">
                                            {{ $event['message'] }}
                                        </a>
                                    @else
                                        <span class="small d-block">{{ $event['message'] }}</span>
                                    @endif
                                </div>
                                <small class="text-muted text-nowrap">{{ $event['created_at']->diffForHumans() }}</small>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="col-12 col-lg-4 col-md-4 mb-3">
            <div class="card shadow-none border">
                <div class="card-body">
                    @if($showWeeklyToast && $wr)
                        <ul class="mb-3 ps-3 small text-muted">
                            @if($wr->plan && $wr->plan->showCta)
                                <li class="mb-2">
                                    <strong class="text-dark">Plan</strong> for next week (W{{ $wr->plan->period->week_number }}, {{ $wr->plan->period->year }}) —
                                    @if($wr->plan->document && $wr->plan->document->isReturnedForRevision())
                                        reviewer asked for changes; please revise and resubmit.
                                    @elseif($wr->plan->document)
                                        finish your draft and submit before the deadline.
                                    @else
                                        not started yet.
                                    @endif
                                </li>
                            @endif
                            @if($wr->report && $wr->report->showCta)
                                <li class="{{ ($wr->plan && $wr->plan->showCta) ? 'mb-0' : 'mb-2' }}">
                                    <strong class="text-dark">Report</strong> for this week (W{{ $wr->report->period->week_number }}, {{ $wr->report->period->year }}) —
                                    @if($wr->report->document && $wr->report->document->isReturnedForRevision())
                                        reviewer asked for changes; please revise and resubmit.
                                    @elseif($wr->report->document)
                                        finish your draft and submit before the deadline.
                                    @else
                                        not started yet.
                                    @endif
                                </li>
                            @endif
                        </ul>
                        <div class="d-flex flex-wrap gap-2">
                            @if($wr->plan && $wr->plan->showCta)
                                <a href="{{ $wr->plan->document
                                    ? route('documents.edit', $wr->plan->document->id)
                                    : route('documents.create', ['type' => 'weekly_plan', 'period_id' => $wr->plan->period->id]) }}"
                                   class="btn btn-sm btn-primary">
                                    <i class="ti {{ $wr->plan->document ? 'ti-edit' : 'ti-circle-plus' }} me-1"></i>
                                    @if($wr->plan->document && $wr->plan->document->isReturnedForRevision())
                                        Revise plan
                                    @elseif($wr->plan->document)
                                        Open plan
                                    @else
                                        Create plan
                                    @endif
                                </a>
                            @endif
                            @if($wr->report && $wr->report->showCta)
                                <a href="{{ $wr->report->document
                                    ? route('documents.edit', $wr->report->document->id)
                                    : route('documents.create', ['type' => 'weekly_report', 'period_id' => $wr->report->period->id]) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="ti {{ $wr->report->document ? 'ti-edit' : 'ti-circle-plus' }} me-1"></i>
                                    @if($wr->report->document && $wr->report->document->isReturnedForRevision())
                                        Revise report
                                    @elseif($wr->report->document)
                                        Open report
                                    @else
                                        Create report
                                    @endif
                                </a>
                            @endif
                        </div>
                    @else
                        <div class="text-center">
                            <i class="ti ti-mood-smile fs-1 text-success"></i>
                            <p class="text-muted mb-0 mt-2">No action needed. Your weekly plan and report are up to date.</p>
                        
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-6 col-md-6 col-12">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="ti ti-checklist me-2"></i>My Weekly Activities</h5>
                    <small class="text-muted">Today's plan & revisions</small>
                </div>
                <div class="card-body">
                    @if($showTodaysWeeklyTasks && $todaysWeeklyTasks->isNotEmpty())
                        <div class="mb-3">
                            <h6 class="text-primary mb-2">
                                <i class="ti ti-calendar-event me-1"></i>{{ $todaysDayName }}'s tasks
                            </h6>
                            @foreach($todaysWeeklyTasks as $task)
                                <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
                                    <span>{{ $task['activity'] }}</span>
                                    <small class="text-muted">{{ $task['source_label'] }}</small>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($returnedDocs->count() > 0)
                        <div class="mb-3">
                            <h6 class="text-danger mb-2"><i class="ti ti-arrow-back-up me-1"></i>Needs Immediate Revision</h6>
                            @foreach($returnedDocs->take(3) as $doc)
                                @php
                                    $docSharedUsers = $doc->relationLoaded('effectiveSharedUsers')
                                        ? $doc->getRelation('effectiveSharedUsers')
                                        : $doc->sharedUsers;
                                @endphp
                                <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
                                    <div>
                                        <strong>{{ $doc->display_title }}</strong>
                                        <div class="small text-muted">
                                            {{ $doc->type_name }} • Updated {{ $doc->updated_at?->diffForHumans() }}
                                        </div>
                                        @if(($doc->type ?? null) === \App\Models\Document::TYPE_WEEKLY_REPORT && $docSharedUsers->isNotEmpty())
                                            <div class="small text-info">
                                                Shared with: {{ $docSharedUsers->pluck('name')->join(', ') }}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="{{ $focusTaskShowRoute($doc) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                        <a href="{{ $focusTaskEditRoute($doc) }}" class="btn btn-sm btn-danger">Revise</a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($plainDraftDocs->count() > 0)
                        <div class="mb-3">
                            <h6 class="text-warning mb-2"><i class="ti ti-file-edit me-1"></i>Drafts To Finalize</h6>
                            @foreach($plainDraftDocs->take(3) as $doc)
                                @php
                                    $docSharedUsers = $doc->relationLoaded('effectiveSharedUsers')
                                        ? $doc->getRelation('effectiveSharedUsers')
                                        : $doc->sharedUsers;
                                @endphp
                                <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
                                    <div>
                                        <strong>{{ $doc->display_title }}</strong>
                                        <div class="small text-muted">{{ $doc->type_name }} • Created {{ $doc->created_at?->format('M d, Y') }}</div>
                                        @if(($doc->type ?? null) === \App\Models\Document::TYPE_WEEKLY_REPORT && $docSharedUsers->isNotEmpty())
                                            <div class="small text-info">
                                                Shared with: {{ $docSharedUsers->pluck('name')->join(', ') }}
                                            </div>
                                        @endif
                                    </div>
                                    <a href="{{ $focusTaskEditRoute($doc) }}" class="btn btn-sm btn-warning text-dark">Continue</a>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($recentReviewerReturns->count() > 0)
                        <div>
                            <h6 class="text-primary mb-2"><i class="ti ti-arrow-back-up me-1"></i>Latest return reasons</h6>
                            @foreach($recentReviewerReturns as $doc)
                                <div class="border-start border-3 border-primary ps-3 mb-2">
                                    <div class="small fw-semibold">{{ $doc->display_title }}</div>
                                    <div class="small text-muted">
                                        {{ $doc->latestRejectionReason() }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($activeActionTaskCount === 0 && $todaysTasksClear && $returnedDocs->isEmpty() && $plainDraftDocs->isEmpty() && $recentReviewerReturns->isEmpty())
                        <div class="text-center py-4">
                            <i class="ti ti-mood-smile fs-1 text-success"></i>
                            <p class="text-muted mt-2 mb-0">Nothing pending right now.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>


        <div class="col-xl-6 col-md-6 col-12">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="ti ti-bolt me-2"></i>My Action Tasks
                        @if($activeActionTaskCount > 0)
                            <span class="badge bg-light-primary border border-primary ms-1">{{ $activeActionTaskCount }}</span>
                        @endif
                    </h5>
                    <a href="{{ route('action-logs.index') }}" class="small text-decoration-none">View all</a>
                </div>
                <div class="card-body">
                    @if($activeActionTaskCount > 0)
                        <p class="text-muted small mb-3">
                            Tasks assigned to you that still need an update. Give a quick progress update.
                        </p>
                        <div class="list-group list-group-flush border rounded">
                            @foreach($activeActionTasks as $task)
                                @php
                                    $tProgress = $task['latest_progress'] ?? 'Not Started';
                                    $badgeClass = match($tProgress) {
                                        'Done' => 'bg-light-success border border-success',
                                        'Cancelled' => 'bg-light-danger border border-danger',
                                        'In Progress' => 'bg-light-info border border-info',
                                        default => 'bg-light-secondary border border-secondary',
                                    };
                                @endphp
                                <div class="list-group-item px-2 py-2">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div class="flex-grow-1 min-w-0">
                                            <div class="small text-dark text-break">{{ $task['task'] ?? 'Task' }}</div>
                                            <div class="mt-1">
                                                <span class="badge {{ $badgeClass }}">{{ $tProgress }}</span>
                                                @if(!empty($task['period_label']))
                                                    <span class="badge bg-light-light border border-light text-dark">{{ $task['period_label'] }}</span>
                                                @endif
                                                @if(!empty($task['needs_update']))
                                                    <span class="badge bg-light-warning border border-warning text-dark">Carried forward</span>
                                                @endif
                                            </div>
                                            @if(!empty($task['needs_update']) && !empty($task['carried_from_period_label']))
                                                <div class="text-muted small mt-1">
                                                    From {{ $task['carried_from_period_label'] }} — no update this week yet.
                                                </div>
                                            @endif
                                        </div>
                                        <div class="flex-shrink-0">
                                            @if(!empty($task['editable']))
                                                <button type="button"
                                                    class="btn btn-sm btn-primary js-quick-update-task"
                                                    data-task="{{ $task['task'] ?? 'Task' }}"
                                                    data-task-uid="{{ $task['task_uid'] }}"
                                                    data-document-id="{{ $task['document_id'] }}"
                                                    data-progress="{{ $tProgress }}"
                                                    data-notes="{{ $task['latest_notes'] ?? '' }}"
                                                    data-period="{{ $task['period_label'] ?? '' }}">
                                                   Update
                                                </button>
                                            @elseif(!empty($task['document_id']))
                                                <a href="{{ route('action-logs.show', $task['document_id']) }}" class="btn btn-sm btn-outline-secondary">
                                                    <i class="ti ti-eye"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="ti ti-check fs-1 text-success"></i>
                            <p class="text-muted mb-0 mt-2">No action tasks need your attention right now.</p>
                           
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Action Task Update Modal -->
    @include('action-logs.partials.quick-update-modal', [
        'modalId' => 'quickActionTaskModal',
        'formId' => 'quickActionTaskForm',
        'triggerClass' => 'js-quick-update-task',
        'teamUsers' => $teamUsers ?? collect(),
    ])

    @if (($currentPeriod ?? null))
        @once
            <style>
                .weekly-matrix-modal-dialog {
                    max-width: 96vw;
                    width: 96vw;
                }

                .wm-matrix-cta-wrap {
                    position: relative;
                    display: inline-flex;
                    padding: 2px;
                    border-radius: 10px;
                    overflow: hidden;
                    isolation: isolate;
                }

                .wm-matrix-cta-wrap::before {
                    content: '';
                    position: absolute;
                    inset: -120%;
                    background: conic-gradient(
                        from 0deg,
                        transparent 0deg,
                        rgba(var(--bs-primary-rgb), 0.15) 40deg,
                        rgba(var(--bs-primary-rgb), 0.85) 120deg,
                        #7dd3fc 180deg,
                        rgba(var(--bs-primary-rgb), 0.85) 240deg,
                        rgba(var(--bs-primary-rgb), 0.15) 300deg,
                        transparent 360deg
                    );
                    animation: wmMatrixBorderSpin 2.8s linear infinite;
                    z-index: 0;
                }

                .wm-matrix-cta-wrap::after {
                    content: '';
                    position: absolute;
                    inset: 0;
                    border-radius: 10px;
                    box-shadow: 0 0 14px rgba(var(--bs-primary-rgb), 0.35);
                    animation: wmMatrixGlowPulse 2.8s ease-in-out infinite;
                    pointer-events: none;
                    z-index: 0;
                }

                .wm-matrix-cta-btn {
                    position: relative;
                    z-index: 1;
                    display: inline-flex;
                    align-items: center;
                    gap: 0.15rem;
                    padding: 0.5rem 1.05rem;
                    border: none;
                    border-radius: 8px;
                    background: #fff;
                    color: var(--bs-primary);
                    font-size: 0.875rem;
                    font-weight: 600;
                    line-height: 1.25;
                    white-space: nowrap;
                    cursor: pointer;
                    transition: transform 0.2s ease, background-color 0.2s ease, color 0.2s ease;
                }

                .wm-matrix-cta-btn:hover {
                    background: rgba(var(--bs-primary-rgb), 0.06);
                    color: var(--bs-primary);
                    transform: translateY(-1px);
                }

                .wm-matrix-cta-btn:focus-visible {
                    outline: 2px solid rgba(var(--bs-primary-rgb), 0.45);
                    outline-offset: 2px;
                }

                .wm-matrix-cta-btn .ti {
                    font-size: 1rem;
                }

                @keyframes wmMatrixBorderSpin {
                    to {
                        transform: rotate(360deg);
                    }
                }

                @keyframes wmMatrixGlowPulse {
                    0%, 100% {
                        box-shadow: 0 0 10px rgba(var(--bs-primary-rgb), 0.25);
                    }
                    50% {
                        box-shadow: 0 0 22px rgba(var(--bs-primary-rgb), 0.5);
                    }
                }

                @media (prefers-reduced-motion: reduce) {
                    .wm-matrix-cta-wrap::before,
                    .wm-matrix-cta-wrap::after {
                        animation: none;
                    }

                    .wm-matrix-cta-wrap::before {
                        background: linear-gradient(
                            135deg,
                            rgba(var(--bs-primary-rgb), 0.5),
                            rgba(var(--bs-primary-rgb), 0.85),
                            #7dd3fc
                        );
                    }
                }
            </style>
        @endonce
        <div class="modal fade" id="dashboardMatrixModal" tabindex="-1" aria-labelledby="dashboardMatrixModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-scrollable weekly-matrix-modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="dashboardMatrixModalLabel">
                            Weekly Matrix — Week {{ $currentPeriod->week_number }} · {{ $currentPeriod->year }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="dashboardMatrixBody">
                        <div class="text-center text-muted py-5">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                            Loading matrix…
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="{{ route('weekly-matrix.period', $currentPeriod) }}" class="btn btn-outline-secondary">
                            <i class="ti ti-external-link me-1"></i> Open period
                        </a>
                        <a href="{{ route('weekly-matrix.download', $currentPeriod) }}" class="btn btn-outline-danger">
                            <i class="ti ti-download me-1"></i> Download PDF
                        </a>
                    </div>
                </div>
            </div>
        </div>

        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const modalEl = document.getElementById('dashboardMatrixModal');
                    const bodyEl = document.getElementById('dashboardMatrixBody');
                    if (!modalEl || !bodyEl) {
                        return;
                    }

                    let loaded = false;
                    modalEl.addEventListener('show.bs.modal', function () {
                        if (loaded) {
                            return;
                        }

                        fetch('{{ route('weekly-matrix.period.table', $currentPeriod) }}', {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        })
                            .then((response) => response.ok ? response.json() : Promise.reject(response))
                            .then((data) => {
                                bodyEl.innerHTML = data.html || '<p class="text-muted mb-0">No matrix present.</p>';
                                loaded = true;
                            })
                            .catch(() => {
                                bodyEl.innerHTML = '<div class="alert alert-warning mb-0">Could not load the weekly matrix. Please try again.</div>';
                            });
                    });
                });
            </script>
        @endpush
    @endif

</x-app-layout>
