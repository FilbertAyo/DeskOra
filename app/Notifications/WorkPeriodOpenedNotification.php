<?php

namespace App\Notifications;

use App\Models\WorkPeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkPeriodOpenedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly WorkPeriod $period) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New Work Period Opened: Week {$this->period->week_number}, {$this->period->year}")
            ->view('mail.work-period-opened', [
                'staffName' => $notifiable->name,
                'period' => $this->period,
                'documentsUrl' => route('documents.create'),
            ]);
    }
}
