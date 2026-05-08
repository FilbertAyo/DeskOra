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
        $now = \Carbon\Carbon::now();
        $previousPeriod = ($currentPeriod ?? null) ? $currentPeriod->getPreviousPeriod() : null;
        $planGraceActive = false;
        $reportGraceActive = false;
        $planGraceEndsAt = null;
        $reportGraceEndsAt = null;
        $planGracePeriod = null;
        $reportGracePeriod = null;
        $resolveGraceWindow = function ($period, string $type) use ($now) {
            if (! $period || ! $period->isOpen()) {
                return null;
            }

            if ($type === 'plan') {
                $defaultCutoff = \Carbon\Carbon::parse($period->week_start_date)->subDays(2)->setTime(23, 59, 0);
                $currentCutoff = $period->getPlanDeadlineCutoff();
            } else {
                $defaultCutoff = \Carbon\Carbon::parse($period->week_start_date)->addDays(5)->setTime(23, 59, 0);
                $currentCutoff = $period->getReportDeadlineCutoff();
            }

            $isGraceActive = $currentCutoff->greaterThan($defaultCutoff)
                && $now->greaterThan($defaultCutoff)
                && $now->lessThanOrEqualTo($currentCutoff);

            if (! $isGraceActive) {
                return null;
            }

            return [
                'period' => $period,
                'ends_at' => $currentCutoff,
            ];
        };

        $planGraceCandidate = $resolveGraceWindow($currentPeriod ?? null, 'plan')
            ?? $resolveGraceWindow($nextPeriod ?? null, 'plan');
        if ($planGraceCandidate) {
            $planGraceActive = true;
            $planGraceEndsAt = $planGraceCandidate['ends_at'];
            $planGracePeriod = $planGraceCandidate['period'];
        }

        $reportGraceCandidate = $resolveGraceWindow($previousPeriod, 'report')
            ?? $resolveGraceWindow($currentPeriod ?? null, 'report');
        if ($reportGraceCandidate) {
            $reportGraceActive = true;
            $reportGraceEndsAt = $reportGraceCandidate['ends_at'];
            $reportGracePeriod = $reportGraceCandidate['period'];
        }
        $hasWeeklyGraceWindow = $planGraceActive || $reportGraceActive;
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

    @if($showWeeklyToast)
        <div class="position-fixed bottom-0 end-0 p-2 p-sm-3 d-flex flex-column gap-2 align-items-stretch dashboard-toast-stack"
             style="z-index: 1080; width: 100%; max-width: min(22rem, calc(100vw - 1rem)); box-sizing: border-box; pointer-events: none;">
            @include('components.weekly-submission-reminder-toast', ['fixedWrapper' => false, 'withScript' => false])
        </div>
    @endif
    

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
                <div class="alert alert-danger border shadow-sm d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-0">
                    <div>
                        <h6 class="alert-heading mb-1">
                            <i class="ti ti-alert-triangle me-1"></i>Year Plan Update Required
                        </h6>
                        <p class="mb-1">
                            Some {{ $planCoverageYear }} sub-department activities still have no planned month selected.
                        </p>
                        <p class="mb-0 small">
                            {{ number_format($planCoverageMissingCount) }} of {{ number_format($planCoverageTotalCount) }} scoped activities need at least one month (Jan-Dec) checked.
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
                    <h5 class="mb-0"><i class="ti ti-bell me-2"></i>My Notifications</h5>
                    <span class="badge bg-primary">
                        {{ ($returnedDocs->count() ?? 0) + ($plainDraftDocs->count() ?? 0) + $activeActionTaskCount + $sharedWithMeCount + $assignedWeeklyActivityCount }}
                    </span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6 col-xl-2">
                            <div class="p-3 border rounded h-100">
                                <p class="mb-1 text-muted small">
                                    <i class="ti ti-arrow-back-up text-danger me-1"></i>Returned for revision
                                </p>
                                <h4 class="mb-1 text-danger">{{ $returnedDocs->count() }}</h4>
                          </div>
                        </div>
                        <div class="col-6 col-xl-2">
                            <div class="p-3 border rounded h-100">
                                <p class="mb-1 text-muted small">
                                    <i class="ti ti-file-edit text-warning me-1"></i>Drafts waiting submission
                                </p>
                                <h4 class="mb-1 text-warning">{{ $plainDraftDocs->count() }}</h4>
                           </div>
                        </div>
                        <div class="col-6 col-xl-2">
                            <div class="p-3 border rounded h-100">
                                <p class="mb-1 text-muted small">
                                    <i class="ti ti-clipboard-list text-primary me-1"></i>Open action tasks
                                </p>
                                <h4 class="mb-1 text-primary">{{ $activeActionTaskCount }}</h4>
                           </div>
                        </div>
                        <div class="col-6 col-xl-4">
                            <div class="p-3 border rounded h-100">
                                <p class="mb-1 text-muted small">
                                    <i class="ti ti-users-group text-info me-1"></i>Shared weekly docs
                                </p>
                                <h4 class="mb-1 text-info">{{ $sharedWithMeCount }}</h4>
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
                                    <i class="ti ti-clipboard-list text-secondary me-1"></i>Assigned activities
                                </p>
                                <h4 class="mb-1 text-secondary">{{ $assignedWeeklyActivityCount }}</h4>
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
                            <p class="mb-1 fw-semibold"><i class="ti ti-clock-hour-4 me-1"></i>Grace period is active</p>
                            <ul class="mb-0 ps-3 small">
                                @if($planGraceActive && $planGraceEndsAt)
                                    <li>
                                        Weekly plan deadline was extended
                                        @if($planGracePeriod)
                                            for Week {{ $planGracePeriod->week_number }} of {{ $planGracePeriod->year }}
                                        @endif.
                                        You can still submit until {{ $planGraceEndsAt->format('M d, Y H:i') }}.
                                    </li>
                                @endif
                                @if($reportGraceActive && $reportGraceEndsAt)
                                    <li>
                                        Weekly report deadline was extended
                                        @if($reportGracePeriod)
                                            for Week {{ $reportGracePeriod->week_number }} of {{ $reportGracePeriod->year }}
                                        @endif.
                                        You can still submit until {{ $reportGraceEndsAt->format('M d, Y H:i') }}.
                                    </li>
                                @endif
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
                                        <a href="{{ route('documents.show', $event['document_id']) }}" class="text-decoration-none small d-block">
                                            {{ $event['message'] }}
                                        </a>
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
            <div class="row">
                <div class="col-6 col-lg-6 col-md-6">
                    <div class="card shadow-none border">
                        <div class="card-body">
                            <h6 class="mb-2 f-w-400 text-muted">Departments</h6>
                            <h4 class="mb-0">
                                {{ number_format($departmentNo ?? 0) }}
                                <span class="badge bg-light border border-secondary text-muted">
                                    <i class="ti ti-building"></i>
                                </span>
                            </h4>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-6 col-md-6">
                    <div class="card shadow-none border">
                        <div class="card-body">
                            <h6 class="mb-2 f-w-400 text-muted">Total Users</h6>
                            <h4 class="mb-0">
                                {{ number_format($userNo ?? 0) }}
                                <span class="badge bg-light-primary border border-primary text-primary">
                                    <i class="ti ti-users"></i>
                                </span>
                            </h4>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-6 col-md-6">
                    <div class="card shadow-none border">
                        <div class="card-body">
                            <h6 class="mb-2 f-w-400 text-muted">Your document drafts</h6>
                            <h4 class="mb-0">
                                {{ number_format($pendingDraftsTotal) }}
                                <span class="badge bg-light-warning border border-warning text-warning">
                                    <i class="ti ti-pencil"></i>
                                </span>
                            </h4>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-6 col-md-6">
                    <div class="card shadow-none border">
                        <div class="card-body">
                            <h6 class="mb-2 f-w-400 text-muted">Active work period</h6>
                            <h4 class="mb-0">
                                @if ($currentPeriod)
                                    <span class="fs-5">Week {{ $currentPeriod->week_number }}, {{ $currentPeriod->year }}</span>
                                    <span class="badge bg-light-success border border-success text-success">
                                        <i class="ti ti-calendar"></i>
                                    </span>
                                @else
                                    <span class="fs-5 text-muted">—</span>
                                    <span class="badge bg-light border border-secondary text-muted">
                                        <i class="ti ti-calendar"></i>
                                    </span>
                                @endif
                            </h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8 col-md-6 col-12 mb-3">
            <div class="card shadow-none border h-100">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="ti ti-checklist me-2"></i>My Focus Tasks</h5>
                    <small class="text-muted">What to do next</small>
                </div>
                <div class="card-body">
                    @if($activeActionTaskCount > 0)
                        <div class="mb-3">
                            <h6 class="text-primary mb-2"><i class="ti ti-clipboard-list me-1"></i>Action Log Tasks</h6>
                            <div class="border rounded p-3 bg-light-subtle">
                                <p class="mb-2 text-muted">
                                    {{ $activeActionTaskCount }} task{{ $activeActionTaskCount === 1 ? '' : 's' }} need update.
                                </p>
                                <div class="mb-2">
                                    @foreach($activeActionTasks->take(3) as $task)
                                        <div class="d-flex justify-content-between align-items-center border rounded bg-white px-2 py-1 mb-1">
                                            <span class="small text-dark">{{ $task['task'] ?? 'Task' }}</span>
                                            <span class="badge bg-secondary">{{ $task['latest_progress'] ?? 'Not Started' }}</span>
                                        </div>
                                    @endforeach
                                    @if($activeActionTaskCount > 3)
                                        <small class="text-muted">+ {{ $activeActionTaskCount - 3 }} more task{{ ($activeActionTaskCount - 3) === 1 ? '' : 's' }}</small>
                                    @endif
                                </div>
                                <div class="d-flex justify-content-end">
                                    @if($currentActionLogDraftId)
                                        <a href="{{ route('action-logs.edit', $currentActionLogDraftId) }}" class="btn btn-sm btn-primary">
                                            <i class="ti ti-edit me-1"></i>Update
                                        </a>
                                    @else
                                        <a href="{{ route('action-logs.index') }}" class="btn btn-sm btn-primary">
                                            <i class="ti ti-eye me-1"></i>View Action Logs
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif

                    @if($assignedWeeklyActivityCount > 0)
                        <div class="mb-3">
                            <h6 class="text-secondary mb-2"><i class="ti ti-clipboard-list me-1"></i>Assigned Weekly Activities</h6>
                            @foreach($assignedWeeklyActivities->take(3) as $assignment)
                                @php
                                    $payload = is_array($assignment->activity_payload ?? null) ? $assignment->activity_payload : [];
                                    $isLockedAssignment = ($assignment->status ?? '') === \App\Models\WeeklyActivityAssignment::STATUS_LOCKED;
                                @endphp
                                <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
                                    <div>
                                        <strong>{{ $payload['activity'] ?? 'Assigned activity' }}</strong>
                                        <div class="small text-muted">
                                            {{ $payload['day'] ?? 'Day not set' }} • From {{ $assignment->sourceUser->name ?? 'Team member' }}
                                            @if(!empty($payload['location']))
                                                • {{ $payload['location'] }}
                                            @endif
                                        </div>
                                        <span class="badge {{ $isLockedAssignment ? 'bg-success' : 'bg-warning text-dark' }} mt-1">
                                            {{ $isLockedAssignment ? 'Locked' : 'Preview' }}
                                        </span>
                                    </div>
                                    <a href="{{ route('documents.show', $assignment->source_document_id) }}" class="btn btn-sm btn-outline-secondary">Open Plan</a>
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

                    @if($activeActionTaskCount === 0 && $assignedWeeklyActivityCount === 0 && $returnedDocs->isEmpty() && $plainDraftDocs->isEmpty() && $recentReviewerReturns->isEmpty())
                        <div class="text-center py-4">
                            <i class="ti ti-mood-smile fs-1 text-success"></i>
                            <p class="text-muted mt-2 mb-0">Great flow. No pending revisions or drafts right now.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>


        <div class="col-xl-4 col-md-6 col-12">
            <div class="card shadow-none border h-100">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="ti ti-bolt me-2"></i>Quick Shortcuts</h5>
                    <small class="text-muted">Personalized actions</small>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('documents.index') }}" class="btn btn-light text-start">
                            <i class="ti ti-folders me-2 text-primary"></i>My Documents Hub
                        </a>
                        @if($showWeeklyPlanShortcut)
                            <a href="{{ route('documents.create', array_filter(['type' => 'weekly_plan', 'period_id' => $nextPeriod->id ?? null])) }}" class="btn btn-light text-start">
                                <i class="ti ti-calendar-plus me-2 text-info"></i>Create Weekly Plan
                            </a>
                        @elseif(isset($availableTypes['weekly_plan']) && ($nextPeriod ?? null))
                            <button type="button" class="btn btn-light text-start" disabled title="Plan deadline has passed">
                                <i class="ti ti-calendar-off me-2 text-muted"></i>Weekly plan closed for this window
                            </button>
                        @endif
                        @if($showWeeklyReportShortcut)
                            <a href="{{ route('documents.create', array_filter(['type' => 'weekly_report', 'period_id' => $currentPeriod->id ?? null])) }}" class="btn btn-light text-start">
                                <i class="ti ti-file-analytics me-2 text-success"></i>Create Weekly Report
                            </a>
                        @elseif(isset($availableTypes['weekly_report']) && ($currentPeriod ?? null))
                            <button type="button" class="btn btn-light text-start" disabled title="Report deadline has passed">
                                <i class="ti ti-file-minus me-2 text-muted"></i>Weekly report closed for this window
                            </button>
                        @endif
                    
                        <a href="{{ route('profile.edit') }}" class="btn btn-light text-start">
                            <i class="ti ti-user me-2 text-secondary"></i>My Profile Settings
                        </a>
                        @if($hasReviewerAccess)
                            <a href="{{ route('documents.reviewer-dashboard') }}" class="btn btn-light text-start">
                                <i class="ti ti-eye-check me-2 text-danger"></i>Reviewer Dashboard
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

      
       
    </div>

    @if($showWeeklyToast)
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof bootstrap === 'undefined' || !bootstrap.Toast) return;

                var weeklyEl = document.getElementById('weeklySubmissionReminderToast');
                if (weeklyEl) {
                    new bootstrap.Toast(weeklyEl, { autohide: false }).show();
                }
            });
        </script>
    @endif
</x-app-layout>
