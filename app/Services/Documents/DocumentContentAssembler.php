<?php

namespace App\Services\Documents;

use App\Models\ActionLog;
use App\Models\Document;
use App\Models\MonthlyReport;
use App\Models\WeeklyPlan;
use App\Models\WeeklyReport;
use Carbon\CarbonInterface;
use Throwable;

class DocumentContentAssembler
{
    public function assembleForDocument(Document $document): array
    {
        return match ($document->type) {
            Document::TYPE_WEEKLY_PLAN => $this->assembleWeeklyPlanContent(
                WeeklyPlan::query()
                    ->with(['activities.assignees', 'meetings.participants', 'budgetItems'])
                    ->find((string) $document->id)
            ),
            Document::TYPE_WEEKLY_REPORT => $this->assembleWeeklyReportContent(
                WeeklyReport::query()
                    ->with([
                        'activities',
                        'meetings.participants',
                        'achievements',
                        'challenges',
                        'budgetStatuses',
                    ])
                    ->find((string) $document->id)
            ),
            Document::TYPE_MONTHLY_REPORT => $this->assembleMonthlyReportContent(
                MonthlyReport::query()
                    ->with(['plannedItems', 'offPlanItems'])
                    ->find((string) $document->id)
            ),
            Document::TYPE_ACTION_LOG => $this->assembleActionLogContent(
                ActionLog::query()
                    ->with(['tasks.assignees', 'tasks.history'])
                    ->find((string) $document->id)
            ),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function assembleWeeklyPlanContent(?WeeklyPlan $weeklyPlan): array
    {
        if (! $weeklyPlan) {
            return [];
        }

        $plannedActivities = [];
        foreach ($weeklyPlan->activities as $activity) {
            $plannedActivities[] = [
                'activity_uid' => (string) $activity->activity_uid,
                'day' => (string) ($activity->day ?? ''),
                'activity' => (string) ($activity->activity ?? ''),
                'location' => (string) ($activity->location ?? ''),
                'notes_inclusion' => $this->sanitizeNotesInclusion((string) ($activity->notes_inclusion ?? '')),
                'carry_over_source_weekly_report_id' => $activity->carry_over_source_weekly_report_id,
                'team_member_ids' => $activity->assignees->pluck('id')->map(fn ($id) => (string) $id)->all(),
            ];
        }

        $keyMeetings = [];
        foreach ($weeklyPlan->meetings as $meeting) {
            $keyMeetings[] = [
                'meeting_uid' => (string) $meeting->meeting_uid,
                'date' => $this->formatMeetingDate($meeting->date),
                'meeting' => (string) ($meeting->meeting ?? ''),
                'status' => (string) ($meeting->status ?? ''),
                'location' => (string) ($meeting->location ?? ''),
                'reactions_notes' => (string) ($meeting->reactions_notes ?? ''),
                'source_type' => (string) ($meeting->source_type ?? ''),
                'source_owner_user_id' => (string) ($meeting->source_owner_user_id ?? ''),
                'is_readonly_for_current_user' => (bool) $meeting->is_readonly_for_current_user,
                'participant_user_ids' => $meeting->participants->pluck('id')->map(fn ($id) => (string) $id)->all(),
            ];
        }

        $budget = [];
        foreach ($weeklyPlan->budgetItems as $item) {
            $budget[] = [
                'budget_uid' => (string) $item->budget_uid,
                'item' => (string) ($item->item ?? ''),
                'description_purpose' => (string) ($item->description_purpose ?? ''),
                'amount' => (string) ($item->amount_raw ?? ''),
            ];
        }

        return [
            'objective' => [
                'reflection_progress' => (string) ($weeklyPlan->objective_reflection_progress ?? ''),
            ],
            'planned_activities' => $plannedActivities,
            'key_meetings' => $keyMeetings,
            'budget' => $budget,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function assembleWeeklyReportContent(?WeeklyReport $weeklyReport): array
    {
        if (! $weeklyReport) {
            return [];
        }

        $activityProgress = [];
        foreach ($weeklyReport->activities as $row) {
            $activityProgress[] = [
                'activity_uid' => (string) ($row->activity_uid ?? ''),
                'activity_output' => (string) ($row->activity_output ?? ''),
                'off_plan_activity' => (string) ($row->off_plan_activity ?? ''),
                'progress' => (string) ($row->progress ?? ''),
                'remarks_challenges' => (string) ($row->remarks_challenges ?? ''),
                'next_step' => (string) ($row->next_step ?? ''),
                'carry_to_next_plan' => (bool) $row->carry_to_next_plan,
            ];
        }

        $keyMeetings = [];
        foreach ($weeklyReport->meetings as $meeting) {
            $keyMeetings[] = [
                'meeting_uid' => (string) ($meeting->meeting_uid ?? ''),
                'date' => $this->formatMeetingDate($meeting->date),
                'meeting' => (string) ($meeting->meeting ?? ''),
                'status' => (string) ($meeting->status ?? ''),
                'location' => (string) ($meeting->location ?? ''),
                'reactions_notes' => (string) ($meeting->reactions_notes ?? ''),
                'source_type' => (string) ($meeting->source_type ?? ''),
                'source_plan_id' => (string) ($meeting->source_plan_id ?? ''),
                'source_owner_user_id' => (string) ($meeting->source_owner_user_id ?? ''),
                'participant_user_ids' => $meeting->participants->pluck('id')->map(fn ($id) => (string) $id)->all(),
            ];
        }

        $keyAchievements = [];
        foreach ($weeklyReport->achievements as $row) {
            $achievement = trim((string) ($row->achievement ?? ''));
            if ($achievement !== '') {
                $keyAchievements[] = $achievement;
            }
        }

        $challengesMitigation = [];
        foreach ($weeklyReport->challenges as $row) {
            $challenge = trim((string) ($row->challenge ?? ''));
            $actionTakenPlanned = trim((string) ($row->action_taken_planned ?? ''));
            if ($challenge !== '' || $actionTakenPlanned !== '') {
                $challengesMitigation[] = [
                    'challenge' => $challenge,
                    'action_taken_planned' => $actionTakenPlanned,
                ];
            }
        }

        $budgetStatuses = [];
        foreach ($weeklyReport->budgetStatuses as $row) {
            $budgetStatuses[] = [
                'status' => (string) ($row->status ?? ''),
                'notes' => (string) ($row->notes ?? ''),
            ];
        }

        return $this->normalizeWeeklyReportContent([
            'summary' => (string) ($weeklyReport->summary ?? ''),
            'target_accomplished_status' => (string) ($weeklyReport->target_accomplished_status ?? ''),
            'activity_progress' => $activityProgress,
            'key_meetings' => $keyMeetings,
            'key_achievements' => $keyAchievements,
            'challenges_mitigation' => $challengesMitigation,
            'budget_statuses' => $budgetStatuses,
        ]);
    }

    /**
     * Align weekly plan content for forms, show, and sync.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public function normalizeWeeklyPlanContent(array $content): array
    {
        $plannedActivities = is_array($content['planned_activities'] ?? null) ? $content['planned_activities'] : [];
        $content['planned_activities'] = array_values(array_map(function ($row) {
            if (! is_array($row)) {
                return $row;
            }

            if (array_key_exists('notes_inclusion', $row)) {
                $row['notes_inclusion'] = $this->sanitizeNotesInclusion((string) ($row['notes_inclusion'] ?? ''));
            }

            return $row;
        }, $plannedActivities));

        return $content;
    }

    /**
     * Decode accidental URL-encoding and import artifacts in notes/inclusion fields.
     */
    public function sanitizeNotesInclusion(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (preg_match('/%[0-9A-Fa-f]{2}/', $value)) {
            $decoded = rawurldecode($value);
            if (preg_match('/%[0-9A-Fa-f]{2}/', $decoded)) {
                $decoded = rawurldecode($decoded);
            }
            $value = $decoded;
        }

        if (preg_match('/^(?:notes_)?(?:include|inclusion):\s*/i', $value)) {
            $value = preg_replace('/^(?:notes_)?(?:include|inclusion):\s*/i', '', $value);
        }

        return trim($value);
    }

    /**
     * Align weekly report content keys/shapes used by forms, show, and sync.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public function normalizeWeeklyReportContent(array $content): array
    {
        $keyAchievements = [];
        $rawAchievements = $content['key_achievements'] ?? $content['achievements'] ?? [];
        if (is_array($rawAchievements)) {
            foreach ($rawAchievements as $item) {
                if (is_string($item)) {
                    $text = $this->sanitizeWeeklyReportText($item);
                    if ($text !== '') {
                        $keyAchievements[] = $text;
                    }

                    continue;
                }

                if (is_array($item)) {
                    $text = $this->sanitizeWeeklyReportText((string) ($item['achievement'] ?? ''));
                    if ($text !== '') {
                        $keyAchievements[] = $text;
                    }
                }
            }
        }
        $content['key_achievements'] = array_values($keyAchievements);
        unset($content['achievements']);

        $challengesMitigation = [];
        $rawChallenges = $content['challenges_mitigation'] ?? $content['challenges'] ?? [];
        if (is_array($rawChallenges)) {
            foreach ($rawChallenges as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $challenge = $this->sanitizeWeeklyReportText((string) ($row['challenge'] ?? ''));
                $actionTakenPlanned = $this->sanitizeWeeklyReportText((string) ($row['action_taken_planned'] ?? ''));
                if ($challenge !== '' || $actionTakenPlanned !== '') {
                    $challengesMitigation[] = [
                        'challenge' => $challenge,
                        'action_taken_planned' => $actionTakenPlanned,
                    ];
                }
            }
        }
        $content['challenges_mitigation'] = array_values($challengesMitigation);
        unset($content['challenges']);

        $rawBudgetStatuses = $content['budget_statuses'] ?? $content['budget_status'] ?? [];
        $content['budget_statuses'] = is_array($rawBudgetStatuses) ? array_values($rawBudgetStatuses) : [];
        unset($content['budget_status']);

        return $content;
    }

    private function sanitizeWeeklyReportText(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return '';
        }

        // Strip accidental Blade placeholder artifacts rendered as literal text.
        if (str_starts_with($trimmed, '@__raw_block_') && str_contains($trimmed, '{{ old(')) {
            return '';
        }

        return $trimmed;
    }

    /**
     * @return array<string, mixed>
     */
    public function assembleMonthlyReportContent(?MonthlyReport $monthlyReport): array
    {
        if (! $monthlyReport) {
            return [];
        }

        $planned = [];
        foreach ($monthlyReport->plannedItems as $row) {
            $planned[] = [
                'year_plan_activity_id' => (string) ($row->year_plan_activity_id ?? ''),
                'implementation_status' => (string) ($row->implementation_status ?? ''),
                'achievements' => (string) ($row->achievements ?? ''),
                'challenges' => (string) ($row->challenges ?? ''),
                'next_steps' => (string) ($row->next_steps ?? ''),
            ];
        }

        $offPlan = [];
        foreach ($monthlyReport->offPlanItems as $row) {
            $offPlan[] = [
                'title' => (string) ($row->title ?? ''),
                'description' => (string) ($row->description ?? ''),
                'status' => (string) ($row->status ?? ''),
                'notes' => (string) ($row->notes ?? ''),
            ];
        }

        return [
            'month_summary' => (string) ($monthlyReport->month_summary ?? ''),
            'planned' => $planned,
            'off_plan' => $offPlan,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function assembleActionLogContent(?ActionLog $actionLog): array
    {
        if (! $actionLog) {
            return [];
        }

        $tasks = [];
        foreach ($actionLog->tasks as $task) {
            $history = [];
            foreach ($task->history as $historyRow) {
                $history[] = [
                    'period_id' => (string) ($historyRow->period_id ?? ''),
                    'period_label' => (string) ($historyRow->period_label ?? ''),
                    'updated_by' => (string) ($historyRow->updated_by ?? ''),
                    'from_progress' => (string) ($historyRow->from_progress ?? ''),
                    'to_progress' => (string) ($historyRow->to_progress ?? ''),
                    'notes_progress' => (string) ($historyRow->notes_progress ?? ''),
                    'status_reason' => (string) ($historyRow->status_reason ?? ''),
                    'changed_at' => (string) ($historyRow->changed_at ?? ''),
                    'source' => (string) ($historyRow->source ?? ''),
                ];
            }

            $tasks[] = [
                'task_uid' => (string) $task->task_uid,
                'task_no' => (int) $task->task_no,
                'task' => (string) ($task->task ?? ''),
                'progress' => (string) ($task->progress ?? ''),
                'due_date' => (string) ($task->due_date ?? ''),
                'compilation_date' => (string) ($task->compilation_date ?? ''),
                'notes_progress' => (string) ($task->notes_progress ?? ''),
                'status_reason' => (string) ($task->status_reason ?? ''),
                'responsible_user_ids' => $task->assignees->pluck('id')->map(fn ($id) => (string) $id)->all(),
                'history' => $history,
            ];
        }

        $meta = [];
        if ($actionLog->submission_email_sent) {
            $meta['submission_email_sent'] = true;
        }

        return [
            'tasks' => $tasks,
            'meta' => $meta,
        ];
    }

    private function formatMeetingDate(mixed $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        if ($date instanceof CarbonInterface) {
            return $date->toDateString();
        }

        $trimmed = trim((string) $date);
        if ($trimmed === '') {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($trimmed)->toDateString();
        } catch (Throwable) {
            return $trimmed;
        }
    }
}
