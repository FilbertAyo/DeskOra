<?php

namespace App\Console\Commands;

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
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BackfillDocumentDomainTables extends Command
{
    protected $signature = 'documents:backfill-domain-tables
        {--chunk=200 : Batch size}
        {--from-id= : Start from this document ULID}
        {--type=* : Optional document type filters}';

    protected $description = 'Backfill normalized domain tables from legacy documents rows';

    public function handle(): int
    {
        $chunk = max((int) $this->option('chunk'), 10);
        $fromId = $this->option('from-id');
        $typeFilter = collect($this->option('type'))
            ->map(fn ($t) => trim((string) $t))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $supportedTypes = [
            Document::TYPE_WEEKLY_PLAN,
            Document::TYPE_WEEKLY_REPORT,
            Document::TYPE_MONTHLY_REPORT,
            Document::TYPE_ACTION_LOG,
        ];
        if ($typeFilter !== []) {
            $unsupported = array_values(array_diff($typeFilter, $supportedTypes));
            if ($unsupported !== []) {
                $this->error('Unsupported type(s): '.implode(', ', $unsupported));

                return self::FAILURE;
            }
            $supportedTypes = $typeFilter;
        }

        $query = Document::query()
            ->whereIn('type', $supportedTypes)
            ->orderBy('id');

        if (is_string($fromId) && trim($fromId) !== '') {
            $query->where('id', '>=', trim($fromId));
        }

        $total = (clone $query)->count();
        $this->info("Backfilling {$total} documents...");

        $stats = [
            'processed' => 0,
            'weekly_plan' => 0,
            'weekly_report' => 0,
            'monthly_report' => 0,
            'action_log' => 0,
            'failed' => 0,
        ];

        $query->chunk($chunk, function ($documents) use (&$stats) {
            foreach ($documents as $document) {
                try {
                    DB::transaction(function () use ($document, &$stats) {
                        $this->backfillDocument($document);
                        $stats['processed']++;
                        $stats[$document->type] = ($stats[$document->type] ?? 0) + 1;
                    }, 3);
                } catch (\Throwable $exception) {
                    $stats['failed']++;
                    $this->error("Failed {$document->id} ({$document->type}): ".$exception->getMessage());
                }
            }
        });

        $this->table(
            ['metric', 'value'],
            collect($stats)->map(fn ($v, $k) => ['metric' => $k, 'value' => (string) $v])->values()->all()
        );

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function backfillDocument(Document $document): void
    {
        match ($document->type) {
            Document::TYPE_WEEKLY_PLAN => $this->backfillWeeklyPlan($document),
            Document::TYPE_WEEKLY_REPORT => $this->backfillWeeklyReport($document),
            Document::TYPE_MONTHLY_REPORT => $this->backfillMonthlyReport($document),
            Document::TYPE_ACTION_LOG => $this->backfillActionLog($document),
            default => null,
        };
    }

    private function backfillWeeklyPlan(Document $document): void
    {
        $content = is_array($document->content) ? $document->content : [];

        $weeklyPlan = WeeklyPlan::query()->updateOrCreate(
            ['legacy_document_id' => (string) $document->id],
            [
                'user_id' => (string) $document->user_id,
                'period_id' => $document->period_id ? (string) $document->period_id : null,
                'title' => $document->title,
                'state' => $document->state ?? WeeklyPlan::STATE_DRAFT,
                'main_target' => $document->main_target,
                'review_rejection_reason' => $document->review_rejection_reason,
                'budget_review_status' => $document->budget_review_status,
                'budget_reviewed_by' => $document->budget_reviewed_by,
                'budget_reviewed_at' => $document->budget_reviewed_at,
                'budget_provided_amount' => $document->budget_provided_amount,
                'budget_review_note' => $document->budget_review_note,
            ]
        );

        $weeklyPlan->activities()->delete();
        $weeklyPlan->meetings()->delete();
        $weeklyPlan->budgetItems()->delete();

        $activityRows = is_array($content['planned_activities'] ?? null) ? $content['planned_activities'] : [];
        foreach (array_values($activityRows) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $activity = WeeklyPlanActivity::query()->create([
                'weekly_plan_id' => (string) $weeklyPlan->id,
                'activity_uid' => trim((string) ($row['activity_uid'] ?? '')) ?: (string) Str::ulid(),
                'day' => trim((string) ($row['day'] ?? '')) ?: null,
                'activity' => trim((string) ($row['activity'] ?? '')) ?: null,
                'location' => trim((string) ($row['location'] ?? '')) ?: null,
                'notes_inclusion' => trim((string) ($row['notes_inclusion'] ?? '')) ?: null,
                'carry_over_source_weekly_report_id' => $this->resolveCarryOverSourceWeeklyReportId($row),
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
            WeeklyPlanBudgetReview::query()->create([
                'weekly_plan_budget_item_id' => (string) $budgetItem->id,
                'decision' => in_array(($review['decision'] ?? null), [
                    Document::BUDGET_REVIEW_PENDING,
                    Document::BUDGET_REVIEW_APPROVED,
                    Document::BUDGET_REVIEW_REJECTED,
                ], true) ? $review['decision'] : Document::BUDGET_REVIEW_PENDING,
                'provided_amount' => (float) ($review['provided_amount'] ?? 0),
                'note' => isset($review['note']) ? trim((string) $review['note']) : null,
                'reviewed_by' => $review['reviewed_by'] ?? null,
                'reviewed_at' => isset($review['reviewed_at']) && trim((string) $review['reviewed_at']) !== ''
                    ? $review['reviewed_at']
                    : null,
            ]);
        }
    }

    private function backfillWeeklyReport(Document $document): void
    {
        $content = is_array($document->content) ? $document->content : [];

        $weeklyReport = WeeklyReport::query()->updateOrCreate(
            ['legacy_document_id' => (string) $document->id],
            [
                'user_id' => (string) $document->user_id,
                'period_id' => $document->period_id ? (string) $document->period_id : null,
                'title' => $document->title,
                'state' => $document->state ?? WeeklyPlan::STATE_DRAFT,
                'summary' => trim((string) ($content['summary'] ?? '')) ?: null,
                'target_accomplished' => $document->target_accomplished,
                'target_accomplished_status' => trim((string) ($content['target_accomplished_status'] ?? '')) ?: null,
                'target_explanation' => $document->target_explanation,
                'review_rejection_reason' => $document->review_rejection_reason,
            ]
        );

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

    private function backfillMonthlyReport(Document $document): void
    {
        $content = is_array($document->content) ? $document->content : [];

        $monthlyReport = MonthlyReport::query()->updateOrCreate(
            ['legacy_document_id' => (string) $document->id],
            [
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
            ]
        );

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

    private function backfillActionLog(Document $document): void
    {
        $content = is_array($document->content) ? $document->content : [];
        $taskRows = is_array($content['tasks'] ?? null) ? $content['tasks'] : [];

        $actionLog = ActionLog::query()->updateOrCreate(
            ['legacy_document_id' => (string) $document->id],
            [
                'user_id' => (string) $document->user_id,
                'period_id' => $document->period_id ? (string) $document->period_id : null,
                'title' => $document->title,
                'state' => $document->state ?? WeeklyPlan::STATE_DRAFT,
            ]
        );

        $actionLog->tasks()->delete();

        foreach (array_values($taskRows) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $task = ActionLogTask::query()->create([
                'action_log_id' => (string) $actionLog->id,
                'task_uid' => trim((string) ($row['task_uid'] ?? '')) ?: ('task_'.Str::lower((string) Str::ulid())),
                'task_no' => (int) ($row['task_no'] ?? ($index + 1)),
                'task' => trim((string) ($row['task'] ?? '')) ?: null,
                'progress' => trim((string) ($row['progress'] ?? '')) ?: 'Not Started',
                'due_date' => trim((string) ($row['due_date'] ?? '')) ?: null,
                'compilation_date' => trim((string) ($row['compilation_date'] ?? '')) ?: null,
                'notes_progress' => trim((string) ($row['notes_progress'] ?? '')) ?: null,
                'status_reason' => trim((string) ($row['status_reason'] ?? '')) ?: null,
                'sort_order' => $index,
            ]);

            $assigneeIds = collect(is_array($row['responsible_user_ids'] ?? null) ? $row['responsible_user_ids'] : [])
                ->map(fn ($id) => trim((string) $id))
                ->filter()
                ->unique()
                ->values()
                ->all();
            $task->assignees()->sync($assigneeIds);

            $historyRows = is_array($row['history'] ?? null) ? $row['history'] : [];
            foreach ($historyRows as $historyRow) {
                if (! is_array($historyRow)) {
                    continue;
                }
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
                ]);
            }
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
            ->where('legacy_document_id', $legacyDocumentId)
            ->value('id');
    }
}
