<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WeeklySubmissionReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $activityType,
        public readonly string $activityLabel,
        public readonly string $periodLabel
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Reminder: Submit {$this->activityLabel}")
            ->greeting("Hello {$notifiable->name},")
            ->line("You have not submitted your {$this->activityLabel} for {$this->periodLabel}.")
            ->line('Please submit it in the system as soon as possible.')
            ->action('Open Documents', url('/documents/create'))
            ->line('Thank you.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'activity_type' => $this->activityType,
            'activity_label' => $this->activityLabel,
            'period_label' => $this->periodLabel,
            'title' => "Reminder: submit {$this->activityLabel}",
            'message' => "You have not submitted your {$this->activityLabel} for {$this->periodLabel}.",
            'action_url' => route('documents.create'),
        ];
    }
}
