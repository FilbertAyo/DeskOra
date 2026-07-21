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
        $recipients = $this->permissionRecipients($permission);
        $extraEmails = $this->extraEmails($recipients);

        if ($recipients->isEmpty() && $extraEmails->isEmpty()) {
            $this->logMissingRecipients($feedback, $permission);

            return;
        }

        try {
            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new AnonymousFeedbackSubmittedNotification($feedback));
            }

            foreach ($extraEmails as $email) {
                Notification::route('mail', $email)
                    ->notify(new AnonymousFeedbackSubmittedNotification($feedback));
            }

            Log::info('Anonymous feedback email notifications sent.', [
                'feedback_id' => $feedback->id,
                'recipient_emails' => $recipients->pluck('email')->filter()->values()->all(),
                'extra_emails' => $extraEmails->all(),
            ]);
        } catch (\Throwable $e) {
            Log::error('AnonymousFeedbackNotificationService: failed to send email notifications', [
                'feedback_id' => $feedback->id,
                'recipient_ids' => $recipients->pluck('id')->all(),
                'extra_emails' => $extraEmails->all(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Active users with the manage permission and an email on file.
     * Channel preference (sms/email/both) is ignored — inbox alerts must reach managers.
     *
     * @return Collection<int, User>
     */
    private function permissionRecipients(string $permission): Collection
    {
        return User::query()
            ->where('status', 'active')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->permission($permission)
            ->get()
            ->filter(fn (User $user) => $user->canReceiveGracePeriodWorkEmail())
            ->values();
    }

    /**
     * @param  Collection<int, User>  $permissionRecipients
     * @return Collection<int, string>
     */
    private function extraEmails(Collection $permissionRecipients): Collection
    {
        $already = $permissionRecipients
            ->map(fn (User $user) => strtolower(trim((string) $user->email)))
            ->filter()
            ->all();

        return collect(config('anonymous_feedback.notify_emails', []))
            ->filter(fn ($email) => is_string($email) && filled(trim($email)))
            ->map(fn (string $email) => strtolower(trim($email)))
            ->unique()
            ->reject(fn (string $email) => in_array($email, $already, true))
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
