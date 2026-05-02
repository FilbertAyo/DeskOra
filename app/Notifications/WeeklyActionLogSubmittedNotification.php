<?php

namespace App\Notifications;

use App\Models\Document;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WeeklyActionLogSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Document $actionLog,
        private readonly User $submittedBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $periodLabel = $this->actionLog->period
            ? 'Week '.$this->actionLog->period->week_number.', '.$this->actionLog->period->year
            : 'Current week';

        return (new MailMessage)
            ->subject('Weekly Action Log Submitted')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->submittedBy->name.' submitted the weekly action log.')
            ->line('Period: '.$periodLabel)
            ->action('View Action Log', route('action-logs.show', $this->actionLog))
            ->line('This update is shared organization-wide.');
    }
}
