<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Request as FinanceRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class ApprovalQueueService
{
    public function __construct(
        private readonly RequestApprovalNavService $financeNav,
    ) {}

    public static function cacheKeyFor(User $user): string
    {
        return 'approval-queue-summary-user-'.$user->id;
    }

    public static function budgetCacheKey(): string
    {
        return 'approval-queue-budget-pending';
    }

    public function forgetFor(User $user): void
    {
        Cache::forget(self::cacheKeyFor($user));
        $this->financeNav->forgetCountsFor($user);
    }

    public function forgetBudgetPending(): void
    {
        Cache::forget(self::budgetCacheKey());
    }

    /**
     * @return array{finance: int, budget: int, total: int}
     */
    public function summaryCounts(User $user): array
    {
        $finance = (int) ($this->financeNav->countsFor($user)['total'] ?? 0);
        $budget = $this->budgetPendingCount($user);

        return [
            'finance' => $finance,
            'budget' => $budget,
            'total' => $finance + $budget,
        ];
    }

    public function budgetPendingCount(User $user): int
    {
        if (! $this->userCanSeeBudgetQueue($user)) {
            return 0;
        }

        if (! Schema::hasTable('documents')) {
            return 0;
        }

        return (int) Cache::remember(
            self::budgetCacheKey(),
            now()->addSeconds(45),
            fn () => Document::countPendingWeeklyBudgetReviewForPeriod(null)
        );
    }

    /**
     * Unified actionable items for the approval queue page.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function itemsFor(User $user, ?string $filter = null): Collection
    {
        $items = collect();

        if ($filter === null || $filter === 'finance' || $filter === 'all') {
            $items = $items->merge($this->financeItems($user));
        }

        if ($filter === null || $filter === 'budget' || $filter === 'all') {
            $items = $items->merge($this->budgetItems($user));
        }

        return $items
            ->sortByDesc(fn (array $item) => $item['sort_at'] ?? '')
            ->values();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function financeItems(User $user): array
    {
        $items = [];

        foreach (FinanceRequest::APPROVAL_STAGES as $stage) {
            if (! FinanceRequest::userCanAccessStage($user, $stage)) {
                continue;
            }

            $requests = FinanceRequest::query()
                ->with(['user', 'pettyDetails', 'requisitionDetails'])
                ->actionableForUserAtStage($user, $stage)
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();

            foreach ($requests as $request) {
                $isRequisition = $request->request_type === 'Staff Requisition';
                $typeFilter = $isRequisition ? 'requisition' : 'petty';

                $items[] = [
                    'kind' => 'finance',
                    'id' => 'finance-'.$request->id.'-'.$stage,
                    'title' => $isRequisition
                        ? (string) ($request->activity_title ?: $request->request_for ?: 'Staff Requisition')
                        : (string) ($request->request_for ?: $request->request_type),
                    'subtitle' => ($request->user?->name ?? 'Unknown').' · '.$request->request_type,
                    'stage' => $stage,
                    'stage_label' => FinanceRequest::stageLabel($stage),
                    'type_label' => $request->request_type,
                    'amount' => (float) $request->amount,
                    'requested_by' => $request->user?->name ?? 'N/A',
                    'date_label' => optional($request->created_at)?->format('d M, Y') ?? '',
                    'sort_at' => optional($request->created_at)?->toIso8601String() ?? '',
                    'url' => route('petty.details', [
                        'petty' => $request->id,
                        'stage' => $stage,
                        'type' => $typeFilter,
                    ]),
                    'queue_url' => route('petty.list', [
                        'stage' => $stage,
                        'type' => $typeFilter,
                    ]),
                ];
            }
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function budgetItems(User $user): array
    {
        if (! $this->userCanSeeBudgetQueue($user)) {
            return [];
        }

        if (! Schema::hasTable('documents')) {
            return [];
        }

        $plans = Document::query()
            ->with(['user', 'period'])
            ->select(['id', 'user_id', 'period_id', 'title', 'content', 'budget_review_status', 'type', 'state', 'created_at', 'updated_at'])
            ->weeklyPlans()
            ->submitted()
            ->where(function ($budgetQuery) {
                $budgetQuery
                    ->whereHas('weeklyPlanDomain.budgetItems')
                    ->orWhere(function ($legacyQuery) {
                        $legacyQuery
                            ->whereNotNull('budget_review_status')
                            ->orWhere('content', 'like', '%"budget"%');
                    });
            })
            ->where(function ($statusQuery) {
                $statusQuery
                    ->whereNull('budget_review_status')
                    ->orWhere('budget_review_status', Document::BUDGET_REVIEW_PENDING);
            })
            ->orderByDesc('updated_at')
            ->limit(40)
            ->get()
            ->filter(fn (Document $document) => $document->hasBudgetItems())
            ->values();

        $items = [];

        foreach ($plans as $plan) {
            $periodLabel = $plan->period
                ? 'Week '.$plan->period->week_number
                : 'Weekly plan';

            $items[] = [
                'kind' => 'budget',
                'id' => 'budget-'.$plan->id,
                'title' => $plan->title ?: 'Weekly budget review',
                'subtitle' => ($plan->user?->name ?? 'Unknown').' · '.$periodLabel,
                'stage' => 'budget',
                'stage_label' => 'Budget',
                'type_label' => 'Weekly Budget',
                'amount' => null,
                'requested_by' => $plan->user?->name ?? 'N/A',
                'date_label' => optional($plan->updated_at ?? $plan->created_at)?->format('d M, Y') ?? '',
                'sort_at' => optional($plan->updated_at ?? $plan->created_at)?->toIso8601String() ?? '',
                'url' => route('documents.weekly-budget-review', ['period_id' => $plan->period_id]),
                'queue_url' => route('documents.weekly-budget-review'),
            ];
        }

        return $items;
    }

    private function userCanSeeBudgetQueue(User $user): bool
    {
        return $user->can('view weekly budgets')
            || $user->can('review weekly budgets')
            || $user->can('approve weekly budgets')
            || $user->can('authorize weekly budgets');
    }
}
