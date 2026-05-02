<?php

namespace App\Notifications;

use App\Models\Document;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WeeklyDocumentReturnedToDraftNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Document $document,
        private readonly User $reviewedBy,
        private readonly string $reason,
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
            ->subject($documentType.' Returned to Draft')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->reviewedBy->name.' returned your '.$documentType.' to draft for revision.')
            ->line('Period: '.$periodLabel)
            ->line('Review reason: '.$this->reason)
            ->action('Update Document', route('documents.edit', $this->document))
            ->line('Please address the feedback and submit again.');
    }
}
