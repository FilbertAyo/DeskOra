<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Support\ActionLogTaskSupport;
use Illuminate\Support\Collection;

class ActionLogKanbanService
{
    /**
     * @return array{
     *     period: ?WorkPeriod,
     *     document: ?Document,
     *     columns: array<string, list<array<string, mixed>>>,
     *     filters: array{assignee_id: ?string, department_id: ?string},
     *     assignees: Collection<int, User>,
     *     can_edit: bool
     * }
     */
    public function boardFor(User $viewer, ?string $periodId = null, ?string $assigneeId = null, ?string $departmentId = null): array
    {
        $period = $periodId
            ? WorkPeriod::query()->find($periodId)
            : WorkPeriod::getCurrent();

        $document = $period
            ? Document::findCanonicalActionLogForPeriod((string) $period->id)
            : null;

        $columns = [];
        foreach (ActionLogTaskSupport::PROGRESS_OPTIONS as $status) {
            $columns[$status] = [];
        }

        $assignees = collect();
        $canEdit = false;

        if (! $document || ! $period) {
            return [
                'period' => $period,
                'document' => $document,
                'columns' => $columns,
                'filters' => [
                    'assignee_id' => $assigneeId,
                    'department_id' => $departmentId,
                ],
                'assignees' => $assignees,
                'can_edit' => false,
            ];
        }

        $canEdit = $viewer->can('update', $document)
            && $period->isOpen();

        $tasks = ActionLogTaskSupport::extractTasks($document->content);
        $userIds = [];

        foreach ($tasks as $task) {
            if (! is_array($task)) {
                continue;
            }
            foreach (ActionLogTaskSupport::normalizeResponsibleIds($task['responsible_user_ids'] ?? []) as $uid) {
                $userIds[$uid] = true;
            }
        }

        $usersById = User::query()
            ->whereIn('id', array_keys($userIds))
            ->get(['id', 'name', 'department_id'])
            ->keyBy(fn (User $user) => (string) $user->id);

        $assignees = $usersById->sortBy('name')->values();

        foreach ($tasks as $task) {
            if (! is_array($task)) {
                continue;
            }

            $label = trim((string) ($task['task'] ?? ''));
            $taskUid = trim((string) ($task['task_uid'] ?? ''));
            if ($label === '' || $taskUid === '') {
                continue;
            }

            $progress = trim((string) ($task['progress'] ?? ActionLogTaskSupport::PROGRESS_NOT_STARTED));
            if (! isset($columns[$progress])) {
                $progress = ActionLogTaskSupport::PROGRESS_NOT_STARTED;
            }

            $responsibleIds = ActionLogTaskSupport::normalizeResponsibleIds($task['responsible_user_ids'] ?? []);
            $responsibleUsers = collect($responsibleIds)
                ->map(fn (string $id) => $usersById->get($id))
                ->filter()
                ->values();

            if ($assigneeId && ! in_array($assigneeId, $responsibleIds, true)) {
                continue;
            }

            if ($departmentId) {
                $matchesDept = $responsibleUsers->contains(
                    fn (User $user) => (string) $user->department_id === (string) $departmentId
                );
                if (! $matchesDept) {
                    continue;
                }
            }

            $columns[$progress][] = [
                'task_uid' => $taskUid,
                'task' => $label,
                'progress' => $progress,
                'notes_progress' => trim((string) ($task['notes_progress'] ?? '')),
                'due_date' => $task['due_date'] ?? null,
                'responsible_user_ids' => $responsibleIds,
                'responsible_names' => $responsibleUsers->pluck('name')->all(),
                'document_id' => (string) $document->id,
                'editable' => $canEdit && (
                    $viewer->hasAnyPermission(['create action logs', 'manage action logs'])
                    || in_array((string) $viewer->id, $responsibleIds, true)
                ),
            ];
        }

        return [
            'period' => $period,
            'document' => $document,
            'columns' => $columns,
            'filters' => [
                'assignee_id' => $assigneeId,
                'department_id' => $departmentId,
            ],
            'assignees' => $assignees,
            'can_edit' => $canEdit,
        ];
    }
}
