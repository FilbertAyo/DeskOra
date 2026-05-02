<?php

namespace App\Notifications;

use App\Models\Document;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WeeklyDocumentSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Document $document,
        private readonly User $submittedBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $documentType = $this->document->type_name;
        $periodLabel = $this->document->period
            ? 'Week '.$this->document->period->week_number.', '.$this->document->period->year
            : 'Current reporting window';

        return (new MailMessage)
            ->subject($documentType.' Submitted for Review')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->submittedBy->name.' submitted a '.$documentType.'.')
            ->line('Period: '.$periodLabel)
            ->action('Review Document', route('documents.show', $this->document))
            ->line('You are receiving this because you can approve documents.');
    }
}
