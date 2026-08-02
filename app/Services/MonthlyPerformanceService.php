<?php

namespace App\Services;

use App\Models\ActionLogTaskHistory;
use App\Models\Document;
use App\Models\User;
use App\Models\WeeklyPlanBudgetReview;
use App\Models\WeeklyWorkLog;
use App\Models\WorkPeriod;
use App\Services\Documents\WeeklyBudgetReviewService;
use App\Support\ActionLogTaskSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MonthlyPerformanceService
{
    public function __construct(
        private readonly ActionLogProgressService $actionLogProgressService,
        private readonly RequestApprovalNavService $requestApprovalNavService,
        private readonly WeeklyBudgetReviewService $weeklyBudgetReviewService,
        private readonly WeeklyScorecardService $weeklyScorecardService,
    ) {}

    /**
     * @return array{
     *     categories: list<string>,
     *     series: list<array{key: string, name: string, data: list<int|null>}>,
     *     tips: list<string>,
     *     overall_score: int,
     *     pending_reviews: int,
     *     month_details: list<array<string, mixed>>
     * }
     */
    public function forUser(User $user, int $months = 6): array
    {
        $months = max(1, min(12, $months));
        $canPlan = $user->can('create weekly plan');
        $canReport = $user->can('create weekly report');
        $canReview = $this->userCanReview($user);

        $categories = [];
        $monthDetails = [];
        $planRates = [];
        $reportRates = [];
        $logRates = [];
        $actionRates = [];
        $reviewRates = [];

        $currentMonthKey = now()->format('Y-m');

        for ($i = $months - 1; $i >= 0; $i--) {
            $monthStart = now()->startOfMonth()->subMonths($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            $label = $monthStart->format('M');
            $categories[] = $label;

            $periods = $this->periodsOverlapping($monthStart, $monthEnd);
            $detail = $this->scoreMonth($user, $periods, $monthStart, $monthEnd, $canPlan, $canReport, $canReview);
            $detail['label'] = $label;
            $detail['month_key'] = $monthStart->format('Y-m');
            $detail['is_current'] = $detail['month_key'] === $currentMonthKey;
            $monthDetails[] = $detail;

            if ($canPlan) {
                $planRates[] = $detail['plans_rate'];
            }
            if ($canReport) {
                $reportRates[] = $detail['reports_rate'];
            }
            $logRates[] = $detail['logs_rate'];
            $actionRates[] = $detail['actions_rate'];
            if ($canReview) {
                $reviewRates[] = $detail['reviews_rate'];
            }
        }

        $series = [];
        if ($canPlan) {
            $series[] = ['key' => 'plans', 'name' => 'Plans', 'data' => $planRates];
        }
        if ($canReport) {
            $series[] = ['key' => 'reports', 'name' => 'Reports', 'data' => $reportRates];
        }
        $series[] = ['key' => 'logs', 'name' => 'Work logs', 'data' => $logRates];
        $series[] = ['key' => 'actions', 'name' => 'Action tasks', 'data' => $actionRates];
        if ($canReview) {
            $series[] = ['key' => 'reviews', 'name' => 'Reviews', 'data' => $reviewRates];
        }

        $latest = $monthDetails[array_key_last($monthDetails)] ?? null;
        $overallScore = $this->averageLatestVisibleRates($latest, $canPlan, $canReport, $canReview);
        $pendingReviews = $canReview ? $this->pendingReviewCount($user) : 0;
        $tips = $this->buildTips($user, $latest, $overallScore, $pendingReviews, $canPlan, $canReport, $canReview);

        return [
            'categories' => $categories,
            'series' => $series,
            'tips' => $tips,
            'overall_score' => $overallScore,
            'pending_reviews' => $pendingReviews,
            'month_details' => $monthDetails,
        ];
    }

    /**
     * @return Collection<int, WorkPeriod>
     */
    private function periodsOverlapping(Carbon $monthStart, Carbon $monthEnd): Collection
    {
        return WorkPeriod::query()
            ->whereDate('week_start_date', '<=', $monthEnd->toDateString())
            ->whereDate('week_end_date', '>=', $monthStart->toDateString())
            ->orderBy('week_start_date')
            ->get();
    }

    /**
     * @param  Collection<int, WorkPeriod>  $periods
     * @return array<string, mixed>
     */
    private function scoreMonth(
        User $user,
        Collection $periods,
        Carbon $monthStart,
        Carbon $monthEnd,
        bool $canPlan,
        bool $canReport,
        bool $canReview,
    ): array {
        $weeks = $periods->count();
        $plansSubmitted = 0;
        $reportsSubmitted = 0;
        $expectedWeekdays = 0;
        $periodIds = [];

        foreach ($periods as $period) {
            $periodIds[] = (string) $period->id;

            if ($canPlan) {
                $plan = Document::findWeeklyPlanForUserAndPeriod((string) $period->id, (string) $user->id);
                if ($plan?->isSubmitted()) {
                    $plansSubmitted++;
                }
            }

            if ($canReport) {
                $report = Document::findWeeklyReportForUserAndPeriod((string) $period->id, (string) $user->id);
                if ($report?->isSubmitted()) {
                    $reportsSubmitted++;
                }
            }

            $expectedWeekdays += $this->weekdayCountInPeriodWithinMonth($period, $monthStart, $monthEnd);
        }

        $logDays = 0;
        if ($periodIds !== [] && Schema::hasTable('weekly_work_logs')) {
            $logDays = (int) WeeklyWorkLog::query()
                ->where('user_id', $user->id)
                ->whereIn('period_id', $periodIds)
                ->whereDate('log_date', '>=', $monthStart->toDateString())
                ->whereDate('log_date', '<=', $monthEnd->toDateString())
                ->count();
        }

        $actionsRate = $this->actionClosureRate($user, $monthStart, $monthEnd, $weeks > 0 && $monthStart->isSameMonth(now()));
        $reviewsRate = $canReview
            ? $this->reviewsHandledRate($user, $monthStart, $monthEnd)
            : null;

        return [
            'weeks' => $weeks,
            'plans_submitted' => $plansSubmitted,
            'plans_expected' => $canPlan ? $weeks : 0,
            'plans_rate' => $canPlan ? $this->rate($plansSubmitted, $weeks) : null,
            'reports_submitted' => $reportsSubmitted,
            'reports_expected' => $canReport ? $weeks : 0,
            'reports_rate' => $canReport ? $this->rate($reportsSubmitted, $weeks) : null,
            'log_days' => $logDays,
            'expected_weekdays' => $expectedWeekdays,
            'logs_rate' => $this->rate($logDays, max(1, $expectedWeekdays)),
            'actions_rate' => $actionsRate,
            'reviews_rate' => $reviewsRate,
            'missed_plans' => $canPlan ? max(0, $weeks - $plansSubmitted) : 0,
            'missed_reports' => $canReport ? max(0, $weeks - $reportsSubmitted) : 0,
        ];
    }

    private function weekdayCountInPeriodWithinMonth(WorkPeriod $period, Carbon $monthStart, Carbon $monthEnd): int
    {
        $start = Carbon::parse($period->week_start_date)->max($monthStart)->startOfDay();
        $end = Carbon::parse($period->week_end_date)->min($monthEnd)->startOfDay();
        if ($start->gt($end)) {
            return 0;
        }

        $count = 0;
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            if ($day->isWeekday()) {
                $count++;
            }
        }

        return $count;
    }

    private function actionClosureRate(User $user, Carbon $monthStart, Carbon $monthEnd, bool $isCurrentMonth): ?int
    {
        $doneInMonth = 0;
        if (Schema::hasTable('action_log_task_history') && Schema::hasTable('action_log_task_assignees')) {
            $doneInMonth = ActionLogTaskHistory::query()
                ->where('to_progress', ActionLogTaskSupport::PROGRESS_DONE)
                ->whereBetween('changed_at', [$monthStart->copy()->startOfDay(), $monthEnd->copy()->endOfDay()])
                ->whereHas('task.assignees', function ($query) use ($user) {
                    $query->where('users.id', $user->id);
                })
                ->pluck('action_log_task_id')
                ->unique()
                ->count();
        }

        $actionProgress = $this->actionLogProgressService->buildForUser($user);
        $openNow = collect($actionProgress['activeTasks'] ?? [])
            ->filter(function ($task) {
                if (! is_array($task)) {
                    return false;
                }
                $progress = strtolower(trim((string) ($task['latest_progress'] ?? 'Not Started')));

                return ! in_array($progress, ['done', 'cancelled'], true);
            })
            ->count();

        if ($doneInMonth === 0 && $openNow === 0) {
            return $isCurrentMonth ? 100 : null;
        }

        if (! $isCurrentMonth && $doneInMonth === 0) {
            return null;
        }

        $denominator = $doneInMonth + ($isCurrentMonth ? $openNow : 0);
        if ($denominator < 1) {
            return $doneInMonth > 0 ? 100 : null;
        }

        return $this->rate($doneInMonth, $denominator);
    }

    private function reviewsHandledRate(User $user, Carbon $monthStart, Carbon $monthEnd): ?int
    {
        $acted = 0;

        if (Schema::hasTable('request_approvals')) {
            $acted += (int) DB::table('request_approvals')
                ->where('actor_id', $user->id)
                ->whereIn('action', ['approved', 'rejected', 'resubmission'])
                ->whereBetween('acted_at', [$monthStart->copy()->startOfDay(), $monthEnd->copy()->endOfDay()])
                ->count();
        }

        if (Schema::hasTable('weekly_plan_budget_reviews')) {
            $acted += (int) WeeklyPlanBudgetReview::query()
                ->where(function ($query) use ($user, $monthStart, $monthEnd) {
                    $query->where(function ($inner) use ($user, $monthStart, $monthEnd) {
                        $inner->where('reviewed_by', $user->id)
                            ->whereBetween('reviewed_at', [$monthStart, $monthEnd]);
                    })->orWhere(function ($inner) use ($user, $monthStart, $monthEnd) {
                        $inner->where('approved_by', $user->id)
                            ->whereBetween('approved_at', [$monthStart, $monthEnd]);
                    })->orWhere(function ($inner) use ($user, $monthStart, $monthEnd) {
                        $inner->where('authorized_by', $user->id)
                            ->whereBetween('authorized_at', [$monthStart, $monthEnd]);
                    });
                })
                ->count();
        }

        $pending = $this->pendingReviewCount($user);
        if ($acted === 0 && $pending === 0) {
            return $monthStart->isSameMonth(now()) ? 100 : null;
        }

        if (! $monthStart->isSameMonth(now()) && $acted === 0) {
            return null;
        }

        $denominator = $acted + ($monthStart->isSameMonth(now()) ? $pending : 0);
        if ($denominator < 1) {
            return $acted > 0 ? 100 : null;
        }

        return $this->rate($acted, $denominator);
    }

    private function pendingReviewCount(User $user): int
    {
        $finance = $this->requestApprovalNavService->countsFor($user)['total'] ?? 0;
        $budget = $this->weeklyBudgetReviewService->actionableStageCountsFor($user);
        $budgetTotal = (int) ($budget['review'] ?? 0) + (int) ($budget['approve'] ?? 0) + (int) ($budget['authorize'] ?? 0);

        return (int) $finance + $budgetTotal;
    }

    private function userCanReview(User $user): bool
    {
        return $user->can('review staff requisition')
            || $user->can('approve staff requisition')
            || $user->can('authorize staff requisition')
            || $user->can('review pettycash')
            || $user->can('approve pettycash')
            || $user->can('authorize pettycash')
            || $user->can('review weekly budgets')
            || $user->can('approve weekly budgets')
            || $user->can('authorize weekly budgets');
    }

    private function rate(int $numerator, int $denominator): int
    {
        if ($denominator < 1) {
            return 100;
        }

        return (int) round(($numerator / $denominator) * 100);
    }

    /**
     * @param  array<string, mixed>|null  $latest
     */
    private function averageLatestVisibleRates(?array $latest, bool $canPlan, bool $canReport, bool $canReview): int
    {
        if ($latest === null) {
            return 0;
        }

        $values = [];
        if ($canPlan && $latest['plans_rate'] !== null) {
            $values[] = (int) $latest['plans_rate'];
        }
        if ($canReport && $latest['reports_rate'] !== null) {
            $values[] = (int) $latest['reports_rate'];
        }
        if ($latest['logs_rate'] !== null) {
            $values[] = (int) $latest['logs_rate'];
        }
        if ($latest['actions_rate'] !== null) {
            $values[] = (int) $latest['actions_rate'];
        }
        if ($canReview && $latest['reviews_rate'] !== null) {
            $values[] = (int) $latest['reviews_rate'];
        }

        if ($values === []) {
            return 0;
        }

        return (int) round(array_sum($values) / count($values));
    }

    /**
     * @param  array<string, mixed>|null  $latest
     * @return list<string>
     */
    private function buildTips(
        User $user,
        ?array $latest,
        int $overallScore,
        int $pendingReviews,
        bool $canPlan,
        bool $canReport,
        bool $canReview,
    ): array {
        $tips = [];
        $scorecard = $this->weeklyScorecardService->forUser($user);

        if ($latest) {
            $label = (string) ($latest['label'] ?? 'this month');
            if ($canPlan && ($latest['missed_plans'] ?? 0) > 0) {
                $tips[] = 'You missed '.$latest['missed_plans'].' weekly '
                    .($latest['missed_plans'] === 1 ? 'plan' : 'plans').' in '.$label.'.';
            }
            if ($canReport && ($latest['missed_reports'] ?? 0) > 0) {
                $tips[] = 'You missed '.$latest['missed_reports'].' weekly '
                    .($latest['missed_reports'] === 1 ? 'report' : 'reports').' in '.$label.'.';
            }
            if (($latest['logs_rate'] ?? 100) < 60) {
                $tips[] = 'Work-log consistency is below 60% for '.$label.' — log a short daily update.';
            }
        }

        if (($scorecard['open_actions'] ?? 0) > 0) {
            $tips[] = 'You have '.$scorecard['open_actions'].' open action '
                .($scorecard['open_actions'] === 1 ? 'task' : 'tasks').' that still need progress.';
        }

        if ($canReview && $pendingReviews > 0) {
            $tips[] = $pendingReviews.' approval '
                .($pendingReviews === 1 ? 'item is' : 'items are').' waiting for your review.';
        }

        if ($tips === [] && $overallScore >= 80) {
            $tips[] = 'Strong month so far — keep submitting on time and closing assigned tasks.';
        }

        if ($tips === []) {
            $tips[] = 'Stay consistent: submit your plan and report, log daily work, and clear open actions.';
        }

        return array_values(array_slice($tips, 0, 3));
    }
}
