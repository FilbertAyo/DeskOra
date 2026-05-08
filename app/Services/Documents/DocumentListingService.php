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
            ->groupBy(function (Document $doc) use ($weeklyDocs) {
                $effectiveSharedUsers = $this->resolveWeeklyDocSharedUsersForIndex($doc, $weeklyDocs);
                $participantIds = collect([$doc->user_id])
                    ->merge($effectiveSharedUsers->pluck('id'))
                    ->filter()
                    ->map(fn ($id) => (string) $id)
                    ->unique()
                    ->sort()
                    ->values();

                return ($doc->period_id ?? 'none').'_'.$participantIds->implode('_');
            })
            ->map(function (Collection $items) use ($weeklyDocs, $pickPreferred) {
                $plan = $pickPreferred($items, Document::TYPE_WEEKLY_PLAN);
                $report = $pickPreferred($items, Document::TYPE_WEEKLY_REPORT);
                $participants = $items
                    ->flatMap(fn (Document $row) => collect([$row->user])->merge($this->resolveWeeklyDocSharedUsersForIndex($row, $weeklyDocs)))
                    ->filter()
                    ->unique('id')
                    ->values();

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

        /** @var Document|null $fallbackPlan */
        $fallbackPlan = $weeklyDocs
            ->where('type', Document::TYPE_WEEKLY_PLAN)
            ->first(function (Document $plan) use ($doc) {
                if ((string) $plan->period_id !== (string) $doc->period_id) {
                    return false;
                }

                if ((string) $plan->user_id === (string) $doc->user_id) {
                    return true;
                }

                return $plan->sharedUsers->contains(function ($sharedUser) use ($doc) {
                    return (string) $sharedUser->id === (string) $doc->user_id;
                });
            });

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
