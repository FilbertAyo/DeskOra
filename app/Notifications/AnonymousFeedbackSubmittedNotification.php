<?php

namespace App\Notifications;

use App\Models\AnonymousFeedback;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class AnonymousFeedbackSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly AnonymousFeedback $feedback,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $categoryLabel = str_replace('_', ' ', ucfirst($this->feedback->category));
        $preview = Str::limit($this->feedback->body, 200);
        $name = trim((string) ($notifiable->name ?? ''));
        $greeting = $name !== '' ? 'Hello '.$name.',' : 'Hello,';

        return (new MailMessage)
            ->subject('New anonymous feedback received')
            ->greeting($greeting)
            ->line('A new anonymous message has been submitted.')
            ->line('Type: '.$categoryLabel)
            ->line('Status: New')
            ->line('Message: '.$preview)
            ->action('Open feedback inbox', route('anonymous-feedback.index'))
            ->line('You are receiving this because you can manage anonymous feedback.');
    }
}
