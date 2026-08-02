<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Request as FinanceRequest;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Support\ActionLogTaskSupport;
use Illuminate\Support\Facades\Schema;

class ExecutivePulseService
{
    public function __construct(
        private readonly RequestApprovalNavService $financeApprovals,
        private readonly WeeklySubmissionWindowService $submissionWindow,
        private readonly WorkPeriodService $workPeriodService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(?User $viewer = null): array
    {
        $current = WorkPeriod::getCurrent();
        $next = $current?->getNextPeriod();
        $deadlines = $this->workPeriodService->deadlineStatusesForUi($current, $next);

        $reportPeriod = $current;
        $planPeriod = $next ?? $current;

        $activeStaff = User::query()
            ->where('status', 'active')
            ->count();

        $reportSubmitted = 0;
        $reportOwing = 0;
        $planSubmitted = 0;
        $planOwing = 0;

        if ($reportPeriod && Schema::hasTable('weekly_reports')) {
            $reportSubmitted = Document::query()
                ->weeklyReports()
                ->submitted()
                ->where('period_id', $reportPeriod->id)
                ->count();
            $reportOwing = $this->submissionWindow
                ->staffOwingWeeklyDocument($reportPeriod, Document::TYPE_WEEKLY_REPORT)
                ->count();
        }

        if ($planPeriod && Schema::hasTable('weekly_plans')) {
            $planSubmitted = Document::query()
                ->weeklyPlans()
                ->submitted()
                ->where('period_id', $planPeriod->id)
                ->count();
            $planOwing = $this->submissionWindow
                ->staffOwingWeeklyDocument($planPeriod, Document::TYPE_WEEKLY_PLAN)
                ->count();
        }

        $reportDenom = max(1, $reportSubmitted + $reportOwing);
        $planDenom = max(1, $planSubmitted + $planOwing);

        $viewerFinanceTotal = 0;
        if ($viewer) {
            $viewerFinanceTotal = (int) ($this->financeApprovals->countsFor($viewer)['total'] ?? 0);
        }

        $openApprovals = 0;
        if (Schema::hasTable('requests')) {
            $openApprovals = FinanceRequest::query()
                ->whereIn('status', FinanceRequest::OPEN_APPROVAL_STATUSES)
                ->count();
        }

        $actionTasks = $this->actionTaskStats($current);
        $budgetPending = 0;
        if (method_exists(Document::class, 'countPendingWeeklyBudgetReviewForPeriod')) {
            $budgetPending = Document::countPendingWeeklyBudgetReviewForPeriod(null);
        }

        return [
            'generated_at' => now(),
            'current_period' => $current,
            'plan_period' => $planPeriod,
            'report_period' => $reportPeriod,
            'active_staff' => $activeStaff,
            'deadlines' => $deadlines,
            'reports' => [
                'submitted' => $reportSubmitted,
                'owing' => $reportOwing,
                'percent' => (int) round(($reportSubmitted / $reportDenom) * 100),
                'period_label' => $reportPeriod
                    ? 'Week '.$reportPeriod->week_number.' of '.$reportPeriod->year
                    : 'N/A',
            ],
            'plans' => [
                'submitted' => $planSubmitted,
                'owing' => $planOwing,
                'percent' => (int) round(($planSubmitted / $planDenom) * 100),
                'period_label' => $planPeriod
                    ? 'Week '.$planPeriod->week_number.' of '.$planPeriod->year
                    : 'N/A',
            ],
            'approvals' => [
                'open_finance_requests' => $openApprovals,
                'viewer_pending_total' => $viewerFinanceTotal,
                'budget_pending_org' => $budgetPending,
            ],
            'action_tasks' => $actionTasks,
            'highlights' => $this->buildHighlights(
                $reportSubmitted,
                $reportOwing,
                $planSubmitted,
                $planOwing,
                $openApprovals,
                $budgetPending,
                $actionTasks
            ),
        ];
    }

    /**
     * @return array{active: int, needs_update: int, overdue: int}
     */
    private function actionTaskStats(?WorkPeriod $period): array
    {
        $active = 0;
        $needsUpdate = 0;
        $overdue = 0;

        if (! $period) {
            return [
                'active' => 0,
                'needs_update' => 0,
                'overdue' => 0,
            ];
        }

        $document = Document::findCanonicalActionLogForPeriod((string) $period->id);
        if (! $document) {
            return [
                'active' => 0,
                'needs_update' => 0,
                'overdue' => 0,
            ];
        }

        foreach (ActionLogTaskSupport::extractTasks($document->content) as $task) {
            if (! is_array($task)) {
                continue;
            }
            $progress = trim((string) ($task['progress'] ?? ActionLogTaskSupport::PROGRESS_NOT_STARTED));
            if (in_array($progress, [ActionLogTaskSupport::PROGRESS_DONE, ActionLogTaskSupport::PROGRESS_CANCELLED], true)) {
                continue;
            }
            $active++;
            if (trim((string) ($task['notes_progress'] ?? '')) === '') {
                $needsUpdate++;
            }
            $due = trim((string) ($task['due_date'] ?? ''));
            if ($due !== '' && strtotime($due) !== false && strtotime($due) < strtotime('today')) {
                $overdue++;
            }
        }

        return [
            'active' => $active,
            'needs_update' => $needsUpdate,
            'overdue' => $overdue,
        ];
    }

    /**
     * @param  array{active: int, needs_update: int, overdue: int}  $actionTasks
     * @return list<string>
     */
    private function buildHighlights(
        int $reportSubmitted,
        int $reportOwing,
        int $planSubmitted,
        int $planOwing,
        int $openApprovals,
        int $budgetPending,
        array $actionTasks
    ): array {
        $items = [];

        if ($reportOwing === 0 && $reportSubmitted > 0) {
            $items[] = 'All tracked weekly reports for the current period are submitted.';
        } elseif ($reportOwing > 0) {
            $items[] = $reportOwing.' staff still owe a weekly report.';
        }

        if ($planOwing > 0) {
            $items[] = $planOwing.' staff still owe a weekly plan for the upcoming period.';
        }

        if ($openApprovals > 0) {
            $items[] = $openApprovals.' finance requests are still in the approval pipeline.';
        }

        if ($budgetPending > 0) {
            $items[] = $budgetPending.' weekly plans have budget items awaiting review.';
        }

        if (($actionTasks['overdue'] ?? 0) > 0) {
            $items[] = $actionTasks['overdue'].' action-log tasks are past their due date.';
        } elseif (($actionTasks['needs_update'] ?? 0) > 0) {
            $items[] = $actionTasks['needs_update'].' active action-log tasks still need a progress note.';
        }

        if ($items === []) {
            $items[] = 'No major operational blockers detected for this snapshot.';
        }

        return array_slice($items, 0, 6);
    }
}
