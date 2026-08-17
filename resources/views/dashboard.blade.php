<x-app-layout>
    @php
        $dashboardData = $weeklyDashboardData ?? null;
        $deadlineStatuses = $dashboardData['deadlineStatuses'] ?? ($periodDeadlineStatuses ?? null);
        $availableTypes = $dashboardData['availableTypes'] ?? ($dashboardAvailableTypes ?? []);
        $activeActionTasks = collect($actionLogProgress['activeTasks'] ?? [])
            ->filter(function ($task) {
                if (! is_array($task)) {
                    return false;
                }
                $latestProgress = strtolower(trim((string) ($task['latest_progress'] ?? 'Not Started')));

                return ! in_array($latestProgress, ['done', 'cancelled'], true);
            })
            ->values();
        $activeActionTaskCount = $activeActionTasks->count();
        $assignedWeeklyActivities = collect($assignedWeeklyActivities ?? []);
        $assignedWeeklyActivityCount = $assignedWeeklyActivities->count();
        $sharedWithMeCount = (int) ($sharedWithMeCount ?? 0);
        $collaborationEvents = collect($collaborationEvents ?? []);
        $approvalActions = collect($approvalActions ?? []);
        $wr = $weeklySubmissionReminder ?? null;
        $showWeeklyToast = $wr?->showToast ?? false;
        $planGraceEntries = collect($weeklyPlanGraceEntries ?? []);
        $reportGraceEntries = collect($weeklyReportGraceEntries ?? []);
        $hasWeeklyGraceWindow = $planGraceEntries->isNotEmpty() || $reportGraceEntries->isNotEmpty();
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
        $hasSubmissionAlerts = ($weeklySubmissionReminder && count($weeklySubmissionReminder->missedMessages) > 0)
            || $hasWeeklyGraceWindow;
    @endphp

    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">

    <div class="dashboard">
        @include('dashboard.partials.header')

        @include('dashboard.partials.plan-coverage-alert')

        <div class="dashboard-body">
            <div class="dashboard-col dashboard-col--main">
                <div class="dashboard-slot dashboard-slot--stats">
                    @include('dashboard.partials.stats')
                </div>
                <div class="dashboard-slot dashboard-slot--work-log">
                    @include('dashboard.partials.work-log')
                </div>
                <div class="dashboard-slot dashboard-slot--performance">
                    @include('dashboard.partials.performance-chart')
                </div>

            
                @if($hasSubmissionAlerts)
                    <div class="dashboard-slot dashboard-slot--alerts">
                        <div class="dashboard-alerts">
                            @include('dashboard.partials.submission-alerts')
                        </div>
                    </div>
                @endif

                <div class="dashboard-slot dashboard-slot--activities">
                    @include('dashboard.partials.weekly-activities')
                </div>
            </div>

            <div class="dashboard-col dashboard-col--side">
                <div class="dashboard-slot dashboard-slot--action">
                    @include('dashboard.partials.action-needed')
                </div>

                <div class="dashboard-slot dashboard-slot--scorecard">
                    @include('dashboard.partials.scorecard')
                </div>

                <div class="dashboard-slot dashboard-slot--tasks">
                    @include('dashboard.partials.action-tasks')
                </div>
            </div>
        </div>
    </div>

    @include('action-logs.partials.quick-update-modal', [
        'modalId' => 'quickActionTaskModal',
        'formId' => 'quickActionTaskForm',
        'triggerClass' => 'js-quick-update-task',
        'teamUsers' => $teamUsers ?? collect(),
    ])

    @include('dashboard.partials.work-log-modal')
    @include('dashboard.partials.matrix-modal')
</x-app-layout>
