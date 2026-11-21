<?php

namespace App\Support;

class ActionLogTaskSupport
{
    public const PROGRESS_NOT_STARTED = 'Not Started';

    public const PROGRESS_IN_PROGRESS = 'In Progress';

    public const PROGRESS_DONE = 'Done';

    public const PROGRESS_CANCELLED = 'Cancelled';

    public const QUICK_UPDATE_REASSIGN = 'Reassign';

    public const STATE_NEEDS_UPDATE = 'needs_update';

    public const STATE_UPDATED_BY_YOU = 'updated_by_you';

    public const STATE_UPDATED = 'updated';

    public const STATE_NOT_UPDATED = 'not_updated';

    public const STATE_CLOSED = 'closed';

    /** @var list<string> */
    public const PROGRESS_OPTIONS = [
        self::PROGRESS_NOT_STARTED,
        self::PROGRESS_IN_PROGRESS,
        self::PROGRESS_DONE,
        self::PROGRESS_CANCELLED,
    ];

    /** @var list<string> */
    public const STAFF_PROGRESS_OPTIONS = [
        self::PROGRESS_NOT_STARTED,
        self::PROGRESS_IN_PROGRESS,
        self::PROGRESS_DONE,
        self::PROGRESS_CANCELLED,
    ];

    /**
     * @param  mixed  $responsibleIds
     * @return list<string>
     */
    public static function normalizeResponsibleIds($responsibleIds): array
    {
        if (! is_array($responsibleIds)) {
            return [];
        }

        $filtered = [];
        foreach ($responsibleIds as $rid) {
            $sid = trim((string) $rid);
            if ($sid !== '' && (ShortId::is($sid) || ShortId::isLegacyUlid($sid))) {
                $filtered[] = $sid;
            }
        }

        return array_values(array_unique($filtered));
    }

    /**
     * @param  list<array<string, mixed>>  $tasks
     * @return list<array<string, mixed>>
     */
    public static function renumberTasks(array $tasks): array
    {
        $counter = 1;
        foreach ($tasks as &$task) {
            if (! is_array($task)) {
                continue;
            }
            $task['task_no'] = $counter++;
        }
        unset($task);

        return array_values($tasks);
    }

    /**
     * @param  mixed  $content
     * @return list<array<string, mixed>>
     */
    public static function extractTasks($content): array
    {
        if (! is_array($content)) {
            return [];
        }

        $tasks = $content['tasks'] ?? [];

        return is_array($tasks) ? $tasks : [];
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    public static function isRealHistoryEntry(array $entry): bool
    {
        $source = trim((string) ($entry['source'] ?? ''));
        if ($source === 'auto_init') {
            return false;
        }
        if (in_array($source, ['update', 'reassign'], true)) {
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

    public static function progressRequiresNotes(string $progress): bool
    {
        return trim($progress) !== self::PROGRESS_NOT_STARTED;
    }

    /**
     * @param  array<string, mixed>  $task
     */
    public static function hasRealUpdateInPeriod(array $task, string $periodId): bool
    {
        $history = is_array($task['history'] ?? null) ? $task['history'] : [];
        foreach ($history as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            if (trim((string) ($entry['period_id'] ?? '')) !== $periodId) {
                continue;
            }

            if (self::isRealHistoryEntry($entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $task
     */
    public static function updatedByUserInPeriod(array $task, string $periodId, string $userId): bool
    {
        $history = is_array($task['history'] ?? null) ? $task['history'] : [];
        foreach ($history as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            if (trim((string) ($entry['period_id'] ?? '')) !== $periodId) {
                continue;
            }
            if (trim((string) ($entry['updated_by'] ?? '')) !== $userId) {
                continue;
            }
            if (self::isRealHistoryEntry($entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * How this task should read on the board for the signed-in viewer.
     *
     * @param  array<string, mixed>  $task
     */
    public static function updateState(array $task, string $periodId, string $viewerId, bool $periodOpen): string
    {
        $progress = trim((string) ($task['progress'] ?? self::PROGRESS_NOT_STARTED));
        $terminal = in_array($progress, [self::PROGRESS_DONE, self::PROGRESS_CANCELLED], true);
        $mine = in_array($viewerId, self::normalizeResponsibleIds($task['responsible_user_ids'] ?? []), true);
        $updated = $periodId !== '' && self::hasRealUpdateInPeriod($task, $periodId);
        $byYou = $updated && self::updatedByUserInPeriod($task, $periodId, $viewerId);

        if ($byYou) {
            return self::STATE_UPDATED_BY_YOU;
        }
        if ($updated) {
            return self::STATE_UPDATED;
        }
        if ($terminal) {
            return self::STATE_CLOSED;
        }
        if ($mine && $periodOpen) {
            return self::STATE_NEEDS_UPDATE;
        }

        return self::STATE_NOT_UPDATED;
    }
}
