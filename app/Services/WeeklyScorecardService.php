<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use App\Models\WeeklyWorkLog;
use App\Models\WorkPeriod;

class WeeklyScorecardService
{
    public function __construct(
        private readonly ActionLogProgressService $actionLogProgressService,
    ) {}

    /**
     * @return array{
     *     period: ?WorkPeriod,
     *     plan_submitted: bool,
     *     report_submitted: bool,
     *     open_actions: int,
     *     work_log_days: int,
     *     work_log_expected_days: int,
     *     carry_overs: int
     * }
     */
    public function forUser(User $user, ?WorkPeriod $period = null): array
    {
        $period = $period ?? WorkPeriod::getCurrent();
        if (! $period) {
            return [
                'period' => null,
                'plan_submitted' => false,
                'report_submitted' => false,
                'open_actions' => 0,
                'work_log_days' => 0,
                'work_log_expected_days' => 0,
                'carry_overs' => 0,
            ];
        }

        $plan = Document::findWeeklyPlanForUserAndPeriod((string) $period->id, (string) $user->id);
        $report = Document::findWeeklyReportForUserAndPeriod((string) $period->id, (string) $user->id);

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

        $workLogDays = WeeklyWorkLog::query()
            ->where('user_id', $user->id)
            ->where('period_id', $period->id)
            ->count();

        $expectedDays = max(1, (int) $period->week_start_date->diffInDays($period->week_end_date) + 1);

        $carryOvers = 0;
        if ($report && is_array($report->content)) {
            foreach (($report->content['activity_progress'] ?? []) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $progress = trim((string) ($row['progress'] ?? ''));
                if in_array($progress, ['Partially done', 'Done and carry'], true)) {
                    $carryOvers++;
                }
            }
        }

        return [
            'period' => $period,
            'plan_submitted' => (bool) ($plan?->isSubmitted()),
            'report_submitted' => (bool) ($report?->isSubmitted()),
            'open_actions' => $openActions,
            'work_log_days' => $workLogDays,
            'work_log_expected_days' => $expectedDays,
            'carry_overs' => $carryOvers,
        ];
    }
}
