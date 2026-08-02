<?php

namespace App\Services;

use App\Models\Deposit;
use App\Models\Request as FinanceRequest;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Models\WeeklyPlanBudgetReview;
use Illuminate\Support\Facades\Schema;

class CashFlowForecastService
{
    /**
     * @return array{
     *     available: float,
     *     committed: float,
     *     projected: float,
     *     awaiting_payment_count: int,
     *     authorized_budget_next_two_weeks: float,
     *     horizon_label: string,
     *     shortage: bool
     * }
     */
    public function forUser(User $user): array
    {
        $scopeIds = $user->departmentScopeIds();

        $available = 0.0;
        if (Schema::hasTable('deposits') && $scopeIds !== []) {
            $latest = Deposit::query()
                ->whereIn('department_id', $scopeIds)
                ->orderByDesc('id')
                ->first();
            $available = (float) ($latest?->remaining ?? 0);
        }

        $committed = 0.0;
        $awaitingCount = 0;
        if (Schema::hasTable('requests') && $scopeIds !== []) {
            $awaiting = FinanceRequest::query()
                ->with(['pettyDetails'])
                ->whereIn('department_id', $scopeIds)
                ->whereIn('request_type', ['Petty Cash', 'Reimbursement'])
                ->where('status', 'approved')
                ->get();

            $awaitingCount = $awaiting->count();
            $committed = (float) $awaiting->sum(fn (FinanceRequest $request) => (float) $request->amount);
        }

        $authorizedBudget = $this->authorizedBudgetForNextTwoWeeks();

        $projected = $available - $committed;

        return [
            'available' => $available,
            'committed' => $committed,
            'projected' => $projected,
            'awaiting_payment_count' => $awaitingCount,
            'authorized_budget_next_two_weeks' => $authorizedBudget,
            'horizon_label' => 'Next 2 weeks',
            'shortage' => $projected < 0,
        ];
    }

    private function authorizedBudgetForNextTwoWeeks(): float
    {
        if (! Schema::hasTable('weekly_plan_budget_reviews') || ! Schema::hasTable('work_periods')) {
            return 0.0;
        }

        $current = WorkPeriod::getCurrent();
        if (! $current) {
            return 0.0;
        }

        $periodIds = WorkPeriod::query()
            ->where(function ($query) use ($current) {
                $query->where('id', $current->id);
                $next = $current->getNextPeriod();
                if ($next) {
                    $query->orWhere('id', $next->id);
                }
            })
            ->pluck('id')
            ->all();

        if ($periodIds === []) {
            return 0.0;
        }

        return (float) WeeklyPlanBudgetReview::query()
            ->where('decision', 'approved')
            ->where('approval_stage', 'authorized')
            ->whereHas('budgetItem.weeklyPlan', function ($query) use ($periodIds) {
                $query->whereIn('period_id', $periodIds);
            })
            ->sum('provided_amount');
    }
}
