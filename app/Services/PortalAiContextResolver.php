<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Document;
use App\Models\Request as FinanceRequest;
use App\Models\User;
use App\Models\WeeklyMatrix;
use App\Models\YearPlan;
use Illuminate\Support\Str;

class PortalAiContextResolver
{
    public function __construct(
        private readonly WorkPeriodService $workPeriodService,
        private readonly RequestApprovalNavService $approvalNavService,
        private readonly ActionLogProgressService $actionLogProgressService,
        private readonly PortalNavigationIntelligenceService $navigationIntelligence,
    ) {}

    /**
     * @return array{domains: array<int, string>, snapshot: string, has_live_data: bool}
     */
    public function resolve(User $user, string $question): array
    {
        if (! $this->questionNeedsLiveData($question)) {
            return [
                'domains' => [],
                'snapshot' => '',
                'has_live_data' => false,
            ];
        }

        $domains = $this->detectDomains($user, $question);
        $sections = [];

        foreach ($domains as $domain) {
            $section = match ($domain) {
                'weekly_submissions' => $this->weeklySubmissionsSection($user),
                'year_plans' => $this->yearPlansSection($user),
                'weekly_matrix' => $this->weeklyMatrixSection($user),
                'finance_requests' => $this->financeRequestsSection($user),
                'approvals' => $this->approvalsSection($user),
                'action_logs' => $this->actionLogsSection($user),
                'documents' => $this->documentsSection($user),
                'system_stats' => $this->systemStatsSection($user),
                default => null,
            };

            if ($section !== null && $section !== '') {
                $sections[] = $section;
            }
        }

        $snapshot = implode("\n\n", $sections);

        return [
            'domains' => $domains,
            'snapshot' => $snapshot,
            'has_live_data' => $snapshot !== '',
        ];
    }

    private function questionNeedsLiveData(string $question): bool
    {
        $normalized = Str::lower(trim($question));

        if ($normalized === '') {
            return false;
        }

        $isNavigationQuestion = (bool) preg_match(
            '/\b(how do i|how to|where (can|do|is|should)|where can i|what is the difference|navigate|go to|open the|find the page|show me how)\b/',
            $normalized
        );

        $isDataQuestion = (bool) preg_match(
            '/\b(my|mine|status|pending|submitted|have i|did i|how many|count|waiting|draft|not submitted|already submitted|due|deadline|overdue|track my|shared with me|shared documents)\b/',
            $normalized
        );

        if ($isNavigationQuestion && ! $isDataQuestion) {
            return false;
        }

        return $isDataQuestion || $this->detectDomains(null, $question) !== [];
    }

    /**
     * @return array<int, string>
     */
    private function detectDomains(?User $user, string $question): array
    {
        $normalized = Str::lower(trim($question));
        $domains = [];

        foreach (config('ai-assistant.context_domains', []) as $domain => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($normalized, Str::lower((string) $keyword))) {
                    $domains[] = $domain;
                    break;
                }
            }
        }

        $domainMap = config('ai-assistant.navigation_entry_domains', []);

        if ($domains === []) {
            $matches = $this->navigationIntelligence->findByIntent($question, $user);
            if ($matches !== []) {
                $topId = (string) ($matches[0]['id'] ?? '');
                if (isset($domainMap[$topId])) {
                    $domains[] = $domainMap[$topId];
                }
            }
        }

        // Broaden: an explicit data-style question ("how many", "count", "list of", "status")
        // that still has no domain is mapped to the best-matching navigation entry's domain.
        $isDataStyle = (bool) preg_match(
            '/\b(how many|number of|count|total|list of|status|pending|overdue|due|my|mine|have i|did i)\b/',
            $normalized
        );

        if ($domains === [] && $isDataStyle) {
            foreach ($this->navigationIntelligence->findByIntent($question, $user) as $match) {
                $entryId = (string) ($match['id'] ?? '');
                if (isset($domainMap[$entryId])) {
                    $domains[] = $domainMap[$entryId];
                    break;
                }
            }
        }

        if ($domains === [] && preg_match('/\b(my|mine|status|have i|did i)\b/', $normalized)) {
            $domains[] = 'weekly_submissions';
        }

        return array_values(array_unique($domains));
    }

    private function weeklySubmissionsSection(User $user): string
    {
        $currentPeriod = $this->workPeriodService->periodForWeeklyReportSubmission();
        $nextPeriod = $this->workPeriodService->periodForWeeklyPlanSubmission();
        $deadlines = $this->workPeriodService->deadlineStatusesForUi($currentPeriod, $nextPeriod);

        $lines = ['Weekly submissions for this user:'];

        if ($nextPeriod) {
            $plan = Document::findWeeklyPlanForPeriodOwner((string) $nextPeriod->id, (string) $user->id);
            $planStatus = $plan ? ($plan->isSubmitted() ? 'submitted' : 'draft') : 'not started';
            $lines[] = sprintf(
                'Plan for Week %s of %s (upcoming): %s',
                $nextPeriod->week_number,
                $nextPeriod->year,
                $planStatus
            );

            if (! empty($deadlines['plan_text'])) {
                $lines[] = 'Plan deadline status: '.$deadlines['plan_text'];
            }
        }

        if ($currentPeriod) {
            $report = Document::findWeeklyReportForPeriodOwner((string) $currentPeriod->id, (string) $user->id);
            $reportStatus = $report ? ($report->isSubmitted() ? 'submitted' : 'draft') : 'not started';
            $lines[] = sprintf(
                'Report for Week %s of %s (current): %s',
                $currentPeriod->week_number,
                $currentPeriod->year,
                $reportStatus
            );

            if (! empty($deadlines['report_text'])) {
                $lines[] = 'Report deadline status: '.$deadlines['report_text'];
            }
        }

        $pendingDraftCounts = Document::query()
            ->forOwnerSharedOrPlanCollaboratorOnWeeklyReport((string) $user->id)
            ->drafts()
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $lines[] = sprintf(
            'Draft documents: weekly plans=%d, weekly reports=%d, monthly reports=%d',
            (int) ($pendingDraftCounts[Document::TYPE_WEEKLY_PLAN] ?? 0),
            (int) ($pendingDraftCounts[Document::TYPE_WEEKLY_REPORT] ?? 0),
            (int) ($pendingDraftCounts[Document::TYPE_MONTHLY_REPORT] ?? 0)
        );

        return implode("\n", $lines);
    }

    private function yearPlansSection(User $user): string
    {
        $currentYear = (int) date('Y');
        $currentMonth = (int) date('n');
        $monthLabels = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
            7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
        ];
        $lines = ['Year plan information for this user:'];

        if ($user->can('manage year plans')) {
            $plans = YearPlan::query()
                ->orderByDesc('year')
                ->limit(3)
                ->withCount('activities')
                ->get();

            if ($plans->isEmpty()) {
                $lines[] = 'No year plans exist yet.';
            } else {
                $lines[] = 'Year plans on file:';
                foreach ($plans as $plan) {
                    $lines[] = sprintf('- %d: %d activities', $plan->year, $plan->activities_count);
                }
            }
        }

        $plannedActivities = MonthlyReportYearPlanActivities::plannedForUserMonth($user, $currentYear, $currentMonth);

        if ($plannedActivities->isEmpty()) {
            $lines[] = sprintf(
                'No year plan activities scheduled for user sub-departments in %s %d.',
                $monthLabels[$currentMonth] ?? 'this month',
                $currentYear
            );
        } else {
            $lines[] = sprintf(
                'Planned activities for user sub-departments in %s %d:',
                $monthLabels[$currentMonth] ?? 'this month',
                $currentYear
            );

            foreach ($plannedActivities->take(5) as $activity) {
                $lines[] = sprintf(
                    '- %s (%s): %s',
                    $activity->displayCode(),
                    $activity->department?->name ?? 'Unknown sub-department',
                    Str::limit((string) $activity->description, 80)
                );
            }

            if ($plannedActivities->count() > 5) {
                $lines[] = sprintf('... and %d more planned activities.', $plannedActivities->count() - 5);
            }
        }

        return implode("\n", $lines);
    }

    private function weeklyMatrixSection(User $user): string
    {
        $period = $this->workPeriodService->periodForWeeklyReportSubmission();

        if ($period === null) {
            return 'Weekly matrix: no open work period is available right now.';
        }

        $subDeptIds = MonthlyReportYearPlanActivities::subDepartmentIdsForUser($user);
        $lines = [
            sprintf('Weekly matrix for Week %s of %s:', $period->week_number, $period->year),
        ];

        if ($subDeptIds === []) {
            $lines[] = 'User has no linked sub-departments, so no weekly matrix applies to them directly.';

            return implode("\n", $lines);
        }

        $matrices = WeeklyMatrix::query()
            ->where('period_id', $period->id)
            ->whereIn('department_id', $subDeptIds)
            ->with(['department', 'entries.yearPlanActivities'])
            ->get()
            ->keyBy(fn (WeeklyMatrix $matrix) => (string) $matrix->department_id);

        foreach ($subDeptIds as $subDeptId) {
            $department = Department::query()->find($subDeptId);
            $departmentName = $department?->name ?? 'Unknown sub-department';
            $matrix = $matrices->get($subDeptId);

            if ($matrix === null) {
                $lines[] = sprintf('- %s: no matrix submitted for this week yet', $departmentName);

                continue;
            }

            $entryCount = $matrix->entries->count();
            $lines[] = sprintf('- %s: %d %s', $departmentName, $entryCount, Str::plural('entry', $entryCount));

            foreach ($matrix->entries->take(3) as $entry) {
                $parts = [];

                if (filled($entry->key_activity)) {
                    $parts[] = 'Activity: '.Str::limit((string) $entry->key_activity, 60);
                }

                if (filled($entry->impact)) {
                    $parts[] = 'Impact: '.Str::limit((string) $entry->impact, 80);
                }

                $yearPlanLinkCount = $entry->yearPlanActivities->count();
                if ($yearPlanLinkCount > 0) {
                    $parts[] = sprintf('%d year plan link(s)', $yearPlanLinkCount);
                }

                if ($parts !== []) {
                    $lines[] = '  · '.implode('; ', $parts);
                }
            }

            if ($entryCount > 3) {
                $lines[] = sprintf('  · ... and %d more entries', $entryCount - 3);
            }
        }

        return implode("\n", $lines);
    }

    private function financeRequestsSection(User $user): string
    {
        if (! $user->can('request pettycash')) {
            return 'ACCESS NOTE: This user does not have permission to raise or view petty cash and requisition requests.';
        }

        $statusCounts = FinanceRequest::query()
            ->where('user_id', $user->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $lines = ['Finance requests for this user:'];
        $lines[] = sprintf(
            'Totals by status: pending=%d, approved=%d, paid=%d, rejected=%d, resubmission=%d',
            (int) ($statusCounts['pending'] ?? 0),
            (int) ($statusCounts['approved'] ?? 0),
            (int) ($statusCounts['paid'] ?? 0),
            (int) ($statusCounts['rejected'] ?? 0),
            (int) ($statusCounts['resubmission'] ?? 0)
        );

        $recentLimit = max(1, (int) config('ai-assistant.context_snapshot_max_requests', 5));
        $recentRequests = FinanceRequest::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit($recentLimit)
            ->get(['request_no', 'request_type', 'status', 'created_at']);

        if ($recentRequests->isNotEmpty()) {
            $lines[] = 'Recent requests:';
            foreach ($recentRequests as $request) {
                $lines[] = sprintf(
                    '- %s (%s): %s on %s',
                    $request->code,
                    $request->request_type,
                    $request->status,
                    optional($request->created_at)->format('Y-m-d') ?? 'unknown date'
                );
            }
        }

        return implode("\n", $lines);
    }

    private function approvalsSection(User $user): string
    {
        if (! $user->canAny([
            'first pettycash approval',
            'last pettycash approval',
            'review staff requisition',
            'approve staff requisition',
            'authorize staff requisition',
            'review requests',
            'approve requests',
            'authorize requests',
        ])) {
            return 'ACCESS NOTE: This user is not an approver, so they have no approval queue.';
        }

        $counts = $this->approvalNavService->countsFor($user);

        return implode("\n", [
            'Approval queue counts for this user:',
            sprintf('Review=%d, Approve=%d, Authorize=%d, Total=%d', $counts['review'], $counts['approve'], $counts['authorize'], $counts['total']),
        ]);
    }

    private function actionLogsSection(User $user): string
    {
        $progress = $this->actionLogProgressService->buildForUser($user);
        $activeTasks = collect($progress['activeTasks'] ?? []);
        $taskLimit = max(1, (int) config('ai-assistant.context_snapshot_max_tasks', 5));

        $lines = ['Action log tasks for this user:'];
        $lines[] = 'Active assigned tasks: '.$activeTasks->count();

        $needsUpdateCount = $activeTasks->where('needs_update', true)->count();
        if ($needsUpdateCount > 0) {
            $lines[] = 'Tasks needing an update this period: '.$needsUpdateCount;
        }

        $previewTasks = $activeTasks->take($taskLimit);
        if ($previewTasks->isNotEmpty()) {
            $lines[] = 'Task preview:';
            foreach ($previewTasks as $task) {
                $lines[] = sprintf(
                    '- %s (%s)%s',
                    $task['task'] ?? 'Untitled task',
                    $task['latest_progress'] ?? 'Unknown',
                    ! empty($task['needs_update']) ? ', needs update' : ''
                );
            }
        }

        return implode("\n", $lines);
    }

    private function documentsSection(User $user): string
    {
        $sharedWithMeCount = Document::query()
            ->whereIn('type', [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT])
            ->forOwnerSharedOrPlanCollaboratorOnWeeklyReport((string) $user->id)
            ->where('user_id', '<>', $user->id)
            ->count();

        $ownedSubmittedCount = Document::query()
            ->forUser((string) $user->id)
            ->submitted()
            ->count();

        return implode("\n", [
            'Documents for this user:',
            'Submitted documents owned by user: '.$ownedSubmittedCount,
            'Weekly plans/reports shared with user (not owned): '.$sharedWithMeCount,
        ]);
    }

    private function systemStatsSection(User $user): string
    {
        $permission = (string) (config('ai-assistant.context_domain_permissions.system_stats') ?? 'users management settings');

        if (! $user->can($permission)) {
            return 'ACCESS NOTE: This user does not have permission to view system-wide user or department statistics.';
        }

        $totalUsers = User::count();
        $activeUsers = User::active()->count();
        $inactiveUsers = max(0, $totalUsers - $activeUsers);
        $activeTopDepartments = Department::query()
            ->where('status', 'active')
            ->whereNull('parent_id')
            ->count();

        return implode("\n", [
            'System statistics:',
            'Total users: '.$totalUsers,
            'Active users: '.$activeUsers,
            'Inactive users: '.$inactiveUsers,
            'Active top-level departments: '.$activeTopDepartments,
        ]);
    }
}
