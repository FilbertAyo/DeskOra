<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Collection;

class DocumentListingService
{
    /**
     * @param  Collection<int, Document>  $weeklyDocs
     * @return Collection<int, array<string, mixed>>
     */
    public function buildWeeklyRowsForIndex(Collection $weeklyDocs): Collection
    {
        $weeklyDocs = $this->appendLinkedWeeklyDocumentsForIndex($weeklyDocs);

        $pickPreferred = function (Collection $rows, string $type): ?Document {
            $typedRows = $rows
                ->where('type', $type)
                ->sortByDesc(fn (Document $row) => $row->updated_at?->timestamp ?? 0)
                ->values();

            /** @var Document|null $submitted */
            $submitted = $typedRows->firstWhere('state', Document::STATE_SUBMITTED);

            return $submitted ?? $typedRows->first();
        };

        return $weeklyDocs
            ->groupBy(fn (Document $doc) => $this->weeklyIndexRowKey($doc, $weeklyDocs))
            ->map(function (Collection $items) use ($weeklyDocs, $pickPreferred) {
                $plan = $pickPreferred($items, Document::TYPE_WEEKLY_PLAN);
                $report = $pickPreferred($items, Document::TYPE_WEEKLY_REPORT);

                if ($plan === null && $report !== null) {
                    $plan = $this->findLinkedWeeklyPlanInCollection($report, $weeklyDocs)
                        ?? $this->findSubmittedWeeklyPlanForReport($report);
                }

                if ($report === null && $plan !== null) {
                    $report = $this->findLinkedWeeklyReportInCollection($plan, $weeklyDocs)
                        ?? $this->findSubmittedWeeklyReportForPlan($plan);
                }

                $participants = $items
                    ->flatMap(fn (Document $row) => collect([$row->user])->merge($this->resolveWeeklyDocSharedUsersForIndex($row, $weeklyDocs)))
                    ->filter()
                    ->unique('id')
                    ->values();

                if ($plan?->user) {
                    $participants = $participants->push($plan->user)->unique('id')->values();
                }

                return [
                    'plan' => $plan,
                    'report' => $report,
                    'period' => $plan?->period ?? $report?->period,
                    'participants' => $participants,
                    'plan_shared_users' => $this->explicitWeeklyDocSharedUsersForIndex($plan),
                    'report_shared_users' => $this->explicitWeeklyDocSharedUsersForIndex($report),
                    'updated_at' => collect([$plan?->updated_at, $report?->updated_at])->filter()->max(),
                ];
            })
            ->sortByDesc(function (array $row) {
                $period = $row['period'] ?? null;
                $year = $period ? (int) $period->year : 0;
                $week = $period ? (int) $period->week_number : 0;
                $updatedAtTimestamp = data_get($row, 'updated_at.timestamp', 0);

                return sprintf('%04d%02d%010d', $year, $week, (int) $updatedAtTimestamp);
            })
            ->values();
    }

    /**
     * Ensure submitted plans paired to visible reports are present before row grouping.
     *
     * @param  Collection<int, Document>  $weeklyDocs
     * @return Collection<int, Document>
     */
    public function appendLinkedWeeklyDocumentsForIndex(Collection $weeklyDocs): Collection
    {
        $extras = collect();

        foreach ($weeklyDocs->where('type', Document::TYPE_WEEKLY_REPORT) as $report) {
            if (! $report->period_id) {
                continue;
            }

            if ($this->findLinkedWeeklyPlanInCollection($report, $weeklyDocs)) {
                continue;
            }

            $plan = $this->findSubmittedWeeklyPlanForReport($report);
            if ($plan && ! $weeklyDocs->contains(fn (Document $doc) => (string) $doc->id === (string) $plan->id)) {
                $plan->loadMissing(['user', 'period', 'sharedUsers:id,name']);
                $extras->push($plan);
            }
        }

        if ($extras->isEmpty()) {
            return $weeklyDocs;
        }

        return $weeklyDocs->merge($extras)->unique('id')->values();
    }

    /**
     * Group by work period and weekly plan owner so plan/report pairs stay on one row.
     *
     * @param  Collection<int, Document>  $weeklyDocs
     */
    public function weeklyIndexRowKey(Document $doc, Collection $weeklyDocs): string
    {
        $periodId = (string) ($doc->period_id ?? 'none');

        return $periodId.'_'.$this->weeklyIndexAnchorUserId($doc, $weeklyDocs);
    }

    /**
     * @param  Collection<int, Document>  $weeklyDocs
     */
    public function weeklyIndexAnchorUserId(Document $doc, Collection $weeklyDocs): string
    {
        if ($doc->type === Document::TYPE_WEEKLY_PLAN) {
            return (string) $doc->user_id;
        }

        $linkedPlan = $this->findLinkedWeeklyPlanInCollection($doc, $weeklyDocs);

        return $linkedPlan ? (string) $linkedPlan->user_id : (string) $doc->user_id;
    }

    /**
     * @param  Collection<int, Document>  $weeklyDocs
     */
    public function findLinkedWeeklyPlanInCollection(Document $report, Collection $weeklyDocs): ?Document
    {
        if ($report->type !== Document::TYPE_WEEKLY_REPORT || ! $report->period_id) {
            return null;
        }

        return $weeklyDocs
            ->where('type', Document::TYPE_WEEKLY_PLAN)
            ->first(function (Document $plan) use ($report) {
                if ((string) $plan->period_id !== (string) $report->period_id) {
                    return false;
                }

                if ((string) $plan->user_id === (string) $report->user_id) {
                    return true;
                }

                return $plan->sharedUsers->contains(
                    fn ($sharedUser) => (string) $sharedUser->id === (string) $report->user_id
                );
            });
    }

    /**
     * @param  Collection<int, Document>  $weeklyDocs
     */
    public function findLinkedWeeklyReportInCollection(Document $plan, Collection $weeklyDocs): ?Document
    {
        if ($plan->type !== Document::TYPE_WEEKLY_PLAN || ! $plan->period_id) {
            return null;
        }

        $editableUserIds = $plan->allEditableUserIds();

        return $weeklyDocs
            ->where('type', Document::TYPE_WEEKLY_REPORT)
            ->first(function (Document $report) use ($plan, $editableUserIds) {
                if ((string) $report->period_id !== (string) $plan->period_id) {
                    return false;
                }

                return in_array((string) $report->user_id, $editableUserIds, true);
            });
    }

    public function findSubmittedWeeklyPlanForReport(Document $report): ?Document
    {
        if ($report->type !== Document::TYPE_WEEKLY_REPORT || ! $report->period_id) {
            return null;
        }

        $plan = Document::findWeeklyPlanForUserAndPeriod(
            (string) $report->period_id,
            (string) $report->user_id
        );

        if ($plan?->isSubmitted()) {
            return $plan;
        }

        $plan = Document::findWeeklyPlanForPeriodOwner(
            (string) $report->period_id,
            (string) $report->user_id
        );

        return $plan?->isSubmitted() ? $plan : null;
    }

    public function findSubmittedWeeklyReportForPlan(Document $plan): ?Document
    {
        if ($plan->type !== Document::TYPE_WEEKLY_PLAN || ! $plan->period_id) {
            return null;
        }

        foreach ($plan->allEditableUserIds() as $userId) {
            $report = Document::findWeeklyReportForPeriodOwner((string) $plan->period_id, $userId);
            if ($report?->isSubmitted()) {
                return $report;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, Document>  $weeklyDocs
     * @return Collection<int, User>
     */
    public function resolveWeeklyDocSharedUsersForIndex(?Document $doc, Collection $weeklyDocs): Collection
    {
        if (! $doc) {
            return collect();
        }

        $directSharedUsers = collect($doc->sharedUsers)
            ->filter(fn ($member) => $member && (string) $member->id !== (string) $doc->user_id)
            ->unique('id')
            ->values();

        if ($doc->type !== Document::TYPE_WEEKLY_REPORT
            || $directSharedUsers->isNotEmpty()
            || ! $doc->period_id) {
            return $directSharedUsers;
        }

        $fallbackPlan = $this->findLinkedWeeklyPlanInCollection($doc, $weeklyDocs);

        if (! $fallbackPlan) {
            return $directSharedUsers;
        }

        return $fallbackPlan->sharedUsers
            ->push($fallbackPlan->user)
            ->filter(fn ($member) => $member && (string) $member->id !== (string) $doc->user_id)
            ->unique('id')
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    public function explicitWeeklyDocSharedUsersForIndex(?Document $doc): Collection
    {
        if (! $doc) {
            return collect();
        }

        return collect($doc->sharedUsers)
            ->filter(fn ($member) => $member && (string) $member->id !== (string) $doc->user_id)
            ->unique('id')
            ->values();
    }

    /**
     * Build document_id => latest rejection reason map for visible documents.
     *
     * @param  array<int, mixed>  $documentIds
     * @return array<string, string>
     */
    public function getReturnedDocumentMapForIds(array $documentIds): array
    {
        if (empty($documentIds)) {
            return [];
        }

        return Document::query()
            ->whereIn('id', $documentIds)
            ->whereNotNull('review_rejection_reason')
            ->where('review_rejection_reason', '!=', '')
            ->get(['id', 'review_rejection_reason'])
            ->mapWithKeys(function (Document $doc) {
                return [(string) $doc->id => trim((string) $doc->review_rejection_reason)];
            })
            ->all();
    }

    /**
     * For staff lists: rows where you are a collaborator (not the author) appear first, then by last update.
     * Reviewers keep a simple newest-first ordering by Document::$updated_at.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Document>  $query
     */
    public function applyWeeklyListSortForUser($query, User $user): void
    {
        if ($user->hasPermissionTo('view all documents')) {
            $query->orderByDesc('documents.updated_at');

            return;
        }

        $query->orderByRaw('CASE WHEN documents.user_id = ? THEN 1 ELSE 0 END', [$user->id])
            ->orderByDesc('documents.updated_at');
    }
}
