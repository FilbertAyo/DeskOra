<?php

namespace App\Services\Documents;

use App\Models\ActionLog;
use App\Models\ActionLogTask;
use App\Models\Document;
use App\Models\MonthlyReport;
use App\Models\MonthlyReportOffPlanItem;
use App\Models\MonthlyReportPlannedItem;
use App\Models\WeeklyPlan;
use App\Models\WeeklyPlanActivity;
use App\Models\WeeklyPlanBudgetItem;
use App\Models\WeeklyPlanBudgetReview;
use App\Models\WeeklyPlanMeeting;
use App\Models\WeeklyReport;
use App\Models\WeeklyReportAchievement;
use App\Models\WeeklyReportActivityProgress;
use App\Models\WeeklyReportBudgetStatus;
use App\Models\WeeklyReportChallenge;
use App\Models\WeeklyReportMeeting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DocumentDomainSyncService
{
    public function syncFromDocument(Document $document): void
    {
        $callback = function () use ($document): void {
            match ($document->type) {
                Document::TYPE_WEEKLY_PLAN => $this->syncWeeklyPlan($document),
                Document::TYPE_WEEKLY_REPORT => $this->syncWeeklyReport($document),
                Document::TYPE_MONTHLY_REPORT => $this->syncMonthlyReport($document),
                Document::TYPE_ACTION_LOG => $this->syncActionLog($document),
                default => null,
            };
        };

        // Participate in the caller's transaction so a later failure rolls back
        // the domain sync instead of leaving a partially saved weekly plan/report.
        if (DB::transactionLevel() > 0) {
            $callback();

            return;
        }

        DB::transaction($callback, 3);
    }

    /**
     * Create or update a domain row while forcing its primary key to match the
     * given Document id.
     *
     * `updateOrCreate(['id' => $id], $values)` cannot be used here: the domain
     * models do not list `id` as fillable, so the matching attribute is dropped
     * when a new instance is built and the `HasShortId` trait then assigns a fresh
     * (different) id. That mismatch makes the saved row unreachable by the
     * Document id and surfaces as "page not found" right after saving.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TModel>  $modelClass
     * @param  array<string, mixed>  $values
     * @return TModel
     */
    private function upsertWithId(string $modelClass, string $id, array $values)
    {
        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = $modelClass::query()->find($id) ?? new $modelClass;
        $model->setAttribute($model->getKeyName(), $id);
        $model->fill($values);
        $model->save();

        return $model;
    }

    private function syncWeeklyPlan(Document $document): void
    {
        $content = is_array($document->content) ? $document->content : [];
        $objective = is_array($content['objective'] ?? null) ? $content['objective'] : [];

        $weeklyPlan = $this->upsertWithId(WeeklyPlan::class, (string) $document->id, [
            'user_id' => (string) $document->user_id,
            'period_id' => $document->period_id ? (string) $document->period_id : null,
            'title' => $document->title,
            'state' => $document->state ?? WeeklyPlan::STATE_DRAFT,
            'main_target' => $document->main_target,
            'objective_reflection_progress' => trim((string) ($objective['reflection_progress'] ?? '')) ?: null,
            'review_rejection_reason' => $document->review_rejection_reason,
            'budget_review_status' => $document->budget_review_status,
            'budget_reviewed_by' => $document->budget_reviewed_by,
            'budget_reviewed_at' => $document->budget_reviewed_at,
            'budget_provided_amount' => $document->budget_provided_amount,
            'budget_review_note' => $document->budget_review_note,
        ]);

        $weeklyPlan->activities()->delete();
        $weeklyPlan->meetings()->delete();
        $weeklyPlan->budgetItems()->delete();

        $activityRows = is_array($content['planned_activities'] ?? null) ? $content['planned_activities'] : [];
        foreach (array_values($activityRows) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $carryOverSourceWeeklyReportId = $this->resolveCarryOverSourceWeeklyReportId($row);
            // Defensive guard: stale clients may still send the retired legacy key.
            $row = Arr::except($row, ['carry_over_source_report_id']);

            $activity = WeeklyPlanActivity::query()->create([
                'weekly_plan_id' => (string) $weeklyPlan->id,
                'activity_uid' => trim((string) ($row['activity_uid'] ?? '')) ?: (string) Str::ulid(),
                'day' => trim((string) ($row['day'] ?? '')) ?: null,
                'activity' => trim((string) ($row['activity'] ?? '')) ?: null,
                'location' => trim((string) ($row['location'] ?? '')) ?: null,
                'notes_inclusion' => app(DocumentContentAssembler::class)->sanitizeNotesInclusion((string) ($row['notes_inclusion'] ?? '')) ?: null,
                'carry_over_source_weekly_report_id' => $carryOverSourceWeeklyReportId,
                'sort_order' => $index,
            ]);
            $assignees = collect(is_array($row['team_member_ids'] ?? null) ? $row['team_member_ids'] : [])
                ->map(fn ($id) => trim((string) $id))
                ->filter()
                ->unique()
                ->values()
                ->all();
            $activity->assignees()->sync($assignees);
        }

        $meetingRows = is_array($content['key_meetings'] ?? null) ? $content['key_meetings'] : [];
        foreach (array_values($meetingRows) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $meeting = WeeklyPlanMeeting::query()->create([
                'weekly_plan_id' => (string) $weeklyPlan->id,
                'meeting_uid' => trim((string) ($row['meeting_uid'] ?? '')) ?: (string) Str::ulid(),
                'date' => trim((string) ($row['date'] ?? '')) ?: null,
                'meeting' => trim((string) ($row['meeting'] ?? '')) ?: null,
                'status' => trim((string) ($row['status'] ?? '')) ?: null,
                'location' => trim((string) ($row['location'] ?? '')) ?: null,
                'reactions_notes' => trim((string) ($row['reactions_notes'] ?? '')) ?: null,
                'source_type' => trim((string) ($row['source_type'] ?? '')) ?: null,
                'source_owner_user_id' => trim((string) ($row['source_owner_user_id'] ?? '')) ?: null,
                'is_readonly_for_current_user' => (bool) ($row['is_readonly_for_current_user'] ?? false),
                'sort_order' => $index,
            ]);
            $participants = collect(is_array($row['participant_user_ids'] ?? null) ? $row['participant_user_ids'] : [])
                ->map(fn ($id) => trim((string) $id))
                ->filter()
                ->unique()
                ->values()
                ->all();
            $meeting->participants()->sync($participants);
        }

        $reviewRows = collect(is_array($document->budget_review_items) ? $document->budget_review_items : [])
            ->filter(fn ($row) => is_array($row) && trim((string) ($row['budget_uid'] ?? '')) !== '')
            ->keyBy(fn ($row) => trim((string) $row['budget_uid']));

        $budgetRows = is_array($content['budget'] ?? null) ? $content['budget'] : [];
        foreach (array_values($budgetRows) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $item = trim((string) ($row['item'] ?? ''));
            $description = trim((string) ($row['description_purpose'] ?? ''));
            $amountRaw = trim((string) ($row['amount'] ?? ''));
            if ($item === '' && $description === '' && $amountRaw === '') {
                continue;
            }

            $budgetUid = trim((string) ($row['budget_uid'] ?? '')) ?: ('legacy_'.$document->id.'_'.$index);
            $budgetItem = WeeklyPlanBudgetItem::query()->create([
                'weekly_plan_id' => (string) $weeklyPlan->id,
                'budget_uid' => $budgetUid,
                'item' => $item !== '' ? $item : null,
                'description_purpose' => $description !== '' ? $description : null,
                'amount_raw' => $amountRaw !== '' ? $amountRaw : null,
                'amount_numeric' => Document::parseBudgetAmount($amountRaw),
                'sort_order' => $index,
            ]);

            $review = $reviewRows->get($budgetUid);
            $decision = in_array(($review['decision'] ?? null), [
                Document::BUDGET_REVIEW_PENDING,
                Document::BUDGET_REVIEW_APPROVED,
                Document::BUDGET_REVIEW_REJECTED,
            ], true) ? $review['decision'] : Document::BUDGET_REVIEW_PENDING;

            $approvalStage = in_array(($review['approval_stage'] ?? null), Document::budgetReviewStages(), true)
                ? $review['approval_stage']
                : ($decision === Document::BUDGET_REVIEW_REJECTED
                    ? Document::BUDGET_REVIEW_STAGE_REJECTED
                    : Document::BUDGET_REVIEW_STAGE_PENDING);

            WeeklyPlanBudgetReview::query()->create([
                'weekly_plan_budget_item_id' => (string) $budgetItem->id,
                'decision' => $decision,
                'approval_stage' => $approvalStage,
                'provided_amount' => (float) ($review['provided_amount'] ?? 0),
                'review_provided_amount' => is_array($review) && array_key_exists('review_provided_amount', $review) && $review['review_provided_amount'] !== null
                    ? (float) $review['review_provided_amount']
                    : null,
                'approve_provided_amount' => is_array($review) && array_key_exists('approve_provided_amount', $review) && $review['approve_provided_amount'] !== null
                    ? (float) $review['approve_provided_amount']
                    : null,
                'note' => isset($review['note']) ? trim((string) $review['note']) : null,
                'reviewed_by' => $review['reviewed_by'] ?? null,
                'reviewed_at' => isset($review['reviewed_at']) && trim((string) $review['reviewed_at']) !== ''
                    ? $review['reviewed_at']
                    : null,
                'approved_by' => $review['approve_stage_by'] ?? null,
                'approved_at' => isset($review['approve_stage_at']) && trim((string) $review['approve_stage_at']) !== ''
                    ? $review['approve_stage_at']
                    : null,
                'authorized_by' => $review['authorize_stage_by'] ?? null,
                'authorized_at' => isset($review['authorize_stage_at']) && trim((string) $review['authorize_stage_at']) !== ''
                    ? $review['authorize_stage_at']
                    : null,
            ]);
        }
    }

    private function syncWeeklyReport(Document $document): void
    {
        $content = is_array($document->content) ? $document->content : [];
        $content = app(DocumentContentAssembler::class)->normalizeWeeklyReportContent($content);

        $weeklyReport = $this->upsertWithId(WeeklyReport::class, (string) $document->id, [
            'user_id' => (string) $document->user_id,
            'period_id' => $document->period_id ? (string) $document->period_id : null,
            'title' => $document->title,
            'state' => $document->state ?? WeeklyPlan::STATE_DRAFT,
            'summary' => trim((string) ($content['summary'] ?? '')) ?: null,
            'target_accomplished' => $document->target_accomplished,
            'target_accomplished_status' => trim((string) ($content['target_accomplished_status'] ?? '')) ?: null,
            'target_explanation' => $document->target_explanation,
            'review_rejection_reason' => $document->review_rejection_reason,
        ]);

        $weeklyReport->activities()->delete();
        $weeklyReport->meetings()->delete();
        $weeklyReport->achievements()->delete();
        $weeklyReport->challenges()->delete();
        $weeklyReport->budgetStatuses()->delete();

        $activityRows = is_array($content['activity_progress'] ?? null) ? $content['activity_progress'] : [];
        foreach (array_values($activityRows) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            WeeklyReportActivityProgress::query()->create([
                'weekly_report_id' => (string) $weeklyReport->id,
                'activity_uid' => trim((string) ($row['activity_uid'] ?? '')) ?: null,
                'activity_output' => trim((string) ($row['activity_output'] ?? '')) ?: null,
                'off_plan_activity' => trim((string) ($row['off_plan_activity'] ?? '')) ?: null,
                'progress' => trim((string) ($row['progress'] ?? '')) ?: null,
                'remarks_challenges' => trim((string) ($row['remarks_challenges'] ?? '')) ?: null,
                'next_step' => trim((string) ($row['next_step'] ?? '')) ?: null,
                'carry_to_next_plan' => (bool) ($row['carry_to_next_plan'] ?? false),
                'sort_order' => $index,
            ]);
        }

        $meetingRows = is_array($content['key_meetings'] ?? null) ? $content['key_meetings'] : [];
        foreach (array_values($meetingRows) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $meeting = WeeklyReportMeeting::query()->create([
                'weekly_report_id' => (string) $weeklyReport->id,
                'meeting_uid' => trim((string) ($row['meeting_uid'] ?? '')) ?: null,
                'date' => trim((string) ($row['date'] ?? '')) ?: null,
                'meeting' => trim((string) ($row['meeting'] ?? '')) ?: null,
                'status' => trim((string) ($row['status'] ?? '')) ?: null,
                'location' => trim((string) ($row['location'] ?? '')) ?: null,
                'reactions_notes' => trim((string) ($row['reactions_notes'] ?? '')) ?: null,
                'source_type' => trim((string) ($row['source_type'] ?? '')) ?: null,
                'source_plan_id' => trim((string) ($row['source_plan_id'] ?? '')) ?: null,
                'source_owner_user_id' => trim((string) ($row['source_owner_user_id'] ?? '')) ?: null,
                'is_readonly_for_current_user' => (bool) ($row['is_readonly_for_current_user'] ?? false),
                'sort_order' => $index,
            ]);

            $participants = collect(is_array($row['participant_user_ids'] ?? null) ? $row['participant_user_ids'] : [])
                ->map(fn ($id) => trim((string) $id))
                ->filter()
                ->unique()
                ->values()
                ->all();
            $meeting->participants()->sync($participants);
        }

        $achievementRows = is_array($content['key_achievements'] ?? null) ? $content['key_achievements'] : [];
        foreach (array_values($achievementRows) as $index => $row) {
            if (is_array($row)) {
                $achievement = trim((string) ($row['achievement'] ?? ''));
            } else {
                $achievement = trim((string) $row);
            }
            if ($achievement === '') {
                continue;
            }
            WeeklyReportAchievement::query()->create([
                'weekly_report_id' => (string) $weeklyReport->id,
                'achievement' => $achievement,
                'sort_order' => $index,
            ]);
        }

        $challengeRows = is_array($content['challenges_mitigation'] ?? null) ? $content['challenges_mitigation'] : [];
        foreach (array_values($challengeRows) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            WeeklyReportChallenge::query()->create([
                'weekly_report_id' => (string) $weeklyReport->id,
                'challenge' => trim((string) ($row['challenge'] ?? '')) ?: null,
                'action_taken_planned' => trim((string) ($row['action_taken_planned'] ?? '')) ?: null,
                'sort_order' => $index,
            ]);
        }

        $budgetStatusRows = is_array($content['budget_statuses'] ?? null) ? $content['budget_statuses'] : [];
        foreach (array_values($budgetStatusRows) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            WeeklyReportBudgetStatus::query()->create([
                'weekly_report_id' => (string) $weeklyReport->id,
                'status' => trim((string) ($row['status'] ?? '')) ?: null,
                'notes' => trim((string) ($row['notes'] ?? '')) ?: null,
                'sort_order' => $index,
            ]);
        }
    }

    private function syncMonthlyReport(Document $document): void
    {
        $content = is_array($document->content) ? $document->content : [];

        $monthlyReport = $this->upsertWithId(MonthlyReport::class, (string) $document->id, [
            'user_id' => (string) $document->user_id,
            'month' => (int) ($document->month ?? 0),
            'year' => (int) ($document->year ?? 0),
            'title' => $document->title,
            'state' => $document->state ?? WeeklyPlan::STATE_DRAFT,
            'file_path' => $document->file_path,
            'attachment' => $document->attachment,
            'file_size' => $document->file_size,
            'original_filename' => $document->original_filename,
            'month_summary' => trim((string) ($content['month_summary'] ?? '')) ?: null,
        ]);

        $monthlyReport->plannedItems()->delete();
        $monthlyReport->offPlanItems()->delete();

        $plannedRows = is_array($content['planned'] ?? null) ? $content['planned'] : [];
        foreach (array_values($plannedRows) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            MonthlyReportPlannedItem::query()->create([
                'monthly_report_id' => (string) $monthlyReport->id,
                'year_plan_activity_id' => trim((string) ($row['year_plan_activity_id'] ?? '')) ?: null,
                'implementation_status' => trim((string) ($row['implementation_status'] ?? '')) ?: null,
                'achievements' => trim((string) ($row['achievements'] ?? '')) ?: null,
                'challenges' => trim((string) ($row['challenges'] ?? '')) ?: null,
                'next_steps' => trim((string) ($row['next_steps'] ?? '')) ?: null,
                'sort_order' => $index,
            ]);
        }

        $offPlanRows = is_array($content['off_plan'] ?? null) ? $content['off_plan'] : [];
        foreach (array_values($offPlanRows) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            MonthlyReportOffPlanItem::query()->create([
                'monthly_report_id' => (string) $monthlyReport->id,
                'title' => trim((string) ($row['title'] ?? '')) ?: null,
                'description' => trim((string) ($row['description'] ?? '')) ?: null,
                'status' => trim((string) ($row['status'] ?? '')) ?: null,
                'notes' => trim((string) ($row['notes'] ?? '')) ?: null,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveCarryOverSourceWeeklyReportId(array $row): ?string
    {
        $explicitId = trim((string) ($row['carry_over_source_weekly_report_id'] ?? ''));
        if ($explicitId !== '') {
            return $explicitId;
        }

        $legacyDocumentId = trim((string) ($row['carry_over_source_report_id'] ?? ''));
        if ($legacyDocumentId === '') {
            return null;
        }

        return WeeklyReport::query()
            ->where('id', $legacyDocumentId)
            ->value('id');
    }

    private function syncActionLog(Document $document): void
    {
        $content = is_array($document->content) ? $document->content : [];
        $taskRows = is_array($content['tasks'] ?? null) ? $content['tasks'] : [];

        $meta = is_array($content['meta'] ?? null) ? $content['meta'] : [];
        $existingActionLog = ActionLog::query()->find((string) $document->id);
        $submissionEmailSent = (bool) ($meta['submission_email_sent'] ?? false)
            || (bool) ($existingActionLog?->submission_email_sent ?? false);

        $removedTaskUids = [];
        if (array_key_exists('removed_task_uids', $meta) && is_array($meta['removed_task_uids'])) {
            $removedTaskUids = array_values(array_filter(array_map(
                static fn ($uid) => trim((string) $uid),
                $meta['removed_task_uids']
            )));
        } elseif (is_array($existingActionLog?->removed_task_uids)) {
            $removedTaskUids = $existingActionLog->removed_task_uids;
        }

        $actionLog = $this->upsertWithId(ActionLog::class, (string) $document->id, [
            'user_id' => (string) $document->user_id,
            'period_id' => $document->period_id ? (string) $document->period_id : null,
            'title' => $document->title,
            'state' => $document->state ?? WeeklyPlan::STATE_DRAFT,
            'submission_email_sent' => $submissionEmailSent,
            'removed_task_uids' => $removedTaskUids !== [] ? $removedTaskUids : null,
        ]);

        $actionLog->load('tasks.history');

        $existingByUid = $actionLog->tasks->keyBy(fn ($t) => (string) $t->task_uid);
        $incomingUids = [];

        foreach (array_values($taskRows) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $taskUid = trim((string) ($row['task_uid'] ?? '')) ?: ('task_'.Str::lower((string) Str::ulid()));
            $incomingUids[] = $taskUid;

            $attributes = [
                'task_no' => (int) ($row['task_no'] ?? ($index + 1)),
                'task' => trim((string) ($row['task'] ?? '')) ?: null,
                'progress' => trim((string) ($row['progress'] ?? '')) ?: 'Not Started',
                'due_date' => trim((string) ($row['due_date'] ?? '')) ?: null,
                'compilation_date' => trim((string) ($row['compilation_date'] ?? '')) ?: null,
                'notes_progress' => trim((string) ($row['notes_progress'] ?? '')) ?: null,
                'status_reason' => trim((string) ($row['status_reason'] ?? '')) ?: null,
                'sort_order' => $index,
            ];

            $task = $existingByUid->get($taskUid);
            if ($task) {
                $task->update($attributes);
            } else {
                $task = ActionLogTask::query()->create(array_merge($attributes, [
                    'action_log_id' => (string) $actionLog->id,
                    'task_uid' => $taskUid,
                ]));
            }

            $assigneeIds = collect(is_array($row['responsible_user_ids'] ?? null) ? $row['responsible_user_ids'] : [])
                ->map(fn ($id) => trim((string) $id))
                ->filter()
                ->unique()
                ->values()
                ->all();
            $task->assignees()->sync($assigneeIds);

            $task->history()->delete();
            $historyRows = is_array($row['history'] ?? null) ? $row['history'] : [];
            foreach ($historyRows as $historyRow) {
                if (! is_array($historyRow)) {
                    continue;
                }

                $reassignedFrom = is_array($historyRow['reassigned_from'] ?? null)
                    ? array_values(array_filter(array_map(static fn ($id) => trim((string) $id), $historyRow['reassigned_from'])))
                    : null;
                $reassignedTo = trim((string) ($historyRow['reassigned_to'] ?? ''));
                $reassignedToIds = $reassignedTo !== '' ? [$reassignedTo] : null;

                $task->history()->create([
                    'period_id' => trim((string) ($historyRow['period_id'] ?? '')) ?: null,
                    'period_label' => trim((string) ($historyRow['period_label'] ?? '')) ?: null,
                    'updated_by' => trim((string) ($historyRow['updated_by'] ?? '')) ?: null,
                    'from_progress' => trim((string) ($historyRow['from_progress'] ?? '')) ?: null,
                    'to_progress' => trim((string) ($historyRow['to_progress'] ?? '')) ?: null,
                    'notes_progress' => trim((string) ($historyRow['notes_progress'] ?? '')) ?: null,
                    'status_reason' => trim((string) ($historyRow['status_reason'] ?? '')) ?: null,
                    'changed_at' => trim((string) ($historyRow['changed_at'] ?? '')) ?: null,
                    'source' => trim((string) ($historyRow['source'] ?? '')) ?: null,
                    'reassigned_from_user_ids' => $reassignedFrom !== [] ? $reassignedFrom : null,
                    'reassigned_to_user_ids' => $reassignedToIds,
                ]);
            }
        }

        if ($incomingUids !== []) {
            $actionLog->tasks()->whereNotIn('task_uid', $incomingUids)->delete();
        } else {
            $actionLog->tasks()->delete();
        }
    }
}
