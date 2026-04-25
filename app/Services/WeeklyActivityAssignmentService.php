<?php

namespace App\Services;

use App\Models\Document;
use App\Models\WeeklyActivityAssignment;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class WeeklyActivityAssignmentService
{
    public function syncForWeeklyPlan(Document $weeklyPlan): void
    {
        if ($weeklyPlan->type !== Document::TYPE_WEEKLY_PLAN) {
            return;
        }

        $weeklyPlan->loadMissing(['user', 'sharedUsers:id']);

        $status = $weeklyPlan->isSubmitted()
            ? WeeklyActivityAssignment::STATUS_LOCKED
            : WeeklyActivityAssignment::STATUS_PREVIEW;

        $activities = collect(is_array($weeklyPlan->content['planned_activities'] ?? null) ? $weeklyPlan->content['planned_activities'] : [])
            ->filter(fn ($row) => is_array($row))
            ->map(function (array $row) {
                $uid = trim((string) ($row['activity_uid'] ?? ''));
                if ($uid === '') {
                    $uid = (string) Str::ulid();
                }

                return [
                    'activity_uid' => $uid,
                    'day' => trim((string) ($row['day'] ?? '')),
                    'activity' => trim((string) ($row['activity'] ?? '')),
                    'location' => trim((string) ($row['location'] ?? '')),
                    'notes_inclusion' => trim((string) ($row['notes_inclusion'] ?? '')),
                    'team_member_ids' => collect(is_array($row['team_member_ids'] ?? null) ? $row['team_member_ids'] : [])
                        ->map(static fn ($id) => trim((string) $id))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),
                ];
            })
            ->filter(function (array $row) {
                return $row['day'] !== ''
                    || $row['activity'] !== ''
                    || $row['location'] !== ''
                    || $row['notes_inclusion'] !== ''
                    || $row['team_member_ids'] !== [];
            })
            ->values();

        $desiredRows = [];
        foreach ($activities as $activity) {
            $assigneeIds = collect($activity['team_member_ids'])
                ->values();

            foreach ($assigneeIds as $assigneeId) {
                $desiredRows[] = [
                    'source_document_id' => $weeklyPlan->id,
                    'source_user_id' => $weeklyPlan->user_id,
                    'assignee_user_id' => (string) $assigneeId,
                    'period_id' => $weeklyPlan->period_id,
                    'activity_uid' => (string) $activity['activity_uid'],
                    'status' => $status,
                    'activity_payload' => json_encode($activity),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if ($desiredRows !== []) {
            WeeklyActivityAssignment::query()->upsert(
                $desiredRows,
                ['source_document_id', 'assignee_user_id', 'activity_uid'],
                ['source_user_id', 'period_id', 'status', 'activity_payload', 'updated_at']
            );
        }

        $desiredKeys = collect($desiredRows)
            ->map(fn ($row) => $row['assignee_user_id'].'|'.$row['activity_uid'])
            ->unique()
            ->all();

        $existingRows = WeeklyActivityAssignment::query()
            ->where('source_document_id', $weeklyPlan->id)
            ->get(['id', 'assignee_user_id', 'activity_uid']);

        $staleIds = $existingRows
            ->filter(function (WeeklyActivityAssignment $assignment) use ($desiredKeys) {
                $key = (string) $assignment->assignee_user_id.'|'.(string) $assignment->activity_uid;

                return ! in_array($key, $desiredKeys, true);
            })
            ->pluck('id')
            ->all();

        if ($staleIds !== []) {
            WeeklyActivityAssignment::query()->whereIn('id', $staleIds)->delete();
        }
    }

    public function clearForSourceDocument(Document $sourceDocument): void
    {
        WeeklyActivityAssignment::query()
            ->where('source_document_id', $sourceDocument->id)
            ->delete();
    }

    /**
     * @return Collection<int, WeeklyActivityAssignment>
     */
    public function assignmentsForUser(string $userId, ?string $periodId = null): Collection
    {
        return $this->assignmentsForUsers([$userId], $periodId);
    }

    /**
     * @param  array<int, string>  $userIds
     * @return Collection<int, WeeklyActivityAssignment>
     */
    public function assignmentsForUsers(array $userIds, ?string $periodId = null): Collection
    {
        $normalizedUserIds = collect($userIds)
            ->map(static fn ($id) => trim((string) $id))
            ->filter()
            ->unique()
            ->values();

        if ($normalizedUserIds->isEmpty()) {
            return collect();
        }

        $query = WeeklyActivityAssignment::query()
            ->with(['sourceUser:id,name', 'sourceDocument:id,title,user_id,type,state,period_id', 'assignee:id,name'])
            ->whereIn('assignee_user_id', $normalizedUserIds->all())
            ->whereColumn('source_user_id', '!=', 'assignee_user_id')
            ->orderByDesc('updated_at');

        if ($periodId) {
            $query->where('period_id', $periodId);
        }

        return $query->get();
    }
}
