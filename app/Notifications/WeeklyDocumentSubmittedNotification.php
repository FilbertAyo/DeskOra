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
        $departmentName = $this->submittedBy->department?->name;
        $submitterLabel = $departmentName
            ? $this->submittedBy->name.' ('.$departmentName.')'
            : $this->submittedBy->name;

        $message = (new MailMessage)
            ->subject($documentType.' Submitted for Review')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($submitterLabel.' submitted a '.$documentType.' for your review.')
            ->line('Period: '.$periodLabel);

        if (filled($this->document->title)) {
            $message->line('Title: '.$this->document->title);
        }

        return $message
            ->action('Review Document', route('documents.show', $this->document))
            ->line('You are receiving this because you can approve weekly plans and reports.');
    }
}
