<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\User;
use App\Models\WeeklyPlanBudgetItem;
use App\Models\WorkPeriod;
use App\Services\WorkPeriodService;
use App\Support\WeeklyBudgetApprovalFlow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WeeklyBudgetReviewService
{
    public function __construct(
        private readonly WorkPeriodService $workPeriodService,
    ) {}

    public function countPendingForPeriod(?string $periodId = null): int
    {
        if (! Schema::hasTable('documents')) {
            return 0;
        }

        $query = Document::query()
            ->select(['id', 'content', 'budget_review_status', 'type', 'state', 'period_id'])
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
            });

        if ($periodId !== null && $periodId !== '') {
            $query->forPeriod($periodId);
        }

        return $query->get()->filter(fn (Document $document) => $document->hasBudgetItems())->count();
    }

    /**
     * Count budget line items awaiting a specific approval stage for the given user.
     *
     * @return array{review: int, approve: int, authorize: int}
     */
    public function actionableStageCountsFor(User $user): array
    {
        $counts = [
            'review' => 0,
            'approve' => 0,
            'authorize' => 0,
        ];

        if (! Schema::hasTable('weekly_plan_budget_reviews') || ! Schema::hasTable('weekly_plan_budget_items')) {
            return $counts;
        }

        $stageMap = [
            'review' => [Document::BUDGET_REVIEW_STAGE_PENDING, 'review weekly budgets'],
            'approve' => [Document::BUDGET_REVIEW_STAGE_REVIEWED, 'approve weekly budgets'],
            'authorize' => [Document::BUDGET_REVIEW_STAGE_APPROVED, 'authorize weekly budgets'],
        ];

        foreach ($stageMap as $key => [$approvalStage, $permission]) {
            if (! $user->can($permission)) {
                continue;
            }

            $counts[$key] = (int) \App\Models\WeeklyPlanBudgetReview::query()
                ->where('approval_stage', $approvalStage)
                ->whereHas('budgetItem.weeklyPlan', function ($planQuery) {
                    $planQuery->where('state', Document::STATE_SUBMITTED);
                })
                ->count();
        }

        // Items with no review row yet still sit at pending for reviewers.
        if ($user->can('review weekly budgets') && Schema::hasTable('weekly_plan_budget_items')) {
            $pendingWithoutReview = (int) \App\Models\WeeklyPlanBudgetItem::query()
                ->whereDoesntHave('review')
                ->whereHas('weeklyPlan', function ($planQuery) {
                    $planQuery->where('state', Document::STATE_SUBMITTED);
                })
                ->count();
            $counts['review'] += $pendingWithoutReview;
        }

        return $counts;
    }

    public function actionableItemCountFor(User $user): int
    {
        return array_sum($this->actionableStageCountsFor($user));
    }

    /**
     * Pending budget items in the current and next work periods only.
     */
    public function reminderItemCountFor(User $user): int
    {
        $periodIds = $this->workPeriodService->latestReminderPeriodIds(
            max(1, (int) config('approval_gate.budget_reminder_period_count', 2))
        );

        if ($periodIds === []) {
            return 0;
        }

        if (! Schema::hasTable('weekly_plan_budget_items') || ! Schema::hasTable('weekly_plans')) {
            return 0;
        }

        return (int) $this->actionableItemsQuery($user)
            ->whereIn('weekly_plans.period_id', $periodIds)
            ->count();
    }

    /**
     * Pending budget line items this user can act on, oldest week first.
     *
     * @return Collection<int, WeeklyPlanBudgetItem>
     */
    public function actionableItemsFor(User $user, int $limit = 25): Collection
    {
        if (! Schema::hasTable('weekly_plan_budget_items') || ! Schema::hasTable('weekly_plans')) {
            return collect();
        }

        return $this->actionableItemsQuery($user)
            ->with(['weeklyPlan.period', 'weeklyPlan.user.department', 'review'])
            ->limit(max(1, $limit))
            ->get();
    }

    public function actionableItemsQuery(User $user): Builder
    {
        $query = WeeklyPlanBudgetItem::query()
            ->select('weekly_plan_budget_items.*')
            ->join('weekly_plans', 'weekly_plan_budget_items.weekly_plan_id', '=', 'weekly_plans.id')
            ->join('work_periods', 'weekly_plans.period_id', '=', 'work_periods.id')
            ->whereNull('weekly_plans.deleted_at')
            ->where('weekly_plans.state', Document::STATE_SUBMITTED)
            ->where(function (Builder $stageQuery) use ($user) {
                $hasClause = false;

                if ($user->can('review weekly budgets')) {
                    $hasClause = true;
                    $stageQuery->where(function (Builder $reviewQuery) {
                        $reviewQuery
                            ->whereDoesntHave('review')
                            ->orWhereHas('review', function (Builder $review) {
                                $review
                                    ->where('approval_stage', Document::BUDGET_REVIEW_STAGE_PENDING)
                                    ->where(function (Builder $decision) {
                                        $decision
                                            ->whereNull('decision')
                                            ->orWhere('decision', '!=', Document::BUDGET_REVIEW_REJECTED);
                                    });
                            });
                    });
                }

                if ($user->can('approve weekly budgets')) {
                    $method = $hasClause ? 'orWhereHas' : 'whereHas';
                    $hasClause = true;
                    $stageQuery->{$method}('review', function (Builder $review) {
                        $review
                            ->where('approval_stage', Document::BUDGET_REVIEW_STAGE_REVIEWED)
                            ->where('decision', '!=', Document::BUDGET_REVIEW_REJECTED);
                    });
                }

                if ($user->can('authorize weekly budgets')) {
                    $method = $hasClause ? 'orWhereHas' : 'whereHas';
                    $hasClause = true;
                    $stageQuery->{$method}('review', function (Builder $review) {
                        $review
                            ->where('approval_stage', Document::BUDGET_REVIEW_STAGE_APPROVED)
                            ->where('decision', '!=', Document::BUDGET_REVIEW_REJECTED);
                    });
                }

                if (! $hasClause) {
                    $stageQuery->whereRaw('1 = 0');
                }
            })
            ->orderBy('work_periods.year')
            ->orderBy('work_periods.week_number')
            ->orderBy('weekly_plan_budget_items.sort_order')
            ->orderBy('weekly_plan_budget_items.id');

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function presentActionableItem(WeeklyPlanBudgetItem $item, User $user): array
    {
        $plan = $item->weeklyPlan;
        $period = $plan?->period;
        $review = $item->review;
        $approvalStage = $review?->approval_stage ?? Document::BUDGET_REVIEW_STAGE_PENDING;
        $decision = $review?->decision ?? Document::BUDGET_REVIEW_PENDING;
        $amountNumeric = (float) ($item->amount_numeric ?? 0);

        $payload = [
            'id' => (string) $item->id,
            'document_id' => (string) $item->weekly_plan_id,
            'budget_uid' => (string) $item->budget_uid,
            'item' => (string) $item->item,
            'description_purpose' => (string) ($item->description_purpose ?? ''),
            'amount' => number_format($amountNumeric, 2),
            'amount_numeric' => $amountNumeric,
            'decision' => $decision,
            'approval_stage' => $approvalStage,
            'provided_amount' => $review?->provided_amount,
            'review_provided_amount' => $review?->review_provided_amount,
            'approve_provided_amount' => $review?->approve_provided_amount,
            'owner_name' => $plan?->user?->name,
            'department_name' => $plan?->user?->department?->name,
            'period_label' => $period
                ? sprintf('Week %s · %s', $period->week_number, $period->year)
                : 'Unknown week',
            'process_url' => route('documents.process-weekly-budget-review', $item->weekly_plan_id),
        ];

        return array_merge($payload, WeeklyBudgetApprovalFlow::itemViewContext($payload, $user));
    }

    public function isAllPendingWeeksView(Request $request): bool
    {
        return $request->input('status') === Document::BUDGET_REVIEW_PENDING
            && ! $request->filled('period_id');
    }

    public function resolveWeeklyBudgetReviewDefaultPeriod(Request $request): ?WorkPeriod
    {
        if ($request->has('period_id') || $this->isAllPendingWeeksView($request)) {
            return null;
        }

        return WorkPeriod::query()
            ->orderBy('year', 'desc')
            ->orderBy('week_number', 'desc')
            ->first();
    }

    public function buildWeeklyBudgetReviewQuery(Request $request, ?WorkPeriod $defaultPeriod, ?string $statusFilter)
    {
        $query = Document::query()
            ->with(['user.department', 'period', 'budgetReviewedBy'])
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
            });

        if ($request->filled('user_id')) {
            $query->forUser($request->string('user_id')->toString());
        }

        if ($request->filled('period_id')) {
            $query->forPeriod($request->string('period_id')->toString());
        } elseif ($defaultPeriod) {
            $query->forPeriod($defaultPeriod->id);
        }

        if ($this->isAllPendingWeeksView($request) || ($request->has('period_id') && ! $request->filled('period_id'))) {
            $query->join('work_periods', 'documents.period_id', '=', 'work_periods.id')
                ->orderBy('work_periods.year')
                ->orderBy('work_periods.week_number')
                ->orderBy('documents.created_at')
                ->select('documents.*');
        }

        if ($request->filled('year')) {
            $year = (int) $request->input('year');
            $query->whereHas('period', function ($periodQuery) use ($year) {
                $periodQuery->where('year', $year);
            });
        }

        if ($statusFilter === Document::BUDGET_REVIEW_PENDING) {
            $query->where(function ($statusQuery) {
                $statusQuery
                    ->whereHas('weeklyPlanDomain', function ($planQuery) {
                        $planQuery
                            ->whereNull('budget_review_status')
                            ->orWhere('budget_review_status', Document::BUDGET_REVIEW_PENDING);
                    });
            });
        } elseif (in_array($statusFilter, [Document::BUDGET_REVIEW_APPROVED, Document::BUDGET_REVIEW_REJECTED], true)) {
            $query->whereHas('weeklyPlanDomain', function ($planQuery) use ($statusFilter) {
                $planQuery->where('budget_review_status', $statusFilter);
            });
        }

        return $query;
    }

    /**
     * @return array<string, string>
     */
    public function weeklyBudgetReviewFilterSummary(Request $request, ?WorkPeriod $defaultPeriod, ?string $statusFilter): array
    {
        $resolvedPeriodId = $request->filled('period_id')
            ? (string) $request->string('period_id')
            : ($defaultPeriod?->id ? (string) $defaultPeriod->id : null);

        $periodLabel = $resolvedPeriodId ? 'Selected Week' : 'All Weeks';
        $periodDateRange = $resolvedPeriodId ? 'Selected Work Period' : 'All work periods';
        if ($resolvedPeriodId) {
            $period = WorkPeriod::query()->find($resolvedPeriodId);
            if ($period) {
                $periodLabel = sprintf('Week %s (%s)', (string) $period->week_number, (string) $period->year);
                $start = $period->week_start_date ? $period->week_start_date->format('Y-m-d') : null;
                $end = $period->week_end_date ? $period->week_end_date->format('Y-m-d') : null;
                $periodDateRange = ($start && $end) ? "{$start} to {$end}" : ($period->date_range ?? 'Selected Work Period');
            }
        }

        $userLabel = 'All Staff';
        if ($request->filled('user_id')) {
            $selectedUser = User::query()->find((string) $request->string('user_id'));
            if ($selectedUser) {
                $userLabel = $selectedUser->name;
            }
        }

        return [
            'period' => $periodLabel,
            'work_period_range' => $periodDateRange,
            'year' => $request->filled('year') ? (string) $request->input('year') : 'All Years',
            'status' => $statusFilter ? ucfirst($statusFilter) : 'All Statuses',
            'staff' => $userLabel,
        ];
    }

    /**
     * @param  array<string, mixed>  $filterSummary
     */
    public function weeklyBudgetReviewExportFilename(string $extension, array $filterSummary): string
    {
        $periodSlug = Str::slug((string) ($filterSummary['period'] ?? 'selected-week'));
        $statusSlug = Str::slug((string) ($filterSummary['status'] ?? 'all-statuses'));
        $dateStamp = now()->format('Ymd_His');

        return "weekly_budget_review_{$periodSlug}_{$statusSlug}_{$dateStamp}.{$extension}";
    }

    /**
     * Group budget documents by work period -> department -> staff.
     * Staff from different weeks stay in separate sections.
     * Processors only see items in their review / approve / authorize queue.
     *
     * @param  User|null  $viewer
     * @return array<string, array{
     *     period: \App\Models\WorkPeriod|null,
     *     period_id: string,
     *     period_label: string,
     *     period_range: string|null,
     *     requested_total: float,
     *     provided_total: float,
     *     departments: array<string, array{
     *         department_name: string,
     *         department_id: string,
     *         requested_total: float,
     *         provided_total: float,
     *         staff: array<string, array<string, mixed>>
     *     }>
     * }>
     */
    public function groupBudgetDocumentsByDepartment(Collection $documents, ?User $viewer = null): array
    {
        $grouped = [];
        $actorIds = [];

        foreach ($documents as $document) {
            $period = $document->period;
            $periodId = (string) ($document->period_id ?? 'none');
            $periodLabel = $period
                ? sprintf('Week %s • %s', $period->week_number, $period->year)
                : 'Unknown week';

            $departmentName = $document->user?->department?->name ?? 'No Department';
            $departmentId = (string) ($document->user?->department_id ?? 'none');
            $staffUserId = (string) $document->user_id;

            $budgetItems = $document->budgetItems();
            $reviewsByUid = $document->budgetReviewItemsByUid();

            foreach ($budgetItems as $item) {
                $uid = $item['budget_uid'];
                $review = $reviewsByUid[$uid] ?? null;
                $amountNumeric = (float) ($item['amount_numeric'] ?? 0);
                $providedAmount = (float) ($review['provided_amount'] ?? 0);
                $approvalStage = $review['approval_stage'] ?? Document::BUDGET_REVIEW_STAGE_PENDING;

                $row = [
                    'budget_uid' => $uid,
                    'item' => $item['item'],
                    'description_purpose' => $item['description_purpose'],
                    'amount' => $item['amount'],
                    'amount_numeric' => $amountNumeric,
                    'decision' => $review['decision'] ?? Document::BUDGET_REVIEW_PENDING,
                    'approval_stage' => $approvalStage,
                    'provided_amount' => $providedAmount,
                    'review_provided_amount' => $review['review_provided_amount'] ?? null,
                    'approve_provided_amount' => $review['approve_provided_amount'] ?? null,
                    'note' => $review['note'] ?? null,
                    'reviewed_by' => $review['reviewed_by'] ?? null,
                    'reviewed_at' => $review['reviewed_at'] ?? null,
                    'review_stage_by' => $review['review_stage_by'] ?? null,
                    'review_stage_at' => $review['review_stage_at'] ?? null,
                    'approve_stage_by' => $review['approve_stage_by'] ?? null,
                    'approve_stage_at' => $review['approve_stage_at'] ?? null,
                    'authorize_stage_by' => $review['authorize_stage_by'] ?? null,
                    'authorize_stage_at' => $review['authorize_stage_at'] ?? null,
                    'document_id' => $document->id,
                ];

                if ($viewer && ! WeeklyBudgetApprovalFlow::itemIsInUserQueue($row, $viewer)) {
                    continue;
                }

                if (! isset($grouped[$periodId])) {
                    $grouped[$periodId] = [
                        'period' => $period,
                        'period_id' => $periodId,
                        'period_label' => $periodLabel,
                        'period_range' => $period?->date_range,
                        'sort_year' => (int) ($period?->year ?? 9999),
                        'sort_week' => (int) ($period?->week_number ?? 9999),
                        'requested_total' => 0.0,
                        'provided_total' => 0.0,
                        'departments' => [],
                    ];
                }

                if (! isset($grouped[$periodId]['departments'][$departmentId])) {
                    $grouped[$periodId]['departments'][$departmentId] = [
                        'department_name' => $departmentName,
                        'department_id' => $departmentId,
                        'requested_total' => 0.0,
                        'provided_total' => 0.0,
                        'staff' => [],
                    ];
                }

                if (! isset($grouped[$periodId]['departments'][$departmentId]['staff'][$staffUserId])) {
                    $grouped[$periodId]['departments'][$departmentId]['staff'][$staffUserId] = [
                        'user' => $document->user,
                        'document' => $document,
                        'items' => [],
                        'requested_total' => 0.0,
                        'provided_total' => 0.0,
                        'summary' => [
                            'pending' => 0,
                            'approved' => 0,
                            'rejected' => 0,
                        ],
                    ];
                }

                foreach (['review_stage_by', 'approve_stage_by', 'authorize_stage_by'] as $actorKey) {
                    if (! empty($review[$actorKey])) {
                        $actorIds[] = (string) $review[$actorKey];
                    }
                }

                $staff = &$grouped[$periodId]['departments'][$departmentId]['staff'][$staffUserId];
                $staff['items'][] = $row;
                $staff['requested_total'] += $amountNumeric;
                $grouped[$periodId]['departments'][$departmentId]['requested_total'] += $amountNumeric;
                $grouped[$periodId]['requested_total'] += $amountNumeric;
                if ($approvalStage === Document::BUDGET_REVIEW_STAGE_AUTHORIZED) {
                    $staff['provided_total'] += $providedAmount;
                    $grouped[$periodId]['departments'][$departmentId]['provided_total'] += $providedAmount;
                    $grouped[$periodId]['provided_total'] += $providedAmount;
                    $staff['summary']['approved']++;
                } elseif (($row['decision'] ?? '') === Document::BUDGET_REVIEW_REJECTED
                    || $approvalStage === Document::BUDGET_REVIEW_STAGE_REJECTED) {
                    $staff['summary']['rejected']++;
                } else {
                    $staff['summary']['pending']++;
                }
                unset($staff);
            }
        }

        $actorNames = User::query()
            ->whereIn('id', array_values(array_unique($actorIds)))
            ->pluck('name', 'id');

        foreach ($grouped as &$periodGroup) {
            foreach ($periodGroup['departments'] as &$department) {
                foreach ($department['staff'] as &$staffData) {
                    foreach ($staffData['items'] as &$item) {
                        $item['review_stage_by_name'] = $actorNames[(string) ($item['review_stage_by'] ?? '')] ?? null;
                        $item['approve_stage_by_name'] = $actorNames[(string) ($item['approve_stage_by'] ?? '')] ?? null;
                        $item['authorize_stage_by_name'] = $actorNames[(string) ($item['authorize_stage_by'] ?? '')] ?? null;
                    }
                    unset($item);
                }
                unset($staffData);

                uasort(
                    $department['staff'],
                    fn ($a, $b) => strcasecmp((string) ($a['user']?->name ?? ''), (string) ($b['user']?->name ?? ''))
                );
            }
            unset($department);

            uasort(
                $periodGroup['departments'],
                fn ($a, $b) => strcasecmp($a['department_name'], $b['department_name'])
            );
        }
        unset($periodGroup);

        uasort(
            $grouped,
            fn ($a, $b) => [$a['sort_year'], $a['sort_week']] <=> [$b['sort_year'], $b['sort_week']]
        );

        return $grouped;
    }

    /**
     * Compute aggregate stats across multiple budget documents.
     *
     * @return array<string, int|float>
     */
    public function computeBudgetStats(Collection $documents): array
    {
        $totalItems = 0;
        $pendingItems = 0;
        $approvedItems = 0;
        $rejectedItems = 0;
        $requestedTotal = 0.0;
        $providedTotal = 0.0;

        foreach ($documents as $document) {
            $summary = $document->budgetReviewSummary();
            $totalItems += $summary['items_total'];
            $pendingItems += $summary['pending'];
            $approvedItems += $summary['approved'];
            $rejectedItems += $summary['rejected'];
            $requestedTotal += $summary['requested_total'];
            $providedTotal += $summary['provided_total'];
        }

        return [
            'total' => $documents->count(),
            'total_items' => $totalItems,
            'pending' => $pendingItems,
            'approved' => $approvedItems,
            'rejected' => $rejectedItems,
            'requested_total' => $requestedTotal,
            'provided_total' => $providedTotal,
        ];
    }

    /**
     * @param  array<string, mixed>  $grouped
     * @return array<string, int|float>
     */
    public function computeBudgetStatsFromGrouped(array $grouped): array
    {
        $totalItems = 0;
        $pendingItems = 0;
        $approvedItems = 0;
        $rejectedItems = 0;
        $requestedTotal = 0.0;
        $providedTotal = 0.0;
        $staffCount = 0;

        foreach ($grouped as $periodGroup) {
            $requestedTotal += (float) ($periodGroup['requested_total'] ?? 0);
            $providedTotal += (float) ($periodGroup['provided_total'] ?? 0);
            foreach ($periodGroup['departments'] ?? [] as $department) {
                foreach ($department['staff'] ?? [] as $staffData) {
                    $staffCount++;
                    $summary = $staffData['summary'] ?? [];
                    $pendingItems += (int) ($summary['pending'] ?? 0);
                    $approvedItems += (int) ($summary['approved'] ?? 0);
                    $rejectedItems += (int) ($summary['rejected'] ?? 0);
                    $totalItems += count($staffData['items'] ?? []);
                }
            }
        }

        return [
            'total' => $staffCount,
            'total_items' => $totalItems,
            'pending' => $pendingItems,
            'approved' => $approvedItems,
            'rejected' => $rejectedItems,
            'requested_total' => $requestedTotal,
            'provided_total' => $providedTotal,
        ];
    }
}
