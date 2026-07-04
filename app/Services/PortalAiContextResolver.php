<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Request as FinanceRequest;
use App\Models\User;
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
                'finance_requests' => $this->financeRequestsSection($user),
                'approvals' => $this->approvalsSection($user),
                'action_logs' => $this->actionLogsSection($user),
                'documents' => $this->documentsSection($user),
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

        if ($domains === []) {
            $matches = $this->navigationIntelligence->findByIntent($question, $user);
            if ($matches !== []) {
                $topId = (string) ($matches[0]['id'] ?? '');
                $domainMap = config('ai-assistant.navigation_entry_domains', []);
                if (isset($domainMap[$topId])) {
                    $domains[] = $domainMap[$topId];
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

    private function financeRequestsSection(User $user): string
    {
        if (! $user->can('request pettycash')) {
            return '';
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
        $counts = $this->approvalNavService->countsFor($user);

        if (($counts['total'] ?? 0) === 0
            && ! $user->canAny([
                'first pettycash approval',
                'last pettycash approval',
                'review staff requisition',
                'approve staff requisition',
                'authorize staff requisition',
                'review requests',
                'approve requests',
                'authorize requests',
            ])) {
            return '';
        }

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
}
