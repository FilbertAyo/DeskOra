<?php

namespace App\Services;

use App\Models\Document;
use App\Models\WeeklyActivityAssignment;
use App\Models\WeeklyPlan;
use App\Services\Documents\DocumentContentAssembler;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class WeeklyActivityAssignmentService
{
    /** @var list<string> */
    public const PLAN_DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    public function syncForWeeklyPlan(Document $weeklyPlan): void
    {
        if ($weeklyPlan->type !== Document::TYPE_WEEKLY_PLAN) {
            return;
        }

        $weeklyPlan->loadMissing(['user', 'sharedUsers:id']);

        $status = $weeklyPlan->isSubmitted()
            ? WeeklyActivityAssignment::STATUS_LOCKED
            : WeeklyActivityAssignment::STATUS_PREVIEW;

        $sourceWeeklyPlanId = (string) $weeklyPlan->id;
        $domainPlan = WeeklyPlan::query()
            ->with(['activities.assignees'])
            ->find($sourceWeeklyPlanId);

        $sourceRows = [];
        if ($domainPlan) {
            $sourceRows = $domainPlan->activities->map(function ($activity) {
                return [
                    'activity_uid' => (string) $activity->activity_uid,
                    'day' => (string) ($activity->day ?? ''),
                    'activity' => (string) ($activity->activity ?? ''),
                    'location' => (string) ($activity->location ?? ''),
                    'notes_inclusion' => app(DocumentContentAssembler::class)->sanitizeNotesInclusion((string) ($activity->notes_inclusion ?? '')),
                    'team_member_ids' => $activity->assignees->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
                ];
            })->all();
        } else {
            $sourceRows = is_array($weeklyPlan->content['planned_activities'] ?? null) ? $weeklyPlan->content['planned_activities'] : [];
        }

        $activities = collect($sourceRows)
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
                    'notes_inclusion' => app(DocumentContentAssembler::class)->sanitizeNotesInclusion((string) ($row['notes_inclusion'] ?? '')),
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

        $existingRows = WeeklyActivityAssignment::query()
            ->where('source_weekly_plan_id', $sourceWeeklyPlanId)
            ->get(['id', 'assignee_user_id', 'activity_uid']);

        $existingIdsByKey = $existingRows
            ->mapWithKeys(function (WeeklyActivityAssignment $assignment): array {
                $key = (string) $assignment->assignee_user_id.'|'.(string) $assignment->activity_uid;

                return [$key => (string) $assignment->id];
            })
            ->all();

        /** @var array<string, true> $reservedIds */
        $reservedIds = array_fill_keys(array_values($existingIdsByKey), true);

        $desiredRows = [];
        foreach ($activities as $activity) {
            $assigneeIds = collect($activity['team_member_ids'])
                ->values();

            foreach ($assigneeIds as $assigneeId) {
                $rowKey = (string) $assigneeId.'|'.(string) $activity['activity_uid'];
                $rowId = $existingIdsByKey[$rowKey] ?? app(ShortIdGenerator::class)->generateUnique($reservedIds);
                $reservedIds[$rowId] = true;

                $desiredRows[] = [
                    'id' => $rowId,
                    'source_weekly_plan_id' => $sourceWeeklyPlanId,
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
                ['source_weekly_plan_id', 'assignee_user_id', 'activity_uid'],
                ['source_weekly_plan_id', 'source_user_id', 'period_id', 'status', 'activity_payload', 'updated_at']
            );
        }

        $desiredKeys = collect($desiredRows)
            ->map(fn ($row) => $row['assignee_user_id'].'|'.$row['activity_uid'])
            ->unique()
            ->all();

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
        if ($sourceDocument->type !== Document::TYPE_WEEKLY_PLAN) {
            return;
        }

        WeeklyActivityAssignment::query()
            ->where('source_weekly_plan_id', (string) $sourceDocument->id)
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
            ->with([
                'sourceUser:id,name',
                'sourceWeeklyPlan:id,title,user_id,period_id,state',
                'assignee:id,name',
            ])
            ->whereIn('assignee_user_id', $normalizedUserIds->all())
            ->whereColumn('source_user_id', '!=', 'assignee_user_id')
            ->orderByDesc('updated_at');

        if ($periodId) {
            $query->where('period_id', $periodId);
        }

        return $query->get();
    }

    /**
     * @return Collection<int, array{activity: string, source_label: string}>
     */
    public function todaysTasksForUser(string $userId, ?string $periodId, ?string $day = null): Collection
    {
        $day = trim((string) ($day ?? now()->format('l')));
        if (! in_array($day, self::PLAN_DAYS, true)) {
            return collect();
        }

        $tasks = collect();

        if ($periodId) {
            $plan = Document::findWeeklyPlanForUserAndPeriod($periodId, $userId);
            if ($plan) {
                $domainPlan = WeeklyPlan::query()
                    ->with('activities')
                    ->find($plan->id);

                foreach ($domainPlan?->activities ?? [] as $activity) {
                    if ((string) ($activity->day ?? '') !== $day) {
                        continue;
                    }

                    $activityText = trim((string) ($activity->activity ?? ''));
                    if ($activityText === '') {
                        continue;
                    }

                    $tasks->push([
                        'activity' => $activityText,
                        'source_label' => 'Your plan',
                    ]);
                }
            }
        }

        foreach ($this->assignmentsForUser($userId, $periodId) as $assignment) {
            $payload = is_array($assignment->activity_payload ?? null) ? $assignment->activity_payload : [];
            if (trim((string) ($payload['day'] ?? '')) !== $day) {
                continue;
            }

            $activityText = trim((string) ($payload['activity'] ?? ''));
            if ($activityText === '') {
                continue;
            }

            $sourceName = trim((string) ($assignment->sourceUser->name ?? ''));
            $tasks->push([
                'activity' => $activityText,
                'source_label' => $sourceName !== '' ? 'From '.$sourceName : 'Assigned',
            ]);
        }

        return $tasks->values();
    }

    private function resolveSourceWeeklyPlan(Document $weeklyPlan, bool $withActivities = false): ?WeeklyPlan
    {
        if ($weeklyPlan->type !== Document::TYPE_WEEKLY_PLAN) {
            return null;
        }

        $query = WeeklyPlan::query()
            ->where('id', $weeklyPlan->id);

        if ($withActivities) {
            $query->with('activities.assignees:id');
        }

        return $query->first();
    }
}
