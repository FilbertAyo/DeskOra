<?php

namespace App\Support;

class ActionLogTaskSupport
{
    public const PROGRESS_NOT_STARTED = 'Not Started';

    public const PROGRESS_IN_PROGRESS = 'In Progress';

    public const PROGRESS_DONE = 'Done';

    public const PROGRESS_CANCELLED = 'Cancelled';

    public const QUICK_UPDATE_REASSIGN = 'Reassign';

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
}
