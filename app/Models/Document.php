<?php

namespace App\Models;

use App\Models\Builders\DocumentBuilder;
use App\Services\Documents\DocumentContentAssembler;
use App\Services\Documents\DocumentDomainSyncService;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Document extends Model
{
    use HasFactory, HasUlids, LogsActivity, SoftDeletes;

    private static function supportsBinaryComparison(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }

    private static function binaryEq(string $column): string
    {
        return self::supportsBinaryComparison()
            ? "BINARY {$column} = BINARY ?"
            : "{$column} = ?";
    }

    private static function submittedOrderByExpression(): string
    {
        return self::supportsBinaryComparison()
            ? 'CASE WHEN BINARY state = BINARY ? THEN 0 ELSE 1 END'
            : 'CASE WHEN state = ? THEN 0 ELSE 1 END';
    }

    public function newEloquentBuilder($query): DocumentBuilder
    {
        return new DocumentBuilder($query);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('documents')
            ->logOnly([
                'user_id',
                'period_id',
                'type',
                'title',
                'state',
                'month',
                'year',
                'main_target',
                'target_accomplished',
                'target_explanation',
                'file_path',
                'original_filename',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Document Types Constants
     */
    public const TYPE_WEEKLY_PLAN = 'weekly_plan';

    public const TYPE_WEEKLY_REPORT = 'weekly_report';

    public const TYPE_MONTHLY_REPORT = 'monthly_report';

    public const TYPE_ACTION_LOG = 'action_log';

    public const SHARE_EVENT_ADDED = 'added';

    public const SHARE_EVENT_REMOVED = 'removed';

    /**
     * Document State Constants
     */
    public const STATE_DRAFT = 'draft';

    public const STATE_SUBMITTED = 'submitted';

    /**
     * Weekly budget review statuses
     */
    public const BUDGET_REVIEW_PENDING = 'pending';

    public const BUDGET_REVIEW_APPROVED = 'approved';

    public const BUDGET_REVIEW_REJECTED = 'rejected';

    public const BUDGET_REVIEW_STAGE_PENDING = 'pending';

    public const BUDGET_REVIEW_STAGE_REVIEWED = 'reviewed';

    public const BUDGET_REVIEW_STAGE_APPROVED = 'approved';

    public const BUDGET_REVIEW_STAGE_AUTHORIZED = 'authorized';

    public const BUDGET_REVIEW_STAGE_REJECTED = 'rejected';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'period_id',
        'type',
        'file_path',
        'attachment',
        'month',
        'year',
        'title',
        'file_size',
        'original_filename',
        'content',
        'main_target',
        'target_accomplished',
        'target_explanation',
        'state',
        'review_rejection_reason',
        'budget_review_status',
        'budget_reviewed_by',
        'budget_reviewed_at',
        'budget_provided_amount',
        'budget_review_note',
        'budget_review_items',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
            'budget_reviewed_at' => 'datetime',
            'budget_provided_amount' => 'decimal:2',
            'budget_review_items' => 'array',
            // Used for template-based weekly_plan/weekly_report forms.
            'content' => 'array',
        ];
    }

    /**
     * Get all available document types
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_WEEKLY_PLAN => 'Weekly Plan',
            self::TYPE_WEEKLY_REPORT => 'Weekly Report',
            self::TYPE_MONTHLY_REPORT => 'Monthly Report',
            self::TYPE_ACTION_LOG => 'Action Log',
        ];
    }

    /**
     * Get all available document states
     */
    public static function getStates(): array
    {
        return [
            self::STATE_DRAFT => 'Draft',
            self::STATE_SUBMITTED => 'Submitted',
        ];
    }

    /**
     * Get human-readable type name
     */
    public function getTypeNameAttribute(): string
    {
        return self::getTypes()[$this->type] ?? ucwords(str_replace('_', ' ', $this->type));
    }

    /**
     * Get display title (use title or generate from type and period)
     */
    public function getDisplayTitleAttribute(): string
    {
        if ($this->title) {
            return $this->title;
        }

        $typeName = $this->type_name;
        if ($this->period) {
            return $typeName.' - Week '.$this->period->week_number;
        }

        return $typeName;
    }

    /**
     * Get human-readable state name
     */
    public function getStateNameAttribute(): string
    {
        return self::getStates()[$this->state] ?? ucfirst($this->state);
    }

    /**
     * Check if document is draft
     */
    public function isDraft(): bool
    {
        return $this->state === self::STATE_DRAFT;
    }

    /**
     * Check if document is submitted
     */
    public function isSubmitted(): bool
    {
        return $this->state === self::STATE_SUBMITTED;
    }

    /**
     * Action log tasks with notes/progress scoped to this document's work period
     * (for read-only views and PDF). The row display uses the current doc's own
     * real entry for its period_id only. The timeline aggregates real history
     * entries across every action log document that shares the same task_uid,
     * and fills non-updated weeks with empty placeholders so users can see the
     * last real update (e.g. Week 16) even when opening a later week's doc.
     *
     * @return list<array<string, mixed>>
     */
    public function getActionLogTasksForPeriodDisplay(): array
    {
        if ($this->type !== self::TYPE_ACTION_LOG) {
            return [];
        }

        $actionLog = ActionLog::query()
            ->with([
                'tasks.assignees:id,name',
                'tasks.history',
            ])
            ->where('id', $this->id)
            ->first();

        if (! $actionLog) {
            $content = is_array($this->content) ? $this->content : [];
            $tasks = is_array($content['tasks'] ?? null) ? $content['tasks'] : [];
            $periodId = $this->period_id !== null && $this->period_id !== ''
                ? (string) $this->period_id
                : null;

            $currentPeriod = $periodId !== null ? WorkPeriod::query()->find($periodId) : null;
            $otherActionLogDocs = null;
            if ($currentPeriod) {
                $otherActionLogDocs = self::query()
                    ->ofType(self::TYPE_ACTION_LOG)
                    ->where('id', '!=', $this->id)
                    ->whereHas('period', function ($query) use ($currentPeriod): void {
                        $query->whereDate('week_start_date', '<=', $currentPeriod->week_start_date);
                    })
                    ->with('period')
                    ->get();
            }

            $result = [];
            foreach ($tasks as $task) {
                if (! is_array($task)) {
                    continue;
                }

                if ($periodId === null || $currentPeriod === null) {
                    $task['display_notes_progress'] = trim((string) ($task['notes_progress'] ?? ''));
                    $task['display_progress'] = trim((string) ($task['progress'] ?? 'Not Started')) ?: 'Not Started';
                    $task['display_history'] = is_array($task['history'] ?? null)
                        ? array_values(array_filter($task['history'], static fn ($e) => is_array($e)))
                        : [];
                    $result[] = $task;

                    continue;
                }

                $ownHistory = is_array($task['history'] ?? null)
                    ? array_values(array_filter($task['history'], static fn ($e) => is_array($e)))
                    : [];
                $taskUid = trim((string) ($task['task_uid'] ?? ''));

                $aggregatedByPeriod = $this->aggregateActionLogTaskHistory(
                    $taskUid,
                    $ownHistory,
                    $otherActionLogDocs ?? collect(),
                    $periodId
                );

                $task['display_history'] = $this->buildActionLogDisplayTimeline($aggregatedByPeriod, $currentPeriod);

                $currentEntry = $aggregatedByPeriod[$periodId] ?? null;
                if (is_array($currentEntry)) {
                    $task['display_notes_progress'] = trim((string) ($currentEntry['notes_progress'] ?? ''));
                    $toProgress = trim((string) ($currentEntry['to_progress'] ?? ''));
                    $task['display_progress'] = $toProgress !== '' ? $toProgress : 'Not Started';
                } elseif ($ownHistory === [] && $task['display_history'] === []) {
                    $task['display_notes_progress'] = trim((string) ($task['notes_progress'] ?? ''));
                    $task['display_progress'] = trim((string) ($task['progress'] ?? 'Not Started')) ?: 'Not Started';
                } else {
                    $task['display_notes_progress'] = '';
                    $task['display_progress'] = null;
                }

                $result[] = $task;
            }

            return $result;
        }

        return $actionLog->tasks->map(function (ActionLogTask $task): array {
            $history = $task->history
                ->map(function (ActionLogTaskHistory $entry): array {
                    return [
                        'period_id' => $entry->period_id,
                        'period_label' => $entry->period_label,
                        'updated_by' => $entry->updated_by,
                        'from_progress' => $entry->from_progress,
                        'to_progress' => $entry->to_progress,
                        'notes_progress' => $entry->notes_progress,
                        'status_reason' => $entry->status_reason,
                        'changed_at' => optional($entry->changed_at)->toDateTimeString(),
                        'source' => $entry->source,
                    ];
                })
                ->values()
                ->all();

            return [
                'task_uid' => $task->task_uid,
                'task_no' => $task->task_no,
                'task' => $task->task,
                'responsible_user_ids' => $task->assignees->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
                'progress' => $task->progress,
                'due_date' => optional($task->due_date)->format('Y-m-d'),
                'compilation_date' => optional($task->compilation_date)->format('Y-m-d'),
                'notes_progress' => $task->notes_progress,
                'status_reason' => $task->status_reason,
                'history' => $history,
                'display_notes_progress' => $task->notes_progress,
                'display_progress' => $task->progress ?: 'Not Started',
                'display_history' => $history,
            ];
        })->values()->all();
    }

    /**
     * Collect the latest real history entry per period_id for a given task,
     * aggregating from the current document's own history plus all action log
     * documents sharing the same task_uid (capped at the current period).
     *
     * @param  list<array<string, mixed>>  $ownHistory
     * @param  \Illuminate\Support\Collection<int, self>  $otherDocs
     * @return array<string, array<string, mixed>>
     */
    private function aggregateActionLogTaskHistory(
        string $taskUid,
        array $ownHistory,
        $otherDocs,
        string $currentPeriodId
    ): array {
        $collected = [];

        $collectFromDoc = function (array $history, ?string $fallbackPeriodId) use (&$collected): void {
            foreach ($history as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $periodId = trim((string) ($entry['period_id'] ?? ''));
                if ($periodId === '' && $fallbackPeriodId !== null) {
                    $periodId = $fallbackPeriodId;
                    $entry['period_id'] = $fallbackPeriodId;
                }
                if ($periodId === '' || ! $this->isRealActionLogHistoryEntry($entry)) {
                    continue;
                }

                $existing = $collected[$periodId] ?? null;
                if ($existing === null
                    || (strtotime((string) ($entry['changed_at'] ?? '')) ?: 0)
                        >= (strtotime((string) ($existing['changed_at'] ?? '')) ?: 0)
                ) {
                    $collected[$periodId] = $entry;
                }
            }
        };

        $collectFromDoc($ownHistory, (string) $this->period_id);

        if ($taskUid !== '') {
            foreach ($otherDocs as $doc) {
                $docContent = is_array($doc->content) ? $doc->content : [];
                $docTasks = is_array($docContent['tasks'] ?? null) ? $docContent['tasks'] : [];
                foreach ($docTasks as $docTask) {
                    if (! is_array($docTask)) {
                        continue;
                    }
                    if (trim((string) ($docTask['task_uid'] ?? '')) !== $taskUid) {
                        continue;
                    }
                    $docHistory = is_array($docTask['history'] ?? null)
                        ? array_values(array_filter($docTask['history'], static fn ($e) => is_array($e)))
                        : [];
                    $collectFromDoc($docHistory, (string) $doc->period_id);
                }
            }
        }

        return $collected;
    }

    /**
     * Decide whether a history entry represents a real user update (not a
     * carry-forward init artifact). Newer entries carry `source => 'update'`,
     * but legacy docs never set that flag, so fall back to a heuristic:
     * an entry is real when it has notes, a reason, or a genuine progress
     * transition (from_progress set and differs from to_progress).
     *
     * @param  array<string, mixed>  $entry
     */
    private function isRealActionLogHistoryEntry(array $entry): bool
    {
        $source = trim((string) ($entry['source'] ?? ''));
        if ($source === 'auto_init') {
            return false;
        }
        if ($source === 'update') {
            return true;
        }

        $notes = trim((string) ($entry['notes_progress'] ?? ''));
        $reason = trim((string) ($entry['status_reason'] ?? ''));
        if ($notes !== '' || $reason !== '') {
            return true;
        }

        $fromProgress = trim((string) ($entry['from_progress'] ?? ''));
        $toProgress = trim((string) ($entry['to_progress'] ?? ''));

        return $fromProgress !== '' && $toProgress !== '' && $fromProgress !== $toProgress;
    }

    /**
     * Build a week-by-week timeline spanning from the earliest real-update
     * period through the current period, emitting empty placeholders for
     * weeks without a real update.
     *
     * @param  array<string, array<string, mixed>>  $entriesByPeriod
     * @return list<array<string, mixed>>
     */
    private function buildActionLogDisplayTimeline(array $entriesByPeriod, WorkPeriod $currentPeriod): array
    {
        if ($entriesByPeriod === []) {
            return [];
        }

        $periodIds = array_keys($entriesByPeriod);
        $firstPeriod = WorkPeriod::query()
            ->whereIn('id', $periodIds)
            ->orderBy('week_start_date')
            ->first();

        if (! $firstPeriod) {
            return [];
        }

        $firstStartTs = strtotime((string) $firstPeriod->week_start_date) ?: 0;
        $currentStartTs = strtotime((string) $currentPeriod->week_start_date) ?: 0;
        if ($currentStartTs === 0 || $firstStartTs > $currentStartTs) {
            $firstPeriod = $currentPeriod;
        }

        $periods = WorkPeriod::query()
            ->whereDate('week_start_date', '>=', $firstPeriod->week_start_date)
            ->whereDate('week_start_date', '<=', $currentPeriod->week_start_date)
            ->orderBy('week_start_date')
            ->get();

        if ($periods->isEmpty()) {
            return [];
        }

        $timeline = [];
        foreach ($periods as $period) {
            $periodId = (string) $period->id;
            $entry = $entriesByPeriod[$periodId] ?? null;
            if (! is_array($entry)) {
                $timeline[] = $this->buildEmptyTimelineEntry($period);

                continue;
            }

            $entry['period_label'] = ! empty($entry['period_label'])
                ? $entry['period_label']
                : 'Week '.$period->week_number.' of '.$period->year;
            $entry['is_empty'] = false;
            $timeline[] = $entry;
        }

        return $timeline;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEmptyTimelineEntry(WorkPeriod $period): array
    {
        return [
            'period_id' => (string) $period->id,
            'period_label' => 'Week '.$period->week_number.' of '.$period->year,
            'updated_by' => null,
            'from_progress' => null,
            'to_progress' => null,
            'notes_progress' => null,
            'status_reason' => null,
            'changed_at' => null,
            'is_empty' => true,
        ];
    }

    /**
     * Submit the document
     */
    public function submit(): bool
    {
        return $this->update([
            'state' => self::STATE_SUBMITTED,
            'review_rejection_reason' => null,
        ]);
    }

    /**
     * Revert document to draft. Pass a reason when returning a submission for revision.
     */
    public function revertToDraft(?string $reviewRejectionReason = null): bool
    {
        $data = ['state' => self::STATE_DRAFT];
        if ($reviewRejectionReason !== null) {
            $data['review_rejection_reason'] = $reviewRejectionReason;
        }

        return $this->update($data);
    }

    /**
     * Get the full file path (URL)
     */
    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? asset('storage/'.$this->file_path) : null;
    }

    /**
     * Get the file path for storage
     */
    public function getStoragePathAttribute(): ?string
    {
        return $this->file_path ? storage_path('app/public/'.$this->file_path) : null;
    }

    /**
     * Check if document has a file
     */
    public function hasFile(): bool
    {
        return ! empty($this->file_path) && file_exists($this->storage_path);
    }

    /**
     * Get human-readable file size
     */
    public function getFormattedFileSizeAttribute(): string
    {
        if (! $this->file_size) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2).' '.$units[$unit];
    }

    /**
     * Relationship: Document belongs to User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: Document belongs to WorkPeriod
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(WorkPeriod::class);
    }

    public function budgetReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'budget_reviewed_by');
    }

    /**
     * Weekly document collaborators explicitly added by the owner.
     */
    public function sharedUsers(): BelongsToMany
    {
        $foreignKey = $this->type === self::TYPE_WEEKLY_REPORT
            ? 'weekly_report_id'
            : 'weekly_plan_id';

        return $this->belongsToMany(User::class, 'document_user_shares', $foreignKey, 'user_id')
            ->withTimestamps();
    }

    public function weeklyActivityAssignments(): HasMany
    {
        return $this->hasMany(WeeklyActivityAssignment::class, 'source_weekly_plan_id');
    }

    public function weeklyPlanDomain(): BelongsTo
    {
        return $this->belongsTo(WeeklyPlan::class, 'id', 'id');
    }

    public function weeklyReportDomain(): BelongsTo
    {
        return $this->belongsTo(WeeklyReport::class, 'id', 'id');
    }

    public function monthlyReportDomain(): BelongsTo
    {
        return $this->belongsTo(MonthlyReport::class, 'id', 'id');
    }

    public function actionLogDomain(): BelongsTo
    {
        return $this->belongsTo(ActionLog::class, 'id', 'id');
    }

    public function isSharedWith(string $userId): bool
    {
        return $this->sharedUsers()
            ->whereRaw(self::binaryEq('users.id'), [$userId])
            ->exists();
    }

    public function allEditableUserIds(): array
    {
        $sharedIds = $this->sharedUsers()
            ->pluck('users.id')
            ->all();

        return array_values(array_unique(array_merge([$this->user_id], $sharedIds)));
    }

    public static function supportsSharedEditing(?string $type): bool
    {
        return in_array($type, [self::TYPE_WEEKLY_PLAN, self::TYPE_WEEKLY_REPORT], true);
    }

    /**
     * Weekly plan for the same period and owning user (submitted preferred).
     */
    public static function findWeeklyPlanForPeriodOwner(string $periodId, string $ownerUserId): ?Document
    {
        $plan = WeeklyPlan::query()
            ->where('period_id', $periodId)
            ->where('user_id', $ownerUserId)
            ->orderByRaw(self::submittedOrderByExpression(), [self::STATE_SUBMITTED])
            ->latest('id')
            ->first();

        if ($plan) {
            return self::fromWeeklyPlanModel($plan);
        }

        return static::query()
            ->where('period_id', $periodId)
            ->ofType(self::TYPE_WEEKLY_PLAN)
            ->forUser($ownerUserId)
            ->orderByRaw(self::submittedOrderByExpression(), [self::STATE_SUBMITTED])
            ->latest('id')
            ->first();
    }

    /**
     * Weekly plan for the same period that the user either owns or is shared on (submitted preferred).
     */
    public static function findWeeklyPlanForUserAndPeriod(string $periodId, string $userId): ?Document
    {
        $ownedPlan = WeeklyPlan::query()
            ->where('period_id', $periodId)
            ->where('user_id', $userId)
            ->orderByRaw(self::submittedOrderByExpression(), [self::STATE_SUBMITTED])
            ->latest('id')
            ->first();

        if ($ownedPlan) {
            return self::fromWeeklyPlanModel($ownedPlan);
        }

        $sharedPlanId = DB::table('document_user_shares')
            ->join('weekly_plans', 'weekly_plans.id', '=', 'document_user_shares.weekly_plan_id')
            ->where('document_user_shares.user_id', $userId)
            ->where('weekly_plans.period_id', $periodId)
            ->orderByDesc('weekly_plans.id')
            ->value('weekly_plans.id');

        if ($sharedPlanId) {
            $sharedPlan = WeeklyPlan::query()->find($sharedPlanId);
            if ($sharedPlan) {
                return self::fromWeeklyPlanModel($sharedPlan);
            }
        }

        return static::query()
            ->forOwnerOrSharedUser($userId)
            ->where('period_id', $periodId)
            ->ofType(self::TYPE_WEEKLY_PLAN)
            ->orderByRaw(self::submittedOrderByExpression(), [self::STATE_SUBMITTED])
            ->latest('id')
            ->first();
    }

    /**
     * Weekly report for the same period and owning user (submitted preferred).
     */
    public static function findWeeklyReportForPeriodOwner(string $periodId, string $ownerUserId): ?Document
    {
        $report = WeeklyReport::query()
            ->where('period_id', $periodId)
            ->where('user_id', $ownerUserId)
            ->orderByRaw(self::submittedOrderByExpression(), [self::STATE_SUBMITTED])
            ->latest('id')
            ->first();

        if ($report) {
            return self::fromWeeklyReportModel($report);
        }

        return static::query()
            ->where('period_id', $periodId)
            ->ofType(self::TYPE_WEEKLY_REPORT)
            ->forUser($ownerUserId)
            ->orderByRaw(self::submittedOrderByExpression(), [self::STATE_SUBMITTED])
            ->latest('id')
            ->first();
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?? $this->getRouteKeyName();

        $document = $this->newQuery()->where($field, $value)->first();
        if ($document !== null) {
            return $document;
        }

        return self::fromDomainId((string) $value);
    }

    public static function fromDomainId(string $id): ?self
    {
        $plan = WeeklyPlan::query()->find($id);
        if ($plan) {
            return self::fromWeeklyPlanModel($plan);
        }

        $report = WeeklyReport::query()->find($id);
        if ($report) {
            return self::fromWeeklyReportModel($report);
        }

        $monthlyReport = MonthlyReport::query()->find($id);
        if ($monthlyReport) {
            return self::fromMonthlyReportModel($monthlyReport);
        }

        $actionLog = ActionLog::query()->find($id);
        if ($actionLog) {
            return self::fromActionLogModel($actionLog);
        }

        return null;
    }

    public static function fromWeeklyPlanModel(WeeklyPlan $plan): self
    {
        $document = new self;
        $document->id = (string) $plan->id;
        $document->user_id = (string) $plan->user_id;
        $document->period_id = $plan->period_id ? (string) $plan->period_id : null;
        $document->type = self::TYPE_WEEKLY_PLAN;
        $document->title = $plan->title;
        $document->main_target = $plan->main_target;
        $document->state = $plan->state;
        $document->review_rejection_reason = $plan->review_rejection_reason;
        $document->budget_review_status = $plan->budget_review_status;
        $document->budget_reviewed_by = $plan->budget_reviewed_by;
        $document->budget_reviewed_at = $plan->budget_reviewed_at;
        $document->budget_provided_amount = $plan->budget_provided_amount;
        $document->budget_review_note = $plan->budget_review_note;
        $document->created_at = $plan->created_at;
        $document->updated_at = $plan->updated_at;
        $document->deleted_at = $plan->deleted_at;
        $document->exists = true;

        return $document;
    }

    public static function fromWeeklyReportModel(WeeklyReport $report): self
    {
        $document = new self;
        $document->id = (string) $report->id;
        $document->user_id = (string) $report->user_id;
        $document->period_id = $report->period_id ? (string) $report->period_id : null;
        $document->type = self::TYPE_WEEKLY_REPORT;
        $document->title = $report->title;
        $document->target_accomplished = $report->target_accomplished;
        $document->target_explanation = $report->target_explanation;
        $document->state = $report->state;
        $document->review_rejection_reason = $report->review_rejection_reason;
        $document->created_at = $report->created_at;
        $document->updated_at = $report->updated_at;
        $document->deleted_at = $report->deleted_at;
        $document->exists = true;

        return $document;
    }

    public static function fromMonthlyReportModel(MonthlyReport $report): self
    {
        $document = new self;
        $document->id = (string) $report->id;
        $document->user_id = (string) $report->user_id;
        $document->type = self::TYPE_MONTHLY_REPORT;
        $document->month = $report->month;
        $document->year = $report->year;
        $document->title = $report->title;
        $document->file_path = $report->file_path;
        $document->attachment = $report->attachment;
        $document->file_size = $report->file_size;
        $document->original_filename = $report->original_filename;
        $document->state = $report->state;
        $document->created_at = $report->created_at;
        $document->updated_at = $report->updated_at;
        $document->deleted_at = $report->deleted_at;
        $document->exists = true;

        return $document;
    }

    public static function fromActionLogModel(ActionLog $actionLog): self
    {
        $document = new self;
        $document->id = (string) $actionLog->id;
        $document->user_id = (string) $actionLog->user_id;
        $document->period_id = $actionLog->period_id ? (string) $actionLog->period_id : null;
        $document->type = self::TYPE_ACTION_LOG;
        $document->title = $actionLog->title;
        $document->state = $actionLog->state;
        $document->created_at = $actionLog->created_at;
        $document->updated_at = $actionLog->updated_at;
        $document->deleted_at = $actionLog->deleted_at;
        $document->exists = true;

        return $document;
    }

    /**
     * Paired weekly plan/report for the same work period as this document.
     */
    public function relatedWeeklyDocument(): ?self
    {
        if (! $this->period_id) {
            return null;
        }

        $periodId = (string) $this->period_id;
        $ownerUserId = (string) $this->user_id;

        if ($this->type === self::TYPE_WEEKLY_PLAN) {
            return self::findWeeklyReportForPeriodOwner($periodId, $ownerUserId);
        }

        if ($this->type === self::TYPE_WEEKLY_REPORT) {
            return self::findWeeklyPlanForUserAndPeriod($periodId, $ownerUserId)
                ?? self::findWeeklyPlanForPeriodOwner($periodId, $ownerUserId);
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function relatedWeeklyCreateActionFor(User $user): ?array
    {
        if (! $this->period_id || ! $this->period) {
            return null;
        }

        if ((string) $this->user_id !== (string) $user->id) {
            return null;
        }

        if ($this->relatedWeeklyDocument() !== null) {
            return null;
        }

        $request = app('request');
        $returnTo = $request instanceof \Illuminate\Http\Request
            ? $request->fullUrl()
            : route('documents.index');

        if ($this->type === self::TYPE_WEEKLY_PLAN
            && self::canUserCreateType($user, self::TYPE_WEEKLY_REPORT, $this->period)) {
            return [
                'type' => self::TYPE_WEEKLY_REPORT,
                'label' => 'Create Weekly Report for this Week',
                'route' => route('documents.create', [
                    'type' => self::TYPE_WEEKLY_REPORT,
                    'period_id' => $this->period_id,
                    'return_to' => $returnTo,
                ]),
                'description' => 'Weekly report is due on '.$this->period->getReportDeadlineCutoff()->format('M d, Y H:i'),
            ];
        }

        if ($this->type === self::TYPE_WEEKLY_REPORT
            && self::canUserCreateType($user, self::TYPE_WEEKLY_PLAN, $this->period)) {
            return [
                'type' => self::TYPE_WEEKLY_PLAN,
                'label' => 'Create Weekly Plan for this Week',
                'route' => route('documents.create', [
                    'type' => self::TYPE_WEEKLY_PLAN,
                    'period_id' => $this->period_id,
                    'return_to' => $returnTo,
                ]),
                'description' => 'Weekly plan should outline goals for this week.',
            ];
        }

        return null;
    }

    /**
     * Scope: Filter by type
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, string $type)
    {
        return $query->whereRaw(self::binaryEq('type'), [$type]);
    }

    /**
     * Scope: Filter by state
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfState($query, string $state)
    {
        return $query->whereRaw(self::binaryEq('state'), [$state]);
    }

    /**
     * Scope: Filter by user
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->whereRaw(self::binaryEq('user_id'), [$userId]);
    }

    public function scopeForOwnerOrSharedUser($query, string $userId)
    {
        return $query->where(function ($nested) use ($userId) {
            $nested->forUser($userId)
                ->orWhereHas('sharedUsers', function ($sharedUsers) use ($userId) {
                    $sharedUsers->whereRaw(self::binaryEq('users.id'), [$userId]);
                });
        });
    }

    /**
     * Include documents the user owns/shares directly, plus weekly reports
     * connected to weekly plans the user collaborates on.
     */
    public function scopeForOwnerSharedOrPlanCollaboratorOnWeeklyReport($query, string $userId)
    {
        return $query->where(function ($nested) use ($userId) {
            $nested->forOwnerOrSharedUser($userId)
                ->orWhere(function ($weeklyReportAccess) use ($userId) {
                    $weeklyReportAccess
                        ->ofType(self::TYPE_WEEKLY_REPORT)
                        ->where(function ($planScope) use ($userId) {
                            $planScope->whereExists(function ($planQuery) use ($userId) {
                                $planQuery->selectRaw('1')
                                    ->from('weekly_plans as wp')
                                    ->whereRaw(self::supportsBinaryComparison()
                                        ? 'BINARY wp.period_id = BINARY documents.period_id'
                                        : 'wp.period_id = documents.period_id')
                                    ->where(function ($planAccess) use ($userId) {
                                        $planAccess->whereRaw(self::supportsBinaryComparison()
                                            ? 'BINARY wp.user_id = BINARY ?'
                                            : 'wp.user_id = ?', [$userId])
                                            ->orWhereExists(function ($planShares) use ($userId) {
                                                $planShares->selectRaw('1')
                                                    ->from('document_user_shares as weekly_plan_shares')
                                                    ->whereRaw(self::supportsBinaryComparison()
                                                        ? 'BINARY weekly_plan_shares.weekly_plan_id = BINARY wp.id'
                                                        : 'weekly_plan_shares.weekly_plan_id = wp.id')
                                                    ->whereRaw(self::supportsBinaryComparison()
                                                        ? 'BINARY weekly_plan_shares.user_id = BINARY ?'
                                                        : 'weekly_plan_shares.user_id = ?', [$userId]);
                                            });
                                    })
                                    // Prevent cross-team leaks: a weekly report is reachable via plan
                                    // collaboration only when the report author belongs to that same
                                    // plan collaboration group (owner or shared member).
                                    ->where(function ($authorAccess) {
                                        $authorAccess->whereRaw(self::supportsBinaryComparison()
                                            ? 'BINARY wp.user_id = BINARY documents.user_id'
                                            : 'wp.user_id = documents.user_id')
                                            ->orWhereExists(function ($authorShares) {
                                                $authorShares->selectRaw('1')
                                                    ->from('document_user_shares as weekly_plan_member_shares')
                                                    ->whereRaw(self::supportsBinaryComparison()
                                                        ? 'BINARY weekly_plan_member_shares.weekly_plan_id = BINARY wp.id'
                                                        : 'weekly_plan_member_shares.weekly_plan_id = wp.id')
                                                    ->whereRaw(self::supportsBinaryComparison()
                                                        ? 'BINARY weekly_plan_member_shares.user_id = BINARY documents.user_id'
                                                        : 'weekly_plan_member_shares.user_id = documents.user_id');
                                            });
                                    });
                            });
                        });
                });
        });
    }

    /**
     * Scope: Get only draft documents
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDrafts($query)
    {
        return $query->ofState(self::STATE_DRAFT);
    }

    /**
     * Scope: Get only submitted documents
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSubmitted($query)
    {
        return $query->ofState(self::STATE_SUBMITTED);
    }

    /**
     * Scope: Get weekly plans
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWeeklyPlans($query)
    {
        return $query->ofType(self::TYPE_WEEKLY_PLAN);
    }

    /**
     * Scope: Get weekly reports
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWeeklyReports($query)
    {
        return $query->ofType(self::TYPE_WEEKLY_REPORT);
    }

    public function scopeWeeklyPlansWithBudget($query)
    {
        return $query
            ->weeklyPlans()
            ->whereHas('weeklyPlanDomain.budgetItems');
    }

    public function scopeBudgetPending($query)
    {
        return $query->where('budget_review_status', self::BUDGET_REVIEW_PENDING);
    }

    public function scopeBudgetApproved($query)
    {
        return $query->where('budget_review_status', self::BUDGET_REVIEW_APPROVED);
    }

    public function scopeBudgetRejected($query)
    {
        return $query->where('budget_review_status', self::BUDGET_REVIEW_REJECTED);
    }

    /**
     * Scope: Get monthly reports
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeMonthlyReports($query)
    {
        return $query->ofType(self::TYPE_MONTHLY_REPORT);
    }

    /**
     * Scope: Get action logs
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActionLogs($query)
    {
        return $query->ofType(self::TYPE_ACTION_LOG);
    }

    /**
     * Scope: Get documents for employees (plans and reports)
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEmployeeDocuments($query)
    {
        return $query->whereIn('type', [
            self::TYPE_WEEKLY_PLAN,
            self::TYPE_WEEKLY_REPORT,
            self::TYPE_MONTHLY_REPORT,
        ]);
    }

    /**
     * Scope: Recent documents (last N days)
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope: Order by most recent
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Scope: Filter by period
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForPeriod($query, string $periodId)
    {
        return $query->where('period_id', $periodId);
    }

    /**
     * Check if a user can create this document type based on their role
     */
    public static function canUserCreateType(User $user, string $type, ?WorkPeriod $period = null): bool
    {
        // For weekly plans, reports, and minutes, check if period is open
        if (in_array($type, [self::TYPE_WEEKLY_PLAN, self::TYPE_WEEKLY_REPORT, self::TYPE_ACTION_LOG])) {
            // If period is provided, check if it's open
            if ($period && ! $period->isOpen()) {
                return false;
            }

            // If no period provided, try to get current period
            if (! $period) {
                $period = WorkPeriod::getCurrent();
                if (! $period || ! $period->isOpen()) {
                    return false;
                }
            }
        }

        // Users with approve documents permission can create any type
        if ($user->hasPermissionTo('approve documents')) {
            return true;
        }

        // Check specific create permissions for each document type
        switch ($type) {
            case self::TYPE_WEEKLY_PLAN:
            case self::TYPE_WEEKLY_REPORT:
            case self::TYPE_MONTHLY_REPORT:
                return $user->hasAnyPermission([
                    'create weekly plan',
                    'create weekly report',
                    'create monthly report',
                ]);

            case self::TYPE_ACTION_LOG:
                return $user->hasPermissionTo('create action logs');
        }

        return false;
    }

    /**
     * Get document types available for a specific user role
     */
    public static function getAvailableTypesForUser(User $user): array
    {
        $allTypes = self::getTypes();
        $availableTypes = [];

        foreach ($allTypes as $type => $name) {
            if (self::canUserCreateType($user, $type)) {
                $availableTypes[$type] = $name;
            }
        }

        return $availableTypes;
    }

    /**
     * Check if document can be edited (only drafts can be edited)
     */
    public function canBeEdited(): bool
    {
        return $this->isDraft();
    }

    /**
     * Check if document can be submitted (only drafts can be submitted)
     */
    public function canBeSubmitted(): bool
    {
        return $this->isDraft();
    }

    /**
     * Check if document can be deleted (only drafts can be deleted)
     */
    public function canBeDeleted(): bool
    {
        return $this->isDraft();
    }

    /**
     * Get latest rejection reason if document was returned to draft.
     */
    public function latestRejectionReason(): ?string
    {
        $raw = $this->review_rejection_reason;
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        return trim($raw);
    }

    /**
     * Check if this draft document was returned for revision by reviewer.
     */
    public function isReturnedForRevision(): bool
    {
        return $this->isDraft() && $this->latestRejectionReason() !== null;
    }

    public function hasBudgetItems(): bool
    {
        return $this->budgetItems() !== [];
    }

    /**
     * Count submitted weekly plans that appear on the Weekly Budget Review panel:
     * same candidate query as {@see \App\Http\Controllers\DocumentController::weeklyBudgetReviewPanel},
     * then restricted to plans that actually have non-empty budget rows ({@see self::hasBudgetItems()}).
     *
     * @param  string|null  $periodId  When set, only plans for this work period (e.g. next plan week).
     */
    public static function countPendingWeeklyBudgetReviewForPeriod(?string $periodId = null): int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('documents')) {
            return 0;
        }

        $query = static::query()
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
                    ->orWhere('budget_review_status', self::BUDGET_REVIEW_PENDING);
            });

        if ($periodId !== null && $periodId !== '') {
            $query->forPeriod($periodId);
        }

        return $query->get()->filter(fn (Document $document) => $document->hasBudgetItems())->count();
    }

    public function getBudgetReviewStatusLabelAttribute(): ?string
    {
        $summary = $this->budgetReviewSummary();
        if ($summary['is_partial']) {
            return 'Partial';
        }

        if ($this->budget_review_status === null) {
            return $this->hasBudgetItems() ? 'Pending' : null;
        }

        return match ($this->budget_review_status) {
            self::BUDGET_REVIEW_PENDING => 'Pending',
            self::BUDGET_REVIEW_APPROVED => 'Approved',
            self::BUDGET_REVIEW_REJECTED => 'Rejected',
            default => ucfirst((string) $this->budget_review_status),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function budgetItems(): array
    {
        $weeklyPlan = WeeklyPlan::query()
            ->with('budgetItems')
            ->where('id', $this->id)
            ->first();

        if ($weeklyPlan) {
            return $weeklyPlan->budgetItems
                ->map(function (WeeklyPlanBudgetItem $item): array {
                    return [
                        'budget_uid' => (string) $item->budget_uid,
                        'item' => (string) ($item->item ?? ''),
                        'description_purpose' => (string) ($item->description_purpose ?? ''),
                        'amount' => (string) ($item->amount_raw ?? ''),
                        'amount_numeric' => (float) ($item->amount_numeric ?? 0),
                    ];
                })
                ->filter(static fn (array $row) => $row['item'] !== '' || $row['description_purpose'] !== '' || $row['amount'] !== '')
                ->values()
                ->all();
        }

        if (! is_array($this->content)) {
            return [];
        }

        $budgetRows = $this->content['budget'] ?? [];
        if (! is_array($budgetRows)) {
            return [];
        }

        $items = [];
        foreach ($budgetRows as $index => $budgetRow) {
            if (! is_array($budgetRow)) {
                continue;
            }

            $item = trim((string) ($budgetRow['item'] ?? ''));
            $description = trim((string) ($budgetRow['description_purpose'] ?? ''));
            $amount = trim((string) ($budgetRow['amount'] ?? ''));
            if ($item === '' && $description === '' && $amount === '') {
                continue;
            }

            $uid = trim((string) ($budgetRow['budget_uid'] ?? ''));
            if ($uid === '') {
                $uid = 'legacy_'.$this->id.'_'.$index;
            }

            $items[] = [
                'budget_uid' => $uid,
                'item' => $item,
                'description_purpose' => $description,
                'amount' => $amount,
                'amount_numeric' => self::parseBudgetAmount($amount),
            ];
        }

        return $items;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function budgetReviewItemsByUid(): array
    {
        $weeklyPlan = WeeklyPlan::query()
            ->with('budgetItems.review')
            ->where('id', $this->id)
            ->first();

        if ($weeklyPlan) {
            $mapped = [];
            foreach ($weeklyPlan->budgetItems as $item) {
                $uid = trim((string) $item->budget_uid);
                if ($uid === '') {
                    continue;
                }
                $review = $item->review;
                $decision = $review?->decision ?? self::BUDGET_REVIEW_PENDING;
                $approvalStage = $review?->approval_stage ?? self::BUDGET_REVIEW_STAGE_PENDING;
                $latestActor = $review?->authorized_by
                    ?? $review?->approved_by
                    ?? $review?->reviewed_by;
                $latestActionAt = $review?->authorized_at
                    ?? $review?->approved_at
                    ?? $review?->reviewed_at;
                $mapped[$uid] = [
                    'budget_uid' => $uid,
                    'decision' => $decision,
                    'approval_stage' => $approvalStage,
                    'provided_amount' => (float) ($review?->provided_amount ?? 0),
                    'note' => $review?->note,
                    'reviewed_by' => $latestActor,
                    'reviewed_at' => optional($latestActionAt)->toIso8601String(),
                    'review_stage_by' => $review?->reviewed_by,
                    'review_stage_at' => optional($review?->reviewed_at)->toIso8601String(),
                    'approve_stage_by' => $review?->approved_by,
                    'approve_stage_at' => optional($review?->approved_at)->toIso8601String(),
                    'authorize_stage_by' => $review?->authorized_by,
                    'authorize_stage_at' => optional($review?->authorized_at)->toIso8601String(),
                ];
            }

            return $mapped;
        }

        $rows = [];
        if (array_key_exists('budget_review_items', $this->attributes) && $this->attributes['budget_review_items'] !== null) {
            $raw = $this->attributes['budget_review_items'];
            if (is_array($raw)) {
                $rows = $raw;
            } elseif (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                $rows = is_array($decoded) ? $decoded : [];
            }
        }
        $mapped = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $uid = trim((string) ($row['budget_uid'] ?? ''));
            if ($uid === '') {
                continue;
            }
            $decision = trim((string) ($row['decision'] ?? ''));
            if (! in_array($decision, [self::BUDGET_REVIEW_PENDING, self::BUDGET_REVIEW_APPROVED, self::BUDGET_REVIEW_REJECTED], true)) {
                $decision = self::BUDGET_REVIEW_PENDING;
            }
            $approvalStage = trim((string) ($row['approval_stage'] ?? ''));
            if (! in_array($approvalStage, self::budgetReviewStages(), true)) {
                $approvalStage = $decision === self::BUDGET_REVIEW_REJECTED
                    ? self::BUDGET_REVIEW_STAGE_REJECTED
                    : self::BUDGET_REVIEW_STAGE_PENDING;
            }
            $mapped[$uid] = [
                'budget_uid' => $uid,
                'decision' => $decision,
                'approval_stage' => $approvalStage,
                'provided_amount' => (float) ($row['provided_amount'] ?? 0),
                'note' => isset($row['note']) ? trim((string) $row['note']) : null,
                'reviewed_by' => $row['reviewed_by'] ?? null,
                'reviewed_at' => isset($row['reviewed_at']) && $row['reviewed_at'] !== '' ? (string) $row['reviewed_at'] : null,
                'review_stage_by' => $row['review_stage_by'] ?? null,
                'review_stage_at' => isset($row['review_stage_at']) && $row['review_stage_at'] !== '' ? (string) $row['review_stage_at'] : null,
                'approve_stage_by' => $row['approve_stage_by'] ?? null,
                'approve_stage_at' => isset($row['approve_stage_at']) && $row['approve_stage_at'] !== '' ? (string) $row['approve_stage_at'] : null,
                'authorize_stage_by' => $row['authorize_stage_by'] ?? null,
                'authorize_stage_at' => isset($row['authorize_stage_at']) && $row['authorize_stage_at'] !== '' ? (string) $row['authorize_stage_at'] : null,
            ];
        }

        return $mapped;
    }

    /**
     * @return array<string, mixed>
     */
    public function budgetReviewSummary(): array
    {
        $items = $this->budgetItems();
        $reviewsByUid = $this->budgetReviewItemsByUid();

        $pending = 0;
        $approved = 0;
        $rejected = 0;
        $requestedTotal = 0.0;
        $providedTotal = 0.0;

        foreach ($items as $item) {
            $uid = (string) $item['budget_uid'];
            $requestedTotal += (float) ($item['amount_numeric'] ?? 0);
            $review = $reviewsByUid[$uid] ?? null;
            $decision = $review['decision'] ?? self::BUDGET_REVIEW_PENDING;
            $approvalStage = $review['approval_stage'] ?? self::BUDGET_REVIEW_STAGE_PENDING;
            if ($decision === self::BUDGET_REVIEW_REJECTED || $approvalStage === self::BUDGET_REVIEW_STAGE_REJECTED) {
                $rejected++;
            } elseif ($decision === self::BUDGET_REVIEW_APPROVED && $approvalStage === self::BUDGET_REVIEW_STAGE_AUTHORIZED) {
                $approved++;
                $providedTotal += (float) ($review['provided_amount'] ?? 0);
            } else {
                $pending++;
            }
        }

        $hasApproved = $approved > 0;
        $hasRejected = $rejected > 0;
        $isPartial = $hasApproved && ($hasRejected || $pending > 0);

        return [
            'items_total' => count($items),
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
            'requested_total' => $requestedTotal,
            'provided_total' => $providedTotal,
            'is_partial' => $isPartial,
            'all_approved' => count($items) > 0 && $approved === count($items),
            'all_rejected' => count($items) > 0 && $rejected === count($items),
        ];
    }

    public static function parseBudgetAmount(string $rawAmount): float
    {
        $rawAmount = trim($rawAmount);
        if ($rawAmount === '') {
            return 0.0;
        }

        $normalizedAmount = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $rawAmount));
        if ($normalizedAmount === null || $normalizedAmount === '' || ! is_numeric($normalizedAmount)) {
            return 0.0;
        }

        return (float) $normalizedAmount;
    }

    public static function budgetReviewStages(): array
    {
        return [
            self::BUDGET_REVIEW_STAGE_PENDING,
            self::BUDGET_REVIEW_STAGE_REVIEWED,
            self::BUDGET_REVIEW_STAGE_APPROVED,
            self::BUDGET_REVIEW_STAGE_AUTHORIZED,
            self::BUDGET_REVIEW_STAGE_REJECTED,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getContentAttribute(mixed $value): ?array
    {
        $content = null;

        if (array_key_exists('content', $this->attributes) && $this->attributes['content'] !== null) {
            $raw = $this->attributes['content'];
            if (is_array($raw)) {
                $content = $raw;
            } elseif (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                $content = is_array($decoded) ? $decoded : null;
            }
        }

        if ($content === null && $this->id && $this->type) {
            $content = app(DocumentContentAssembler::class)->assembleForDocument($this);
        }

        if ($content !== null && $this->type === self::TYPE_WEEKLY_REPORT) {
            $content = app(DocumentContentAssembler::class)->normalizeWeeklyReportContent($content);
        }

        if ($content !== null && $this->type === self::TYPE_WEEKLY_PLAN) {
            $content = app(DocumentContentAssembler::class)->normalizeWeeklyPlanContent($content);
        }

        return $content;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function getBudgetReviewItemsAttribute(mixed $value): ?array
    {
        if (array_key_exists('budget_review_items', $this->attributes) && $this->attributes['budget_review_items'] !== null) {
            $raw = $this->attributes['budget_review_items'];
            if (is_array($raw)) {
                return $raw;
            }
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);

                return is_array($decoded) ? $decoded : null;
            }
        }

        if ($this->type !== self::TYPE_WEEKLY_PLAN || ! $this->id) {
            return null;
        }

        return array_values($this->budgetReviewItemsByUid());
    }

    public function save(array $options = []): bool
    {
        if (! $this->type) {
            return false;
        }

        if (! $this->id) {
            // Lowercase to match the `HasUlids` convention used by the domain
            // tables (weekly_plans, weekly_reports, ...). Keeping the same casing
            // ensures the Document id resolves the persisted domain row on
            // case-sensitive databases.
            $this->id = Str::lower((string) Str::ulid());
        }

        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        app(DocumentDomainSyncService::class)->syncFromDocument($this);

        $this->exists = true;
        $this->syncOriginal();
        $this->fireModelEvent('saved', false);

        return true;
    }

    public function delete(): ?bool
    {
        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

        $deleted = match ($this->type) {
            self::TYPE_WEEKLY_PLAN => (bool) WeeklyPlan::query()->whereKey($this->id)->first()?->delete(),
            self::TYPE_WEEKLY_REPORT => (bool) WeeklyReport::query()->whereKey($this->id)->first()?->delete(),
            self::TYPE_MONTHLY_REPORT => (bool) MonthlyReport::query()->whereKey($this->id)->first()?->delete(),
            self::TYPE_ACTION_LOG => (bool) ActionLog::query()->whereKey($this->id)->first()?->delete(),
            default => false,
        };

        if ($deleted) {
            $this->fireModelEvent('deleted', false);
        }

        return $deleted;
    }

    public function forceDelete(): ?bool
    {
        return $this->delete();
    }
}
