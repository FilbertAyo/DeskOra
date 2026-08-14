<?php

namespace App\Services;

use App\Models\User;
use App\Models\WeeklyWorkLog;
use App\Models\WorkPeriod;
use App\Notifications\DailyDigestNotification;
use App\Services\Documents\WeeklyBudgetReviewService;
use Illuminate\Support\Facades\Log;

class DailyDigestService
{
    public function __construct(
        private readonly WeeklySubmissionReminderService $weeklySubmissionReminderService,
        private readonly ActionLogProgressService $actionLogProgressService,
        private readonly RequestApprovalNavService $requestApprovalNavService,
        private readonly WeeklyBudgetReviewService $weeklyBudgetReviewService,
        private readonly WeeklyScorecardService $weeklyScorecardService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildPayload(User $user): array
    {
        $reminder = $this->weeklySubmissionReminderService->forUser($user);
        $actionProgress = $this->actionLogProgressService->buildForUser($user);
        $openActions = collect($actionProgress['activeTasks'] ?? [])
            ->filter(function ($task) {
                if (! is_array($task)) {
                    return false;
                }
                $progress = strtolower(trim((string) ($task['latest_progress'] ?? 'Not Started')));

                return ! in_array($progress, ['done', 'cancelled'], true);
            })
            ->count();

        $approvalTotal = $this->requestApprovalNavService->countsFor($user)['total'] ?? 0;
        $budgetCounts = $this->weeklyBudgetReviewService->actionableStageCountsFor($user);
        $budgetTotal = ($budgetCounts['review'] ?? 0) + ($budgetCounts['approve'] ?? 0) + ($budgetCounts['authorize'] ?? 0);
        $scorecard = $this->weeklyScorecardService->forUser($user);
        $period = WorkPeriod::getCurrent();
        $loggedToday = $period
            ? WeeklyWorkLog::query()
                ->where('user_id', $user->id)
                ->where('period_id', $period->id)
                ->whereDate('log_date', now()->toDateString())
                ->exists()
            : false;

        $lines = [];
        if (! $loggedToday) {
            $lines[] = 'You have not logged today’s work yet.';
        }
        if ($openActions > 0) {
            $lines[] = $openActions.' open action '.($openActions === 1 ? 'task' : 'tasks').' need attention.';
        }
        if ($approvalTotal > 0) {
            $lines[] = $approvalTotal.' finance approval '.($approvalTotal === 1 ? 'item' : 'items').' waiting.';
        }
        if ($budgetTotal > 0) {
            $lines[] = $budgetTotal.' weekly budget '.($budgetTotal === 1 ? 'item' : 'items').' need your review.';
        }
        if ($reminder?->plan?->showCta) {
            $lines[] = 'Weekly plan still needs your attention.';
        }
        if ($reminder?->report?->showCta) {
            $lines[] = 'Weekly report still needs your attention.';
        }
        if ($scorecard['plan_submitted'] && ! $scorecard['report_submitted'] && $scorecard['work_log_days'] > 0) {
            $lines[] = 'You have work logs ready to help draft this week’s report.';
        }

        return [
            'lines' => $lines,
            'open_actions' => $openActions,
            'approval_total' => $approvalTotal,
            'budget_total' => $budgetTotal,
            'logged_today' => $loggedToday,
            'dashboard_url' => route('dashboard'),
            'work_log_url' => route('dashboard'),
        ];
    }

    public function sendToUser(User $user): void
    {
        $payload = $this->buildPayload($user);
        if (($payload['lines'] ?? []) === []) {
            return;
        }

        try {
            $user->notify(new DailyDigestNotification($payload));
        } catch (\Throwable $e) {
            Log::error('DailyDigestService: failed to notify user', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function sendToAllActiveUsers(): int
    {
        $sent = 0;
        User::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->chunkById(100, function ($users) use (&$sent) {
                foreach ($users as $user) {
                    $this->sendToUser($user);
                    $sent++;
                }
            });

        return $sent;
    }
}
