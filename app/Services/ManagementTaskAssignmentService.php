<?php

namespace App\Services;

use App\Models\ManagementAssignedTask;
use App\Models\ManagementAssignedTaskRecipient;
use App\Models\User;
use App\Models\WeeklyReport;
use App\Models\WorkPeriod;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ManagementTaskAssignmentService
{
    /**
     * @param  list<string>  $assigneeUserIds
     */
    public function assign(
        User $assignedBy,
        string $periodId,
        string $title,
        ?string $description,
        ?string $deadline,
        array $assigneeUserIds
    ): ManagementAssignedTask {
        $assigneeIds = collect($assigneeUserIds)
            ->map(static fn ($id) => trim((string) $id))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return DB::transaction(function () use ($assignedBy, $periodId, $title, $description, $deadline, $assigneeIds) {
            $task = ManagementAssignedTask::query()->create([
                'assigned_by_user_id' => (string) $assignedBy->id,
                'period_id' => $periodId,
                'title' => trim($title),
                'description' => $description !== null && trim($description) !== '' ? trim($description) : null,
                'deadline' => $deadline !== null && trim($deadline) !== '' ? trim($deadline) : null,
            ]);

            foreach ($assigneeIds as $assigneeId) {
                ManagementAssignedTaskRecipient::query()->create([
                    'management_assigned_task_id' => (string) $task->id,
                    'assignee_user_id' => $assigneeId,
                    'status' => $this->resolveStatus(null, $task->deadline),
                    'progress' => null,
                    'report_feedback' => null,
                ]);
            }

            return $task->load(['assignedBy:id,name', 'period', 'recipients.assignee:id,name']);
        });
    }

    /**
     * @return Collection<int, ManagementAssignedTaskRecipient>
     */
    public function forAssignee(string $userId, ?string $periodId = null): Collection
    {
        $query = ManagementAssignedTaskRecipient::query()
            ->with([
                'task.assignedBy:id,name',
                'task.period',
                'assignee:id,name',
            ])
            ->where('assignee_user_id', $userId)
            ->whereHas('task', function ($taskQuery) use ($periodId) {
                $taskQuery->whereNull('deleted_at');
                if ($periodId) {
                    $taskQuery->where('period_id', $periodId);
                }
            })
            ->orderByDesc('created_at');

        return $query->get()->each(function (ManagementAssignedTaskRecipient $recipient) {
            $this->refreshOverdueStatus($recipient);
        });
    }

    /**
     * @param  list<string>  $userIds
     * @return Collection<int, ManagementAssignedTaskRecipient>
     */
    public function forAssignees(array $userIds, ?string $periodId = null): Collection
    {
        $normalized = collect($userIds)
            ->map(static fn ($id) => trim((string) $id))
            ->filter()
            ->unique()
            ->values();

        if ($normalized->isEmpty()) {
            return collect();
        }

        $query = ManagementAssignedTaskRecipient::query()
            ->with([
                'task.assignedBy:id,name',
                'task.period',
                'assignee:id,name',
            ])
            ->whereIn('assignee_user_id', $normalized->all())
            ->whereHas('task', function ($taskQuery) use ($periodId) {
                $taskQuery->whereNull('deleted_at');
                if ($periodId) {
                    $taskQuery->where('period_id', $periodId);
                }
            })
            ->orderByDesc('created_at');

        return $query->get()->each(function (ManagementAssignedTaskRecipient $recipient) {
            $this->refreshOverdueStatus($recipient);
        });
    }

    /**
     * @param  list<array{management_task_recipient_id?: mixed, progress?: mixed, report_feedback?: mixed}>  $rows
     */
    public function syncRecipientProgressFromReport(
        WeeklyReport|string $weeklyReport,
        string $assigneeUserId,
        array $rows
    ): void {
        $reportId = $weeklyReport instanceof WeeklyReport
            ? (string) $weeklyReport->id
            : (string) $weeklyReport;

        $byId = collect($rows)
            ->filter(static fn ($row) => is_array($row))
            ->keyBy(static fn (array $row) => trim((string) ($row['management_task_recipient_id'] ?? '')))
            ->filter(static fn ($row, $id) => $id !== '');

        if ($byId->isEmpty()) {
            return;
        }

        $recipients = ManagementAssignedTaskRecipient::query()
            ->with('task')
            ->where('assignee_user_id', $assigneeUserId)
            ->whereIn('id', $byId->keys()->all())
            ->get();

        foreach ($recipients as $recipient) {
            $row = $byId->get((string) $recipient->id);
            if (! is_array($row)) {
                continue;
            }

            $progress = trim((string) ($row['progress'] ?? ''));
            if ($progress !== '' && ! in_array($progress, ManagementAssignedTaskRecipient::progressOptions(), true)) {
                $progress = '';
            }

            $feedback = trim((string) ($row['report_feedback'] ?? ''));

            $recipient->progress = $progress !== '' ? $progress : null;
            $recipient->report_feedback = $feedback !== '' ? $feedback : null;
            $recipient->weekly_report_id = $reportId;
            $recipient->status = $this->resolveStatus(
                $recipient->progress,
                $recipient->task?->deadline
            );
            $recipient->save();
        }
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function dashboardRows(
        ?string $periodId = null,
        ?string $status = null,
        ?string $assigneeUserId = null
    ): Collection {
        $query = ManagementAssignedTaskRecipient::query()
            ->with([
                'task.assignedBy:id,name',
                'task.period',
                'assignee:id,name',
            ])
            ->whereHas('task', function ($taskQuery) use ($periodId) {
                $taskQuery->whereNull('deleted_at');
                if ($periodId) {
                    $taskQuery->where('period_id', $periodId);
                }
            })
            ->orderByDesc('created_at');

        if ($assigneeUserId) {
            $query->where('assignee_user_id', $assigneeUserId);
        }

        $rows = $query->get()->map(function (ManagementAssignedTaskRecipient $recipient) {
            $this->refreshOverdueStatus($recipient);

            return $this->recipientDashboardPayload($recipient);
        });

        if ($status) {
            $rows = $rows->filter(static fn (array $row) => ($row['status'] ?? '') === $status)->values();
        }

        return $rows->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function recipientPlanPayload(ManagementAssignedTaskRecipient $recipient): array
    {
        $this->refreshOverdueStatus($recipient);
        $task = $recipient->task;

        return [
            'recipient_id' => (string) $recipient->id,
            'task_id' => (string) ($task?->id ?? ''),
            'title' => (string) ($task?->title ?? ''),
            'description' => (string) ($task?->description ?? ''),
            'deadline' => $task?->deadline?->toDateString(),
            'assigned_by' => (string) ($task?->assignedBy?->name ?? ''),
            'assigned_by_user_id' => (string) ($task?->assigned_by_user_id ?? ''),
            'period_id' => (string) ($task?->period_id ?? ''),
            'period_label' => $task?->period?->display_name,
            'status' => (string) $recipient->status,
            'progress' => $recipient->progress,
            'report_feedback' => $recipient->report_feedback,
            'date_assigned' => $task?->created_at?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recipientReportProgressRow(ManagementAssignedTaskRecipient $recipient): array
    {
        $payload = $this->recipientPlanPayload($recipient);

        return [
            'management_task_recipient_id' => $payload['recipient_id'],
            'task_id' => $payload['task_id'],
            'title' => $payload['title'],
            'description' => $payload['description'],
            'deadline' => $payload['deadline'],
            'assigned_by' => $payload['assigned_by'],
            'status' => $payload['status'],
            'progress' => $payload['progress'] ?? '',
            'report_feedback' => $payload['report_feedback'] ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recipientDashboardPayload(ManagementAssignedTaskRecipient $recipient): array
    {
        $payload = $this->recipientPlanPayload($recipient);
        $task = $recipient->task;

        return [
            ...$payload,
            'assignee' => (string) ($recipient->assignee?->name ?? ''),
            'assignee_user_id' => (string) $recipient->assignee_user_id,
            'weekly_report_id' => $recipient->weekly_report_id ? (string) $recipient->weekly_report_id : null,
            'show_url' => route('management.assigned-tasks.show', $task),
            'report_url' => $recipient->weekly_report_id
                ? route('documents.show', $recipient->weekly_report_id)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function taskShowPayload(ManagementAssignedTask $task): array
    {
        $task->loadMissing(['assignedBy:id,name', 'period', 'recipients.assignee:id,name']);

        return [
            'id' => (string) $task->id,
            'title' => (string) $task->title,
            'description' => (string) ($task->description ?? ''),
            'deadline' => $task->deadline?->toDateString(),
            'date_assigned' => $task->created_at?->toDateString(),
            'assigned_by' => (string) ($task->assignedBy?->name ?? ''),
            'period_id' => (string) $task->period_id,
            'period_label' => $task->period?->display_name,
            'recipients' => $task->recipients->map(function (ManagementAssignedTaskRecipient $recipient) {
                return $this->recipientDashboardPayload($recipient);
            })->values()->all(),
            'index_url' => route('management.assigned-tasks.index'),
        ];
    }

    public function resolveStatus(?string $progress, mixed $deadline): string
    {
        $normalized = trim((string) ($progress ?? ''));
        $isDone = $normalized === ManagementAssignedTaskRecipient::PROGRESS_DONE;

        if ($isDone) {
            return ManagementAssignedTaskRecipient::STATUS_COMPLETED;
        }

        $deadlineDate = null;
        if ($deadline instanceof Carbon) {
            $deadlineDate = $deadline->copy()->startOfDay();
        } elseif (is_string($deadline) && trim($deadline) !== '') {
            try {
                $deadlineDate = Carbon::parse($deadline)->startOfDay();
            } catch (\Throwable) {
                $deadlineDate = null;
            }
        }

        $isPastDeadline = $deadlineDate !== null && $deadlineDate->lt(now()->startOfDay());

        if ($isPastDeadline) {
            return ManagementAssignedTaskRecipient::STATUS_OVERDUE;
        }

        if (
            $normalized === ManagementAssignedTaskRecipient::PROGRESS_IN_PROGRESS
            || $normalized === ManagementAssignedTaskRecipient::PROGRESS_PARTIALLY_DONE
        ) {
            return ManagementAssignedTaskRecipient::STATUS_IN_PROGRESS;
        }

        return ManagementAssignedTaskRecipient::STATUS_PENDING;
    }

    public function refreshOverdueStatus(ManagementAssignedTaskRecipient $recipient): void
    {
        $recipient->loadMissing('task');
        $resolved = $this->resolveStatus($recipient->progress, $recipient->task?->deadline);

        if ($recipient->status !== $resolved) {
            $recipient->status = $resolved;
            $recipient->save();
        }
    }

    /**
     * Merge stored report progress with current assignments for a staff member.
     *
     * @param  list<array<string, mixed>>|null  $existing
     * @return list<array<string, mixed>>
     */
    public function mergeReportProgressRows(
        string $assigneeUserId,
        ?string $periodId,
        ?array $existing = null
    ): array {
        $assignments = $this->forAssignee($assigneeUserId, $periodId);
        $existingById = collect(is_array($existing) ? $existing : [])
            ->filter(static fn ($row) => is_array($row))
            ->keyBy(static fn (array $row) => trim((string) ($row['management_task_recipient_id'] ?? '')));

        return $assignments->map(function (ManagementAssignedTaskRecipient $recipient) use ($existingById) {
            $base = $this->recipientReportProgressRow($recipient);
            $existingRow = $existingById->get((string) $recipient->id);

            if (is_array($existingRow)) {
                $progress = trim((string) ($existingRow['progress'] ?? ''));
                $feedback = trim((string) ($existingRow['report_feedback'] ?? ''));
                if ($progress !== '') {
                    $base['progress'] = $progress;
                }
                if ($feedback !== '') {
                    $base['report_feedback'] = $feedback;
                }
            }

            return $base;
        })->values()->all();
    }

    /**
     * @return Collection<int, WorkPeriod>
     */
    public function periodOptions(int $limit = 20): Collection
    {
        return WorkPeriod::query()
            ->orderByDesc('year')
            ->orderByDesc('week_number')
            ->limit($limit)
            ->get();
    }
}
