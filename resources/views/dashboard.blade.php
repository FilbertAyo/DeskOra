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
    @endphp

    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Dashboard</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="javascript: void(0)">Dashboard</a></li>
                        <li class="breadcrumb-item" aria-current="page">Dashboard</li>
                    </ul>
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
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="ti ti-bell me-2"></i>Insights</h5>
                    <span class="badge bg-light-primary">
                        {{ ($returnedDocs->count() ?? 0) + ($plainDraftDocs->count() ?? 0) + $activeActionTaskCount + $sharedWithMeCount + $assignedWeeklyActivityCount }}
                    </span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6 col-xl-2">
                            <div class="p-3 border rounded h-100">
                                <p class="mb-1 text-muted small">
                                    <i class="ti ti-arrow-back-up text-danger me-1"></i>Returned
                                </p>
                                <h4 class="mb-1 text-dark">{{ $returnedDocs->count() }}</h4>
                          </div>
                        </div>
                        <div class="col-6 col-xl-2">
                            <div class="p-3 border rounded h-100">
                                <p class="mb-1 text-muted small">
                                    <i class="ti ti-file-edit text-warning me-1"></i>Drafts 
                                </p>
                                <h4 class="mb-1 text-dark">{{ $plainDraftDocs->count() }}</h4>
                           </div>
                        </div>
                        <div class="col-6 col-xl-2">
                            <div class="p-3 border rounded h-100">
                                <p class="mb-1 text-muted small">
                                    <i class="ti ti-clipboard-list text-primary me-1"></i>Open
                                </p>
                                <h4 class="mb-1 text-dark">{{ $activeActionTaskCount }}</h4>
                           </div>
                        </div>
                        <div class="col-6 col-xl-4">
                            <div class="p-3 border rounded h-100">
                                <p class="mb-1 text-muted small">
                                    <i class="ti ti-users-group text-info me-1"></i>Shared
                                </p>
                                <h4 class="mb-1 text-dark">{{ $sharedWithMeCount }}</h4>
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
                        <div class="col-6 col-xl-2">
                            <div class="p-3 border rounded h-100">
                                <p class="mb-1 text-muted small">
                                    <i class="ti ti-clipboard-list text-secondary me-1"></i>Assigned
                                </p>
                                <h4 class="mb-1 text-dark">{{ $assignedWeeklyActivityCount }}</h4>
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
                        <div class="border rounded p-3 mt-3">
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
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4 col-md-4 mb-3">
            <div class="card shadow-none border h-100">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0"><i class="ti ti-calendar-event me-2"></i>Weekly plan & report</h5>
                </div>
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
                            <div class="d-flex flex-wrap justify-content-center gap-2 mt-3">
                                @if(isset($availableTypes['weekly_plan']))
                                    <a href="{{ route('documents.weekly-plans') }}" class="btn btn-sm btn-outline-primary">
                                        <i class="ti ti-target me-1"></i>Weekly plans
                                    </a>
                                @endif
                                @if(isset($availableTypes['weekly_report']))
                                    <a href="{{ route('documents.weekly-reports') }}" class="btn btn-sm btn-outline-primary">
                                        <i class="ti ti-activity me-1"></i>Weekly reports
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if($assignedWeeklyActivityCount > 0 && $currentPeriod)
            @include('documents.partials.weekly-assigned-activities-card', [
                'assignmentPeriod' => $currentPeriod,
            ])
        @endif

        <div class="col-xl-6 col-md-6 col-12">
            <div class="card shadow-none border h-100">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="ti ti-checklist me-2"></i>My Weekly Activities</h5>
                    <small class="text-muted">Revisions and drafts</small>
                </div>
                <div class="card-body">
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

                    @if($activeActionTaskCount === 0 && $assignedWeeklyActivityCount === 0 && $returnedDocs->isEmpty() && $plainDraftDocs->isEmpty() && $recentReviewerReturns->isEmpty())
                        <div class="text-center py-4">
                            <i class="ti ti-mood-smile fs-1 text-success"></i>
                            <p class="text-muted mt-2 mb-0">Great flow. No pending revisions or drafts right now.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>


        <div class="col-xl-6 col-md-6 col-12">
            <div class="card shadow-none border h-100">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="ti ti-bolt me-2"></i>My Action Tasks
                        @if($activeActionTaskCount > 0)
                            <span class="badge bg-light-primary ms-1">{{ $activeActionTaskCount }}</span>
                        @endif
                    </h5>
                    <a href="{{ route('action-logs.index') }}" class="small text-decoration-none">View all</a>
                </div>
                <div class="card-body">
                    @if($activeActionTaskCount > 0)
                        <p class="text-muted small mb-3">
                            Tasks assigned to you that still need an update. Give a quick progress update without leaving the dashboard.
                        </p>
                        <div class="list-group list-group-flush border rounded">
                            @foreach($activeActionTasks as $task)
                                @php
                                    $tProgress = $task['latest_progress'] ?? 'Not Started';
                                    $badgeClass = match($tProgress) {
                                        'Done' => 'bg-light-success',
                                        'Cancelled' => 'bg-light-danger',
                                        'In Progress' => 'bg-light-info',
                                        default => 'bg-light-secondary',
                                    };
                                @endphp
                                <div class="list-group-item px-2 py-2">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div class="flex-grow-1 min-w-0">
                                            <div class="small text-dark text-break">{{ $task['task'] ?? 'Task' }}</div>
                                            <div class="mt-1">
                                                <span class="badge {{ $badgeClass }}">{{ $tProgress }}</span>
                                                @if(!empty($task['period_label']))
                                                    <span class="badge bg-light-light text-dark">{{ $task['period_label'] }}</span>
                                                @endif
                                            </div>
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
                                                    <i class="ti ti-edit me-1"></i>Update
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
                            <i class="ti ti-checkup-list fs-1 text-muted"></i>
                            <p class="text-muted mb-0 mt-2">No action tasks need your attention right now.</p>
                            <a href="{{ route('action-logs.index') }}" class="btn btn-sm btn-outline-primary mt-2">
                                <i class="ti ti-eye me-1"></i>View Action Logs
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Action Task Update Modal -->
    <div class="modal fade" id="quickActionTaskModal" tabindex="-1" aria-labelledby="quickActionTaskModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="quickActionTaskForm" data-action-template="{{ route('action-logs.quick-update', ['document' => '__DOCUMENT__']) }}">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title" id="quickActionTaskModalLabel"><i class="ti ti-bolt me-2"></i>Quick Update</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="task_uid" id="quickTaskUid" value="">
                        <div class="mb-3">
                            <label class="form-label text-muted small mb-1">Task</label>
                            <div class="border rounded bg-light px-2 py-2 small text-dark" id="quickTaskLabel"></div>
                            <small class="text-muted" id="quickTaskPeriod"></small>
                        </div>
                        <div class="mb-3">
                            <label for="quickTaskProgress" class="form-label">Progress status <span class="text-danger">*</span></label>
                            <select class="form-select" name="progress" id="quickTaskProgress" required>
                                <option value="Not Started">Not Started</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Done">Done</option>
                            </select>
                        </div>
                        <div class="mb-0">
                            <label for="quickTaskNotes" class="form-label">Progress notes</label>
                            <textarea class="form-control" name="notes_progress" id="quickTaskNotes" rows="4" maxlength="4000" placeholder="What did you do this week? Any blockers or context?"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="quickTaskSaveBtn">
                            <i class="ti ti-check me-1"></i>Save update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalEl = document.getElementById('quickActionTaskModal');
            if (!modalEl) {
                return;
            }
            const form = document.getElementById('quickActionTaskForm');
            const actionTemplate = form.getAttribute('data-action-template');
            const labelEl = document.getElementById('quickTaskLabel');
            const periodEl = document.getElementById('quickTaskPeriod');
            const uidEl = document.getElementById('quickTaskUid');
            const progressEl = document.getElementById('quickTaskProgress');
            const notesEl = document.getElementById('quickTaskNotes');
            const saveBtn = document.getElementById('quickTaskSaveBtn');

            document.querySelectorAll('.js-quick-update-task').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const documentId = btn.getAttribute('data-document-id') || '';
                    form.setAttribute('action', actionTemplate.replace('__DOCUMENT__', documentId));
                    labelEl.textContent = btn.getAttribute('data-task') || 'Task';
                    periodEl.textContent = btn.getAttribute('data-period') || '';
                    uidEl.value = btn.getAttribute('data-task-uid') || '';
                    progressEl.value = btn.getAttribute('data-progress') || 'Not Started';
                    notesEl.value = btn.getAttribute('data-notes') || '';
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    }
                });
            });

            form.addEventListener('submit', function () {
                if (saveBtn) {
                    saveBtn.disabled = true;
                    saveBtn.innerHTML = '<i class="ti ti-loader me-1"></i>Saving...';
                }
            });
        });
    </script>

</x-app-layout>
