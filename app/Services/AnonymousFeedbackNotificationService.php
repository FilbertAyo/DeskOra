<?php

namespace App\Services;

use App\Models\AnonymousFeedback;
use App\Models\User;
use App\Notifications\AnonymousFeedbackSubmittedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class AnonymousFeedbackNotificationService
{
    public function notifySubmitted(AnonymousFeedback $feedback): void
    {
        $permission = (string) config('anonymous_feedback.notify_permission', 'manage anonymous feedback');
        $recipients = $this->recipientsForPermission($permission);

        if ($recipients->isEmpty()) {
            $this->logMissingRecipients($feedback, $permission);

            return;
        }

        try {
            Notification::send($recipients, new AnonymousFeedbackSubmittedNotification($feedback));
        } catch (\Throwable $e) {
            Log::error('AnonymousFeedbackNotificationService: failed to send email notifications', [
                'feedback_id' => $feedback->id,
                'recipient_ids' => $recipients->pluck('id')->all(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function recipientsForPermission(string $permission): Collection
    {
        return User::query()
            ->where('status', 'active')
            ->permission($permission)
            ->get()
            ->filter(fn (User $user) => $user->receivesEmailNotifications())
            ->values();
    }

    private function logMissingRecipients(AnonymousFeedback $feedback, string $permission): void
    {
        if (! config('anonymous_feedback.log_missing_recipients', true)) {
            return;
        }

        Log::warning('Anonymous feedback email notification skipped: no eligible recipients.', [
            'feedback_id' => $feedback->id,
            'permission' => $permission,
        ]);
    }
}
