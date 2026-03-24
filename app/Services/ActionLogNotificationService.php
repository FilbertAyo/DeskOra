<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ActionLogNotificationService
{
    public function __construct(private NotificationService $notificationService)
    {
    }

    /**
     * Notify assignees for a newly created/updated action log payload.
     */
    public function notifyOnStore(Document $document, array $tasks, bool $submitted, int $actorUserId): array
    {
        $assigneeIds = $this->extractAssigneeIds($tasks);
        $summary = [
            'assignment_notifications' => 0,
            'submission_notifications' => 0,
        ];

        foreach ($assigneeIds as $userId) {
            if ($userId === $actorUserId) {
                continue;
            }
            $user = User::find($userId);
            if (!$user) {
                continue;
            }
            if ($this->notifyUser($user, $this->assignmentMessage($document))) {
                $summary['assignment_notifications']++;
            }
        }

        if ($submitted) {
            foreach ($assigneeIds as $userId) {
                if ($userId === $actorUserId) {
                    continue;
                }
                $user = User::find($userId);
                if (!$user) {
                    continue;
                }
                if ($this->notifyUser($user, $this->submissionMessage($document))) {
                    $summary['submission_notifications']++;
                }
            }
        }

        return $summary;
    }

    /**
     * Notify assignees based on assignment changes and submission transition.
     */
    public function notifyOnUpdate(Document $document, array $oldTasks, array $newTasks, string $oldState, string $newState, int $actorUserId): array
    {
        $oldAssigneeIds = $this->extractAssigneeIds($oldTasks);
        $newAssigneeIds = $this->extractAssigneeIds($newTasks);
        $newlyAssigned = array_values(array_diff($newAssigneeIds, $oldAssigneeIds));

        $summary = [
            'assignment_notifications' => 0,
            'submission_notifications' => 0,
        ];

        foreach ($newlyAssigned as $userId) {
            if ((int) $userId === $actorUserId) {
                continue;
            }
            $user = User::find((int) $userId);
            if (!$user) {
                continue;
            }
            if ($this->notifyUser($user, $this->assignmentMessage($document))) {
                $summary['assignment_notifications']++;
            }
        }

        if ($oldState !== Document::STATE_SUBMITTED && $newState === Document::STATE_SUBMITTED) {
            foreach ($newAssigneeIds as $userId) {
                if ((int) $userId === $actorUserId) {
                    continue;
                }
                $user = User::find((int) $userId);
                if (!$user) {
                    continue;
                }
                if ($this->notifyUser($user, $this->submissionMessage($document))) {
                    $summary['submission_notifications']++;
                }
            }
        }

        return $summary;
    }

    private function extractAssigneeIds(array $tasks): array
    {
        $ids = [];
        foreach ($tasks as $task) {
            if (!is_array($task)) {
                continue;
            }
            $responsible = $task['responsible_user_ids'] ?? [];
            if (!is_array($responsible)) {
                continue;
            }
            foreach ($responsible as $id) {
                $intId = (int) $id;
                if ($intId > 0) {
                    $ids[$intId] = $intId;
                }
            }
        }
        return array_values($ids);
    }

    private function assignmentMessage(Document $document): string
    {
        $periodLabel = $document->period
            ? ('Week ' . $document->period->week_number . ' of ' . $document->period->year)
            : 'current period';

        return "You have been assigned a task in Action Log: {$document->display_title} ({$periodLabel}). Please update your task progress.";
    }

    private function submissionMessage(Document $document): string
    {
        $periodLabel = $document->period
            ? ('Week ' . $document->period->week_number . ' of ' . $document->period->year)
            : 'current period';

        return "Action Log submitted for {$periodLabel}. You can continue updating your assigned task progress.";
    }

    private function notifyUser(User $user, string $message): bool
    {
        $channel = $user->notification_channel ?: 'sms';
        $smsSent = false;
        $emailSent = false;

        if (in_array($channel, ['sms', 'both'], true) && !empty($user->phone)) {
            $smsSent = $this->notificationService->sendSms($user->phone, $message);
        }

        if (in_array($channel, ['email', 'both'], true) && !empty($user->email)) {
            try {
                Mail::raw($message, function ($mail) use ($user) {
                    $mail->to($user->email)->subject('Action Log Task Update');
                });
                $emailSent = true;
            } catch (\Throwable $e) {
                Log::warning('ActionLog notification email failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $smsSent || $emailSent;
    }
}
