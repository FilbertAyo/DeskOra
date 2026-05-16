<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Services\WorkPeriodService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class WeeklyBudgetReviewService
{
    public function __construct(
        private readonly WorkPeriodService $workPeriodService,
    ) {}

    public function resolveWeeklyBudgetReviewDefaultPeriod(Request $request): ?WorkPeriod
    {
        if ($request->filled('period_id')) {
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

        $periodLabel = 'Selected Week';
        $periodDateRange = 'Selected Work Period';
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
     * Group budget documents by department -> staff -> budget items for the review UI.
     *
     * @return array<string, array{department_name: string, department_id: string|null, requested_total: float, provided_total: float, staff: array}>
     */
    public function groupBudgetDocumentsByDepartment(Collection $documents): array
    {
        $grouped = [];

        foreach ($documents as $document) {
            $departmentName = $document->user?->department?->name ?? 'No Department';
            $departmentId = (string) ($document->user?->department_id ?? 'none');

            if (! isset($grouped[$departmentId])) {
                $grouped[$departmentId] = [
                    'department_name' => $departmentName,
                    'department_id' => $departmentId,
                    'requested_total' => 0.0,
                    'provided_total' => 0.0,
                    'staff' => [],
                ];
            }

            $userId = (string) $document->user_id;
            if (! isset($grouped[$departmentId]['staff'][$userId])) {
                $grouped[$departmentId]['staff'][$userId] = [
                    'user' => $document->user,
                    'document' => $document,
                    'items' => [],
                    'requested_total' => 0.0,
                    'provided_total' => 0.0,
                    'summary' => $document->budgetReviewSummary(),
                ];
            }

            $budgetItems = $document->budgetItems();
            $reviewsByUid = $document->budgetReviewItemsByUid();

            foreach ($budgetItems as $item) {
                $uid = $item['budget_uid'];
                $review = $reviewsByUid[$uid] ?? null;
                $amountNumeric = (float) ($item['amount_numeric'] ?? 0);
                $providedAmount = (float) ($review['provided_amount'] ?? 0);

                $grouped[$departmentId]['staff'][$userId]['items'][] = [
                    'budget_uid' => $uid,
                    'item' => $item['item'],
                    'description_purpose' => $item['description_purpose'],
                    'amount' => $item['amount'],
                    'amount_numeric' => $amountNumeric,
                    'decision' => $review['decision'] ?? Document::BUDGET_REVIEW_PENDING,
                    'provided_amount' => $providedAmount,
                    'note' => $review['note'] ?? null,
                    'reviewed_by' => $review['reviewed_by'] ?? null,
                    'reviewed_at' => $review['reviewed_at'] ?? null,
                    'document_id' => $document->id,
                ];

                $grouped[$departmentId]['staff'][$userId]['requested_total'] += $amountNumeric;
                $grouped[$departmentId]['staff'][$userId]['provided_total'] += $providedAmount;
                $grouped[$departmentId]['requested_total'] += $amountNumeric;
                $grouped[$departmentId]['provided_total'] += $providedAmount;
            }
        }

        uasort($grouped, fn ($a, $b) => strcasecmp($a['department_name'], $b['department_name']));

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
}
